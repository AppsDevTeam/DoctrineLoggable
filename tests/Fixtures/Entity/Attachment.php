<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * A second logged collection of Article, mapped by a property name no other child has.
 */
#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['fileName'])]
class Attachment
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $fileName;

	#[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'attachments')]
	private ?Article $owner = null;

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

	public function getOwner(): ?Article
	{
		return $this->owner;
	}

	public function setOwner(?Article $owner): void
	{
		$this->owner = $owner;
	}
}
