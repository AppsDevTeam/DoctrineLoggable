<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Listener;

use ADT\DoctrineLoggable\ChangeSet\Redacted;
use ADT\DoctrineLoggable\ChangeSet\Scalar;
use ADT\DoctrineLoggable\Serializer\ChangeSetSerializer;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Account;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\Author;
use ADT\DoctrineLoggable\Tests\Fixtures\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * #[LoggableProperty(withValue: false)] - zaloguje se, ze se vlastnost zmenila, ale ne na co.
 *
 * Vynechat rovnou cely atribut by nestacilo: zmena by se nezalogovala vubec a v historii
 * by chybela. Typicky pripad je hash hesla, ktery v logu nema co delat ani jako historie.
 */
final class RedactedValueTest extends TestCase
{
	private EntityManagerInterface $em;

	protected function setUp(): void
	{
		$this->em = EntityManagerFactory::create();
	}

	protected function tearDown(): void
	{
		$this->em->getConnection()->close();
	}

	public function testChangeIsLoggedWithoutTheValue(): void
	{
		$account = new Account('jan@example.com');
		$this->em->persist($account);
		$this->em->flush();

		$account->setPassword('$2y$10$novyhash');
		$this->em->flush();

		$logs = EntityManagerFactory::findChangeLogs($this->em);
		self::assertCount(1, $logs);

		$properties = $logs[0]->getChangeSet()->getChangedProperties();
		self::assertInstanceOf(Redacted::class, $properties['password']);
		self::assertSame(['password'], array_keys($properties));

		// ani v surovem JSON, na ktery se v databazi da dotazovat
		self::assertStringNotContainsString('novyhash', $this->fetchRawChangeSet(1));
	}

	public function testRedactedValueSurvivesTheRoundTrip(): void
	{
		$account = new Account('jan@example.com');
		$this->em->persist($account);
		$this->em->flush();

		$account->setPassword('$2y$10$novyhash');
		$this->em->flush();

		$this->em->clear();
		$properties = EntityManagerFactory::findChangeLogs($this->em)[0]->getChangeSet()->getChangedProperties();

		self::assertInstanceOf(Redacted::class, $properties['password']);
		self::assertSame('password', $properties['password']->getName());
		self::assertTrue($properties['password']->isChanged());
	}

	/**
	 * Bez hodnot uz neni co porovnavat, takze uzel musi hlasit zmenu sam za sebe -
	 * jinak by ho change set zahodil a zmena hesla by v logu nebyla.
	 */
	public function testRedactedChangeAloneStillCreatesARow(): void
	{
		$account = new Account('jan@example.com');
		$this->em->persist($account);
		$this->em->flush();

		$account->setPassword('prvni');
		$this->em->flush();

		$account->setPassword('druhy');
		$this->em->flush();

		// jeden radek na entitu a request, doplnovany dalsimi flushi
		self::assertCount(1, EntityManagerFactory::findChangeLogs($this->em));
	}

	public function testUnchangedRedactedPropertyIsNotLogged(): void
	{
		$account = new Account('jan@example.com');
		$this->em->persist($account);
		$this->em->flush();

		$account->setEmail('jan.novak@example.com');
		$this->em->flush();

		$properties = EntityManagerFactory::findChangeLogs($this->em)[0]->getChangeSet()->getChangedProperties();

		self::assertArrayNotHasKey('password', $properties);
		self::assertInstanceOf(Scalar::class, $properties['email']);
	}

	/** Funguje i na vlastnicke toOne vazbe, tam zmenu hlasi Doctrine stejne jako u sloupce. */
	public function testAssociationChangeIsRedactedToo(): void
	{
		$franta = new Author('Franta');
		$account = new Account('jan@example.com');
		$this->em->persist($franta);
		$this->em->persist($account);
		$this->em->flush();

		$account->setOwner($franta);
		$this->em->flush();

		$properties = EntityManagerFactory::findChangeLogs($this->em)[0]->getChangeSet()->getChangedProperties();

		self::assertInstanceOf(Redacted::class, $properties['owner']);
		self::assertStringNotContainsString('Franta', $this->fetchRawChangeSet(1));
	}

	public function testSerializedShapeIsJustTheType(): void
	{
		$serializer = new ChangeSetSerializer();

		$account = new Account('jan@example.com');
		$this->em->persist($account);
		$this->em->flush();

		$account->setPassword('tajne');
		$this->em->flush();

		$data = $serializer->toArray(EntityManagerFactory::findChangeLogs($this->em)[0]->getChangeSet());

		self::assertSame(['type' => 'redacted'], $data['properties']['password']);
	}

	private function fetchRawChangeSet(int $id): string
	{
		return (string) $this->em->getConnection()
			->fetchOne('SELECT change_set FROM change_log WHERE id = ?', [$id]);
	}
}
