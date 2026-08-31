<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Legacy;

class ScalarStub
{
	protected mixed $o;

	protected mixed $n;

	public function __construct(mixed $old, mixed $new)
	{
		$this->o = $old;
		$this->n = $new;
	}
}
