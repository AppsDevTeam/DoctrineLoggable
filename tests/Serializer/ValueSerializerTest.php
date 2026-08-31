<?php

declare(strict_types=1);

namespace ADT\DoctrineLoggable\Tests\Serializer;

use ADT\DoctrineLoggable\Serializer\ValueHandler;
use ADT\DoctrineLoggable\Serializer\ValueSerializer;
use ADT\DoctrineLoggable\Tests\Fixtures\Entity\ArticleStateEnum;
use ADT\DoctrineLoggable\UnexpectedValueException;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueSerializerTest extends TestCase
{
	private ValueSerializer $serializer;

	protected function setUp(): void
	{
		$this->serializer = new ValueSerializer();
	}

	public static function nativeValues(): array
	{
		return [
			'null' => [null],
			'true' => [true],
			'false' => [false],
			'zero' => [0],
			'int' => [42],
			'negative int' => [-7],
			'float' => [39.5],
			'float without fraction' => [39.0],
			'empty string' => [''],
			'string' => ['Pivo 12°'],
			'numeric string' => ['0042'],
		];
	}

	#[DataProvider('nativeValues')]
	public function testNativeValuesAreStoredWithoutAnEnvelope(mixed $value): void
	{
		self::assertSame($value, $this->serializer->encode($value));
		self::assertSame($value, $this->serializer->decode($this->serializer->encode($value)));
	}

	public function testDateTimeImmutableRoundTripsWithItsClass(): void
	{
		$value = new DateTimeImmutable('2026-08-27 12:00:00', new \DateTimeZone('Europe/Prague'));

		$encoded = $this->serializer->encode($value);

		self::assertSame(
			['@type' => 'datetime', 'class' => DateTimeImmutable::class, 'value' => '2026-08-27T12:00:00+02:00'],
			$encoded
		);

		$decoded = $this->serializer->decode($encoded);
		self::assertInstanceOf(DateTimeImmutable::class, $decoded);
		self::assertSame($value->format('c'), $decoded->format('c'));
	}

	public function testMutableDateTimeKeepsItsClass(): void
	{
		$decoded = $this->serializer->decode($this->serializer->encode(new DateTime('2026-01-01 00:00:00')));

		self::assertInstanceOf(DateTime::class, $decoded);
	}

	public function testMicrosecondsAreKeptOnlyWhenPresent(): void
	{
		$withMicroseconds = $this->serializer->encode(new DateTimeImmutable('2026-08-27T12:00:00.123456+02:00'));
		$withoutMicroseconds = $this->serializer->encode(new DateTimeImmutable('2026-08-27T12:00:00+02:00'));

		self::assertSame('2026-08-27T12:00:00.123456+02:00', $withMicroseconds['value']);
		self::assertSame('2026-08-27T12:00:00+02:00', $withoutMicroseconds['value']);
	}

	public function testBackedEnumRoundTrips(): void
	{
		$encoded = $this->serializer->encode(ArticleStateEnum::Published);

		self::assertSame(
			['@type' => 'enum', 'class' => ArticleStateEnum::class, 'value' => 'published'],
			$encoded
		);
		self::assertSame(ArticleStateEnum::Published, $this->serializer->decode($encoded));
	}

	public function testRemovedEnumCaseDegradesToTheRawValue(): void
	{
		$decoded = $this->serializer->decode(['@type' => 'enum', 'class' => ArticleStateEnum::class, 'value' => 'archived']);

		self::assertSame('archived', $decoded);
	}

	public function testMissingEnumClassDegradesToTheRawValue(): void
	{
		$decoded = $this->serializer->decode(['@type' => 'enum', 'class' => 'App\\Gone\\Enum', 'value' => 'draft']);

		self::assertSame('draft', $decoded);
	}

	public function testArrayIsWrappedSoThatValuePositionsStayUnambiguous(): void
	{
		$encoded = $this->serializer->encode(['vat' => 21, 'tags' => ['a', 'b']]);

		self::assertSame(['@type' => 'array', 'value' => ['vat' => 21, 'tags' => ['a', 'b']]], $encoded);
		self::assertSame(['vat' => 21, 'tags' => ['a', 'b']], $this->serializer->decode($encoded));
	}

	public function testNestedArraysStayPlainArrays(): void
	{
		$value = ['a' => ['b' => ['c' => 1]]];

		self::assertSame(['@type' => 'array', 'value' => $value], $this->serializer->encode($value));
		self::assertSame($value, $this->serializer->decode($this->serializer->encode($value)));
	}

	public function testNonJsonValuesInsideAnArrayGetAnEnvelope(): void
	{
		$value = ['from' => new DateTimeImmutable('2026-08-27T12:00:00+02:00'), 'state' => ArticleStateEnum::Draft];

		$encoded = $this->serializer->encode($value);

		self::assertSame('datetime', $encoded['value']['from']['@type']);
		self::assertSame('enum', $encoded['value']['state']['@type']);

		$decoded = $this->serializer->decode($encoded);
		self::assertSame('2026-08-27T12:00:00+02:00', $decoded['from']->format('c'));
		self::assertSame(ArticleStateEnum::Draft, $decoded['state']);
	}

	public function testArrayContainingOurMarkerKeyIsEscapedAndRoundTrips(): void
	{
		$value = ['@type' => 'datetime', 'value' => 'not ours'];

		$encoded = $this->serializer->encode($value);

		self::assertSame($value, $this->serializer->decode($encoded));
	}

	public function testNestedArrayContainingOurMarkerKeyRoundTrips(): void
	{
		$value = ['payload' => ['@type' => 'enum', 'class' => 'whatever', 'value' => 'x']];

		self::assertSame($value, $this->serializer->decode($this->serializer->encode($value)));
	}

	public function testInvalidUtf8StringIsStoredAsBase64(): void
	{
		$value = "\x80\xFF binary";

		$encoded = $this->serializer->encode($value);

		self::assertSame('binary', $encoded['@type']);
		self::assertSame($value, $this->serializer->decode($encoded));
		self::assertJson(json_encode($encoded, JSON_THROW_ON_ERROR));
	}

	public function testNonFiniteFloatsRoundTrip(): void
	{
		self::assertSame(INF, $this->serializer->decode($this->serializer->encode(INF)));
		self::assertSame(-INF, $this->serializer->decode($this->serializer->encode(-INF)));
		self::assertNan($this->serializer->decode($this->serializer->encode(NAN)));
	}

	public function testStringableObjectFallsBackToItsStringRepresentation(): void
	{
		$value = new class implements \Stringable {
			public function __toString(): string
			{
				return '5f2b8c1a';
			}
		};

		$encoded = $this->serializer->encode($value);

		self::assertSame('object', $encoded['@type']);
		self::assertSame('5f2b8c1a', $encoded['value']);
		self::assertSame('5f2b8c1a', $this->serializer->decode($encoded));
	}

	public function testJsonSerializableObjectFallsBackToItsJsonRepresentation(): void
	{
		$value = new class implements \JsonSerializable {
			public function jsonSerialize(): array
			{
				return ['amount' => 100, 'currency' => 'CZK'];
			}
		};

		$encoded = $this->serializer->encode($value);

		self::assertSame(['amount' => 100, 'currency' => 'CZK'], $this->serializer->decode($encoded));
	}

	public function testUnknownEnvelopeTypeDegradesToItsValueInsteadOfBreakingTheLog(): void
	{
		self::assertSame('39.00', $this->serializer->decode(['@type' => 'money', 'value' => '39.00']));
	}

	public function testBareArrayInAValuePositionIsRejected(): void
	{
		$this->expectException(UnexpectedValueException::class);

		$this->serializer->decode(['vat' => 21]);
	}

	public function testCustomHandlerTakesPrecedenceOverTheBuiltInOnes(): void
	{
		$handler = new class implements ValueHandler {
			public function getType(): string
			{
				return 'datetime';
			}

			public function supports(mixed $value): bool
			{
				return $value instanceof DateTimeImmutable;
			}

			public function encode(mixed $value, ValueSerializer $serializer): array
			{
				return ['value' => $value->getTimestamp()];
			}

			public function decode(array $data, ValueSerializer $serializer): DateTimeImmutable
			{
				return new DateTimeImmutable('@' . $data['value']);
			}
		};

		$value = new DateTimeImmutable('2026-08-27T12:00:00+02:00');
		$serializer = new ValueSerializer([$handler]);

		$encoded = $serializer->encode($value);

		self::assertSame(['@type' => 'datetime', 'value' => $value->getTimestamp()], $encoded);
		self::assertSame($value->getTimestamp(), $serializer->decode($encoded)->getTimestamp());
	}
}
