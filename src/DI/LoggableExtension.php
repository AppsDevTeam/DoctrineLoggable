<?php

namespace ADT\DoctrineLoggable\DI;

use ADT\DoctrineLoggable\Listener\LoggableListener;
use ADT\DoctrineLoggable\Service\ChangeSetFactory;
use Doctrine\Common\EventManager;
use Nette\DI\CompilerExtension;

class LoggableExtension extends CompilerExtension
{
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('changeSetFactory'))
			->setFactory(ChangeSetFactory::class)
			->addSetup('setEntityManager', ['@nettrine.orm.managers.logdb.entityManagerDecorator']);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		// intentionally registered here instead of in loadConfiguration to avoid autoregistration
		// in nettrine dbal extension
		$builder->addDefinition($this->prefix('listener'))
			->setFactory(LoggableListener::class, ['@' . $this->prefix('changeSetFactory')]);
		$builder->getDefinition($builder->getByType(EventManager::class))
			->addSetup('addEventSubscriber', ['@' . $this->prefix('listener')]);
	}
}
