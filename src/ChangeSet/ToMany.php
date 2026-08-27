<?php

namespace ADT\DoctrineLoggable\ChangeSet;

class ToMany extends PropertyChangeSet
{

	/** @var Id[] removed identifications */
	protected $r = [];

	/** @var Id[] added identifications */
	protected $a = [];

	/** @var ChangeSet[] array of change sets of changed entities present in new collection */
	protected $ch = [];

	/**
	 * @return bool
	 */
	public function isChanged()
	{
		return count($this->r) > 0 ||
			count($this->a) > 0 ||
			count($this->ch) > 0;
	}

	public function addChangeSet(?ChangeSet $changeSet = NULL)
	{
		if ($changeSet !== NULL && $changeSet->isChanged()) {
			if (($k = array_search($changeSet, $this->ch)) === FALSE) {
				$this->ch[] = $changeSet;
			}
		}
	}

	/**
	 * Mark identification as added to collection
	 *
	 * @param Id $identification
	 */
	public function addAdded(Id $identification)
	{
		if (($k = array_search($identification, $this->r)) !== FALSE) {
			unset($this->r[$k]);
		} elseif (array_search($identification, $this->a) === FALSE) {
			$this->a[] = $identification;
		}
	}

	/**
	 * Mark identification as removed from collection
	 *
	 * @param Id $identification
	 */
	public function addRemoved(Id $identification)
	{
		if (($k = array_search($identification, $this->a)) !== FALSE) {
			unset($this->a[$k]);
		} elseif (array_search($identification, $this->r) === FALSE) {
			$this->r[] = $identification;
		}
	}

	/**
	 * @param ToMany $toMany
	 */
	public function merge(PropertyChangeSet $toMany)
	{
		foreach ($toMany->getAdded() as $identification) {
			$this->addAdded($identification);
		}
		foreach ($toMany->getRemoved() as $identification) {
			$this->addRemoved($identification);
		}
		foreach ($this->ch as $k => $nodeEntity) {
			if (!$nodeEntity->isChanged()) {
			    unset($this->ch[$k]);
			}
		}
		foreach ($toMany->getChangeSets() as $nodeEntity) {
			$this->addChangeSet($nodeEntity);
		}
	}

	/**
	 * get list of identifications removed from collection
	 *
	 * @return Id[]
	 */
	public function getRemoved()
	{
		return $this->r;
	}

	/**
	 * get list of identifications added to collection
	 *
	 * @return Id[]
	 */
	public function getAdded()
	{
		return $this->a;
	}

	/**
	 * @return ChangeSet[]
	 */
	public function getChangeSets()
	{
		return $this->ch;
	}

	public function getType()
	{
		return self::TYPE_TO_MANY;
	}

	/**
	 * Assigns the whole collection change without the lookups done by addAdded(), addRemoved()
	 * and addChangeSet(). Those compare objects by value, which would fatal on a cyclic graph.
	 *
	 * @param Id[] $added
	 * @param Id[] $removed
	 * @param ChangeSet[] $changeSets
	 * @internal used by the serializer
	 */
	public function restore(array $added, array $removed, array $changeSets): void
	{
		$this->a = array_values($added);
		$this->r = array_values($removed);
		$this->ch = array_values($changeSets);
	}
}
