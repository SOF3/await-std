<?php

namespace SOFe\AwaitStd;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\plugin\Plugin;
use pocketmine\Server;

final class EventAwaiter {
	private Plugin $plugin;
	private int $nextHandlerId = 0;
	private DisposableListener $disposableListener;
	/** @var array<string, array<int, EventQueueEntry<Event>>> */
	private array $queues = [];

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		$this->disposableListener = new DisposableListener($plugin);
	}

	/**
	 * @template E of Event
	 * @param class-string<E> $eventClass
	 * @param Closure(E):bool $eventFilter
	 * @param list<object> $disposables
	 * @return Generator<mixed, mixed, mixed, E>
	 */
	public function await(string $eventClass, Closure $eventFilter, bool $consume, int $priority, bool $handleCancelled, array $disposables) : Generator {
		$id = $this->nextHandlerId++;

		$queueKey = $this->ensureListener($eventClass, $priority, $handleCancelled);
		$this->queues[$queueKey][$id] = new EventQueueEntry($eventFilter, yield, $consume);

		$fail = yield Await::REJECT;
		foreach($disposables as $disposable) {
			$this->disposableListener->addFinalizer($disposable, function() use($disposable, $eventClass, $id, $fail) : void {
				if(isset($this->queues[$eventClass][$id])) {
					unset($this->queues[$eventClass][$id]);
					$fail(new DisposeException($this->plugin, $this->disposableListener->getEventDescription($disposable), $disposable));
				}
			});
		}

		return yield Await::ONCE;
	}

	/**
	 * @param class-string<Event> $eventClass
	 */
	private function ensureListener(string $eventClass, int $priority, bool $handleCancelled) : string {
		$queueKey = "$eventClass:$priority:$handleCancelled";

		if(array_key_exists($queueKey, $this->queues)) {
			return $queueKey;
		}

		$this->queues[$queueKey] = [];
		Server::getInstance()->getPluginManager()->registerEvent($eventClass, function(Event $event) use($queueKey) : void {
			foreach($this->queues[$queueKey] as $handlerId => $entry) {
				if($entry->filter($event)) {
					if($entry->isConsume()) {
						unset($this->queues[$queueKey][$handlerId]);
					}
					$entry->resolve($event);
				}
			}
		}, $priority, $this->plugin, $handleCancelled);

		return $queueKey;
	}
}
