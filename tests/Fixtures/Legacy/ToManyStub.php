<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Legacy;

class ToManyStub
{
	protected array $r;

	protected array $a;

	protected array $ch;

	/**
	 * @param IdStub[] $removed
	 * @param IdStub[] $added
	 * @param ChangeSetStub[] $changeSets
	 */
	public function __construct(array $removed = [], array $added = [], array $changeSets = [])
	{
		$this->r = $removed;
		$this->a = $added;
		$this->ch = $changeSets;
	}
}
