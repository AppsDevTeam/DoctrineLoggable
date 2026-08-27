<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures;

use ADT\DoctrineLoggable\Doctrine\ChangeSetType;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\DoctrineLoggable\Listener\LoggableListener;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;
use ADT\DoctrineLoggable\Service\ChangeSetFactory;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nette\Security\User;

final class EntityManagerFactory
{
	/**
	 * @param ValueHandler[] $valueHandlers
	 */
	public static function create(array $valueHandlers = [], ?User $user = null): EntityManagerInterface
	{
		ChangeSetType::register(new ChangeSetSerializer(new ValueSerializer($valueHandlers)));

		$config = ORMSetup::createAttributeMetadataConfiguration(
			[__DIR__ . '/Entity', dirname(__DIR__, 2) . '/src/Entity'],
			true
		);

		// the same strategy projects use, so that the table is change_log like in the migrations
		$config->setNamingStrategy(new UnderscoreNamingStrategy());

		$eventManager = new EventManager();
		$eventManager->addEventSubscriber(new LoggableListener(new ChangeSetFactory($user ?? new FakeUser())));

		$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
		$em = new EntityManager($connection, $config, $eventManager);

		$schemaTool = new SchemaTool($em);
		$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

		return $em;
	}

	public static function findChangeLogs(EntityManagerInterface $em): array
	{
		return $em->getRepository(ChangeLog::class)->findBy([], ['id' => 'ASC']);
	}
}
