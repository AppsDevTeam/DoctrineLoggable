<?php

namespace ADT\DoctrineLoggable\Entity;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\Doctrine\ChangeSetType;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Index(fields: ['objectClass', 'objectId'])]
#[ORM\Index(fields: ['createdAt'])]
#[ORM\Index(fields: ['identityClass', 'identityId'])]
#[ORM\Entity]
class ChangeLog
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column(length: 8, nullable: false)]
	private string $action;

	#[ORM\Column(nullable: false)]
	private DateTimeImmutable $createdAt;

	#[ORM\Column(nullable: false)]
	private string $objectClass;

	#[ORM\Column(nullable: true)]
	private ?int $objectId = null;

	#[ORM\Column(type: ChangeSetType::NAME, nullable: false)]
	private ChangeSet $changeSet;

	#[ORM\Column(nullable: true)]
	private ?string $identityClass = null;

	#[ORM\Column(nullable: true)]
	private ?int $identityId = null;

	/**
	 * Cas se uklada v UTC, ne v zone aplikace.
	 *
	 * Change log konci vedle ostatnich logu - typicky v oddelenem ulozisti, kam se odvazi
	 * i request log nebo auditni stopa, a ty UTC pisou vzdycky. Kdyby si kazda tabulka
	 * nesla jinou zonu, nedaly by se mezi sebou porovnat a nikde by to nebylo videt.
	 * Uzivateli se cas stejne prevadi az pri zobrazeni.
	 */
	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getAction(): string
	{
		return $this->action;
	}

	public function setAction(string $action): void
	{
		$this->action = $action;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function getObjectClass(): string
	{
		return $this->objectClass;
	}

	public function setObjectClass(string $objectClass): void
	{
		$this->objectClass = $objectClass;
	}

	public function getObjectId(): ?int
	{
		return $this->objectId;
	}

	public function setObjectId(?int $objectId): void
	{
		$this->objectId = $objectId;
	}

	public function getChangeSet(): ChangeSet
	{
		return $this->changeSet;
	}

	public function setChangeSet(ChangeSet $changeSet): void
	{
		$this->changeSet = $changeSet;
	}

	public function getIdentityClass(): ?string
	{
		return $this->identityClass;
	}

	public function setIdentityClass(?string $identityClass): void
	{
		$this->identityClass = $identityClass;
	}

	public function getIdentityId(): ?int
	{
		return $this->identityId;
	}

	public function setIdentityId(?int $identityId): void
	{
		$this->identityId = $identityId;
	}
}