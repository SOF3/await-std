<?php

namespace SOFe\AwaitStd;

use SplObjectStorage;
use pocketmine\event\Listener;

final class QuitListener implements Listener {
	/** @var AwaitStd $std */
	private $std;

	/** @var SplObjectStorage<Closure>[] */
	private $calls = [];

	public function onQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();

		foreach($this->std->getListeners() as $listener) {
			$listener->removePlayer($player);
		}

		$hash = spl_object_hash($player);
		if(isset($this->calls[$hash])) {
			foreach($this->calls[$hash] as $closure) {
				$closure();
			}
			unset($this->calls[$hash]);
		}
	}

	public function add(Player $player, Closure $closure) : void {
		$hash = spl_object_hash($player);
		if(!isset($this->calls[$hash])) {
			$this->calls[$hash] = new SplObjectStorage;
		}
		$this->calls[$hash]->attach($closure);
	}

	public function remove(Player $player, Closure $closure) : void {
		$hash = spl_object_hash($player);
		$this->calls[$hash]->detach($closure);
	}
}
