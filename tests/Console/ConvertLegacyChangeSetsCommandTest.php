<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Console;

use ADT\DoctrineLoggable\Console\ConvertLegacyChangeSetsCommand;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Service\LegacyChangeSetConverter;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\ChangeSetStub;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\LegacyPayloadBuilder;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\ScalarStub;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConvertLegacyChangeSetsCommandTest extends TestCase
{
	private Connection $connection;

	private CommandTester $tester;

	protected function setUp(): void
	{
		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
		$this->connection->executeStatement('CREATE TABLE change_log (id INTEGER PRIMARY KEY AUTOINCREMENT, change_set BLOB NOT NULL)');

		$this->tester = new CommandTester(
			new ConvertLegacyChangeSetsCommand(new LegacyChangeSetConverter($this->connection, new ChangeSetSerializer()))
		);
	}

	protected function tearDown(): void
	{
		$this->connection->close();
	}

	public function testItConvertsEveryRowAndTellsYouToChangeTheColumn(): void
	{
		$this->insertLegacyRow();
		$this->insertLegacyRow();

		self::assertSame(Command::SUCCESS, $this->tester->execute([]));

		$output = $this->tester->getDisplay();
		self::assertStringContainsString('Converting 2 change_log rows', $output);
		self::assertStringContainsString('2 converted, 0 already json, 0 failed', $output);
		self::assertStringContainsString('ALTER TABLE change_log MODIFY change_set JSON NOT NULL', $output);
		self::assertStringStartsWith('{', $this->readRawRow(1));
	}

	public function testDryRunReportsWithoutWritingAndWithoutTheAlterHint(): void
	{
		$this->insertLegacyRow();

		self::assertSame(Command::SUCCESS, $this->tester->execute(['--dry-run' => true]));

		$output = $this->tester->getDisplay();
		self::assertStringContainsString('[dry run]', $output);
		self::assertStringContainsString('1 converted', $output);
		self::assertStringNotContainsString('ALTER TABLE', $output);
		self::assertStringStartsWith('O:', $this->readRawRow(1));
	}

	public function testABrokenRowMakesTheCommandFail(): void
	{
		$this->insertLegacyRow();
		$this->connection->executeStatement('INSERT INTO change_log (change_set) VALUES (?)', ['garbage']);

		self::assertSame(Command::FAILURE, $this->tester->execute([]));

		$output = $this->tester->getDisplay();
		self::assertStringContainsString('1 converted, 0 already json, 1 failed', $output);
		self::assertStringContainsString('row 2:', $output);
		self::assertStringContainsString('cannot be changed to JSON yet', $output);
	}

	public function testBatchSizeIsPassedThrough(): void
	{
		for ($i = 0; $i < 3; $i++) {
			$this->insertLegacyRow();
		}

		$this->tester->execute(['--batch-size' => '1']);

		self::assertSame(3, substr_count($this->tester->getDisplay(), 'batch: 1 converted'));
	}

	public function testAnEmptyTableSucceeds(): void
	{
		self::assertSame(Command::SUCCESS, $this->tester->execute([]));
		self::assertStringContainsString('0 converted', $this->tester->getDisplay());
	}

	private function insertLegacyRow(): void
	{
		$this->connection->executeStatement(
			'INSERT INTO change_log (change_set) VALUES (?)',
			[LegacyPayloadBuilder::build((new ChangeSetStub())->addProperty('title', new ScalarStub('a', 'b')))]
		);
	}

	private function readRawRow(int $id): string
	{
		return (string) $this->connection->fetchOne('SELECT change_set FROM change_log WHERE id = ?', [$id]);
	}
}
