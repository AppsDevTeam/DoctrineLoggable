<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer;

use ADT\DoctrineLoggable\Serializer\Handlers\ArrayHandler;
use ADT\DoctrineLoggable\Serializer\Handlers\BinaryHandler;
use ADT\DoctrineLoggable\Serializer\Handlers\DateTimeHandler;
use ADT\DoctrineLoggable\Serializer\Handlers\EnumHandler;
use ADT\DoctrineLoggable\Serializer\Handlers\FloatHandler;
use ADT\DoctrineLoggable\Serializer\Handlers\ObjectHandler;
use ADT\DoctrineLoggable\UnexpectedValueException;

/**
 * Converts logged property values to a JSON friendly structure.
 *
 * A value position holds either a plain JSON scalar (string, number, bool, null)
 * or an envelope object with the "@type" key. Arrays never appear raw in a value
 * position, so "@type" is always an unambiguous marker.
 */
class ValueSerializer
{
	public const TYPE_KEY = '@type';

	/** @var ValueHandler[] */
	private array $handlers;

	/**
	 * @param ValueHandler[] $handlers tried before the built-in ones, so they can override them
	 */
	public function __construct(array $handlers = [])
	{
		$this->handlers = [
			...array_values($handlers),
			new DateTimeHandler(),
			new EnumHandler(),
			new FloatHandler(),
			new BinaryHandler(),
			new ArrayHandler(),
			new ObjectHandler(),
		];
	}

	public function encode(mixed $value): mixed
	{
		foreach ($this->handlers as $handler) {
			if ($handler->supports($value)) {
				return [self::TYPE_KEY => $handler->getType()] + $handler->encode($value, $this);
			}
		}

		if ($value === null || is_scalar($value)) {
			return $value;
		}

		throw new UnexpectedValueException('There is no value handler for a value of type "' . get_debug_type($value) . '".');
	}

	public function decode(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		if (!isset($value[self::TYPE_KEY])) {
			throw new UnexpectedValueException('A value position holds an array without the "' . self::TYPE_KEY . '" key.');
		}

		foreach ($this->handlers as $handler) {
			if ($handler->getType() === $value[self::TYPE_KEY]) {
				return $handler->decode($value, $this);
			}
		}

		// the handler is gone, degrade to whatever is readable instead of breaking the whole log
		return $value['value'] ?? null;
	}

	/**
	 * Encodes a value nested inside an array. Unlike encode(), arrays stay arrays
	 * so that the contents of a json column remain readable.
	 */
	public function encodeNested(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $this->encode($value);
		}

		$encoded = $this->encodeArray($value);

		// the array itself contains our marker key, escape it so decoding stays unambiguous
		return isset($encoded[self::TYPE_KEY])
			? [self::TYPE_KEY => ArrayHandler::TYPE, 'value' => $encoded]
			: $encoded;
	}

	public function decodeNested(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		return isset($value[self::TYPE_KEY]) ? $this->decode($value) : $this->decodeArray($value);
	}

	/**
	 * @param array<mixed> $values
	 * @return array<mixed>
	 */
	public function encodeArray(array $values): array
	{
		return array_map(fn ($value) => $this->encodeNested($value), $values);
	}

	/**
	 * @param array<mixed> $values
	 * @return array<mixed>
	 */
	public function decodeArray(array $values): array
	{
		return array_map(fn ($value) => $this->decodeNested($value), $values);
	}
}
