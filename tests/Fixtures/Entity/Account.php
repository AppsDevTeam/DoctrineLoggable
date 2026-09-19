<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entita s vlastností, u které se loguje změna, ale ne hodnota - viz
 * LoggableProperty::$withValue. V praxi hash hesla.
 */
#[ORM\Entity]
#[ADA\LoggableEntity]
#[ADA\LoggableIdentification(fields: ['email'])]
class Account
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column]
	#[ADA\LoggableProperty]
	private string $email;

	#[ORM\Column(nullable: true)]
	#[ADA\LoggableProperty(withValue: false)]
	private ?string $password = null;

	#[ORM\ManyToOne(targetEntity: Author::class)]
	#[ADA\LoggableProperty(withValue: false)]
	private ?Author $owner = null;

	public function __construct(string $email)
	{
		$this->email = $email;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

	public function setPassword(?string $password): void
	{
		$this->password = $password;
	}

	public function setOwner(?Author $owner): void
	{
		$this->owner = $owner;
	}
}
