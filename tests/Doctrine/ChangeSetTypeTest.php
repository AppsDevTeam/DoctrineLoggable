<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Doctrine;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\Doctrine\ChangeSetType;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;

final class ChangeSetTypeTest extends TestCase
{
	private ChangeSetType $type;

	private SQLitePlatform $platform;

	protected function setUp(): void
	{
		$this->type = new ChangeSetType();
		$this->platform = new SQLitePlatform();
	}

	public function testNullPassesThrough(): void
	{
		self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
		self::assertNull($this->type->convertToPHPValue(null, $this->platform));
		self::assertNull($this->type->convertToPHPValue('', $this->platform));
	}

	public function testChangeSetIsStoredAsJsonAndReadBack(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->setIdentification(new Id('42', Article::class, ['title' => 'Pivo']));
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));

		$stored = $this->type->convertToDatabaseValue($changeSet, $this->platform);

		self::assertJson($stored);
		self::assertStringContainsString('"title"', $stored);

		$decoded = $this->type->convertToPHPValue($stored, $this->platform);
		self::assertSame('Pivo 12°', $decoded->getChangedProperties()['title']->getNew());
	}

	public function testStreamResourceIsSupported(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'a', 'b'));

		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $this->type->convertToDatabaseValue($changeSet, $this->platform));
		rewind($stream);

		$decoded = $this->type->convertToPHPValue($stream, $this->platform);
		fclose($stream);

		self::assertSame('b', $decoded->getChangedProperties()['title']->getNew());
	}

	public function testNonChangeSetValueIsRejected(): void
	{
		$this->expectException(InvalidType::class);

		$this->type->convertToDatabaseValue('nope', $this->platform);
	}

	public function testBrokenJsonIsRejected(): void
	{
		$this->expectException(ValueNotConvertible::class);

		$this->type->convertToPHPValue('{not json', $this->platform);
	}

	public function testScalarJsonIsRejected(): void
	{
		$this->expectException(ValueNotConvertible::class);

		$this->type->convertToPHPValue('42', $this->platform);
	}

	public function testRegisteringTwiceOverridesTheType(): void
	{
		ChangeSetType::register();
		ChangeSetType::register();

		self::assertInstanceOf(ChangeSetType::class, \Doctrine\DBAL\Types\Type::getType(ChangeSetType::NAME));
	}
}
