<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Legacy;

/**
 * Builds change set payloads in the format version 3 wrote with serialize().
 *
 * The stub classes below mirror what __sleep() used to keep: property names were left out of
 * Scalar, ToOne and ToMany, because __wakeup() restored them from the array keys of ChangeSet::$p.
 * The serialized class names are rewritten to the original "Adt" casing the old rows carry.
 */
final class LegacyPayloadBuilder
{
	private const CLASS_MAP = [
		ChangeSetStub::class => 'Adt\\DoctrineLoggable\\ChangeSet\\ChangeSet',
		IdStub::class => 'Adt\\DoctrineLoggable\\ChangeSet\\Id',
		ScalarStub::class => 'Adt\\DoctrineLoggable\\ChangeSet\\Scalar',
		ToOneStub::class => 'Adt\\DoctrineLoggable\\ChangeSet\\ToOne',
		ToManyStub::class => 'Adt\\DoctrineLoggable\\ChangeSet\\ToMany',
	];

	public static function build(ChangeSetStub $changeSet): string
	{
		return preg_replace_callback(
			'~O:\d+:"([^"]+)"~',
			static function (array $match): string {
				$class = self::CLASS_MAP[$match[1]] ?? $match[1];

				return 'O:' . strlen($class) . ':"' . $class . '"';
			},
			serialize($changeSet)
		);
	}
}
