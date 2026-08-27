<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * The owning side of the OneToOne, Article holds the inverse side.
 */
#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['fileName'])]
class Cover
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $fileName;

	#[ORM\OneToOne(targetEntity: Article::class, inversedBy: 'cover')]
	private ?Article $article = null;

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

	public function getArticle(): ?Article
	{
		return $this->article;
	}

	public function setArticle(?Article $article): void
	{
		$this->article = $article;
	}
}
