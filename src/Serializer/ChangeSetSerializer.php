<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\PropertyChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Redacted;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\UnexpectedValueException;

/**
 * Converts the change set object graph to a JSON friendly structure and back.
 *
 * The property type is stored as a "scalar"/"toOne"/"toMany" discriminator instead of a class
 * name, so the classes of this library can be moved or renamed without breaking existing logs.
 *
 * The graph may contain cycles. A change set referenced more than once gets a "$id" and every
 * further occurrence is written as {"$ref": id}. Change sets referenced just once, which is
 * almost always the case, carry no ids at all.
 */
class ChangeSetSerializer
{
	public const VERSION = 1;

	// the on-disk contract, deliberately independent of PropertyChangeSet::TYPE_* so that renaming
	// a constant in the model can never change what is already stored
	public const TYPE_SCALAR = 'scalar';
	public const TYPE_TO_ONE = 'toOne';
	public const TYPE_TO_MANY = 'toMany';
	public const TYPE_REDACTED = 'redacted';

	private const KEY_ID = '$id';
	private const KEY_REF = '$ref';

	private ValueSerializer $valueSerializer;

	public function __construct(?ValueSerializer $valueSerializer = null)
	{
		$this->valueSerializer = $valueSerializer ?? new ValueSerializer();
	}

	public function getValueSerializer(): ValueSerializer
	{
		return $this->valueSerializer;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(ChangeSet $changeSet): array
	{
		$counts = [];
		$this->countReferences($changeSet, $counts);

		$ids = [];
		$emitted = [];

		return ['version' => self::VERSION] + $this->encodeChangeSet($changeSet, $counts, $ids, $emitted);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function fromArray(array $data): ChangeSet
	{
		$version = $data['version'] ?? 0;
		if ($version > self::VERSION) {
			throw new UnexpectedValueException("Change set format version {$version} is newer than the supported version " . self::VERSION . '.');
		}

		$references = [];
		$this->registerReferences($data, $references);

		return $this->decodeChangeSet($data, $references);
	}

	/**
	 * Zalozi prazdnou instanci pro kazde "$id" v dokumentu, jeste nez se cokoliv dekoduje.
	 *
	 * Dekodovat se musi dat i dokument, ve kterem "$ref" predchazi svemu "$id" - takove
	 * v change_logu jsou. Odkazy se rozpoustely v poradi cteni, takze skoncily na vyjimce
	 * "points to an unknown change set" a cely zaznam byl necitelny. Instance je tu drive
	 * nez kterykoliv odkaz na ni, takze na poradi uz nezalezi; naplni se, az dojde
	 * na uzel s "$id", a drzitel odkazu to vidi, protoze je to tentyz objekt.
	 *
	 * Odkaz na "$id", ktere v dokumentu nikde neni, zustava chybou - viz decodeChangeSet().
	 *
	 * @param array<string, mixed> $data
	 * @param array<int, ChangeSet> $references
	 */
	private function registerReferences(array $data, array &$references): void
	{
		if (isset($data[self::KEY_ID])) {
			$references[$data[self::KEY_ID]] ??= new ChangeSet();
		}

		foreach ($data['properties'] ?? [] as $property) {
			if (!is_array($property)) {
				continue;
			}

			if (isset($property['changeSet']) && is_array($property['changeSet'])) {
				$this->registerReferences($property['changeSet'], $references);
			}

			foreach ($property['changeSets'] ?? [] as $nested) {
				if (is_array($nested)) {
					$this->registerReferences($nested, $references);
				}
			}
		}
	}

	/**
	 * @param array<int, int> $counts
	 */
	private function countReferences(ChangeSet $changeSet, array &$counts): void
	{
		$oid = spl_object_id($changeSet);
		$counts[$oid] = ($counts[$oid] ?? 0) + 1;

		if ($counts[$oid] > 1) {
			return;
		}

		foreach ($changeSet->getChangedProperties() as $property) {
			if ($property instanceof ToOne) {
				if ($property->getChangeSet() !== null) {
					$this->countReferences($property->getChangeSet(), $counts);
				}
			} elseif ($property instanceof ToMany) {
				foreach ($property->getChangeSets() as $nested) {
					$this->countReferences($nested, $counts);
				}
			}
		}
	}

	/**
	 * @param array<int, int> $counts
	 * @param array<int, int> $ids
	 * @param array<int, true> $emitted
	 * @return array<string, mixed>
	 */
	private function encodeChangeSet(ChangeSet $changeSet, array $counts, array &$ids, array &$emitted): array
	{
		$oid = spl_object_id($changeSet);

		if (isset($emitted[$oid])) {
			return [self::KEY_REF => $ids[$oid]];
		}

		$data = [];
		if (($counts[$oid] ?? 1) > 1) {
			$ids[$oid] = count($ids) + 1;
			$data[self::KEY_ID] = $ids[$oid];
		}
		$emitted[$oid] = true;

		$data['action'] = $changeSet->getAction();
		$data['entity'] = $this->encodeId($changeSet->getIdentification());
		$data['properties'] = [];

		foreach ($changeSet->getChangedProperties() as $property) {
			$data['properties'][$property->getName()] = $this->encodeProperty($property, $counts, $ids, $emitted);
		}

		return $data;
	}

	/**
	 * @param array<int, int> $counts
	 * @param array<int, int> $ids
	 * @param array<int, true> $emitted
	 * @return array<string, mixed>
	 */
	private function encodeProperty(PropertyChangeSet $property, array $counts, array &$ids, array &$emitted): array
	{
		if ($property instanceof Scalar) {
			return [
				'type' => self::TYPE_SCALAR,
				'old' => $this->valueSerializer->encode($property->getOld()),
				'new' => $this->valueSerializer->encode($property->getNew()),
			];
		}

		// nese jen jmeno; hodnota se vedome nikam neuklada, takze ani neni co zapsat
		if ($property instanceof Redacted) {
			return ['type' => self::TYPE_REDACTED];
		}

		if ($property instanceof ToOne) {
			return [
				'type' => self::TYPE_TO_ONE,
				'old' => $this->encodeId($property->getOld()),
				'new' => $this->encodeId($property->getNew()),
				'changeSet' => $property->getChangeSet() !== null
					? $this->encodeChangeSet($property->getChangeSet(), $counts, $ids, $emitted)
					: null,
			];
		}

		if ($property instanceof ToMany) {
			return [
				'type' => self::TYPE_TO_MANY,
				'added' => array_map(fn (Id $id) => $this->encodeId($id), array_values($property->getAdded())),
				'removed' => array_map(fn (Id $id) => $this->encodeId($id), array_values($property->getRemoved())),
				'changeSets' => array_map(
					fn (ChangeSet $nested) => $this->encodeChangeSet($nested, $counts, $ids, $emitted),
					array_values($property->getChangeSets())
				),
			];
		}

		throw new UnexpectedValueException('There is no encoder for a property change set of type "' . $property::class . '".');
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function encodeId(?Id $id): ?array
	{
		if ($id === null) {
			return null;
		}

		return [
			'class' => $id->getClass(),
			'id' => $id->getId(),
			'identification' => $this->valueSerializer->encodeArray($id->getIdentification() ?: []),
		];
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<int, ChangeSet> $references
	 */
	private function decodeChangeSet(array $data, array &$references): ChangeSet
	{
		if (isset($data[self::KEY_REF])) {
			$ref = $data[self::KEY_REF];
			if (!isset($references[$ref])) {
				throw new UnexpectedValueException("Change set reference \"{$ref}\" points to an unknown change set.");
			}

			return $references[$ref];
		}

		// instance uz existuje z registerReferences(), aby na ni sel odkaz i zpetne;
		// cykly se diky tomu rozpousti na tentyz objekt jako driv
		$changeSet = isset($data[self::KEY_ID])
			? $references[$data[self::KEY_ID]] ??= new ChangeSet()
			: new ChangeSet();

		$changeSet->setAction($data['action'] ?? ChangeSet::ACTION_EDIT);
		$changeSet->setIdentification($this->decodeId($data['entity'] ?? null));

		foreach ($data['properties'] ?? [] as $name => $property) {
			$changeSet->restoreProperty($this->decodeProperty((string) $name, $property, $references));
		}

		return $changeSet;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<int, ChangeSet> $references
	 */
	private function decodeProperty(string $name, array $data, array &$references): PropertyChangeSet
	{
		switch ($data['type'] ?? null) {
			case self::TYPE_SCALAR:
				return new Scalar(
					$name,
					$this->valueSerializer->decode($data['old'] ?? null),
					$this->valueSerializer->decode($data['new'] ?? null)
				);

			case self::TYPE_REDACTED:
				return new Redacted($name);

			case self::TYPE_TO_ONE:
				$toOne = new ToOne($name, $this->decodeId($data['old'] ?? null), $this->decodeId($data['new'] ?? null));
				if (isset($data['changeSet'])) {
					$toOne->restoreChangeSet($this->decodeChangeSet($data['changeSet'], $references));
				}

				return $toOne;

			case self::TYPE_TO_MANY:
				$toMany = new ToMany($name);
				$toMany->restore(
					array_map(fn (array $id) => $this->decodeId($id), $data['added'] ?? []),
					array_map(fn (array $id) => $this->decodeId($id), $data['removed'] ?? []),
					array_map(fn (array $nested) => $this->decodeChangeSet($nested, $references), $data['changeSets'] ?? [])
				);

				return $toMany;
		}

		throw new UnexpectedValueException('There is no decoder for a property change set of type "' . ($data['type'] ?? 'null') . '".');
	}

	/**
	 * @param array<string, mixed>|null $data
	 */
	private function decodeId(?array $data): ?Id
	{
		if ($data === null) {
			return null;
		}

		return new Id(
			$data['id'] ?? null,
			$data['class'] ?? '',
			$this->valueSerializer->decodeArray($data['identification'] ?? [])
		);
	}
}
