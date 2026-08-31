<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Service;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\UnexpectedValueException;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Rewrites change_log rows written by version 3 and older, where the change set was a PHP
 * serialize() payload, into the JSON format of version 4.
 *
 * Run this while the column is still a BLOB, only then change it to a JSON column:
 *
 *     ALTER TABLE change_log MODIFY change_set JSON NOT NULL;
 *
 * Rows are read and written with plain SQL, so the change_set DBAL type is never involved and
 * cannot choke on the legacy payload. Already converted rows are recognised and left alone,
 * which makes the whole thing safe to run repeatedly.
 */
class LegacyChangeSetConverter
{
	private Connection $connection;

	private ChangeSetSerializer $serializer;

	private string $table;

	public function __construct(Connection $connection, ChangeSetSerializer $serializer, string $table = 'change_log')
	{
		$this->connection = $connection;
		$this->serializer = $serializer;
		$this->table = $table;
	}

	public function countRows(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->quotedTable());
	}

	/**
	 * @param callable(LegacyChangeSetConversionResult): void|null $onBatch called after every committed batch
	 */
	public function convert(int $batchSize = 500, ?callable $onBatch = null, bool $dryRun = false): LegacyChangeSetConversionResult
	{
		if ($batchSize < 1) {
			throw new UnexpectedValueException('The batch size has to be at least 1.');
		}

		$total = new LegacyChangeSetConversionResult();
		$lastId = 0;

		while (true) {
			$rows = $this->connection->fetchAllAssociative(
				'SELECT id, change_set FROM ' . $this->quotedTable() . ' WHERE id > ? ORDER BY id ASC LIMIT ' . $batchSize,
				[$lastId]
			);

			if (!$rows) {
				break;
			}

			$batch = $this->convertBatch($rows, $dryRun);
			$total->add($batch);
			$lastId = (int) $rows[array_key_last($rows)]['id'];

			if ($onBatch !== null) {
				$onBatch($batch);
			}
		}

		return $total;
	}

	/**
	 * @param array<array{id: mixed, change_set: mixed}> $rows
	 */
	private function convertBatch(array $rows, bool $dryRun): LegacyChangeSetConversionResult
	{
		$result = new LegacyChangeSetConversionResult();

		$this->connection->beginTransaction();

		try {
			foreach ($rows as $row) {
				$id = (int) $row['id'];
				$payload = $this->readPayload($row['change_set']);

				if ($this->isAlreadyJson($payload)) {
					$result->addSkipped();
					continue;
				}

				try {
					$json = $this->convertPayload($payload);
				} catch (Throwable $e) {
					$result->addFailure($id, $e->getMessage());
					continue;
				}

				if (!$dryRun) {
					$this->connection->executeStatement(
						'UPDATE ' . $this->quotedTable() . ' SET change_set = ? WHERE id = ?',
						[$json, $id]
					);
				}

				$result->addConverted();
			}

			if ($dryRun) {
				$this->connection->rollBack();
			} else {
				$this->connection->commit();
			}
		} catch (Throwable $e) {
			$this->connection->rollBack();
			throw $e;
		}

		return $result;
	}

	public function convertPayload(string $payload): string
	{
		self::warmUpChangeSetClasses();

		$changeSet = @unserialize($payload);

		if (!$changeSet instanceof ChangeSet) {
			throw new UnexpectedValueException('The payload does not unserialize into a ' . ChangeSet::class . '.');
		}

		// __sleep() left the property names out of the payload, __wakeup() used to put them back
		$this->restoreNames($changeSet, []);

		return json_encode(
			$this->serializer->toArray($changeSet),
			JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Legacy payloads carry the original "Adt" spelling of the namespace. PHP resolves class names
	 * case insensitively, but only once the class is in the class table, and a composer classmap
	 * built as authoritative refuses to autoload a spelling it does not hold. Loading the classes
	 * up front makes unserialize() find them whatever the payload says.
	 */
	private static function warmUpChangeSetClasses(): void
	{
		foreach ([ChangeSet::class, Id::class, Scalar::class, ToOne::class, ToMany::class] as $class) {
			class_exists($class);
		}
	}

	/**
	 * @param array<int, true> $visited guards against the cycles the old format allowed
	 */
	private function restoreNames(ChangeSet $changeSet, array $visited): void
	{
		$oid = spl_object_id($changeSet);
		if (isset($visited[$oid])) {
			return;
		}
		$visited[$oid] = true;

		foreach ($changeSet->getChangedProperties() as $name => $property) {
			$property->setName((string) $name);

			if ($property instanceof ToOne) {
				if ($property->getChangeSet() !== null) {
					$this->restoreNames($property->getChangeSet(), $visited);
				}
			} elseif ($property instanceof ToMany) {
				foreach ($property->getChangeSets() as $nested) {
					$this->restoreNames($nested, $visited);
				}
			}
		}
	}

	private function readPayload(mixed $value): string
	{
		if (is_resource($value)) {
			$value = stream_get_contents($value);
		}

		return (string) $value;
	}

	/**
	 * A JSON payload always starts with "{", a serialize() payload never does. Deciding this in
	 * PHP rather than in SQL keeps the converter working on every platform.
	 */
	private function isAlreadyJson(string $payload): bool
	{
		return str_starts_with(ltrim($payload), '{');
	}

	private function quotedTable(): string
	{
		return $this->connection->quoteSingleIdentifier($this->table);
	}
}
