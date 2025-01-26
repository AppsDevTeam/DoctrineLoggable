<?php

namespace ADT\DoctrineLoggable\Attributes;

use Attribute;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

final class AttributeReader
{
    /** @var array<string,bool> */
    private array $isRepeatableAttribute = [];

	/**
	 * @return array<Attribute|Attribute[]>
	 * @throws ReflectionException
	 */
	public function getClassAttributes(ReflectionClass $class): array
	{
		return $this->convertToAttributeInstances($class->getAttributes());
	}

	/**
	 * @phpstan-param class-string $annotationName
	 *
	 * @return Attribute|Attribute[]|null
	 * @throws ReflectionException
	 */
    public function getClassAttribute(ReflectionClass $class, string $annotationName): array|object|null
	{
		return $this->getClassAttributes($class)[$annotationName] ?? null;
    }

	/**
	 * @return array<Attribute|Attribute[]>
	 * @throws ReflectionException
	 */
    public function getPropertyAttributes(ReflectionProperty $property): array
    {
        return $this->convertToAttributeInstances($property->getAttributes());
    }

	/**
	 * @phpstan-param class-string $annotationName
	 *
	 * @return Attribute|Attribute[]|null
	 * @throws ReflectionException
	 */
    public function getPropertyAttribute(ReflectionProperty $property, string $annotationName): array|object|null
	{
        return $this->getPropertyAttributes($property)[$annotationName] ?? null;
    }

	/**
	 * @param array<ReflectionAttribute> $attributes
	 *
	 * @return array<string, Attribute|Attribute[]>
	 * @throws ReflectionException
	 */
	private function convertToAttributeInstances(array $attributes): array
	{
		$instances = [];

		foreach ($attributes as $attribute) {
			$attributeName = $attribute->getName();
			assert(is_string($attributeName));

			$instance = $attribute->newInstance();

			if ($this->isRepeatable($attributeName)) {
				if (!isset($instances[$attributeName])) {
					$instances[$attributeName] = [];
				}

				$instances[$attributeName][] = $instance;
			} else {
				$instances[$attributeName] = $instance;
			}
		}

		return $instances;
	}

	/**
	 * @throws ReflectionException
	 */
	private function isRepeatable(string $attributeClassName): bool
    {
        if (isset($this->isRepeatableAttribute[$attributeClassName])) {
            return $this->isRepeatableAttribute[$attributeClassName];
        }

        $reflectionClass = new ReflectionClass($attributeClassName);
        $attribute = $reflectionClass->getAttributes()[0]->newInstance();

        return $this->isRepeatableAttribute[$attributeClassName] = ($attribute->flags & Attribute::IS_REPEATABLE) > 0;
    }
}
