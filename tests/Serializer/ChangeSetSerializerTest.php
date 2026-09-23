<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Serializer;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\ArticleStateEnum;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Tag;
use ADT\DoctrineLoggable\UnexpectedValueException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ChangeSetSerializerTest extends TestCase
{
	private ChangeSetSerializer $serializer;

	protected function setUp(): void
	{
		$this->serializer = new ChangeSetSerializer();
	}

	public function testScalarChangeSetProducesReadableJson(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->setIdentification(new Id('42', Article::class, ['title' => 'Pivo']));
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));
		$changeSet->addPropertyChange(new Scalar('rating', null, 5));

		self::assertSame(
			[
				'version' => 1,
				'action' => 'edit',
				'entity' => ['class' => Article::class, 'id' => '42', 'identification' => ['title' => 'Pivo']],
				'properties' => [
					'title' => ['type' => 'scalar', 'old' => 'Pivo', 'new' => 'Pivo 12°'],
					'rating' => ['type' => 'scalar', 'old' => null, 'new' => 5],
				],
			],
			$this->serializer->toArray($changeSet)
		);
	}

	public function testDiacriticsStayReadableInTheStoredJson(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->setIdentification(new Id('42', Article::class, ['title' => 'Nápoje']));
		$changeSet->addPropertyChange(new Scalar('title', 'Příliš', 'žluťoučký kůň'));

		$json = json_encode($this->serializer->toArray($changeSet), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		self::assertStringContainsString('Nápoje', $json);
		self::assertStringContainsString('žluťoučký kůň', $json);
		self::assertStringNotContainsString('\\u', $json);
	}

	public function testActionIsPreserved(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->setAction(ChangeSet::ACTION_DELETE);
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', null));

		$decoded = $this->serializer->fromArray($this->serializer->toArray($changeSet));

		self::assertSame(ChangeSet::ACTION_DELETE, $decoded->getAction());
	}

	public function testRichScalarValuesRoundTrip(): void
	{
		$publishedAt = new DateTimeImmutable('2026-08-27T12:00:00+02:00');

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('publishedAt', null, $publishedAt));
		$changeSet->addPropertyChange(new Scalar('state', ArticleStateEnum::Draft, ArticleStateEnum::Published));
		$changeSet->addPropertyChange(new Scalar('meta', null, ['vat' => 21]));

		$decoded = $this->serializer->fromArray($this->serializer->toArray($changeSet));
		$properties = $decoded->getChangedProperties();

		self::assertSame($publishedAt->format('c'), $properties['publishedAt']->getNew()->format('c'));
		self::assertSame(ArticleStateEnum::Published, $properties['state']->getNew());
		self::assertSame(['vat' => 21], $properties['meta']->getNew());
	}

	public function testToOneWithoutNestedChangeSet(): void
	{
		$old = new Id('1', Author::class, ['name' => 'Franta']);
		$new = new Id('2', Author::class, ['name' => 'Pepa']);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new ToOne('author', $old, $new));

		$data = $this->serializer->toArray($changeSet);

		self::assertSame(
			[
				'type' => 'toOne',
				'old' => ['class' => Author::class, 'id' => '1', 'identification' => ['name' => 'Franta']],
				'new' => ['class' => Author::class, 'id' => '2', 'identification' => ['name' => 'Pepa']],
				'changeSet' => null,
			],
			$data['properties']['author']
		);

		$author = $this->serializer->fromArray($data)->getChangedProperties()['author'];
		self::assertSame('Franta', $author->getOld()->getIdentification()['name']);
		self::assertSame('2', $author->getNew()->getId());
		self::assertNull($author->getChangeSet());
	}

	public function testNullIdentificationsRoundTrip(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new ToOne('author', null, new Id('2', Author::class, [])));

		$author = $this->serializer->fromArray($this->serializer->toArray($changeSet))->getChangedProperties()['author'];

		self::assertNull($author->getOld());
		self::assertSame([], $author->getNew()->getIdentification());
	}

	public function testNestedChangeSetOfAToOneRoundTrips(): void
	{
		$nested = new ChangeSet();
		$nested->setIdentification(new Id('1', Author::class, ['name' => 'Franta']));
		$nested->addPropertyChange(new Scalar('name', 'Franta', 'František'));

		$toOne = new ToOne('author', new Id('1', Author::class, []), new Id('1', Author::class, []));
		$toOne->setChangeSet($nested);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($toOne);

		$decoded = $this->serializer->fromArray($this->serializer->toArray($changeSet));
		$decodedNested = $decoded->getChangedProperties()['author']->getChangeSet();

		self::assertSame('František', $decodedNested->getChangedProperties()['name']->getNew());
	}

	public function testToManyRoundTrips(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addAdded(new Id('9', Tag::class, ['name' => 'akce']));
		$toMany->addRemoved(new Id('3', Tag::class, ['name' => 'novinka']));

		$nested = new ChangeSet();
		$nested->setIdentification(new Id('9', Tag::class, ['name' => 'akce']));
		$nested->addPropertyChange(new Scalar('name', 'akce', 'sleva'));
		$toMany->addChangeSet($nested);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($toMany);

		$data = $this->serializer->toArray($changeSet);
		self::assertSame('toMany', $data['properties']['tags']['type']);

		$decoded = $this->serializer->fromArray($data)->getChangedProperties()['tags'];
		self::assertSame('akce', array_values($decoded->getAdded())[0]->getIdentification()['name']);
		self::assertSame('novinka', array_values($decoded->getRemoved())[0]->getIdentification()['name']);
		self::assertSame('sleva', array_values($decoded->getChangeSets())[0]->getChangedProperties()['name']->getNew());
	}

	public function testToManyKeysAreReindexedAfterAnAddedIdentificationIsRemovedAgain(): void
	{
		$first = new Id('1', Tag::class, ['name' => 'a']);
		$second = new Id('2', Tag::class, ['name' => 'b']);

		$toMany = new ToMany('tags');
		$toMany->addAdded($first);
		$toMany->addAdded($second);
		$toMany->addRemoved($first);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($toMany);

		$data = $this->serializer->toArray($changeSet);

		self::assertSame([0], array_keys($data['properties']['tags']['added']));
		self::assertSame('b', $data['properties']['tags']['added'][0]['identification']['name']);
	}

	public function testChangeSetReferencedOnceCarriesNoIds(): void
	{
		$nested = new ChangeSet();
		$nested->addPropertyChange(new Scalar('name', 'a', 'b'));

		$toOne = new ToOne('author', null, null);
		$toOne->setChangeSet($nested);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($toOne);

		$json = json_encode($this->serializer->toArray($changeSet));

		self::assertStringNotContainsString('$id', $json);
		self::assertStringNotContainsString('$ref', $json);
	}

	public function testCycleIsStoredAsAReferenceAndRestoresObjectIdentity(): void
	{
		$article = new ChangeSet();
		$article->setIdentification(new Id('42', Article::class, ['title' => 'Pivo']));

		$author = new ChangeSet();
		$author->setIdentification(new Id('1', Author::class, ['name' => 'Franta']));
		$author->addPropertyChange(new Scalar('name', 'Franta', 'František'));

		$backToArticle = new ToOne('article', null, null);
		$backToArticle->restoreChangeSet($article);
		$author->restoreProperty($backToArticle);

		$toAuthor = new ToOne('author', null, null);
		$toAuthor->setChangeSet($author);
		$article->addPropertyChange($toAuthor);

		$data = $this->serializer->toArray($article);

		self::assertSame(1, $data['$id']);
		self::assertSame(['$ref' => 1], $data['properties']['author']['changeSet']['properties']['article']['changeSet']);

		$decoded = $this->serializer->fromArray($data);
		$decodedAuthor = $decoded->getChangedProperties()['author']->getChangeSet();

		self::assertSame($decoded, $decodedAuthor->getChangedProperties()['article']->getChangeSet());
		self::assertSame('František', $decodedAuthor->getChangedProperties()['name']->getNew());
	}

	public function testSelfReferencingChangeSetRoundTrips(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->setIdentification(new Id('42', Article::class, []));

		$toSelf = new ToOne('parent', null, null);
		$toSelf->restoreChangeSet($changeSet);
		$changeSet->restoreProperty($toSelf);

		$decoded = $this->serializer->fromArray($this->serializer->toArray($changeSet));

		self::assertSame($decoded, $decoded->getChangedProperties()['parent']->getChangeSet());
	}

	public function testTheSameChangeSetUsedTwiceIsStoredOnceAndSharedAfterDecoding(): void
	{
		$shared = new ChangeSet();
		$shared->setIdentification(new Id('1', Author::class, ['name' => 'Franta']));
		$shared->addPropertyChange(new Scalar('name', 'Franta', 'František'));

		$first = new ToOne('author', null, null);
		$first->setChangeSet($shared);
		$second = new ToOne('reviewer', null, null);
		$second->setChangeSet($shared);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($first);
		$changeSet->addPropertyChange($second);

		$data = $this->serializer->toArray($changeSet);

		self::assertSame(1, $data['properties']['author']['changeSet']['$id']);
		self::assertSame(['$ref' => 1], $data['properties']['reviewer']['changeSet']);

		$decoded = $this->serializer->fromArray($data);
		self::assertSame(
			$decoded->getChangedProperties()['author']->getChangeSet(),
			$decoded->getChangedProperties()['reviewer']->getChangeSet()
		);
	}

	public function testReferenceIsResolvedEvenWhenItPrecedesItsDefinition(): void
	{
		// Takove zaznamy v change_logu jsou - "$ref" stoji driv nez "$id", na ktere ukazuje.
		// Drive na nich cteni skoncilo vyjimkou a cely zaznam byl necitelny.
		$data = [
			'version' => 1,
			'action' => 'edit',
			'entity' => null,
			'properties' => [
				'reviewer' => ['type' => 'toOne', 'old' => null, 'new' => null, 'changeSet' => ['$ref' => 1]],
				'author' => [
					'type' => 'toOne',
					'old' => null,
					'new' => null,
					'changeSet' => [
						'$id' => 1,
						'action' => 'edit',
						'entity' => null,
						'properties' => ['name' => ['type' => 'scalar', 'old' => 'Franta', 'new' => 'František']],
					],
				],
			],
		];

		$decoded = $this->serializer->fromArray($data);

		$author = $decoded->getChangedProperties()['author']->getChangeSet();
		$reviewer = $decoded->getChangedProperties()['reviewer']->getChangeSet();

		self::assertSame($author, $reviewer, 'Odkaz i definice jsou tentyz objekt');
		self::assertSame('František', $author->getChangedProperties()['name']->getNew(), 'Odkaz vidi naplnena data');
	}

	public function testDanglingReferenceIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		$this->serializer->fromArray([
			'version' => 1,
			'action' => 'edit',
			'entity' => null,
			'properties' => ['author' => ['type' => 'toOne', 'old' => null, 'new' => null, 'changeSet' => ['$ref' => 9]]],
		]);
	}

	public function testNewerFormatVersionIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		$this->serializer->fromArray(['version' => 2, 'action' => 'edit', 'entity' => null, 'properties' => []]);
	}

	public function testUnknownPropertyTypeIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		$this->serializer->fromArray([
			'version' => 1,
			'action' => 'edit',
			'entity' => null,
			'properties' => ['title' => ['type' => 'whatever']],
		]);
	}
}
