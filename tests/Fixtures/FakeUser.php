<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures;

use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;

/**
 * Nette\Security\User needs a storage, tests do not need a real one.
 */
final class FakeUser extends User
{
	public function __construct(?IIdentity $identity = null)
	{
		parent::__construct(new class ($identity) implements UserStorage {
			public function __construct(private ?IIdentity $identity)
			{
			}

			public function saveAuthentication(IIdentity $identity): void
			{
				$this->identity = $identity;
			}

			public function clearAuthentication(bool $clearIdentity): void
			{
				$this->identity = null;
			}

			public function getState(): array
			{
				return [$this->identity !== null, $this->identity, null];
			}

			public function setExpiration(?string $expire, bool $clearIdentity): void
			{
			}
		});
	}

	public static function withIdentity(int $id): self
	{
		return new self(new SimpleIdentity($id));
	}
}
