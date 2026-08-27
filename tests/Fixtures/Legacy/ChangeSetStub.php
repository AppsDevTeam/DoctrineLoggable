<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Legacy;

class ChangeSetStub
{
	protected string $a = 'edit';

	protected ?IdStub $i = null;

	/** @var array<string, object> keyed by property name, that is how __wakeup() restored them */
	protected array $p = [];

	public function setAction(string $action): static
	{
		$this->a = $action;

		return $this;
	}

	public function setIdentification(?IdStub $identification): static
	{
		$this->i = $identification;

		return $this;
	}

	public function addProperty(string $name, object $property): static
	{
		$this->p[$name] = $property;

		return $this;
	}
}
