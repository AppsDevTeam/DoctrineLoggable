<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Entity;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ChangeLogTest extends TestCase
{
	public function testItStampsItselfOnCreation(): void
	{
		$before = new DateTimeImmutable();
		$log = new ChangeLog();

		self::assertGreaterThanOrEqual($before, $log->getCreatedAt());
	}

	public function testEverythingItIsGivenComesBack(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));

		$log = new ChangeLog();
		$log->setAction(ChangeSet::ACTION_EDIT);
		$log->setObjectClass(Article::class);
		$log->setObjectId(42);
		$log->setChangeSet($changeSet);
		$log->setIdentityClass('App\\Model\\Entities\\Identity');
		$log->setIdentityId(7);

		self::assertSame(ChangeSet::ACTION_EDIT, $log->getAction());
		self::assertSame(Article::class, $log->getObjectClass());
		self::assertSame(42, $log->getObjectId());
		self::assertSame($changeSet, $log->getChangeSet());
		self::assertSame('App\\Model\\Entities\\Identity', $log->getIdentityClass());
		self::assertSame(7, $log->getIdentityId());
	}

	public function testAGuestLeavesNoIdentity(): void
	{
		$log = new ChangeLog();
		$log->setIdentityClass(null);
		$log->setIdentityId(null);

		self::assertNull($log->getIdentityClass());
		self::assertNull($log->getIdentityId());
	}

	public function testAnObjectIdIsOptional(): void
	{
		$log = new ChangeLog();
		$log->setObjectId(null);

		self::assertNull($log->getObjectId());
	}
}
