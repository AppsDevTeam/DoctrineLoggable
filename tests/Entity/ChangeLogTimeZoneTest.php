<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Entity;

use ADT\DoctrineLoggable\Entity\ChangeLog;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Change log konci vedle ostatnich logu (request log, auditni stopa), ktere cas pisou
 * v UTC. Kdyby si nesl zonu aplikace, nedaly by se mezi sebou porovnat - a poznalo by
 * se to az podle toho, ze zaznamy v prehledu skacou o hodinu nebo dve.
 */
class ChangeLogTimeZoneTest extends TestCase
{
	public function testCreatedAtIsInUtcRegardlessOfApplicationTimeZone(): void
	{
		$puvodni = date_default_timezone_get();
		date_default_timezone_set('Europe/Prague');

		try {
			$changeLog = new ChangeLog();

			$this->assertSame(
				'UTC',
				$changeLog->getCreatedAt()->getTimezone()->getName(),
				'cas vznikl v UTC, ne v zone aplikace',
			);
			$this->assertEqualsWithDelta(
				new \DateTimeImmutable('now', new DateTimeZone('UTC'))->getTimestamp(),
				$changeLog->getCreatedAt()->getTimestamp(),
				5,
				'okamzik sedi - neni to jen prestitkovana lokalni hodnota',
			);
		} finally {
			date_default_timezone_set($puvodni);
		}
	}
}
