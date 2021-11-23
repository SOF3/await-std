<?php

namespace SOFe\AwaitStd;

use Exception;
use pocketmine\plugin\Plugin;

/**
 * Thrown when a Disposable is disposed when awaiting on it.
 *
 * For example, if `consumeNextChat` is called on a player,
 * but the player quits without sending a message,
 * this exception is thrown in the `consumeNextChat` yield.
 *
 * Developers should ALWAYS handle this exception.
 * Usually, this is handled by returning in thr `catch` block.
 */
final class DisposeException extends Exception {
	private object $disposable;

	public function __construct(Plugin $plugin, string $eventDescription, object $disposable) {
		parent::__construct("Plugin {$plugin->getName()} did not handle the case of $eventDescription when awaiting its events");
		$this->disposable = $disposable;
	}

	/**
	 * @return object
	 */
	public function getDisposable() {
		return $this->disposable;
	}
}
