<?php

namespace ADT\DoctrineLoggable\ChangeSet;

class ChangeSet
{

	const ACTION_CREATE = 'create';
	const ACTION_EDIT = 'edit';
	const ACTION_DELETE = 'delete';

	/** @var string */
	protected $a = self::ACTION_EDIT;

	/** @var Id */
	protected $i;

	/** @var PropertyChangeSet[] list of changed properties */
	protected $p = [];

	/**
	 * @param PropertyChangeSet $property
	 * @return $this
	 */
	public function addPropertyChange(PropertyChangeSet $property)
	{
		if ($property->isChanged()) {
			if (isset($this->p[$property->getName()])) {
				$oldNodeProperty = $this->p[$property->getName()];
				$oldNodeProperty->merge($property);
			} else {
				$this->p[$property->getName()] = $property;
			}
		}
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isChanged()
	{
		return count($this->p) > 0;
	}

	/**
	 * @return Id
	 */
	public function getIdentification()
	{
		return $this->i;
	}

	/**
	 * @param $identification
	 * @return $this
	 */
	public function setIdentification($identification)
	{
		$this->i = $identification;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->a;
	}

	/**
	 * @param $action
	 * @return $this
	 */
	public function setAction($action)
	{
		$this->a = $action;
		return $this;
	}

	/**
	 * Adds a property change without the isChanged() filter used by addPropertyChange().
	 *
	 * While a cyclic graph is being decoded, a nested change set is still empty at the moment
	 * its parent property is restored, so the filter would throw the property away.
	 *
	 * @internal used by the serializer
	 */
	public function restoreProperty(PropertyChangeSet $property): void
	{
		$this->p[$property->getName()] = $property;
	}

	/**
	 * @return PropertyChangeSet[]
	 */
	public function getChangedProperties()
	{
		return $this->p;
	}

}
