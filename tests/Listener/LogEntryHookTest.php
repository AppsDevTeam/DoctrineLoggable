<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Listener;

use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use ADT\DoctrineLoggable\Tests\Fixtures\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The $onLogEntry hook - the seam a consumer uses to turn a change log row into something
 * else, typically an audit trail record.
 */
final class LogEntryHookTest extends TestCase
{
	private EntityManagerInterface $em;

	/** @var list<array{ChangeLog, object, bool}> */
	private array $announced = [];

	protected function setUp(): void
	{
		$this->em = EntityManagerFactory::create();
		$this->announced = [];

		EntityManagerFactory::findListener($this->em)->onLogEntry[] = function (
			ChangeLog $logEntry,
			object $entity,
			bool $announced,
		): void {
			$this->announced[] = [$logEntry, $entity, $announced];
		};
	}

	protected function tearDown(): void
	{
		$this->em->getConnection()->close();
	}

	public function testChangeIsAnnouncedWithItsEntity(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		// an insert is not logged, so there is nothing to announce yet
		self::assertSame([], $this->announced);

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		self::assertCount(1, $this->announced);
		[$logEntry, $entity, $announced] = $this->announced[0];

		self::assertSame($article, $entity);
		self::assertFalse($announced);
		self::assertSame(Article::class, $logEntry->getObjectClass());
		self::assertSame($article->getId(), $logEntry->getObjectId());

		// the row is committed by now, so it has an id the consumer can key on
		self::assertNotNull($logEntry->getId());

		$properties = $logEntry->getChangeSet()->getChangedProperties();
		self::assertSame('Pivo 12°', $properties['title']->getNew());
	}

	public function testUntouchedEntryIsNotAnnouncedAgain(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();
		$this->em->flush();

		self::assertCount(1, $this->announced);
	}

	/**
	 * One entity has one change log row per request and every further flush grows it. The
	 * consumer gets it again, flagged, with the change set holding the older changes too.
	 */
	public function testGrowingEntryIsAnnouncedAgainAsAlreadyAnnounced(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$article->setRating(5);
		$this->em->flush();

		self::assertCount(2, $this->announced);
		self::assertFalse($this->announced[0][2]);
		self::assertTrue($this->announced[1][2]);

		// the same row, not a second one
		self::assertSame($this->announced[0][0], $this->announced[1][0]);
		self::assertCount(1, EntityManagerFactory::findChangeLogs($this->em));

		$properties = $this->announced[1][0]->getChangeSet()->getChangedProperties();
		self::assertSame('Pivo 12°', $properties['title']->getNew());
		self::assertSame(5, $properties['rating']->getNew());
	}

	/**
	 * After clear() the entries are detached and gone; a change made afterwards starts over
	 * with a fresh row, so it must not come flagged as already announced.
	 */
	public function testClearStartsOver(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$this->em->clear();

		$reloaded = $this->em->getRepository(Article::class)->find($article->getId());
		$reloaded->setRating(5);
		$this->em->flush();

		self::assertCount(2, $this->announced);
		self::assertFalse($this->announced[1][2]);
		self::assertNotSame($this->announced[0][0], $this->announced[1][0]);
	}

	/**
	 * A consumer that persists something of its own must not make the hook fire twice for the
	 * same state - its flush runs while we are still inside the outer postFlush.
	 */
	public function testConsumerFlushInsideTheCallbackDoesNotRepeatTheAnnouncement(): void
	{
		EntityManagerFactory::findListener($this->em)->onLogEntry[] = function (): void {
			$this->em->flush();
		};

		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		self::assertCount(1, $this->announced);
	}
}
