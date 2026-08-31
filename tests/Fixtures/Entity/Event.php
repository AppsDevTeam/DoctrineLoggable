<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Covers the trickier identification fields: a date with a time, a date at midnight and a path
 * that walks into a collection.
 */
#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['startsAt', 'wholeDayAt', 'tags.name'])]
class Event
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private DateTimeImmutable $startsAt;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private DateTimeImmutable $wholeDayAt;

	/** @var Collection<int, Tag> */
	#[ORM\ManyToMany(targetEntity: Tag::class)]
	private Collection $tags;

	public function __construct()
	{
		$this->startsAt = new DateTimeImmutable('2026-08-27 14:30:00');
		$this->wholeDayAt = new DateTimeImmutable('2026-08-27 00:00:00');
		$this->tags = new ArrayCollection();
	}

	public function getId(): ?int
	{
		return $this->id;
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
}
