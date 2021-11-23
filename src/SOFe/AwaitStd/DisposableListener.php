<?php

namespace SOFe\AwaitStd;

use Closure;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\world\World;
use SplObjectStorage;

final class DisposableListener {
	private Plugin $plugin;
	/** @var SplObjectStorage<object, Closure[]> */
	private SplObjectStorage $finalizers;
	/** @var array<class-string, DisposableSpec> */
	private array $disposableSpecs = [];
	/** @var array<class-string<Event>, true> */
	private array $listenersSet = [];

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		$this->finalizers = new SplObjectStorage;

		$this->specifyDisposable(
			Player::class,
			PlayerQuitEvent::class,
			fn($event) => $event->getPlayer(),
			fn($player) => "player {$player->getName()} quit"
		);
		$this->specifyDisposable(World::class,
			WorldUnloadEvent::class,
			fn($event) => $event->getWorld(),
			fn($world) => "world {$world->getName()} unload"
		);
	}

	/**
	 * @template T
	 * @template E of Event
	 * @param class-string<T> $disposableClass
	 * @param class-string<E> $eventClass
	 * @param Closure(E):T $eventToDisposable
	 * @param Closure(T):string $eventDescription
	 */
	private function specifyDisposable(string $disposableClass, string $eventClass, Closure $eventToDisposable, Closure $eventDescription) : void {
		/** @phpstan-ignore-next-line */
		$spec = new DisposableSpec($eventClass, $eventToDisposable, $eventDescription);
		$this->disposableSpecs[$disposableClass] = $spec;
	}

	/**
	 * @var Closure():void $closure */
	public function addFinalizer(object $disposable, Closure $closure) : void {
		$spec = $this->getSpec($disposable);
		$this->setupListener($spec);
		if(!$this->finalizers->contains($disposable)) {
			$this->finalizers->attach($disposable, []);
		}
		$this->finalizers[$disposable][] = $closure;
	}

	private function triggerFinalizers(object $object) : void {
		if(isset($this->finalizers[$object])) {
			foreach($this->finalizers[$object] as $closure) {
				$closure();
			}
			$this->finalizers->detach($object);
		}
	}

	private function getSpec(object $disposable) : DisposableSpec {
		if(isset($this->disposableSpecs[get_class($disposable)])) {
			return $this->disposableSpecs[get_class($disposable)];
		}

		foreach($this->disposableSpecs as $class => $spec) {
			if($disposable instanceof $class) {
				return $spec;
			}
		}

		$class = get_class($disposable);
		throw new \InvalidArgumentException("$class is not a disposable");
	}

	private function setupListener(DisposableSpec $spec) : void {
		if(array_key_exists($spec->getEvent(), $this->listenersSet)) {
			return;
		}

		$this->listenersSet[$spec->getEvent()] = true;
		Server::getInstance()->getPluginManager()->registerEvent($spec->getEvent(), function($event) use($spec) : void {
			$disposable = $spec->eventToDisposable($event);
			$this->triggerFinalizers($disposable);
		}, EventPriority::MONITOR, $this->plugin);
	}

	public function getEventDescription(object $disposable) : string {
		$spec = $this->getSpec($disposable);
		return $spec->eventDescription($disposable);
	}
}
