<?php

namespace SOFe\AwaitStd;

use Closure;
use pocketmine\event\Event;

final class DisposableSpec {
	/** @var class-string<Event> */
	private string $event;
	/** @var Closure(Event):object */
	private Closure $eventToDisposable;
	/** @var Closure(object):string */
	private Closure $eventDescription;

	/**
	 * @param class-string<Event> $event
	 * @param Closure(Event):object $eventToDisposable
	 * @param Closure(object):string $eventDescription
	 */
	public function __construct(string $event, Closure $eventToDisposable, Closure $eventDescription) {
		$this->event = $event;
		$this->eventToDisposable = $eventToDisposable;
		$this->eventDescription = $eventDescription;
	}

	/**
	 * @return class-string<Event>
	 */
	public function getEvent() : string {
		return $this->event;
	}

	public function eventToDisposable(Event $event) : object {
		return ($this->eventToDisposable)($event);
	}

	public function eventDescription(object $disposable) : string {
		return ($this->eventDescription)($disposable);
	}
}
