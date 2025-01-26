<?php

namespace ADT\DoctrineLoggable\Entity;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Index(name: 'log_entity_lookup_idx', fields: ['objectId', 'objectClass'])]
#[ORM\Index(fields: ['loggedAt'])]
#[ORM\Entity]
class LogEntry
{
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column(length: 8, nullable: false)]
	private string $action;

	#[ORM\Column(nullable: false)]
	private DateTimeImmutable $loggedAt;

	#[ORM\Column(nullable: true)]
	private int $objectId;

	#[ORM\Column(nullable: false)]
	private string $objectClass;

	#[ORM\Column(type: 'text', nullable: false)]
	private string $changeSet;

	#[ORM\Column(nullable: true)]
	private ?int $userId = null;

	public function setLoggedNow(): void
	{
		$this->loggedAt = new DateTimeImmutable();
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

	public function getLoggedAt(): DateTimeImmutable
	{
		return $this->loggedAt;
	}

	public function getObjectId(): int
	{
		return $this->objectId;
	}

	public function setObjectId(int $objectId): void
	{
		$this->objectId = $objectId;
	}

	public function getObjectClass(): string
	{
		return $this->objectClass;
	}

	public function setObjectClass(string $objectClass): void
	{
		$this->objectClass = $objectClass;
	}

	public function getChangeSet(): ChangeSet
	{
		return unserialize($this->changeSet);
	}

	public function setChangeSet(ChangeSet $changeSet): void
	{
		$this->changeSet = serialize($changeSet);
	}

	public function getUserId(): ?int
	{
		return $this->userId;
	}

	public function setUserId(?int $userId): void
	{
		$this->userId = $userId;
	}
}
