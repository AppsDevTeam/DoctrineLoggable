<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer\Handlers;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;

/**
 * NAN and INF are valid PHP floats but json_encode() refuses them.
 */
class FloatHandler implements ValueHandler
{
	public const TYPE = 'float';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function supports(mixed $value): bool
	{
		return is_float($value) && !is_finite($value);
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		assert(is_float($value));

		if (is_nan($value)) {
			return ['value' => 'NAN'];
		}

		return ['value' => $value > 0 ? 'INF' : '-INF'];
	}

	public function decode(array $data, ValueSerializer $serializer): float
	{
		return match ($data['value'] ?? null) {
			'INF' => INF,
			'-INF' => -INF,
			default => NAN,
		};
	}
}
