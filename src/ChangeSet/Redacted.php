<?php

namespace ADT\DoctrineLoggable\ChangeSet;

/**
 * Vlastnost se zmenila, ale hodnota se nezaznamenava - viz LoggableProperty::$withValue.
 *
 * Nese jen jmeno, takze z logu je videt "heslo se zmenilo" a nic vic. Je to vedome
 * jednosmerna ztrata: ze zaznamu uz puvodni hodnotu nikdo nedostane, ani ten, kdo se
 * dostane k dumpu databaze.
 */
class Redacted extends PropertyChangeSet
{
	/**
	 * Uzel vznika jen tehdy, kdyz uz je zmena potvrzena - jinou informaci nenese,
	 * takze nema co dal porovnavat.
	 *
	 * @return bool
	 */
	public function isChanged()
	{
		return true;
	}

	public function getType()
	{
		return self::TYPE_REDACTED;
	}

	/**
	 * Dva zaznamy o teze skryte zmene jsou nerozlisitelne, slucovat neni co.
	 */
	public function merge(PropertyChangeSet $property)
	{
	}
}
