<?php

namespace SOFe\AwaitStd;

use Closure;
use Generator;
use pocketmine\player\Player;
use pocketmine\event;
use pocketmine\event\EventPriority;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;

final class AwaitStd {
	/** @var Plugin $plugin */
	private $plugin;

	/** @var AwaitExecutor[] $listeners */
	private $listeners;

	/** @var QuitListener */
	private $quitListener;

	public static function init(Plugin $plugin) : self {
		$self = new self;
		$self->plugin = $plugin;
		$self->quitListener = new QuitListener($self);
		return $self;
	}

	private function __construct() {
	}

	public function sleep(int $ticks) : Generator {
		$callback = yield Await::ONCE;
		$task = new ClosureTask(static function() use($callback) : void {
			$callback();
		});
		$this->plugin->getScheduler()->scheduleDelayedTask($task, $ticks);
		yield Await::ONCE;
	}

	/**
	 * Waits until the player chats and returns the event.
	 *
	 * @return Generator<mixed, mixed, mixed, event\player\PlayerChatEvent>
	 */
	public function nextChat(Player $player, int $priority = EventPriority::NORMAL, bool $handleCancelled = false) : Generator {
		$event = yield $this->awaitEvent($player, event\player\PlayerChatEvent::class,
			$priority, $handleCancelled, self::toPlayer());
		return $event;
	}

	/**
	 * Waits until the player chats, cancels the chat and returns the message.
	 *
	 * @return Generator<mixed, mixed, mixed, string>
	 */
	public function consumeNextChat(Player $player, int $priority = EventPriority::NORMAL, bool $handleCancelled = false) : Generator {
		$event = yield $this->nextChat($player);
		$event->setCancelled();
		return $event->getMessage();
	}

	/**
	 * Waits until the player interacts with a block and returns the event.
	 *
	 * @return Generator<mixed, mixed, mixed, event\player\PlayerInteractEvent>
	 */
	public function nextInteract(Player $player, int $priority = EventPriority::NORMAL, bool $handleCancelled = false) : Generator {
		return $this->awaitEvent($player, event\player\PlayerInteractEvent::class,
			$priority, $handleCancelled, self::toPlayer());
	}

	/**
	 * Waits until the player attacks an entity and returns the event.
	 *
	 * @return Generator<mixed, mixed, mixed, event\entity\EntityDamageByEntityEvent>
	 */
	public function nextAttack(Player $player, int $priority = EventPriority::NORMAL, bool $handleCancelled = false) : Generator {
		return $this->awaitEvent($player, event\entity\EntityDamageByEntityEvent::class,
			$priority, $handleCancelled, static function(event\entity\EntityDamageByEntityEvent $event) : ?Player {
				$entity = $event->getDamager();
				if($entity instanceof Player) {
					return $entity;
				} else {
					return null;
				}
			});
	}

	/**
	 * The generic version of `nextChat`, `nextAttack`, etc.
	 *
	 * @param Player $player the player to watch
	 * @param string $event the event name
	 * @param int $priority
	 * @param bool $handleCancelled
	 * @param Closure $toPlayer a closure that resolves an event to the relevant player, or null;
	 * caller to this function must ensure that this closure always returns an online player
	 * @phpstan-param Closure(event\Event) : Player|null $toPlayer
	 */
	public function awaitEvent(Player $player, string $event, int $priority, bool $handleCancelled, Closure $toPlayer) : Generator {
		$key = "$event:$priority:$handleCancelled";
		if(!isset($this->listeners[$key])) {
			$this->listeners[$key] = $this->registerListener($event, $priority, $handleCancelled, $toPlayer);
		}

		$onSuccess = yield;
		$onError = yield Await::REJECT;
		$onQuit = function(event\player\PlayerQuitEvent $quitEvent) use($event, $onError) : void {
			$onError(new QuitException($this->plugin, $event, $quitEvent));
		};
		$this->quitListener->add($player, $onQuit);
		$this->listeners[$key]->queuePlayer($player, function($event) use($player, $onSuccess, $onQuit) : void {
			// If it is no longer in QuitListener,
			// this event couldn't be fired.
			$this->quitListener->remove($player, $onQuit);
			$onSuccess($event);
		});
		return yield Await::ONCE;
	}

	private static function toPlayer() : Closure {
		return static function(event\player\PlayerEvent $event) : Player {
			return $event->getPlayer();
		};
	}

	private function registerListener(string $event, int $priority, bool $handleCancelled, Closure $toPlayer) : AwaitExecutor {
		$listener = new AwaitExecutor($toPlayer);
		$this->plugin->getServer()->getPluginManager()->registerEvent(
			$event,
			Closure::fromCallable([$listener, "execute"]),
			$priority,
			$this->plugin,
			$handleCancelled
		);
		return $listener;
	}

	/**
	 * @internal This method is semver-exempt and only for internal use.
	 *
	 * @return AwaitExecutor[]
	 */
	public function getListeners() : array {
		return $this->listeners;
	}
}
