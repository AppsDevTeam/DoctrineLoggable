<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\DI;

use ADT\DoctrineLoggable\Console\ConvertLegacyChangeSetsCommand;
use ADT\DoctrineLoggable\DI\LoggableExtension;
use ADT\DoctrineLoggable\Doctrine\ChangeSetType;
use ADT\DoctrineLoggable\Entity\ChangeLog;
use ADT\DoctrineLoggable\Listener\LoggableListener;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Service\ChangeSetFactory;
use ADT\DoctrineLoggable\Service\LegacyChangeSetConverter;
use ADT\DoctrineLoggable\Tests\Fixtures\FakeUser;
use ADT\DoctrineLoggable\Tests\Fixtures\LogEntryRecorder;
use ADT\DoctrineLoggable\Tests\Fixtures\Money;
use ADT\DoctrineLoggable\Tests\Fixtures\MoneyHandler;
use ADT\DoctrineLoggable\Tests\Fixtures\TestConnectionFactory;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Types\Type;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Definitions\Statement;
use PHPUnit\Framework\TestCase;

final class LoggableExtensionTest extends TestCase
{
	public function testExtensionRegistersTheListenerAndTheDbalType(): void
	{
		$container = $this->createContainer();

		self::assertInstanceOf(ChangeSetFactory::class, $container->getByType(ChangeSetFactory::class));
		self::assertInstanceOf(ChangeSetSerializer::class, $container->getByType(ChangeSetSerializer::class));
		self::assertContains(
			$container->getByType(LoggableListener::class),
			$container->getByType(EventManager::class)->getListeners('onFlush')
		);

		self::assertTrue(Type::hasType(ChangeSetType::NAME));
		self::assertInstanceOf(ChangeSetType::class, Type::getType(ChangeSetType::NAME));
	}

	public function testLegacyConverterAndItsCommandAreRegistered(): void
	{
		$container = $this->createContainer();

		self::assertInstanceOf(LegacyChangeSetConverter::class, $container->getByType(LegacyChangeSetConverter::class));
		self::assertInstanceOf(ConvertLegacyChangeSetsCommand::class, $container->getByType(ConvertLegacyChangeSetsCommand::class));
	}

	public function testConfiguredValueHandlersReachTheRegisteredType(): void
	{
		$this->createContainer(['valueHandlers' => [MoneyHandler::class]]);

		$valueSerializer = Type::getType(ChangeSetType::NAME)->getSerializer()->getValueSerializer();
		$encoded = $valueSerializer->encode(new Money(3900, 'CZK'));

		self::assertSame(['@type' => 'money', 'amount' => 3900, 'currency' => 'CZK'], $encoded);
		self::assertEquals(new Money(3900, 'CZK'), $valueSerializer->decode($encoded));
	}

	public function testConfiguredCallbacksAreAttachedToTheListener(): void
	{
		$container = $this->createContainer(['onLogEntry' => [['@recorder', 'logEntry']]]);

		$listener = $container->getByType(LoggableListener::class);
		self::assertCount(1, $listener->onLogEntry);

		$logEntry = new ChangeLog();
		$entity = new \stdClass();
		($listener->onLogEntry[0])($logEntry, $entity, true);

		self::assertSame([[$logEntry, $entity, true]], $container->getByType(LogEntryRecorder::class)->recorded);
	}

	/**
	 * @param array<string, mixed> $extensionConfig
	 */
	private function createContainer(array $extensionConfig = []): Container
	{
		$config = [
			'services' => [
				'eventManager' => EventManager::class,
				'connection' => new Statement([TestConnectionFactory::class, 'create']),
				'user' => FakeUser::class,
				'recorder' => LogEntryRecorder::class,
			],
		];

		if ($extensionConfig) {
			$config['doctrineLoggable'] = $extensionConfig;
		}

		$loader = new ContainerLoader(sys_get_temp_dir() . '/doctrine-loggable-di', true);
		$class = $loader->load(
			function (Compiler $compiler) use ($config): void {
				$compiler->addExtension('doctrineLoggable', new LoggableExtension());
				$compiler->addConfig($config);
			},
			json_encode($extensionConfig)
		);

		$container = new $class();
		// Nette\Bootstrap\Configurator does this for a real application
		$container->initialize();

		return $container;
	}
}
