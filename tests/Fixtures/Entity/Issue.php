<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * The other half of the cycle, see Series.
 */
#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['title'])]
class Issue
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $title;

	#[ORM\ManyToOne(targetEntity: Series::class)]
	#[ADA\LoggableProperty]
	private ?Series $series = null;

	public function __construct(string $title)
	{
		$this->title = $title;
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

	public function getSeries(): ?Series
	{
		return $this->series;
	}

	public function setSeries(?Series $series): void
	{
		$this->series = $series;
	}
}
