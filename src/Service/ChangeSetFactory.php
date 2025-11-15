<?php

namespace ADT\DoctrineLoggable\Service;

use Adt\DoctrineLoggable\ChangeSet AS CS;
use ADT\DoctrineLoggable\Attributes AS DLA;
use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use Adt\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Proxy;
use Nette\Security\User;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Traversable;

class ChangeSetFactory
{
	protected EntityManager $em;

	protected UnitOfWork $uow;

	private DLA\AttributeReader $reader;

	protected array $loggableEntityClasses = [];

	protected array $loggableEntityProperties = [];

	/**
	 * List of all log entries
	 *
	 * @var ChangeLog[]
	 */
	protected array $logEntries = [];

	protected array $scheduledEntities = [];

	/**
	 * @var ChangeSet[]
	 */
	protected array $computedEntityChangeSets = [];

	protected array $identifications = [];

	private User $userIdProvider;

	protected array $associationStructure = [];

	public function __construct(User $userIdProvider)
	{
		$this->reader = new DLA\AttributeReader();
		$this->userIdProvider = $userIdProvider;
	}

	// vytvori napriklad nasledujici strukturu
	//$structure = [
	//	'Entity\UserAgreement' => [
	//		[
	//			'user'
	//		]
	//	],
	//	'Entity\File' => [
	//		[
	//			'userAgreementDocument',
	//			'user'
	//		],
	//		[
	//			'branchPhoto'
	//		]
	//	]
	//];
	// jedna se o cestu od entity, na ktere se udala zmena, k materske entite, u ktere je anotace loggableEntity
	/**
	 * @throws ReflectionException
	 * @throws MappingException|\Doctrine\Persistence\Mapping\MappingException
	 */
	public function getLoggableEntityAssociationStructure($className = null, $path = []): array
	{
		if ($this->associationStructure) {
			return $this->associationStructure;
		}

		$structure = [];
		$metadataFactory = $this->em->getMetadataFactory();
		$classes = $className ? [$metadataFactory->getMetadataFor($className)] : $metadataFactory->getAllMetadata();
		foreach ($classes as $classMetadata) {
			// pokud nejsme zanoreni, tak nas zajimaji jen entity s anotaci LoggableEntity
			if (!$className && !$this->isEntityLogged($classMetadata->getName())) {
				continue;
			}

			foreach ($this->getLoggedProperties($classMetadata->getName()) as $property) {
				// zajimaji nas jen asociace, nikoliv pole
				if (!$classMetadata->hasAssociation($property->getName())) {
					continue;
				}

				$associationMapping = $classMetadata->getAssociationMapping($property->getName());
				$associationPropertyName = '';
				if ($associationMapping['type'] === ClassMetadata::ONE_TO_ONE) {
					$associationPropertyName = 'inversedBy';
				}
				elseif ($associationMapping['type'] === ClassMetadata::ONE_TO_MANY){
					$associationPropertyName = 'mappedBy';
				}
				if ($associationPropertyName) {
					if (!empty($associationMapping[$associationPropertyName])) {
						$structure[$associationMapping['targetEntity']][] = array_merge([$associationMapping[$associationPropertyName]], $path);
					} else {
						$structure[$associationMapping['targetEntity']][] = $classMetadata->getName(). '::' . $property->getName();
					}
				}
			}
		}

		if ($className) {
			return $structure;
		}

		return $this->associationStructure = $structure;
	}

	/**
	 * @throws ReflectionException
	 */
	public function getLoggableEntityFromAssociationStructure($associationEntity)
	{
		$associationEntityClassName = ClassUtils::getClass($associationEntity);
		foreach($this->associationStructure[$associationEntityClassName] as $propertyStructure) {
			if (is_array($propertyStructure)) {
				foreach ($propertyStructure as $propertyName) {
					$property = new ReflectionProperty($associationEntityClassName, $propertyName);
					$property->setAccessible(true);
					$value = $property->getValue($associationEntity);

					// pokud neni nastavena hodnota, vime ze jsme ve spatne ceste
					if (!$value) {
						continue 2;
					}

					// vylezli jsme o uroven vys, je potreba nastavit aktualni tridu, ve ktere se nachazime
					$associationEntityClassName = get_class($value);
					$associationEntity = $value;
				}

				if ($value) {
					return $value;
				}
			} else {
				list ($className, $propertyName) = explode('::', $propertyStructure);
				return $this->em->getRepository($className)->findOneBy([$propertyName => $associationEntity->getId()]);
			}
		}

		return null;
	}

	/**
	 * @throws ReflectionException
	 */
	public function isEntityLogged($entityClass)
	{
		if (!array_key_exists($entityClass, $this->loggableEntityClasses)) {
			$reflection = new ReflectionClass($entityClass);
			$an = $this->reader->getClassAttribute($reflection, DLA\LoggableEntity::class);
			$this->loggableEntityClasses[$entityClass] = (bool) $an;
		}
		return $this->loggableEntityClasses[$entityClass];
	}

	/**
	 * @throws ReflectionException
	 * @throws ORMException
	 */
	public function processLoggedEntity($entity, $relatedEntity = null): void
	{
		// pokud primarni entita nema id, nelogujeme, je to vlozeni
		if (!$this->getIdentifier($entity)) {
			return;
		}

		$changeSet = $this->getChangeSet($entity, $relatedEntity);
		if (!$changeSet->isChanged()) {
			return;
		}
		$this->updateLogEntry($entity, $changeSet);
	}

	public function updateIdentification($entity): void
	{
		$oid = spl_object_hash($entity);
		if (array_key_exists($oid, $this->identifications)) {

			$metadata = $this->em->getClassMetadata(ClassUtils::getClass($entity));
			$id = $metadata->getIdentifierValues($entity);

			$identification = $this->identifications[$oid];
			$identification->setId(implode('-', $id));
		}
	}

	/**
	 * @param object|null $entity
	 * @param object|null $relatedEntity
	 * @return ChangeSet|null
	 * @throws ReflectionException
	 */
	protected function getChangeSet(?object $entity =  null, ?object $relatedEntity = null): ?ChangeSet
	{
		if ($entity === null) {
			return null;
		}

		$sploh = spl_object_hash($entity);
		if (isset($this->computedEntityChangeSets[$sploh])) {
			$changeSet = $this->computedEntityChangeSets[$sploh];

		} else {
			$changeSet = new CS\ChangeSet();
			$changeSet->setIdentification($this->createIdentification($entity));
			$this->computedEntityChangeSets[$sploh] = $changeSet;

			$insertions = $this->uow->getScheduledEntityInsertions();
			if (isset($insertions[$sploh])) {
				$changeSet->setAction(CS\ChangeSet::ACTION_CREATE);
			} else {
				$deletions = $this->uow->getScheduledEntityDeletions();
				if (isset($deletions[$sploh])) {
					$changeSet->setAction(CS\ChangeSet::ACTION_DELETE);
				}
			}
		}

		$uowEntiyChangeSet = $this->uow->getEntityChangeSet($entity);
		foreach ($this->getLoggedProperties(get_class($entity)) as $property) {
			// property is scalar
			$columnAnnotation = $this->reader->getPropertyAttribute($property, Column::class);
			if ($columnAnnotation) {
				if (isset($uowEntiyChangeSet[$property->getName()])) {
					$propertyChangeSet = $uowEntiyChangeSet[$property->getName()];

					$nodeScalar = new CS\Scalar($property->name, $propertyChangeSet[0], $propertyChangeSet[1]);
					$changeSet->addPropertyChange($nodeScalar);
				}
				continue;
			}

			// property is toOne association
			/** @var ManyToOne $manyToOneAnnotation */
			$manyToOneAnnotation = $this->reader->getPropertyAttribute($property, ManyToOne::class);
			/** @var OneToOne $oneToOneAnnotation */
			$oneToOneAnnotation = $this->reader->getPropertyAttribute($property, OneToOne::class);
			if ($manyToOneAnnotation || $oneToOneAnnotation) {
				$nodeAssociation = $this->getAssociationChangeSet($entity, $property);
				$changeSet->addPropertyChange($nodeAssociation);
				continue;
			}

			// property is toMany collection
			/** @var ManyToOne $manyToOneAnnotation */
			$manyToManyAnnotation = $this->reader->getPropertyAttribute($property, ManyToMany::class);
			/** @var OneToOne $oneToOneAnnotation */
			$oneToManyAnnotation = $this->reader->getPropertyAttribute($property, OneToMany::class);
			if ($manyToManyAnnotation || $oneToManyAnnotation) {

				$nodeCollection = $this->getCollectionChangeSet($entity, $property, $relatedEntity);

				$changeSet->addPropertyChange($nodeCollection);
			}

		}
		return $changeSet;
	}

	/**
	 * @param $entity
	 * @param ReflectionProperty $property
	 * @param null $relatedEntity
	 * @return ToMany
	 * @throws ReflectionException
	 */
	public function getCollectionChangeSet($entity, ReflectionProperty $property, $relatedEntity = null): CS\ToMany
	{
		$nodeCollection = new CS\ToMany($property->name);

		if ($entity instanceof Proxy) {
			if (!$entity->__isInitialized()) {
				$entity->__load();
			}
		}

		/** @var PersistentCollection $collection */
		$property->setAccessible(TRUE);
		$collection = $property->getValue($entity);

		if ($collection instanceof PersistentCollection) {
			$removed = $collection->getDeleteDiff();
			$added = $collection->getInsertDiff();
		} elseif ($collection instanceof Collection) {
			$removed = [];
			$added = $collection->toArray();
		} else {
			return $nodeCollection;
		}

		foreach ($removed as $_relatedEntity) {
			$nodeCollection->addRemoved($this->createIdentification($_relatedEntity));
		}

		foreach ($added as $_relatedEntity) {
			$nodeCollection->addAdded($this->createIdentification($_relatedEntity));
		}

		if ($relatedEntity) {
			foreach ($collection as $_relatedEntity) {
				if ($relatedEntity === $_relatedEntity) {
					$nodeCollection->addChangeSet($this->getChangeSet($relatedEntity));
					break;
				}
			}
		}

		return $nodeCollection;
	}

	/**
	 * @param $entity
	 * @param ReflectionProperty $property
	 * @return CS\ToOne
	 * @throws ReflectionException
	 */
	protected function getAssociationChangeSet($entity, ReflectionProperty $property): CS\ToOne
	{
		$changeSet = null;

		/** @var ManyToOne $manyToOneAnnotation */
		$manyToOneAnnotation = $this->reader->getPropertyAttribute($property, ManyToOne::class);
		/** @var OneToOne $oneToOneAnnotation */
		$oneToOneAnnotation = $this->reader->getPropertyAttribute($property, OneToOne::class);

		$relatedEntity = $this->em->getClassMetadata(ClassUtils::getClass($entity))->getFieldValue($entity, $property->name);
		$newIdentification = $oldIdentification = $this->createIdentification($relatedEntity);

		// owning side (ManyToOne is always owning side, OneToOne only if inversedBy is set (or nothing set - unidirectional)
		if ($manyToOneAnnotation || ($oneToOneAnnotation && $oneToOneAnnotation->mappedBy === NULL)) {
			$changeSet = $this->getChangeSet($relatedEntity);
			if (!$changeSet || !$changeSet->isChanged()) {
				$uowEntityChangeSet = $this->uow->getEntityChangeSet($entity);
				if (isset($uowEntityChangeSet[$property->getName()])) {
					$propertyChangeSet = $uowEntityChangeSet[$property->getName()];
					$oldIdentification = $this->createIdentification($propertyChangeSet[0]);
				}
			}

			// inversed side - its OneToOne with mappedBy annotation
			// TODO poradne otestovat, nebo este lepsi udelat testy
		} else {
			$ownerProperty = $oneToOneAnnotation->mappedBy;
			$ownerClass = $this->em->getClassMetadata(ClassUtils::getClass($entity))
				->getAssociationTargetClass($property->name);
			$identityMap = $this->uow->getIdentityMap();
			if (isset($identityMap[$ownerClass])) {
				foreach ($identityMap[$ownerClass] as $ownerEntity) {

					if (isset($this->scheduledEntities[spl_object_hash($ownerEntity)])) {

						if ($this->scheduledEntities[spl_object_hash($ownerEntity)] == CS\ChangeSet::ACTION_DELETE) {
							if ($entity === $ownerEntity->{$ownerProperty}) {
								$oldIdentification = $this->createIdentification($ownerEntity);
								break;
							}
						} else {
							$ownerEntityChangeSet = $this->uow->getEntityChangeSet($ownerEntity);
							if (isset($ownerEntityChangeSet[$ownerProperty])) {
								if ($ownerEntityChangeSet[$ownerProperty][0] == $entity) {
									$oldIdentification = $this->createIdentification($ownerEntity);
									break;
								}
							}
						}
					}
				}
			}
		}

		$toOne = new CS\ToOne($property->name, $oldIdentification, $newIdentification);
		$toOne->setChangeSet($changeSet);
		return $toOne;
	}

	/**
	 * @param object|NULL $entity
	 * @return CS\Id|NULL
	 * @throws ReflectionException
	 */
	public function createIdentification(?object $entity = NULL): ?CS\Id
	{
		if ($entity === NULL) {
			return NULL;
		}
		$entityHash = spl_object_hash($entity);
		if (!isset($this->identifications[$entityHash])) {
			$class = ClassUtils::getClass($entity);
			$metadata = $this->em->getClassMetadata($class);
			/** @var DLA\LoggableIdentification $identificationAnnotation */
			$identificationAnnotation = $this->reader->getClassAttribute(new ReflectionClass($class), DLA\LoggableIdentification::class);
			$identificationData = [];
			if ($identificationAnnotation) {
				foreach ($identificationAnnotation->fields as $fieldName) {
					$fieldNameParts = explode('.', $fieldName);
					$values = [$entity];
					$newValues = [];
					foreach ($fieldNameParts as $fieldNamePart) {
						foreach ($values as $value) {
							if ($value instanceof Proxy) {
								if (!$value->__isInitialized()) {
									$value->__load();
								}
							}

							$getter = 'get' . ucfirst($fieldNamePart);
							if (method_exists($value, $getter)) {
								$fieldValue = $value->$getter();
							} else {
								$fieldValue = $this->em->getClassMetadata(ClassUtils::getClass($value))
									->getFieldValue($value, $fieldNamePart);
							}
							if (is_array($fieldValue) || $fieldValue instanceof Traversable) {
								foreach ($fieldValue as $item) {
									$newValues[] = $this->convertIdentificationValue($item);
								}
							} else {
								$newValues[] = $this->convertIdentificationValue($fieldValue);
							}
						}
						$values = $newValues;
						$newValues = [];
					}
					$identificationData[$fieldName] = implode(', ', $values);
				}
			}
			$id = $metadata->getIdentifierValues($entity);
			$identification = new CS\Id(implode('-', $id), $class, $identificationData);

			$this->identifications[$entityHash] = $identification;
		}
		return $this->identifications[$entityHash];
	}

	protected function convertIdentificationValue($value)
	{
		if ($value instanceof DateTimeInterface) {
			if ($value->format('H:i:s') == '00:00:00') {
				$value = $value->format('j.n.Y');
			} else {
				$value = $value->format('j.n.Y H:i');
			}
		}
		return $value;
	}

	/**
	 * @param $entityClassName
	 * @return ReflectionProperty[]
	 * @throws ReflectionException
	 */
	protected function getLoggedProperties($entityClassName): array
	{
		if (!isset($this->loggableEntityProperties[$entityClassName])) {
			$reflection = new ReflectionClass($entityClassName);
			$list = [];
			foreach ($reflection->getProperties() as $property) {
				$an = $this->reader->getPropertyAttribute($property, DLA\LoggableProperty::class);
				if ($an !== NULL) {
					$list[] = $property;
				}
			}
			$this->loggableEntityProperties[$entityClassName] = $list;
		}
		return $this->loggableEntityProperties[$entityClassName];
	}

	/**
	 * @throws ReflectionException
	 */
	public function isPropertyLogged($entityClassName, $propertyName): bool
	{
		$properties = $this->getLoggedProperties($entityClassName);
		return isset($properties[$propertyName]);
	}

	/**
	 * @throws ReflectionException
	 */
	public function getPropertyAnnotation($entityClassName, $propertyName): ?ReflectionProperty
	{
		$properties = $this->getLoggedProperties($entityClassName);
		return $properties[$propertyName] ?? NULL;
	}

	/**
	 * @throws ORMException
	 */
	public function updateLogEntry($entity, ChangeSet $changeSet): void
	{
		$soh = spl_object_hash($entity);
		$logEntry = $this->logEntries[$soh] ?? null;
		if (!$logEntry) {
			$logEntry = new ChangeLog();
			$logEntry->setIdentityClass(ClassUtils::getClass($this->userIdProvider->getIdentity()));
			$logEntry->setIdentityId($this->userIdProvider->getId());
			$logEntry->setObjectClass($this->em->getClassMetadata(get_class($entity))->name);
			$logEntry->setObjectId($this->getIdentifier($entity));
			$logEntry->setAction(CS\ChangeSet::ACTION_EDIT);
		}
		$logEntry->setChangeset($changeSet);
		if (!isset($this->logEntries[$soh])) {
			$this->em->persist($logEntry);
			$this->em->getUnitOfWork()->computeChangeSet($this->em->getClassMetadata(get_class($logEntry)), $logEntry);
			$this->logEntries[spl_object_hash($entity)] = $logEntry;

		} else {
			$this->em->getUnitOfWork()->recomputeSingleEntityChangeSet($this->em->getClassMetadata(get_class($logEntry)), $logEntry);
		}
	}

	/**
	 * @param $em
	 * @return $this
	 */
	public function setEntityManager($em): static
	{
		$this->em = $em;
		$this->uow = $this->em->getUnitOfWork();
		return $this;
	}

	private function getIdentifier($entity): ?int
	{
		return $entity->getId();
	}
}
