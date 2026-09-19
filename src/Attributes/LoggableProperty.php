<?php

namespace ADT\DoctrineLoggable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class LoggableProperty
{
	/**
	 * @param bool $withValue FALSE = zaloguje se, ze se vlastnost zmenila, ale ne na co.
	 *        Pro hodnoty, ktere do logu nepatri ani jako historie - typicky hash hesla:
	 *        change_log by jinak drzel i davno neplatna hesla a pri uniku dumpu by to byl
	 *        material na offline lamani. Vynechat rovnou cely atribut nestaci, tim by se
	 *        zmena nezalogovala vubec a zmena hesla by v historii chybela.
	 *
	 *        Plati pro sloupce a vlastnicke toOne vazby, tedy tam, kde zmenu hlasi
	 *        Doctrine v change setu entity; u kolekci se ignoruje.
	 */
	public function __construct(
		public readonly bool $withValue = true,
	) {
	}
}
