<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['name'])]
class Tag
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	private string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}
}
