<?php

namespace ADT\DoctrineLoggable\DI;

use ADT\DoctrineLoggable\Console\ConvertLegacyChangeSetsCommand;
use ADT\DoctrineLoggable\Doctrine\ChangeSetType;
use ADT\DoctrineLoggable\Listener\LoggableListener;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;
use ADT\DoctrineLoggable\Service\ChangeSetFactory;
use ADT\DoctrineLoggable\Service\LegacyChangeSetConverter;
use Doctrine\Common\EventManager;
use Symfony\Component\Console\Command\Command;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class LoggableExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			// ADT\DoctrineLoggable\Serializer\ValueHandler implementations, tried before the built-in ones
			'valueHandlers' => Expect::listOf(Expect::string()->dynamic()),
			// callbacks over stored change log entries, e.g. onLogEntry: [[@auditSubscriber, logEntry]]
			// - see LoggableListener::$onLogEntry
			'onLogEntry' => Expect::listOf('mixed'),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('changeSetFactory'))
			->setFactory(ChangeSetFactory::class);

		$handlers = [];
		foreach ($this->config->valueHandlers as $index => $handler) {
			$handlers[] = $builder->addDefinition($this->prefix('valueHandler.' . $index))
				->setFactory($handler)
				->setAutowired(false);
		}

		$builder->addDefinition($this->prefix('valueSerializer'))
			->setFactory(ValueSerializer::class, [$handlers]);

		$builder->addDefinition($this->prefix('changeSetSerializer'))
			->setFactory(ChangeSetSerializer::class);

		$builder->addDefinition($this->prefix('legacyChangeSetConverter'))
			->setFactory(LegacyChangeSetConverter::class);

		// symfony/console is optional, the library works without it
		if (class_exists(Command::class)) {
			$builder->addDefinition($this->prefix('convertLegacyChangeSetsCommand'))
				->setFactory(ConvertLegacyChangeSetsCommand::class);
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		// intentionally registered here instead of in loadConfiguration to avoid autoregistration
		// in nettrine dbal extension
		$listener = $builder->addDefinition($this->prefix('listener'))
			->setFactory(LoggableListener::class);

		foreach ($this->config->onLogEntry as $callback) {
			$listener->addSetup('$onLogEntry[]', [$callback]);
		}

		$builder->getDefinition($builder->getByType(EventManager::class))
			->addSetup('addEventSubscriber', ['@' . $this->prefix('listener')]);
	}

	public function afterCompile(ClassType $class): void
	{
		// the dbal type has to know the serializer before the first entity is loaded or persisted
		$this->initialization->addBody(
			'\\' . ChangeSetType::class . '::register($this->getService(?));',
			[$this->prefix('changeSetSerializer')]
		);
	}
}
