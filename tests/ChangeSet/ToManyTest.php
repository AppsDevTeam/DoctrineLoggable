<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\ChangeSet;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\PropertyChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Tag;
use PHPUnit\Framework\TestCase;

final class ToManyTest extends TestCase
{
	public function testAnEmptyCollectionChangeIsNotAChange(): void
	{
		$toMany = new ToMany('tags');

		self::assertFalse($toMany->isChanged());
		self::assertSame('tags', $toMany->getName());
		self::assertSame(PropertyChangeSet::TYPE_TO_MANY, $toMany->getType());
	}

	public function testAddedAndRemovedAreKeptApart(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addAdded($this->tag('9', 'akce'));
		$toMany->addRemoved($this->tag('3', 'novinka'));

		self::assertTrue($toMany->isChanged());
		self::assertSame(['akce'], $this->names($toMany->getAdded()));
		self::assertSame(['novinka'], $this->names($toMany->getRemoved()));
	}

	public function testAddingTheSameIdentificationTwiceKeepsItOnce(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addAdded($this->tag('9', 'akce'));
		$toMany->addAdded($this->tag('9', 'akce'));

		self::assertCount(1, $toMany->getAdded());
	}

	public function testRemovingSomethingThatWasJustAddedCancelsBothOut(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addAdded($this->tag('9', 'akce'));
		$toMany->addRemoved($this->tag('9', 'akce'));

		self::assertSame([], $toMany->getAdded());
		self::assertSame([], $toMany->getRemoved());
		self::assertFalse($toMany->isChanged());
	}

	public function testAddingSomethingThatWasJustRemovedCancelsBothOut(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addRemoved($this->tag('9', 'akce'));
		$toMany->addAdded($this->tag('9', 'akce'));

		self::assertSame([], $toMany->getAdded());
		self::assertSame([], $toMany->getRemoved());
	}

	public function testAChangeSetOfAnItemIsAChangeOnItsOwn(): void
	{
		$nested = new ChangeSet();
		$nested->addPropertyChange(new Scalar('name', 'akce', 'sleva'));

		$toMany = new ToMany('tags');
		$toMany->addChangeSet($nested);

		self::assertTrue($toMany->isChanged());
		self::assertSame([$nested], $toMany->getChangeSets());
	}

	public function testEmptyAndNullChangeSetsAreIgnored(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addChangeSet(new ChangeSet());
		$toMany->addChangeSet(null);

		self::assertSame([], $toMany->getChangeSets());
	}

	public function testMergeCombinesBothCollectionChanges(): void
	{
		$first = new ToMany('tags');
		$first->addAdded($this->tag('9', 'akce'));

		$second = new ToMany('tags');
		$second->addAdded($this->tag('5', 'sleva'));
		$second->addRemoved($this->tag('3', 'novinka'));

		$first->merge($second);

		self::assertSame(['akce', 'sleva'], $this->names($first->getAdded()));
		self::assertSame(['novinka'], $this->names($first->getRemoved()));
	}

	public function testMergeCancelsOutWhatTheOtherSideRemoved(): void
	{
		$first = new ToMany('tags');
		$first->addAdded($this->tag('9', 'akce'));

		$second = new ToMany('tags');
		$second->addRemoved($this->tag('9', 'akce'));

		$first->merge($second);

		self::assertSame([], $first->getAdded());
		self::assertSame([], $first->getRemoved());
	}

	public function testRestoreAssignsEverythingWithoutAnyLookup(): void
	{
		$added = [$this->tag('9', 'akce')];
		$removed = [$this->tag('3', 'novinka')];
		$changeSets = [new ChangeSet()];

		$toMany = new ToMany('tags');
		$toMany->restore($added, $removed, $changeSets);

		self::assertSame($added, $toMany->getAdded());
		self::assertSame($removed, $toMany->getRemoved());
		self::assertSame($changeSets, $toMany->getChangeSets());
	}

	public function testRestoreReindexesTheArrays(): void
	{
		$toMany = new ToMany('tags');
		$toMany->restore([3 => $this->tag('9', 'akce')], [7 => $this->tag('3', 'novinka')], []);

		self::assertSame([0], array_keys($toMany->getAdded()));
		self::assertSame([0], array_keys($toMany->getRemoved()));
	}

	private function tag(string $id, string $name): Id
	{
		return new Id($id, Tag::class, ['name' => $name]);
	}

	/**
	 * @param Id[] $identifications
	 * @return string[]
	 */
	private function names(array $identifications): array
	{
		return array_values(array_map(fn (Id $id) => $id->getIdentification()['name'], $identifications));
	}
}
