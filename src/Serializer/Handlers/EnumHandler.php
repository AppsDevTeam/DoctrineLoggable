<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer\Handlers;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;
use BackedEnum;
use UnitEnum;

class EnumHandler implements ValueHandler
{
	public const TYPE = 'enum';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function supports(mixed $value): bool
	{
		return $value instanceof UnitEnum;
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		assert($value instanceof UnitEnum);

		return [
			'class' => $value::class,
			'value' => $value instanceof BackedEnum ? $value->value : $value->name,
		];
	}

	public function decode(array $data, ValueSerializer $serializer): mixed
	{
		$value = $data['value'] ?? null;
		$class = $data['class'] ?? null;

		if ($value === null || !is_string($class) || !enum_exists($class)) {
			return $value;
		}

		if (is_a($class, BackedEnum::class, true)) {
			return $class::tryFrom($value) ?? $value;
		}

		foreach ($class::cases() as $case) {
			if ($case->name === $value) {
				return $case;
			}
		}

		// the case was removed from the code, keep the raw value readable
		return $value;
	}
}
