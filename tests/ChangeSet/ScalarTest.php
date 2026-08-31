<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\ChangeSet;

use ADT\DoctrineLoggable\ChangeSet\PropertyChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\UnexpectedValueException;
use PHPUnit\Framework\TestCase;

final class ScalarTest extends TestCase
{
	public function testItExposesItsNameOldAndNewValue(): void
	{
		$scalar = new Scalar('title', 'Pivo', 'Pivo 12°');

		self::assertSame('title', $scalar->getName());
		self::assertSame('Pivo', $scalar->getOld());
		self::assertSame('Pivo 12°', $scalar->getNew());
		self::assertSame(PropertyChangeSet::TYPE_SCALAR, $scalar->getType());
	}

	public function testEqualValuesAreNotAChange(): void
	{
		self::assertFalse((new Scalar('title', 'Pivo', 'Pivo'))->isChanged());
		self::assertFalse((new Scalar('rating', null, null))->isChanged());
	}

	public function testDifferentValuesAreAChange(): void
	{
		self::assertTrue((new Scalar('title', 'Pivo', 'Pivo 12°'))->isChanged());
		self::assertTrue((new Scalar('rating', null, 5))->isChanged());
	}

	public function testMergeKeepsTheOriginalOldValue(): void
	{
		$scalar = new Scalar('title', 'Pivo', 'Pivo 12°');
		$scalar->merge(new Scalar('title', 'Pivo 12°', 'Pivo 11°'));

		self::assertSame('Pivo', $scalar->getOld());
		self::assertSame('Pivo 11°', $scalar->getNew());
	}

	public function testMergingWithAnotherTypeIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		(new Scalar('title', 'a', 'b'))->merge(new ToOne('author'));
	}

	public function testNameCanBeChanged(): void
	{
		$scalar = new Scalar('title', 'a', 'b');
		$scalar->setName('name');

		self::assertSame('name', $scalar->getName());
	}
}
