<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Service;

class LegacyChangeSetConversionResult
{
	private int $converted = 0;

	private int $skipped = 0;

	/** @var array<int, string> row id => error message */
	private array $failures = [];

	public function getConverted(): int
	{
		return $this->converted;
	}

	/**
	 * Rows that already held JSON, so a repeated run leaves them alone.
	 */
	public function getSkipped(): int
	{
		return $this->skipped;
	}

	/**
	 * @return array<int, string>
	 */
	public function getFailures(): array
	{
		return $this->failures;
	}

	public function hasFailures(): bool
	{
		return $this->failures !== [];
	}

	public function addConverted(): void
	{
		$this->converted++;
	}

	public function addSkipped(): void
	{
		$this->skipped++;
	}

	public function addFailure(int $id, string $message): void
	{
		$this->failures[$id] = $message;
	}

	public function add(self $other): void
	{
		$this->converted += $other->converted;
		$this->skipped += $other->skipped;
		$this->failures += $other->failures;
	}
}
