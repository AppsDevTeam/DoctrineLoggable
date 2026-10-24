<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * Carries a plain #[LoggableEntity] of its own - it must not switch off the creation logging
 * its interface asks for.
 */
#[ORM\Entity]
#[ADA\LoggableEntity]
class Digest implements Subscriber
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	public function getId(): ?int
	{
		return $this->id;
	}
}
