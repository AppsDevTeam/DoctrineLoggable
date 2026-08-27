<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Attributes;

use ADT\DoctrineLoggable\Attributes\LoggableIdentification;
use PHPUnit\Framework\TestCase;

final class LoggableIdentificationTest extends TestCase
{
	public function testAPlainListOfFields(): void
	{
		self::assertSame(['name', 'email'], (new LoggableIdentification(['name', 'email']))->fields);
	}

	/**
	 * The annotation era spelling, #[LoggableIdentification(fields: [...])] lands here as well.
	 */
	public function testAWrappedListOfFields(): void
	{
		self::assertSame(['name'], (new LoggableIdentification(['fields' => ['name']]))->fields);
	}

	public function testAnEmptyListIsAllowed(): void
	{
		self::assertSame([], (new LoggableIdentification([]))->fields);
	}
}
