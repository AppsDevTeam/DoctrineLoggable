<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer\Handlers;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;

/**
 * Strings that are not valid UTF-8 would make json_encode() fail, so they are stored base64 encoded.
 */
class BinaryHandler implements ValueHandler
{
	public const TYPE = 'binary';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function supports(mixed $value): bool
	{
		return is_string($value) && preg_match('//u', $value) !== 1;
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		assert(is_string($value));

		return ['value' => base64_encode($value)];
	}

	public function decode(array $data, ValueSerializer $serializer): string
	{
		return base64_decode((string) ($data['value'] ?? ''), true) ?: '';
	}
}
