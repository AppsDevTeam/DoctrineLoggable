# DoctrineLoggable

Logging of changes in Doctrine entities. Every change is stored as one `change_log` row with the
change set serialized to JSON, so the log is readable straight from the database.

## Installation

1. Install via composer:

    ```bash
    composer require adt/doctrine-loggable
    ```

2. Register this extension in your config.neon:

    ```neon
    extensions:
        - ADT\DoctrineLoggable\DI\LoggableExtension
    ```

3. Do database migrations

4. Add attributes to entities you wish to log

```php
<?php

use Doctrine\ORM\Mapping as ORM;
use ADT\DoctrineLoggable\Attributes as ADA;

#[ORM\Entity]
#[ADA\LoggableEntity]
class User
{
	#[ORM\Column(nullable: true)]
	#[ADA\LoggableProperty]
	protected ?string $firstname = null;

	#[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
	#[ADA\LoggableProperty]
	protected Collection $roles;
}

#[ORM\Entity]
#[ADA\LoggableIdentification(fields: ['name'])]
class Role
{
	#[ORM\Column]
	protected string $name;

	#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'roles')]
	protected Collection $users;
}
```

## Logging a change without its value

Some values do not belong in a log even as history - a password hash is the usual one. Dropping
the `#[LoggableProperty]` attribute is not the answer: the change would then not be logged at all
and the history would be missing the fact that the password ever changed. Keep the attribute and
turn the value off:

```php
#[ORM\Column(nullable: true)]
#[ADA\LoggableProperty(withValue: false)]
protected ?string $password = null;
```

The change set then holds a `Redacted` node carrying nothing but the property name, so the log
says *password changed* and no more. Nothing is written anywhere, so the old value cannot be
recovered from the log even by someone holding a database dump.

Works for columns and owning-side `toOne` associations, that is wherever Doctrine reports the
change in the entity change set. On collections the flag is ignored.

## Stored format

The `change_set` column is a JSON column handled by the `change_set` DBAL type, which the extension
registers for you. `ChangeLog::getChangeSet()` returns the usual `ChangeSet` object graph.

```json
{
  "version": 1,
  "action": "edit",
  "entity": {
    "class": "App\\Model\\Entities\\Product",
    "id": "42",
    "identification": { "name": "Pivo 12°" }
  },
  "properties": {
    "name":  { "type": "scalar", "old": "Pivo", "new": "Pivo 12°" },
    "price": { "type": "scalar", "old": 39.0, "new": 45.0 },
    "validFrom": {
      "type": "scalar",
      "old": null,
      "new": { "@type": "datetime", "class": "DateTimeImmutable", "value": "2026-08-27T12:00:00+02:00" }
    },
    "category": {
      "type": "toOne",
      "old": { "class": "App\\Model\\Entities\\Category", "id": "3", "identification": { "name": "Nápoje" } },
      "new": { "class": "App\\Model\\Entities\\Category", "id": "7", "identification": { "name": "Pivo" } },
      "changeSet": null
    },
    "tags": {
      "type": "toMany",
      "added":   [ { "class": "App\\Model\\Entities\\Tag", "id": "9", "identification": { "name": "akce" } } ],
      "removed": [],
      "changeSets": []
    }
  }
}
```

A value position holds either a plain JSON scalar or an envelope object with the `@type` key.
Arrays never appear raw in a value position, so `@type` is always an unambiguous marker.
Built-in envelope types are `datetime`, `enum`, `array`, `binary`, `float` (for `NAN` and `INF`)
and `object` as the last resort fallback.

The property type is a `scalar`/`toOne`/`toMany`/`redacted` discriminator rather than a class name, so the
classes of this library can be moved or renamed without breaking existing logs.

The change set graph may contain cycles. A change set referenced more than once gets a `$id` and
every further occurrence is written as `{"$ref": id}`. Change sets referenced just once, which is
almost always the case, carry no ids at all.

## Reacting to logged changes

`LoggableListener::$onLogEntry` announces every stored change log row, so a change can be turned
into something else - an audit trail record, a notification, a search index update. The library
announces everything and filters nothing: which change matters is the consumer's business.

```neon
doctrineLoggable:
	onLogEntry:
		- [@App\Log\ChangeAuditSubscriber, logEntry]
```

```php
class ChangeAuditSubscriber
{
	public function logEntry(ChangeLog $logEntry, object $entity, bool $announced): void
	{
		if (!$entity instanceof User) {
			return;
		}

		$this->audit->record(
			$logEntry->getObjectClass(),
			$logEntry->getObjectId(),
			$logEntry->getChangeSet(),
		);
	}
}
```

The callback runs in `postFlush`, that is after the commit: what gets announced is in the database
and `$logEntry->getId()` is already assigned.

One entity has **one** change log row per request and every further flush that touches it grows
that row. Such a row is announced again with `$announced` set to `TRUE`, and its change set is
cumulative - it holds what the previous announcement carried as well. A consumer writing an
append-only trail should therefore key on `$logEntry->getId()`: either skip the repeats, or write
a record that supersedes the previous one. Calling `$em->clear()` starts over - entities logged
afterwards get fresh rows announced with `$announced` set to `FALSE`.

Flushing from inside the callback is safe; the nested `postFlush` announces nothing twice.

## Custom value types

Values coming from custom Doctrine types (money, uuid, embeddables) end up in the `object` envelope,
which keeps their readable representation but does not restore the original object. Register a
handler to make the round trip lossless:

```php
use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;

class MoneyHandler implements ValueHandler
{
	public function getType(): string
	{
		return 'money';
	}

	public function supports(mixed $value): bool
	{
		return $value instanceof Money;
	}

	public function encode(mixed $value, ValueSerializer $serializer): array
	{
		return ['amount' => $value->getAmount(), 'currency' => $value->getCurrency()];
	}

	public function decode(array $data, ValueSerializer $serializer): Money
	{
		return new Money($data['amount'], $data['currency']);
	}
}
```

```neon
doctrineLoggable:
    valueHandlers:
        - App\Log\MoneyHandler
```

Handlers registered this way are tried before the built-in ones, so they can override them.

## Upgrading from 3.x

The `change_set` column changes from `LONGBLOB` holding a PHP `serialize()` payload to a JSON
column. Convert the existing rows first, while the column is still a BLOB:

```bash
php bin/console doctrine-loggable:convert-legacy-change-sets --dry-run
php bin/console doctrine-loggable:convert-legacy-change-sets
```

The command reads and rewrites the rows with plain SQL, so the `change_set` DBAL type never sees a
legacy payload. It commits per batch (`--batch-size`, 500 by default), recognises rows that already
hold JSON and leaves them alone, so it is safe to run again. A row that fails to convert is
reported and left untouched, and the command exits with a failure so you do not change the column
type on top of data that did not make it.

Only when it reports no failures:

```sql
ALTER TABLE change_log MODIFY change_set JSON NOT NULL;
```

`symfony/console` is optional. Without it the command is not registered and you can drive
`ADT\DoctrineLoggable\Service\LegacyChangeSetConverter` yourself.

`Adt\DoctrineLoggable\ChangeSet\*` is now declared as `ADT\DoctrineLoggable\ChangeSet\*`, which is
what the PSR-4 prefix always said. The old casing keeps autoloading, PHP treats both as the same
class.

## Tests

```bash
composer install
vendor/bin/phpunit
```
