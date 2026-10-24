<?php

namespace ADT\DoctrineLoggable\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class LoggableEntity
{
	/**
	 * @param bool $logCreate TRUE = zaloguje se i zalozeni entity, se vsemi logovanymi
	 *        vlastnostmi jako zmenou z null. Vychozi je vypnuto: kdo a kdy entitu zalozil,
	 *        nese obvykle entita sama, a u entit, kterych pribyva hodne (objednavky, jejich
	 *        polozky, pohyby), by change_log rostl radove rychleji, aniz by v nem bylo neco
	 *        noveho. Zapina se tam, kde zalozeni samo o sobe je udalost, kterou je treba
	 *        dohledat - identity, role, opravneni, API klice.
	 *
	 *        Smazani se loguje vzdy, nezavisle na tomto priznaku: po smazani uz data nikde
	 *        nejsou a change_log je jedina stopa.
	 */
	public function __construct(
		public readonly bool $logCreate = false,
	) {
	}
}
