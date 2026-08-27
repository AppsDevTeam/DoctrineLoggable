<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer\Handlers;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;
use JsonSerializable;
use Stringable;

/**
 * Last resort fallback for value objects coming from custom Doctrine types.
 *
 * Decoding is lossy on purpose, it returns the readable representation instead of the
 * original object. Register a dedicated ValueHandler if a lossless round trip is needed.
 */
class ObjectHandler implements ValueHandler
{
	public const TYPE = 'object';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function supports(mixed $value): bool
	{
		return is_object($value);
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		assert(is_object($value));

		if ($value instanceof Stringable) {
			return ['class' => $value::class, 'value' => (string) $value];
		}

		if ($value instanceof JsonSerializable) {
			return ['class' => $value::class, 'value' => $serializer->encodeNested($value->jsonSerialize())];
		}

		return ['class' => $value::class, 'value' => null];
	}

	public function decode(array $data, ValueSerializer $serializer): mixed
	{
		return $serializer->decodeNested($data['value'] ?? null);
	}
}
