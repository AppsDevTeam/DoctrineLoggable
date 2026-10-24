<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * Logged only through the interface, the class itself says nothing.
 */
#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['name'])]
class Newsletter implements Subscriber
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}
}
