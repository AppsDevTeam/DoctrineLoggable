<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer\Handlers;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

class DateTimeHandler implements ValueHandler
{
	public const TYPE = 'datetime';

	private const FORMAT = 'Y-m-d\TH:i:sP';

	private const FORMAT_MICROSECONDS = 'Y-m-d\TH:i:s.uP';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function supports(mixed $value): bool
	{
		return $value instanceof DateTimeInterface;
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		assert($value instanceof DateTimeInterface);

		return [
			'class' => $value::class,
			'value' => $value->format($value->format('u') === '000000' ? self::FORMAT : self::FORMAT_MICROSECONDS),
		];
	}

	public function decode(array $data, ValueSerializer $serializer): ?DateTimeInterface
	{
		if (!isset($data['value'])) {
			return null;
		}

		$class = $data['class'] ?? DateTimeImmutable::class;
		if (!is_a($class, DateTimeInterface::class, true)) {
			$class = DateTimeImmutable::class;
		}

		try {
			return new $class($data['value']);
		} catch (Throwable) {
			return new DateTimeImmutable($data['value']);
		}
	}
}
