<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures;

use ADT\DoctrineLoggable\Entity\ChangeLog;

/**
 * Stands in for a consumer of LoggableListener::$onLogEntry, e.g. an audit trail subscriber.
 */
final class LogEntryRecorder
{
	/** @var list<array{ChangeLog, object, bool}> */
	public array $recorded = [];

	public function logEntry(ChangeLog $logEntry, object $entity, bool $announced): void
	{
		$this->recorded[] = [$logEntry, $entity, $announced];
	}
}
