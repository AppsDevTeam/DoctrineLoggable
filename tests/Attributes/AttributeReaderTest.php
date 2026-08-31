<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Attributes;

use ADT\DoctrineLoggable\Attributes\AttributeReader;
use ADT\DoctrineLoggable\Attributes\LoggableEntity;
use ADT\DoctrineLoggable\Attributes\LoggableIdentification;
use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\ManyToOne;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class AttributeReaderTest extends TestCase
{
	private AttributeReader $reader;

	protected function setUp(): void
	{
		$this->reader = new AttributeReader();
	}

	public function testClassAttributesAreKeyedByTheirClassName(): void
	{
		$attributes = $this->reader->getClassAttributes(new ReflectionClass(Article::class));

		self::assertArrayHasKey(LoggableEntity::class, $attributes);
		self::assertArrayHasKey(LoggableIdentification::class, $attributes);
	}

	public function testASingleClassAttributeIsReturnedAsAnInstance(): void
	{
		$attribute = $this->reader->getClassAttribute(new ReflectionClass(Author::class), LoggableIdentification::class);

		self::assertInstanceOf(LoggableIdentification::class, $attribute);
		self::assertSame(['name'], $attribute->fields);
	}

	public function testAMissingClassAttributeIsNull(): void
	{
		self::assertNull($this->reader->getClassAttribute(new ReflectionClass(Author::class), LoggableEntity::class));
	}

	public function testARepeatableAttributeIsReturnedAsAList(): void
	{
		$indexes = $this->reader->getClassAttribute(new ReflectionClass(\ADT\DoctrineLoggable\Entity\ChangeLog::class), Index::class);

		self::assertIsArray($indexes);
		self::assertContainsOnlyInstancesOf(Index::class, $indexes);
		self::assertCount(3, $indexes);
	}

	public function testPropertyAttributesAreKeyedByTheirClassName(): void
	{
		$attributes = $this->reader->getPropertyAttributes(new ReflectionProperty(Article::class, 'title'));

		self::assertArrayHasKey(Column::class, $attributes);
		self::assertArrayHasKey(LoggableProperty::class, $attributes);
	}

	public function testASinglePropertyAttributeIsReturnedAsAnInstance(): void
	{
		$attribute = $this->reader->getPropertyAttribute(new ReflectionProperty(Article::class, 'author'), ManyToOne::class);

		self::assertInstanceOf(ManyToOne::class, $attribute);
		self::assertSame(Author::class, $attribute->targetEntity);
	}

	public function testAMissingPropertyAttributeIsNull(): void
	{
		self::assertNull($this->reader->getPropertyAttribute(new ReflectionProperty(Article::class, 'id'), LoggableProperty::class));
	}
}
