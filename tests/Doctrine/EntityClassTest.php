<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Doctrine {

	use ADT\DoctrineLoggable\Doctrine\EntityClass;
	use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article;
	use PHPUnit\Framework\TestCase;
	use Proxies\__CG__\ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article as ArticleProxy;

	final class EntityClassTest extends TestCase
	{
		public function testEntityKeepsItsClass(): void
		{
			self::assertSame(Article::class, EntityClass::of(new Article('x')));
		}

		public function testProxyResolvesToEntityClass(): void
		{
			self::assertSame(Article::class, EntityClass::of(new ArticleProxy('x')));
		}
	}
}

// Named like the proxies Doctrine generates (__CG__ marker in the namespace)
namespace Proxies\__CG__\ADT\DoctrineLoggable\Tests\Fixtures\Entity {

	final class Article extends \ADT\DoctrineLoggable\Tests\Fixtures\Entity\Article
	{
	}
}
