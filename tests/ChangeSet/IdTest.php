<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\ChangeSet;

use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use PHPUnit\Framework\TestCase;

final class IdTest extends TestCase
{
	public function testItExposesEverythingItWasGiven(): void
	{
		$id = new Id('42', Author::class, ['name' => 'Franta']);

		self::assertSame('42', $id->getId());
		self::assertSame(Author::class, $id->getClass());
		self::assertSame(['name' => 'Franta'], $id->getIdentification());
	}

	/**
	 * An entity persisted in this very flush has no id yet, postPersist fills it in later.
	 */
	public function testTheIdCanBeFilledInAfterwards(): void
	{
		$id = new Id('', Author::class, []);
		$id->setId('7');

		self::assertSame('7', $id->getId());
	}
}
