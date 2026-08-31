<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;

final class MoneyHandler implements ValueHandler
{
	public function getType(): string
	{
		return 'money';
	}

	public function supports(mixed $value): bool
	{
		return $value instanceof Money;
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		return ['amount' => $value->amount, 'currency' => $value->currency];
	}

	public function decode(array $data, ValueSerializer $serializer): Money
	{
		return new Money($data['amount'], $data['currency']);
	}
}
