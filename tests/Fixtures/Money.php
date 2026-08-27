<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures;

final class Money
{
	public function __construct(public readonly int $amount, public readonly string $currency)
	{
	}
}
