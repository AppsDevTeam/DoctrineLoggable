<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Service;

use ADT\DoctrineLoggable\Service\ChangeSetFactory;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Comment;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Cover;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Event;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Tag;
use ADT\DoctrineLoggable\Tests\Fixtures\EntityManagerFactory;
use ADT\DoctrineLoggable\Tests\Fixtures\FakeUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ChangeSetFactoryTest extends TestCase
{
	private EntityManagerInterface $em;

	private ChangeSetFactory $factory;

	protected function setUp(): void
	{
		$this->em = EntityManagerFactory::create();
		$this->factory = (new ChangeSetFactory(new FakeUser()))->setEntityManager($this->em);
	}

	protected function tearDown(): void
	{
		$this->em->getConnection()->close();
	}

	public function testOnlyEntitiesWithTheAttributeAreLogged(): void
	{
		self::assertTrue($this->factory->isEntityLogged(Article::class));
		self::assertFalse($this->factory->isEntityLogged(Author::class));
		self::assertFalse($this->factory->isEntityLogged(Tag::class));
	}

	public function testIdentificationCarriesTheClassIdAndTheConfiguredFields(): void
	{
		$author = new Author('Franta');
		$this->em->persist($author);
		$this->em->flush();

		$identification = $this->factory->createIdentification($author);

		self::assertSame(Author::class, $identification->getClass());
		self::assertSame((string) $author->getId(), $identification->getId());
		self::assertSame(['name' => 'Franta'], $identification->getIdentification());
	}

	public function testIdentificationOfNullIsNull(): void
	{
		self::assertNull($this->factory->createIdentification(null));
	}

	public function testTheSameEntityAlwaysGetsTheSameIdentificationInstance(): void
	{
		$author = new Author('Franta');

		self::assertSame($this->factory->createIdentification($author), $this->factory->createIdentification($author));
	}

	public function testIdentificationFollowsADottedPath(): void
	{
		$comment = new Comment('Dobré', new Author('Franta'));

		self::assertSame(
			['text' => 'Dobré', 'author.name' => 'Franta'],
			$this->factory->createIdentification($comment)->getIdentification()
		);
	}

	/**
	 * Regrese: a nullable relation along the path used to reach method_exists(null, ...) and
	 * kill the whole flush with a TypeError.
	 */
	public function testADottedPathThroughANullRelationIsEmptyInsteadOfFatal(): void
	{
		$comment = new Comment('Dobré');

		self::assertSame(
			['text' => 'Dobré', 'author.name' => ''],
			$this->factory->createIdentification($comment)->getIdentification()
		);
	}

	public function testAnEntityWithoutTheIdentificationAttributeHasNoData(): void
	{
		$article = new Article('Pivo');
		$this->em->persist($article);
		$this->em->flush();

		$identification = $this->factory->createIdentification($article);

		self::assertSame(['title' => 'Pivo'], $identification->getIdentification());
	}

	/**
	 * An entity persisted in this very flush has no id when the identification is created,
	 * postPersist fills it in afterwards.
	 */
	public function testUpdateIdentificationFillsInTheIdAfterPersist(): void
	{
		$author = new Author('Franta');
		$identification = $this->factory->createIdentification($author);

		self::assertSame('', $identification->getId());

		$this->em->persist($author);
		$this->em->flush();
		$this->factory->updateIdentification($author);

		self::assertSame((string) $author->getId(), $identification->getId());
	}

	public function testUpdateIdentificationIgnoresAnUnknownEntity(): void
	{
		$this->expectNotToPerformAssertions();

		$this->factory->updateIdentification(new Author('Franta'));
	}

	public function testADateIdentificationDropsTheTimeAtMidnight(): void
	{
		$identification = $this->factory->createIdentification(new Event())->getIdentification();

		self::assertSame('27.8.2026 14:30', $identification['startsAt']);
		self::assertSame('27.8.2026', $identification['wholeDayAt']);
	}

	public function testAPathIntoACollectionJoinsEveryValue(): void
	{
		$event = new Event();
		$event->addTag(new Tag('akce'));
		$event->addTag(new Tag('sleva'));

		self::assertSame('akce, sleva', $this->factory->createIdentification($event)->getIdentification()['tags.name']);
	}

	public function testAnEmptyCollectionIdentificationIsAnEmptyString(): void
	{
		self::assertSame('', $this->factory->createIdentification(new Event())->getIdentification()['tags.name']);
	}

	/**
	 * Regrese: the logged properties were cached as a numerically indexed list, so a lookup by
	 * property name never found anything.
	 */
	public function testPropertiesCanBeLookedUpByName(): void
	{
		self::assertTrue($this->factory->isPropertyLogged(Article::class, 'title'));
		self::assertFalse($this->factory->isPropertyLogged(Article::class, 'id'));
		self::assertFalse($this->factory->isPropertyLogged(Author::class, 'name'));

		self::assertSame('title', $this->factory->getPropertyAnnotation(Article::class, 'title')->getName());
		self::assertNull($this->factory->getPropertyAnnotation(Article::class, 'id'));
	}

	public function testAssociationStructureMapsChildrenToThePathBackToTheLoggedEntity(): void
	{
		$structure = $this->factory->getLoggableEntityAssociationStructure();

		// Article::$comments is a OneToMany mapped by Comment::$article
		self::assertArrayHasKey(Comment::class, $structure);
		self::assertContains(['article'], $structure[Comment::class]);
	}

	/**
	 * Regrese: for a OneToOne only inversedBy was consulted, so an inverse side fell back to a
	 * findOneBy() on the inverse association and Doctrine threw InvalidFindByCall mid flush.
	 */
	public function testAssociationStructureFollowsAnInverseOneToOneThroughMappedBy(): void
	{
		$structure = $this->factory->getLoggableEntityAssociationStructure();

		self::assertArrayHasKey(Cover::class, $structure);
		self::assertContains(['article'], $structure[Cover::class]);
	}

	public function testTheLoggedParentIsFoundThroughAnInverseOneToOne(): void
	{
		$article = new Article('Pivo');
		$cover = new Cover('pivo.jpg');
		$article->setCover($cover);
		$this->em->persist($cover);
		$this->em->persist($article);
		$this->em->flush();

		$this->factory->getLoggableEntityAssociationStructure();

		self::assertSame($article, $this->factory->getLoggableEntityFromAssociationStructure($cover));
	}

	public function testAssociationStructureSkipsToManyAndToOneOwningSides(): void
	{
		$structure = $this->factory->getLoggableEntityAssociationStructure();

		// Article::$tags is a ManyToMany and Article::$author a ManyToOne, neither leads back
		self::assertArrayNotHasKey(Tag::class, $structure);
		self::assertArrayNotHasKey(Author::class, $structure);
	}

	public function testAssociationStructureIsComputedOnce(): void
	{
		self::assertSame(
			$this->factory->getLoggableEntityAssociationStructure(),
			$this->factory->getLoggableEntityAssociationStructure()
		);
	}

	public function testTheLoggedParentIsFoundFromAChild(): void
	{
		$article = new Article('Pivo');
		$comment = new Comment('Dobré');
		$article->addComment($comment);
		$this->em->persist($article);
		$this->em->persist($comment);
		$this->em->flush();

		$this->factory->getLoggableEntityAssociationStructure();

		self::assertSame($article, $this->factory->getLoggableEntityFromAssociationStructure($comment));
	}

	public function testAChildWithoutAParentResolvesToNull(): void
	{
		$comment = new Comment('Dobré');
		$this->em->persist($comment);
		$this->em->flush();

		$this->factory->getLoggableEntityAssociationStructure();

		self::assertNull($this->factory->getLoggableEntityFromAssociationStructure($comment));
	}
}
