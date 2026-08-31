<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\ChangeSet;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use PHPUnit\Framework\TestCase;

final class ChangeSetTest extends TestCase
{
	public function testItStartsEmptyAndEditing(): void
	{
		$changeSet = new ChangeSet();

		self::assertFalse($changeSet->isChanged());
		self::assertSame(ChangeSet::ACTION_EDIT, $changeSet->getAction());
		self::assertSame([], $changeSet->getChangedProperties());
		self::assertNull($changeSet->getIdentification());
	}

	public function testChangedPropertiesAreKeyedByName(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));

		self::assertTrue($changeSet->isChanged());
		self::assertSame(['title'], array_keys($changeSet->getChangedProperties()));
	}

	public function testUnchangedPropertiesAreDropped(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo'));

		self::assertFalse($changeSet->isChanged());
		self::assertSame([], $changeSet->getChangedProperties());
	}

	public function testAddingTheSamePropertyTwiceMergesIt(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo 12°', 'Pivo 11°'));

		$title = $changeSet->getChangedProperties()['title'];
		self::assertCount(1, $changeSet->getChangedProperties());
		self::assertSame('Pivo', $title->getOld());
		self::assertSame('Pivo 11°', $title->getNew());
	}

	public function testActionAndIdentificationAreFluent(): void
	{
		$identification = new Id('42', Article::class, ['title' => 'Pivo']);
		$changeSet = new ChangeSet();

		self::assertSame($changeSet, $changeSet->setAction(ChangeSet::ACTION_DELETE));
		self::assertSame($changeSet, $changeSet->setIdentification($identification));
		self::assertSame(ChangeSet::ACTION_DELETE, $changeSet->getAction());
		self::assertSame($identification, $changeSet->getIdentification());
	}

	public function testRestorePropertyKeepsEvenAnUnchangedProperty(): void
	{
		$changeSet = new ChangeSet();
		$toOne = new ToOne('author', null, null);
		$changeSet->restoreProperty($toOne);

		self::assertSame(['author' => $toOne], $changeSet->getChangedProperties());
	}

	public function testRestorePropertyOverwritesInsteadOfMerging(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));
		$changeSet->restoreProperty(new Scalar('title', 'a', 'b'));

		self::assertSame('a', $changeSet->getChangedProperties()['title']->getOld());
	}
}
