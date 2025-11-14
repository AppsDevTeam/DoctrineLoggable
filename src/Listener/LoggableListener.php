<?php

namespace ADT\DoctrineLoggable\Listener;

use ADT\DoctrineLoggable\Service\ChangeSetFactory;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;

class LoggableListener implements EventSubscriber
{
	private ChangeSetFactory $changeSetFactory;

	public function __construct(ChangeSetFactory $changeSetFactory)
	{
		$this->changeSetFactory = $changeSetFactory;
	}

	function getSubscribedEvents(): array
	{
		return [
			'onFlush',
			'postPersist',
		];
	}

	/**
	 * @throws ReflectionException
	 * @throws ORMException|MappingException
	 */
	public function onFlush(OnFlushEventArgs $eventArgs): void
	{
		// musi se vytvorit struktura asociaci,
		// protoze pokud ma loggableEntity OneToOne nebo OneToMany vazby s nastavenym loggableProperty,
		// ve kterych dojde ke zmene, tak v getScheduledEntity metodach bude jen tato kolekce,
		// ale nebude tu materska entita, ktera ma nastaveno loggableEntity, a tudiz nedojde k zalogovani
		$structure = $this->changeSetFactory->getLoggableEntityAssociationStructure();

		$uow = $eventArgs->getObjectManager()->getUnitOfWork();

		foreach (['getScheduledEntityInsertions', 'getScheduledEntityUpdates', 'getScheduledEntityDeletions'] as $method) {
			foreach (call_user_func([$uow, $method]) as $entity) {
				$entityClass = ClassUtils::getClass($entity);
				// jedna se o entitu s anotaci loggableEntity
				if ($this->changeSetFactory->isEntityLogged($entityClass)) {
					$this->changeSetFactory->processLoggedEntity($entity);
				}
				// jedna se o upravenou asociaci, jejiz primarni entita ma anotaci loggableEntity
				elseif (isset($structure[$entityClass])) {
					if ($loggableEntity = $this->changeSetFactory->getLoggableEntityFromAssociationStructure($entity)) {
						$this->changeSetFactory->processLoggedEntity($loggableEntity, $entity);
					}
				}
			}
		}
	}

	public function postPersist(PostPersistEventArgs $args): void
	{
		$object = $args->getObject();
		$this->changeSetFactory->updateIdentification($object);
	}
}
