<?php

namespace ADT\DoctrineLoggable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class LoggableIdentification
{
	public array $fields;

	public function __construct(array $fields)
	{
		if (isset($fields['fields'])) {
			$this->fields = $fields['fields'];
		} else {
			$this->fields = $fields;
		}
	}
}
