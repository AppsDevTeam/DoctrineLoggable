<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Legacy;

class IdStub
{
	protected string $id;

	protected string $c;

	protected array $d;

	public function __construct(string $id, string $class, array $identificationData = [])
	{
		$this->id = $id;
		$this->c = $class;
		$this->d = $identificationData;
	}
}
