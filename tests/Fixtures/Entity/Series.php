<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * Half of a cycle, Issue::$series points back here and both properties are logged.
 */
#[ORM\Entity]
#[ADA\LoggableEntity]
#[ADA\LoggableIdentification(fields: ['name'])]
class Series
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $name;

	#[ORM\ManyToOne(targetEntity: Issue::class)]
	#[ADA\LoggableProperty]
	private ?Issue $latestIssue = null;

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

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getLatestIssue(): ?Issue
	{
		return $this->latestIssue;
	}

	public function setLatestIssue(?Issue $latestIssue): void
	{
		$this->latestIssue = $latestIssue;

		if ($latestIssue !== null) {
			$latestIssue->setSeries($this);
		}
	}
}
