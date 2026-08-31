<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class TestConnectionFactory
{
	public static function create(): Connection
	{
		return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
	}
}
