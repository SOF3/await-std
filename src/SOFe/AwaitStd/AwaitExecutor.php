<?php

namespace SOFe\AwaitStd;

use Closure;
use function spl_object_hash;
use pocketmine\player\Player;
use pocketmine\event\Event;

final class AwaitExecutor {
	/**
	 * @var Closure $closure
	 * @phpstan-var Closure(Event) : Player
	 */
	private $toPlayer;

	/** @var Closure[][] $queues */
	private $queues = [];

	// phpstan's covariant closure bug saves me from using templates lol
	/**
	 * @param Closure $closure
	 * @phpstan-param Closure(Event) : Player
	 */
	public function __construct(Closure $toPlayer) {
		$this->toPlayer = $toPlayer;
	}

	public function queuePlayer(Player $player, Closure $closure) : void {
		$hash = spl_object_hash($player);
		if(!isset($this->queues[$hash])) {
			$this->queues[$hash] = [];
		}
		$this->queues[$hash][] = $closure;
	}

	public function removePlayer(Player $player) : void {
		$hash = spl_object_hash($player);
		if(isset($this->queues[$hash])) {
			unset($this->queues[$hash]);
		}
	}

	public function execute(Event $event) : void {
		$closure = $this->toPlayer;
		$player = $closure($event);
		if($player === null) {
			return;
		}
		$hash = spl_object_hash($player);
		if(!isset($this->queues[$hash])) {
			return;
		}

		$queue = $this->queues[$hash];
		$this->queues[$hash] = [];
		foreach($queue as $closure) {
			$closure($event);
		}
	}
}
