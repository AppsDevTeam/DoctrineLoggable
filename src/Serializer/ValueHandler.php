<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Serializer;

/**
 * Converts a single PHP value to a JSON envelope and back.
 *
 * The envelope is always a JSON object with the "@type" key set to getType().
 * Everything else in the envelope is up to the handler.
 */
interface ValueHandler
{
	/**
	 * Discriminator stored under the "@type" key.
	 */
	public function getType(): string;

	public function supports(mixed $value): bool;

	/**
	 * @return array<string, mixed> envelope payload without the "@type" key
	 */
	public function encode(mixed $value, ValueSerializer $serializer): array;

	/**
	 * @param array<string, mixed> $data full envelope including the "@type" key
	 */
	public function decode(array $data, ValueSerializer $serializer): mixed;
}
