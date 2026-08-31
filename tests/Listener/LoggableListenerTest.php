<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Listener;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Attachment;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\ArticleStateEnum;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Comment;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Cover;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Issue;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Series;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Tag;
use ADT\DoctrineLoggable\Tests\Fixtures\EntityManagerFactory;
use ADT\DoctrineLoggable\Tests\Fixtures\FakeUser;
use ADT\DoctrineLoggable\Tests\Fixtures\Money;
use ADT\DoctrineLoggable\Tests\Fixtures\MoneyHandler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Nette\Security\SimpleIdentity;
use PHPUnit\Framework\TestCase;

final class LoggableListenerTest extends TestCase
{
	private EntityManagerInterface $em;

	protected function setUp(): void
	{
		$this->em = EntityManagerFactory::create();
	}

	protected function tearDown(): void
	{
		$this->em->getConnection()->close();
	}

	public function testInsertIsNotLogged(): void
	{
		$this->em->persist(new Article('Pivo'));
		$this->em->flush();

		self::assertSame([], EntityManagerFactory::findChangeLogs($this->em));
	}

	public function testScalarChangeIsStoredAsReadableJson(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$article->setRating(5);
		$this->em->flush();

		$json = $this->fetchRawChangeSet(1);

		self::assertStringContainsString('"title"', $json);
		self::assertStringContainsString('Pivo 12°', $json);
		self::assertStringNotContainsString('O:', $json);

		$logs = EntityManagerFactory::findChangeLogs($this->em);
		self::assertCount(1, $logs);
		self::assertSame(Article::class, $logs[0]->getObjectClass());
		self::assertSame($article->getId(), $logs[0]->getObjectId());
		self::assertSame(ChangeSet::ACTION_EDIT, $logs[0]->getAction());

		$properties = $logs[0]->getChangeSet()->getChangedProperties();
		self::assertInstanceOf(Scalar::class, $properties['title']);
		self::assertSame('Pivo', $properties['title']->getOld());
		self::assertSame('Pivo 12°', $properties['title']->getNew());
		self::assertNull($properties['rating']->getOld());
		self::assertSame(5, $properties['rating']->getNew());
	}

	public function testDateTimeEnumAndJsonColumnsSurviveTheRoundTrip(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$publishedAt = new DateTimeImmutable('2026-08-27 12:00:00');
		$article->setPublishedAt($publishedAt);
		$article->setState(ArticleStateEnum::Published);
		$article->setMeta(['vat' => 21, 'tags' => ['akce']]);
		$this->em->flush();

		$this->em->clear();
		$properties = $this->reloadFirstChangeLog()->getChangeSet()->getChangedProperties();

		self::assertSame($publishedAt->format('c'), $properties['publishedAt']->getNew()->format('c'));
		self::assertSame(['vat' => 21, 'tags' => ['akce']], $properties['meta']->getNew());

		// Doctrine hands backed enums to the unit of work already converted to their value
		self::assertSame(ArticleStateEnum::Published->value, $properties['state']->getNew());
	}

	public function testToOneChangeIsLoggedWithIdentification(): void
	{
		$franta = new Author('Franta');
		$pepa = new Author('Pepa');
		$article = new Article('Pivo');
		$article->setAuthor($franta);
		$this->em->persist($franta);
		$this->em->persist($pepa);
		$this->em->persist($article);
		$this->em->flush();

		$article->setAuthor($pepa);
		$this->em->flush();

		$this->em->clear();
		$author = $this->reloadFirstChangeLog()->getChangeSet()->getChangedProperties()['author'];

		self::assertInstanceOf(ToOne::class, $author);
		self::assertSame(['name' => 'Franta'], $author->getOld()->getIdentification());
		self::assertSame(['name' => 'Pepa'], $author->getNew()->getIdentification());
		self::assertSame(Author::class, $author->getNew()->getClass());
	}

	public function testToManyChangeIsLoggedWithAddedAndRemovedIdentifications(): void
	{
		$akce = new Tag('akce');
		$novinka = new Tag('novinka');
		$article = new Article('Pivo');
		$article->addTag($novinka);
		$this->em->persist($akce);
		$this->em->persist($novinka);
		$this->em->persist($article);
		$this->em->flush();

		$article->removeTag($novinka);
		$article->addTag($akce);
		$this->em->flush();

		$this->em->clear();
		$tags = $this->reloadFirstChangeLog()->getChangeSet()->getChangedProperties()['tags'];

		self::assertInstanceOf(ToMany::class, $tags);
		self::assertSame(['akce'], array_map(fn ($id) => $id->getIdentification()['name'], array_values($tags->getAdded())));
		self::assertSame(['novinka'], array_map(fn ($id) => $id->getIdentification()['name'], array_values($tags->getRemoved())));
	}

	public function testTwoChangesOfTheSameEntityInOneFlushEndUpInASingleRow(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$article->setRating(5);
		$this->em->flush();

		$this->em->clear();
		self::assertCount(1, EntityManagerFactory::findChangeLogs($this->em));
	}

	/**
	 * Regrese: the change set is mutated in place, so the row is only updated if the field is
	 * forced dirty. Doctrine compares object valued fields by identity and would see no change.
	 */
	public function testRepeatedFlushesOfTheSameEntityKeepUpdatingTheSameRow(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$article->setTitle('Pivo 11°');
		$article->setRating(5);
		$this->em->flush();

		$this->em->clear();
		$logs = EntityManagerFactory::findChangeLogs($this->em);

		self::assertCount(1, $logs);

		$properties = $logs[0]->getChangeSet()->getChangedProperties();
		self::assertSame('Pivo', $properties['title']->getOld());
		self::assertSame('Pivo 11°', $properties['title']->getNew());
		self::assertSame(5, $properties['rating']->getNew());
	}

	public function testGuestIdentityIsStoredAsNull(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$log = EntityManagerFactory::findChangeLogs($this->em)[0];

		self::assertNull($log->getIdentityId());
		self::assertNull($log->getIdentityClass());
	}

	public function testCustomValueHandlerIsUsedForTheWholeRoundTrip(): void
	{
		$this->em->getConnection()->close();
		$this->em = EntityManagerFactory::create([new MoneyHandler()]);

		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		// meta is a json column, a value object inside it goes through the registered handler
		$article->setMeta(['price' => new Money(3900, 'CZK')]);
		$this->em->flush();

		self::assertStringContainsString('"@type":"money"', $this->fetchRawChangeSet(1));

		$this->em->clear();
		$meta = $this->reloadFirstChangeLog()->getChangeSet()->getChangedProperties()['meta']->getNew();

		self::assertEquals(new Money(3900, 'CZK'), $meta['price']);
	}

	public function testTheIdentityOfTheLoggedInUserIsStored(): void
	{
		$this->em->getConnection()->close();
		$this->em = EntityManagerFactory::create([], FakeUser::withIdentity(7));

		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$log = EntityManagerFactory::findChangeLogs($this->em)[0];

		self::assertSame(7, $log->getIdentityId());
		self::assertSame(SimpleIdentity::class, $log->getIdentityClass());
	}

	/**
	 * Comment is not logged itself, but Article::$comments is a logged OneToMany, so a change
	 * of a comment has to end up in the log of its article.
	 */
	public function testAChangeOfANonLoggedChildIsLoggedOnItsParent(): void
	{
		$article = new Article('Pivo');
		$comment = new Comment('Dobré');
		$article->addComment($comment);
		$this->em->persist($article);
		$this->em->persist($comment);
		$this->em->flush();

		$comment->setText('Výborné');
		$this->em->flush();

		$this->em->clear();
		$logs = EntityManagerFactory::findChangeLogs($this->em);

		self::assertCount(1, $logs);
		self::assertSame(Article::class, $logs[0]->getObjectClass());
		self::assertSame($article->getId(), $logs[0]->getObjectId());

		$comments = $logs[0]->getChangeSet()->getChangedProperties()['comments'];
		self::assertInstanceOf(ToMany::class, $comments);

		$nested = array_values($comments->getChangeSets())[0];
		self::assertSame('Výborné', $nested->getChangedProperties()['text']->getNew());
		self::assertSame(Comment::class, $nested->getIdentification()->getClass());
	}

	public function testAddingAChildToALoggedCollectionIsLogged(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$comment = new Comment('Dobré');
		$article->addComment($comment);
		$this->em->persist($comment);
		$this->em->flush();

		$this->em->clear();
		$comments = $this->reloadFirstChangeLog()->getChangeSet()->getChangedProperties()['comments'];

		self::assertSame(['Dobré'], array_map(
			fn ($id) => $id->getIdentification()['text'],
			array_values($comments->getAdded())
		));
	}

	public function testAChangeOfANotLoggedPropertyIsIgnored(): void
	{
		$author = new Author('Franta');
		$this->em->persist($author);
		$this->em->flush();

		$author->setName('František');
		$this->em->flush();

		self::assertSame([], EntityManagerFactory::findChangeLogs($this->em));
	}

	/**
	 * Documents a gap, not a wanted behaviour: ChangeSet::ACTION_DELETE exists, but a deletion
	 * produces no property change, so the change set is empty and no row is written at all.
	 */
	public function testDeletingALoggedEntityWritesNoRow(): void
	{
		$article = new Article('Pivo');
		$article->addTag($tag = new Tag('akce'));
		$this->em->persist($tag);
		$this->em->persist($article);
		$this->em->flush();

		$this->em->remove($article);
		$this->em->flush();

		self::assertSame([], EntityManagerFactory::findChangeLogs($this->em));
	}

	public function testChangingAnEntityBehindAnInverseOneToOneIsLoggedOnTheOwner(): void
	{
		$article = new Article('Pivo');
		$cover = new Cover('pivo.jpg');
		$article->setCover($cover);
		$this->em->persist($cover);
		$this->em->persist($article);
		$this->em->flush();

		$cover->setFileName('pivo-12.jpg');
		$this->em->flush();

		$this->em->clear();
		$logs = EntityManagerFactory::findChangeLogs($this->em);

		self::assertCount(1, $logs);
		self::assertSame(Article::class, $logs[0]->getObjectClass());

		$nested = $logs[0]->getChangeSet()->getChangedProperties()['cover']->getChangeSet();
		self::assertSame('pivo-12.jpg', $nested->getChangedProperties()['fileName']->getNew());
		self::assertSame(Cover::class, $nested->getIdentification()->getClass());
	}

	public function testAttachingAnEntityThroughAnOwningToOneIsLogged(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$author = new Author('Franta');
		$article->setAuthor($author);
		$this->em->persist($author);
		$this->em->flush();

		$this->em->clear();
		$logged = $this->reloadFirstChangeLog()->getChangeSet()->getChangedProperties()['author'];

		self::assertNull($logged->getOld());
		self::assertSame(['name' => 'Franta'], $logged->getNew()->getIdentification());

		// documents a gap: the identification of an entity inserted in this very flush is built
		// before the insert runs, and postPersist updates the object too late for the stored row
		self::assertSame('', $logged->getNew()->getId());
	}

	public function testTheStoredJsonIsValidAndCarriesTheFormatVersion(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$json = $this->fetchRawChangeSet(1);

		self::assertJson($json);
		self::assertSame(ChangeSetSerializer::VERSION, json_decode($json, true)['version']);
	}

	/**
	 * Regrese: Series::$latestIssue and Issue::$series point at each other, the cache returned
	 * the change set that was still being built and the walk below it ran again every time.
	 * The recursion never closed and ate all the memory, without a single line in the log.
	 */
	public function testACycleBetweenTwoLoggedPropertiesDoesNotRecurseForever(): void
	{
		$series = new Series('Pivní speciály');
		$series->setLatestIssue($issue = new Issue('Ležák'));
		$this->em->persist($issue);
		$this->em->persist($series);
		$this->em->flush();

		$series->setName('Pivní speciály 2026');
		$issue->setTitle('Ležák 12°');
		$this->em->flush();

		$logs = EntityManagerFactory::findChangeLogs($this->em);

		self::assertCount(1, $logs);
		self::assertSame(Series::class, $logs[0]->getObjectClass());

		$properties = $logs[0]->getChangeSet()->getChangedProperties();
		self::assertSame('Pivní speciály 2026', $properties['name']->getNew());

		$nested = $properties['latestIssue']->getChangeSet();
		self::assertSame('Ležák 12°', $nested->getChangedProperties()['title']->getNew());
	}

	/**
	 * The cycle survives the round trip through the database as a reference, the same change set
	 * instance is on both ends of it.
	 */
	public function testACyclicChangeSetIsStoredAndReadBackAsAReference(): void
	{
		$series = new Series('Pivní speciály');
		$series->setLatestIssue($issue = new Issue('Ležák'));
		$this->em->persist($issue);
		$this->em->persist($series);
		$this->em->flush();

		$series->setName('Pivní speciály 2026');
		$this->em->flush();

		$this->em->clear();
		$changeSet = $this->reloadFirstChangeLog()->getChangeSet();

		$issueChangeSet = $changeSet->getChangedProperties()['latestIssue']->getChangeSet();
		self::assertSame($changeSet, $issueChangeSet->getChangedProperties()['series']->getChangeSet());
	}

	/**
	 * Regrese: the changed child used to be looked up by walking the whole collection, so Doctrine
	 * hydrated every row of it just to find one entity and ran out of memory on big ones.
	 */
	public function testTheChangedChildIsFoundWithoutLoadingTheWholeCollection(): void
	{
		$article = new Article('Pivo');
		foreach (['Dobré', 'Výborné', 'Ujde'] as $text) {
			$article->addComment($comment = new Comment($text));
			$this->em->persist($comment);
		}
		$this->em->persist($article);
		$this->em->flush();
		$commentId = $article->getComments()->first()->getId();

		$this->em->clear();
		$comment = $this->em->find(Comment::class, $commentId);
		$comment->setText('Naprosto skvělé');
		$this->em->flush();

		$comments = $comment->getArticle()->getComments();
		self::assertInstanceOf(PersistentCollection::class, $comments);
		self::assertFalse($comments->isInitialized());

		$logs = EntityManagerFactory::findChangeLogs($this->em);
		self::assertCount(1, $logs);

		$nested = array_values($logs[0]->getChangeSet()->getChangedProperties()['comments']->getChangeSets())[0];
		self::assertSame('Naprosto skvělé', $nested->getChangedProperties()['text']->getNew());
	}

	public function testAnAlreadyLoadedCollectionStillFindsTheChangedChild(): void
	{
		$article = new Article('Pivo');
		$article->addComment($comment = new Comment('Dobré'));
		$this->em->persist($comment);
		$this->em->persist($article);
		$this->em->flush();
		$commentId = $comment->getId();

		$this->em->clear();
		$comment = $this->em->find(Comment::class, $commentId);
		$comments = $comment->getArticle()->getComments();
		$comments->toArray();
		self::assertTrue($comments->isInitialized());

		$comment->setText('Výborné');
		$this->em->flush();

		$logs = EntityManagerFactory::findChangeLogs($this->em);
		self::assertCount(1, $logs);

		$nested = array_values($logs[0]->getChangeSet()->getChangedProperties()['comments']->getChangeSets())[0];
		self::assertSame('Výborné', $nested->getChangedProperties()['text']->getNew());
	}

	/**
	 * Regrese: the same changed entity is offered to every collection of the logged entity, so
	 * a comment reached Article::$attachments too and the mappedBy field was read on it. That
	 * property does not exist on a comment and the whole flush died on
	 * "Call to a member function getValue() on null".
	 */
	public function testACollectionOfAnotherTypeIgnoresTheChangedChild(): void
	{
		$article = new Article('Pivo');
		$article->addComment($comment = new Comment('Dobré'));
		$article->addAttachment($attachment = new Attachment('pivo.pdf'));
		$this->em->persist($comment);
		$this->em->persist($attachment);
		$this->em->persist($article);
		$this->em->flush();
		$commentId = $comment->getId();

		$this->em->clear();
		$comment = $this->em->find(Comment::class, $commentId);
		$comment->setText('Výborné');
		$this->em->flush();

		$attachments = $comment->getArticle()->getAttachments();
		self::assertInstanceOf(PersistentCollection::class, $attachments);
		self::assertFalse($attachments->isInitialized());

		$properties = EntityManagerFactory::findChangeLogs($this->em)[0]->getChangeSet()->getChangedProperties();
		self::assertArrayNotHasKey('attachments', $properties);
		self::assertArrayHasKey('comments', $properties);
	}

	/**
	 * A change of a child of the second collection has to be logged the very same way.
	 */
	public function testAChangeOfAChildOfTheSecondCollectionIsLoggedOnTheParent(): void
	{
		$article = new Article('Pivo');
		$article->addComment($comment = new Comment('Dobré'));
		$article->addAttachment($attachment = new Attachment('pivo.pdf'));
		$this->em->persist($comment);
		$this->em->persist($attachment);
		$this->em->persist($article);
		$this->em->flush();
		$attachmentId = $attachment->getId();

		$this->em->clear();
		$attachment = $this->em->find(Attachment::class, $attachmentId);
		$attachment->setFileName('pivo-12.pdf');
		$this->em->flush();

		$properties = EntityManagerFactory::findChangeLogs($this->em)[0]->getChangeSet()->getChangedProperties();

		self::assertArrayNotHasKey('comments', $properties);

		$nested = array_values($properties['attachments']->getChangeSets())[0];
		self::assertSame('pivo-12.pdf', $nested->getChangedProperties()['fileName']->getNew());
	}

	/**
	 * Regrese: an entity loaded through a relation is a proxy and get_class() returns the proxy
	 * class. Its own properties carry attributes of classes the project need not have installed,
	 * and the log row would remember the proxy class instead of the entity.
	 */
	public function testAProxiedEntityIsLoggedUnderItsRealClass(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();
		$articleId = $article->getId();

		$this->em->clear();
		$article = $this->em->getReference(Article::class, $articleId);
		$article->setTitle('Pivo 12°');
		$this->em->flush();

		$logs = EntityManagerFactory::findChangeLogs($this->em);

		self::assertCount(1, $logs);
		self::assertSame(Article::class, $logs[0]->getObjectClass());
		self::assertSame('Pivo 12°', $logs[0]->getChangeSet()->getChangedProperties()['title']->getNew());
	}

	private function fetchRawChangeSet(int $id): string
	{
		return (string) $this->em->getConnection()->fetchOne('SELECT change_set FROM change_log WHERE id = ?', [$id]);
	}

	private function reloadFirstChangeLog(): ChangeLog
	{
		return EntityManagerFactory::findChangeLogs($this->em)[0];
	}
}
