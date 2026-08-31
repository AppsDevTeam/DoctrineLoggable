<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Legacy;

class ToOneStub
{
	protected ?IdStub $o;

	protected ?IdStub $n;

	protected ?ChangeSetStub $ch;

	public function __construct(?IdStub $old = null, ?IdStub $new = null, ?ChangeSetStub $changeSet = null)
	{
		$this->o = $old;
		$this->n = $new;
		$this->ch = $changeSet;
	}
}
