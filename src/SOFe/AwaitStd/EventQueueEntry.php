<?php

namespace SOFe\AwaitStd;

use Closure;
use pocketmine\event\Event;

/**
 * @template E of Event
 */
final class EventQueueEntry {
	/** @var Closure(E):bool */
	private Closure $filter;
	/** @var Closure(E):void */
	private Closure $resolve;
	private bool $consume;

	/**
	 * @param Closure(E):bool $filter
	 * @param Closure(E):void $resolve
	 */
	public function __construct(Closure $filter, Closure $resolve, bool $consume) {
		$this->filter = $filter;
		$this->resolve = $resolve;
		$this->consume = $consume;
	}

	/**
	 * @param E $event
	 */
	public function filter(Event $event) : bool {
		return ($this->filter)($event);
	}

	/**
	 * @param E $event
	 */
	public function resolve(Event $event) : void {
		($this->resolve)($event);
	}

	public function isConsume() : bool {
		return $this->consume;
	}
}
