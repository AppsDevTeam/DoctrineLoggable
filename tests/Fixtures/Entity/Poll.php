<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * A logged entity whose primary key is not an int, the shape a uuid keyed entity has.
 */
#[ORM\Entity]
#[ADA\LoggableEntity]
#[ADA\LoggableIdentification(fields: ['question'])]
class Poll
{
	#[ORM\Id]
	#[ORM\Column]
	private string $code;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $question;

	public function __construct(string $code, string $question)
	{
		$this->code = $code;
		$this->question = $question;
	}

	public function getId(): string
	{
		return $this->code;
	}

	public function getQuestion(): string
	{
		return $this->question;
	}

	public function setQuestion(string $question): void
	{
		$this->question = $question;
	}
}
