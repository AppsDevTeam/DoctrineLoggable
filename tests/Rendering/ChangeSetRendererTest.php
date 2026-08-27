<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Rendering;

use ADT\DoctrineLoggable\ChangeSet\ChangeSet;
use ADT\DoctrineLoggable\ChangeSet\Id;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\ChangeSet\ToMany;
use ADT\DoctrineLoggable\ChangeSet\ToOne;
use ADT\DoctrineLoggable\Rendering\ChangeSetRenderer;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Tag;
use PHPUnit\Framework\TestCase;

final class ChangeSetRendererTest extends TestCase
{
	private ChangeSetRenderer $renderer;

	protected function setUp(): void
	{
		$this->renderer = new ChangeSetRenderer();
	}

	public function testAnEmptyChangeSetRendersNothing(): void
	{
		self::assertSame('', $this->render(new ChangeSet()));
	}

	public function testAScalarChangeRendersAsATableRow(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', 'Pivo', 'Pivo 12°'));

		$html = $this->render($changeSet);

		self::assertStringContainsString('<table>', $html);
		self::assertStringContainsString('<td>title</td>', $html);
		self::assertStringContainsString('Pivo', $html);
		self::assertStringContainsString('Pivo 12°', $html);
	}

	public function testANullValueRendersAsNull(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('rating', null, 5));

		self::assertStringContainsString('NULL', $this->render($changeSet));
	}

	public function testALongValueIsTruncatedButKeptInTheTitle(): void
	{
		$long = str_repeat('a', 300);
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new Scalar('title', '', $long));

		$html = $this->render($changeSet);

		self::assertStringContainsString('title="' . $long . '"', $html);
		self::assertStringContainsString('…', $html);
	}

	public function testAToOneChangeRendersBothIdentifications(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new ToOne(
			'author',
			new Id('1', Author::class, ['name' => 'Franta']),
			new Id('2', Author::class, ['name' => 'Pepa'])
		));

		$html = $this->render($changeSet);

		self::assertStringContainsString('<td>author</td>', $html);
		self::assertStringContainsString('name: Franta', $html);
		self::assertStringContainsString('name: Pepa', $html);
	}

	public function testAMissingIdentificationRendersAsNull(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange(new ToOne('author', null, new Id('2', Author::class, ['name' => 'Pepa'])));

		self::assertStringContainsString('NULL', $this->render($changeSet));
	}

	public function testANestedChangeSetIsRenderedInPlace(): void
	{
		$nested = new ChangeSet();
		$nested->setIdentification(new Id('1', Author::class, ['name' => 'Franta']));
		$nested->addPropertyChange(new Scalar('name', 'Franta', 'František'));

		$toOne = new ToOne('author', null, null);
		$toOne->setChangeSet($nested);

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($toOne);

		$html = $this->render($changeSet);

		self::assertSame(2, substr_count($html, '<table>'));
		self::assertStringContainsString('František', $html);
	}

	public function testAToManyChangeRendersARowPerItem(): void
	{
		$toMany = new ToMany('tags');
		$toMany->addAdded(new Id('9', Tag::class, ['name' => 'akce']));
		$toMany->addAdded(new Id('5', Tag::class, ['name' => 'sleva']));
		$toMany->addRemoved(new Id('3', Tag::class, ['name' => 'novinka']));

		$changeSet = new ChangeSet();
		$changeSet->addPropertyChange($toMany);

		$html = $this->render($changeSet);

		self::assertStringContainsString("<td rowspan='2'>tags</td>", $html);
		self::assertStringContainsString('name: akce', $html);
		self::assertStringContainsString('name: sleva', $html);
		self::assertStringContainsString('name: novinka', $html);
	}

	public function testACycleIsRenderedAsRecursionInsteadOfLoopingForever(): void
	{
		$article = new ChangeSet();
		$article->setIdentification(new Id('42', Author::class, ['name' => 'Franta']));

		$author = new ChangeSet();
		$back = new ToOne('article', null, null);
		$back->restoreChangeSet($article);
		$author->restoreProperty($back);

		$toAuthor = new ToOne('author', null, null);
		$toAuthor->setChangeSet($author);
		$article->addPropertyChange($toAuthor);

		$html = $this->render($article);

		self::assertStringContainsString('recursion(', $html);
		self::assertStringContainsString('name: Franta', $html);
	}

	/**
	 * Regrese: the reset used to assign a misspelled dynamic property, so a change set rendered
	 * twice by the same instance came out as "recursion(...)" the second time.
	 */
	public function testTheSameChangeSetCanBeRenderedTwice(): void
	{
		$changeSet = new ChangeSet();
		$changeSet->setIdentification(new Id('1', Author::class, ['name' => 'Franta']));
		$changeSet->addPropertyChange(new Scalar('name', 'Franta', 'František'));

		$first = $this->render($changeSet);

		self::assertSame($first, $this->render($changeSet));
		self::assertStringNotContainsString('recursion(', $first);
	}

	private function render(ChangeSet $changeSet): string
	{
		ob_start();

		try {
			$this->renderer->render($changeSet);

			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}
}
