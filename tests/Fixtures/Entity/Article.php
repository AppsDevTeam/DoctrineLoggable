<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ADA\LoggableEntity]
#[ADA\LoggableIdentification(fields: ['title'])]
class Article
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $title;

	#[ORM\Column(nullable: true)]
	#[ADA\LoggableProperty]
	private ?int $rating = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	#[ADA\LoggableProperty]
	private ?DateTimeImmutable $publishedAt = null;

	#[ORM\Column(nullable: true, enumType: ArticleStateEnum::class)]
	#[ADA\LoggableProperty]
	private ?ArticleStateEnum $state = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	#[ADA\LoggableProperty]
	private ?array $meta = null;

	#[ORM\ManyToOne(targetEntity: Author::class)]
	#[ADA\LoggableProperty]
	private ?Author $author = null;

	/** @var Collection<int, Tag> */
	#[ORM\ManyToMany(targetEntity: Tag::class)]
	#[ADA\LoggableProperty]
	private Collection $tags;

	#[ORM\OneToOne(targetEntity: Cover::class, mappedBy: 'article')]
	#[ADA\LoggableProperty]
	private ?Cover $cover = null;

	/** @var Collection<int, Comment> */
	#[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'article')]
	#[ADA\LoggableProperty]
	private Collection $comments;

	public function __construct(string $title)
	{
		$this->title = $title;
		$this->tags = new ArrayCollection();
		$this->comments = new ArrayCollection();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function setTitle(string $title): void
	{
		$this->title = $title;
	}

	public function setRating(?int $rating): void
	{
		$this->rating = $rating;
	}

	public function setPublishedAt(?DateTimeImmutable $publishedAt): void
	{
		$this->publishedAt = $publishedAt;
	}

	public function setState(?ArticleStateEnum $state): void
	{
		$this->state = $state;
	}

	public function setMeta(?array $meta): void
	{
		$this->meta = $meta;
	}

	public function getAuthor(): ?Author
	{
		return $this->author;
	}

	public function setAuthor(?Author $author): void
	{
		$this->author = $author;
	}

	/** @return Collection<int, Tag> */
	public function getTags(): Collection
	{
		return $this->tags;
	}

	public function addTag(Tag $tag): void
	{
		$this->tags->add($tag);
	}

	public function removeTag(Tag $tag): void
	{
		$this->tags->removeElement($tag);
	}

	public function getCover(): ?Cover
	{
		return $this->cover;
	}

	public function setCover(?Cover $cover): void
	{
		$this->cover = $cover;

		if ($cover !== null) {
			$cover->setArticle($this);
		}
	}

	/** @return Collection<int, Comment> */
	public function getComments(): Collection
	{
		return $this->comments;
	}

	public function addComment(Comment $comment): void
	{
		$this->comments->add($comment);
		$comment->setArticle($this);
	}

	public function removeComment(Comment $comment): void
	{
		$this->comments->removeElement($comment);
		$comment->setArticle(null);
	}
}
