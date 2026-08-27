<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer\Handlers;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;

class ArrayHandler implements ValueHandler
{
	public const TYPE = 'array';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function supports(mixed $value): bool
	{
		return is_array($value);
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		assert(is_array($value));

		return ['value' => $serializer->encodeArray($value)];
	}

	public function decode(array $data, ValueSerializer $serializer): array
	{
		$value = $data['value'] ?? [];

		return is_array($value) ? $serializer->decodeArray($value) : [$value];
	}
}
