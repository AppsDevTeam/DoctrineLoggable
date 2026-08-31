<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Service;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Service\LegacyChangeSetConverter;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Tag;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\ChangeSetStub;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\IdStub;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\LegacyPayloadBuilder;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\ScalarStub;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\ToManyStub;
use ADT\DoctrineLoggable\Tests\Fixtures\Legacy\ToOneStub;
use ADT\DoctrineLoggable\UnexpectedValueException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class LegacyChangeSetConverterTest extends TestCase
{
	private Connection $connection;

	private LegacyChangeSetConverter $converter;

	private ChangeSetSerializer $serializer;

	protected function setUp(): void
	{
		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
		$this->connection->executeStatement('CREATE TABLE change_log (id INTEGER PRIMARY KEY AUTOINCREMENT, change_set BLOB NOT NULL)');

		$this->serializer = new ChangeSetSerializer();
		$this->converter = new LegacyChangeSetConverter($this->connection, $this->serializer);
	}

	protected function tearDown(): void
	{
		$this->connection->close();
	}

	public function testScalarPayloadBecomesJson(): void
	{
		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())
				->setIdentification(new IdStub('42', Article::class, ['title' => 'Pivo']))
				->addProperty('title', new ScalarStub('Pivo', 'Pivo 12°'))
		);

		self::assertSame(
			[
				'version' => 1,
				'action' => 'edit',
				'entity' => ['class' => Article::class, 'id' => '42', 'identification' => ['title' => 'Pivo']],
				'properties' => ['title' => ['type' => 'scalar', 'old' => 'Pivo', 'new' => 'Pivo 12°']],
			],
			json_decode($this->converter->convertPayload($payload), true)
		);
	}

	/**
	 * Regrese: __sleep() left the names out and __wakeup() put them back from the array keys.
	 * Without restoring them the converted rows would carry an empty property name.
	 */
	public function testPropertyNamesAreRestoredFromTheArrayKeys(): void
	{
		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())
				->addProperty('title', new ScalarStub('Pivo', 'Pivo 12°'))
				->addProperty('rating', new ScalarStub(null, 5))
		);

		$converted = json_decode($this->converter->convertPayload($payload), true);

		self::assertSame(['title', 'rating'], array_keys($converted['properties']));
	}

	public function testNestedNamesAreRestoredToo(): void
	{
		$nested = (new ChangeSetStub())
			->setIdentification(new IdStub('1', Author::class, ['name' => 'Franta']))
			->addProperty('name', new ScalarStub('Franta', 'František'));

		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())->addProperty('author', new ToOneStub(null, null, $nested))
		);

		$converted = json_decode($this->converter->convertPayload($payload), true);

		self::assertSame(['name'], array_keys($converted['properties']['author']['changeSet']['properties']));
	}

	public function testActionIsCarriedOver(): void
	{
		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())->setAction(ChangeSet::ACTION_DELETE)->addProperty('title', new ScalarStub('Pivo', null))
		);

		self::assertSame('delete', json_decode($this->converter->convertPayload($payload), true)['action']);
	}

	public function testToOneAndToManyPayloadsBecomeJson(): void
	{
		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())
				->addProperty('author', new ToOneStub(
					new IdStub('1', Author::class, ['name' => 'Franta']),
					new IdStub('2', Author::class, ['name' => 'Pepa'])
				))
				->addProperty('tags', new ToManyStub(
					[new IdStub('3', Tag::class, ['name' => 'novinka'])],
					[new IdStub('9', Tag::class, ['name' => 'akce'])]
				))
		);

		$converted = json_decode($this->converter->convertPayload($payload), true);

		self::assertSame('Franta', $converted['properties']['author']['old']['identification']['name']);
		self::assertSame('Pepa', $converted['properties']['author']['new']['identification']['name']);
		self::assertSame('akce', $converted['properties']['tags']['added'][0]['identification']['name']);
		self::assertSame('novinka', $converted['properties']['tags']['removed'][0]['identification']['name']);
	}

	public function testObjectValuesInsideAScalarAreConverted(): void
	{
		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())->addProperty('publishedAt', new ScalarStub(null, new DateTimeImmutable('2026-08-27T12:00:00+02:00')))
		);

		$converted = json_decode($this->converter->convertPayload($payload), true);

		self::assertSame(
			['@type' => 'datetime', 'class' => DateTimeImmutable::class, 'value' => '2026-08-27T12:00:00+02:00'],
			$converted['properties']['publishedAt']['new']
		);
	}

	public function testCyclicPayloadIsConvertedIntoReferences(): void
	{
		$article = new ChangeSetStub();
		$author = (new ChangeSetStub())->addProperty('article', new ToOneStub(null, null, $article));
		$article->addProperty('author', new ToOneStub(null, null, $author));

		$converted = json_decode($this->converter->convertPayload(LegacyPayloadBuilder::build($article)), true);

		self::assertSame(1, $converted['$id']);
		self::assertSame(['$ref' => 1], $converted['properties']['author']['changeSet']['properties']['article']['changeSet']);
	}

	public function testConvertedPayloadCanBeReadBackByTheSerializer(): void
	{
		$payload = LegacyPayloadBuilder::build(
			(new ChangeSetStub())
				->setIdentification(new IdStub('42', Article::class, ['title' => 'Pivo']))
				->addProperty('title', new ScalarStub('Pivo', 'Pivo 12°'))
		);

		$changeSet = $this->serializer->fromArray(json_decode($this->converter->convertPayload($payload), true));

		self::assertSame('Pivo 12°', $changeSet->getChangedProperties()['title']->getNew());
		self::assertSame('42', $changeSet->getIdentification()->getId());
	}

	public function testGarbagePayloadIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		$this->converter->convertPayload('not a serialize payload');
	}

	public function testAllRowsAreConverted(): void
	{
		$this->insertLegacyRow('Pivo', 'Pivo 12°');
		$this->insertLegacyRow('Kofola', 'Kofola 0,5');

		$result = $this->converter->convert();

		self::assertSame(2, $result->getConverted());
		self::assertSame(0, $result->getSkipped());
		self::assertFalse($result->hasFailures());
		self::assertSame('Pivo 12°', $this->readRow(1)->getChangedProperties()['title']->getNew());
		self::assertSame('Kofola 0,5', $this->readRow(2)->getChangedProperties()['title']->getNew());
	}

	public function testRunningItAgainSkipsRowsThatAreAlreadyJson(): void
	{
		$this->insertLegacyRow('Pivo', 'Pivo 12°');
		$this->converter->convert();

		$result = $this->converter->convert();

		self::assertSame(0, $result->getConverted());
		self::assertSame(1, $result->getSkipped());
	}

	public function testBatchingWalksThroughEveryRow(): void
	{
		for ($i = 1; $i <= 7; $i++) {
			$this->insertLegacyRow('old ' . $i, 'new ' . $i);
		}

		$batches = [];
		$result = $this->converter->convert(2, function ($batch) use (&$batches): void {
			$batches[] = $batch->getConverted();
		});

		self::assertSame(7, $result->getConverted());
		self::assertSame([2, 2, 2, 1], $batches);
		self::assertSame('new 7', $this->readRow(7)->getChangedProperties()['title']->getNew());
	}

	public function testDryRunLeavesTheRowsAlone(): void
	{
		$this->insertLegacyRow('Pivo', 'Pivo 12°');

		$result = $this->converter->convert(500, null, true);

		self::assertSame(1, $result->getConverted());
		self::assertStringStartsWith('O:', $this->readRawRow(1));
	}

	public function testABrokenRowIsReportedAndTheRestStillConverts(): void
	{
		$this->insertLegacyRow('Pivo', 'Pivo 12°');
		$this->connection->executeStatement('INSERT INTO change_log (change_set) VALUES (?)', ['garbage']);
		$this->insertLegacyRow('Kofola', 'Kofola 0,5');

		$result = $this->converter->convert();

		self::assertSame(2, $result->getConverted());
		self::assertTrue($result->hasFailures());
		self::assertSame([2], array_keys($result->getFailures()));
		self::assertSame('garbage', $this->readRawRow(2));
		self::assertSame('Kofola 0,5', $this->readRow(3)->getChangedProperties()['title']->getNew());
	}

	public function testCountRowsReportsTheWholeTable(): void
	{
		$this->insertLegacyRow('Pivo', 'Pivo 12°');
		$this->insertLegacyRow('Kofola', 'Kofola 0,5');

		self::assertSame(2, $this->converter->countRows());
	}

	public function testZeroBatchSizeIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		$this->converter->convert(0);
	}

	private function insertLegacyRow(string $old, string $new): void
	{
		$this->connection->executeStatement(
			'INSERT INTO change_log (change_set) VALUES (?)',
			[LegacyPayloadBuilder::build((new ChangeSetStub())->addProperty('title', new ScalarStub($old, $new)))]
		);
	}

	private function readRawRow(int $id): string
	{
		return (string) $this->connection->fetchOne('SELECT change_set FROM change_log WHERE id = ?', [$id]);
	}

	private function readRow(int $id): ChangeSet
	{
		return $this->serializer->fromArray(json_decode($this->readRawRow($id), true));
	}
}
