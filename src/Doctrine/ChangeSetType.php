<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Doctrine;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;
use JsonException;

/**
 * Stores a ChangeSet as JSON, so that the log is readable straight from the database.
 *
 * A change log row is only ever inserted, never loaded and updated, which matters because
 * Doctrine compares object valued fields by identity. Mutating a loaded ChangeSet in place
 * would not be detected as a change.
 */
class ChangeSetType extends JsonType
{
	public const NAME = 'change_set';

	private const ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	private ?ChangeSetSerializer $serializer;

	public function __construct(?ChangeSetSerializer $serializer = null)
	{
		$this->serializer = $serializer;
	}

	/**
	 * Registers the type. Safe to call repeatedly, the last registration wins.
	 */
	public static function register(?ChangeSetSerializer $serializer = null): void
	{
		$type = new self($serializer);

		if (Type::hasType(self::NAME)) {
			Type::overrideType(self::NAME, $type);
		} else {
			Type::addType(self::NAME, $type);
		}
	}

	public function getSerializer(): ChangeSetSerializer
	{
		return $this->serializer ??= new ChangeSetSerializer();
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
	{
		if ($value === null) {
			return null;
		}

		if (!$value instanceof ChangeSet) {
			throw InvalidType::new($value, self::NAME, [ChangeSet::class, 'null']);
		}

		try {
			return json_encode($this->getSerializer()->toArray($value), self::ENCODE_FLAGS);
		} catch (JsonException $e) {
			throw SerializationFailed::new($value, 'json', $e->getMessage(), $e);
		}
	}

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ChangeSet
	{
		if ($value === null || $value === '') {
			return null;
		}

		if ($value instanceof ChangeSet) {
			return $value;
		}

		if (is_resource($value)) {
			$value = stream_get_contents($value);
		}

		try {
			$data = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw ValueNotConvertible::new($value, self::NAME, $e->getMessage(), $e);
		}

		if (!is_array($data)) {
			throw ValueNotConvertible::new($value, self::NAME, 'The stored value is not a JSON object.');
		}

		return $this->getSerializer()->fromArray($data);
	}
}
