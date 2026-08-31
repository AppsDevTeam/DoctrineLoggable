<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['text', 'author.name'])]
class Comment
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $text;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private DateTimeImmutable $createdAt;

	#[ORM\ManyToOne(targetEntity: Author::class)]
	private ?Author $author = null;

	#[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'comments')]
	private ?Article $article = null;

	public function __construct(string $text, ?Author $author = null)
	{
		$this->text = $text;
		$this->author = $author;
		$this->createdAt = new DateTimeImmutable('2026-08-27 12:00:00');
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getText(): string
	{
		return $this->text;
	}

	public function setText(string $text): void
	{
		$this->text = $text;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function getAuthor(): ?Author
	{
		return $this->author;
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
