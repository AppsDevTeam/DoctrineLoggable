<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\ChangeSet;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\PropertyChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use PHPUnit\Framework\TestCase;

final class ToOneTest extends TestCase
{
	public function testItExposesBothIdentifications(): void
	{
		$old = new Id('1', Author::class, ['name' => 'Franta']);
		$new = new Id('2', Author::class, ['name' => 'Pepa']);
		$toOne = new ToOne('author', $old, $new);

		self::assertSame('author', $toOne->getName());
		self::assertSame($old, $toOne->getOld());
		self::assertSame($new, $toOne->getNew());
		self::assertNull($toOne->getChangeSet());
		self::assertSame(PropertyChangeSet::TYPE_TO_ONE, $toOne->getType());
	}

	public function testTheSameIdentificationOnBothSidesIsNotAChange(): void
	{
		$identification = new Id('1', Author::class, []);

		self::assertFalse((new ToOne('author', $identification, $identification))->isChanged());
		self::assertFalse((new ToOne('author'))->isChanged());
	}

	public function testADifferentIdentificationIsAChange(): void
	{
		$toOne = new ToOne('author', new Id('1', Author::class, []), new Id('2', Author::class, []));

		self::assertTrue($toOne->isChanged());
	}

	public function testANestedChangeSetAloneIsAChange(): void
	{
		$identification = new Id('1', Author::class, []);
		$nested = new ChangeSet();
		$nested->addPropertyChange(new Scalar('name', 'Franta', 'František'));

		$toOne = new ToOne('author', $identification, $identification);
		$toOne->setChangeSet($nested);

		self::assertTrue($toOne->isChanged());
		self::assertSame($nested, $toOne->getChangeSet());
	}

	public function testSetChangeSetDropsAnUnchangedOne(): void
	{
		$toOne = new ToOne('author');
		$toOne->setChangeSet(new ChangeSet());

		self::assertNull($toOne->getChangeSet());

		$toOne->setChangeSet(null);
		self::assertNull($toOne->getChangeSet());
	}

	public function testRestoreChangeSetKeepsAnEmptyOne(): void
	{
		$empty = new ChangeSet();
		$toOne = new ToOne('author');
		$toOne->restoreChangeSet($empty);

		self::assertSame($empty, $toOne->getChangeSet());
	}

	public function testMergeTakesTheNewIdentificationAndChangeSet(): void
	{
		$first = new ToOne('author', new Id('1', Author::class, []), new Id('2', Author::class, []));

		$nested = new ChangeSet();
		$nested->addPropertyChange(new Scalar('name', 'a', 'b'));
		$second = new ToOne('author', new Id('2', Author::class, []), new Id('3', Author::class, []));
		$second->setChangeSet($nested);

		$first->merge($second);

		self::assertSame('1', $first->getOld()->getId());
		self::assertSame('3', $first->getNew()->getId());
		self::assertSame($nested, $first->getChangeSet());
	}
}
