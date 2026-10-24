<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A logged entity whose creation is logged too.
 */
#[ORM\Entity]
#[ADA\LoggableEntity(logCreate: true)]
#[ADA\LoggableIdentification(fields: ['email'])]
class Member
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	private string $email;

	#[ORM\Column(nullable: true)]
	#[ADA\LoggableProperty]
	private ?string $nickname = null;

	#[ORM\ManyToOne(targetEntity: Author::class)]
	#[ADA\LoggableProperty]
	private ?Author $author = null;

	/** @var Collection<int, Tag> */
	#[ORM\ManyToMany(targetEntity: Tag::class)]
	#[ADA\LoggableProperty]
	private Collection $tags;

	public function __construct(string $email)
	{
		$this->email = $email;
		$this->tags = new ArrayCollection();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setNickname(?string $nickname): void
	{
		$this->nickname = $nickname;
	}

	public function setAuthor(?Author $author): void
	{
		$this->author = $author;
	}

	public function addTag(Tag $tag): void
	{
		$this->tags->add($tag);
	}
}
