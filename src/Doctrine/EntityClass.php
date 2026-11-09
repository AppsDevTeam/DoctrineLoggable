<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Doctrine;

use Doctrine\Persistence\Proxy;

/**
 * Real class of an entity that may be a Doctrine proxy - replaces ClassUtils::getClass()
 * from the abandoned doctrine/common. Native lazy objects (PHP 8.4+) already have the real class.
 */
final class EntityClass
{
	/**
	 * @return class-string
	 */
	public static function of(object $entity): string
	{
		$class = $entity::class;
		$pos = strrpos($class, '\\' . Proxy::MARKER . '\\');

		/** @var class-string */
		return $pos === false ? $class : substr($class, $pos + Proxy::MARKER_LENGTH + 2);
	}
}
