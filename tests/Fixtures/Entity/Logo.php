<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * The target of a unidirectional OneToOne, so there is no back reference to walk from here
 * up to the logged entity.
 */
#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['fileName'])]
class Logo
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $fileName;

	public function __construct(string $fileName)
	{
		$this->fileName = $fileName;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getFileName(): string
	{
		return $this->fileName;
	}

	public function setFileName(string $fileName): void
	{
		$this->fileName = $fileName;
	}
}
