<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

enum ArticleStateEnum: string
{
	case Draft = 'draft';
	case Published = 'published';
}
