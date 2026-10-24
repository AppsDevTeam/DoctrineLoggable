<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Fixtures\Entity;

use ADT\DoctrineLoggable\Attributes as ADA;

/**
 * Logging declared by an interface, the way a library declares it for entities whose class
 * lives in the project.
 */
#[ADA\LoggableEntity(logCreate: true)]
interface Subscriber
{
}
