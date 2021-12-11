<?php

namespace SOFe\AwaitStd;

use AssertionError;
use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;

final class AwaitStd {
	private Plugin $plugin;

	private EventAwaiter $eventAwaiter;

	public static function init(Plugin $plugin) : self {
		$self = new self;
		$self->plugin = $plugin;
		$self->eventAwaiter = new EventAwaiter($plugin);
		return $self;
	}

	private function __construct() {
	}

	/**
	 * @return Generator<mixed, mixed, mixed, void>
	 */
	public function sleep(int $ticks) : Generator {
		$callback = yield;
		$task = new ClosureTask(fn() => $callback());
		$this->plugin->getScheduler()->scheduleDelayedTask($task, $ticks);
		yield Await::ONCE;
	}

	/**
	 * @return Generator<mixed, mixed, mixed, PlayerChatEvent>
	 */
	public function consumeNextChat(Player $player, int $priority = EventPriority::NORMAL) : Generator {
		/** @var PlayerChatEvent $event */
		$event = yield $this->awaitEvent(
			PlayerChatEvent::class,
			fn($event) => $event->getPlayer() === $player,
			true,
			$priority,
			false,
			$player,
		);
		$event->cancel();
		return $event;
	}

	/**
	 * @return Generator<mixed, mixed, mixed, PlayerInteractEvent>
	 */
	public function consumeNextInteract(Player $player, int $priority = EventPriority::NORMAL) : Generator {
		/** @var PlayerInteractEvent $event */
		$event = yield $this->awaitEvent(
			PlayerInteractEvent::class,
			fn($event) => $event->getPlayer() === $player,
			true,
			$priority,
			false,
			$player,
		);
		$event->cancel();
		return $event;
	}

	/**
	 * @template T
	 * @template U
	 * @param Generator<mixed, mixed, mixed, T> $promise
	 * @param U $onTimeout
	 * @return Generator<mixed, mixed, mixed, T|U>
	 */
	public function timeout(Generator $promise, int $ticks, $onTimeout = null) : Generator {
		$sleep = $this->sleep($ticks);
		[$which, $ret] = yield from Await::race([$sleep, $promise]);
		return match($which) {
			0 => $onTimeout,
			1 => $ret,
			default => throw new AssertionError("unreachable"),
		};
	}

	/**
	 * @template E of Event
	 * @param class-string<E> $event
	 * @param Closure(E):bool $eventFilter
	 * @return Generator<mixed, mixed, mixed, E>
	 */
	public function awaitEvent(string $event, Closure $eventFilter, bool $consume, int $priority, bool $handleCancelled, object ...$disposables) : Generator {
		return $this->eventAwaiter->await($event, $eventFilter, $consume, $priority, $handleCancelled, $disposables);
	}
}
