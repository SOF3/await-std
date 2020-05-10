<?php

namespace SOFe\AwaitStd;

use Exception;
use pocketmine\plugin\Plugin;
use pocketmine\event\player\PlayerQuitEvent;

// This is NOT a RuntimeException.
// Not handling QuitException is always a potential bug.

// FAQ: Why is this exception thrown instead of just not calling onSuccess?
// Answer: If onSuccess is not called, the coroutine is dropped and no longer referenced.
// If the coroutine holds an open resource, this would lead to resource leak.
// For example:
// ```php
// Await::f2c(function() {
//   $file = fopen("file.txt", "a");
//   fwrite($file, $this->std->consumeNextMessage());
//   fclose($file);
// });
// ```
//
// $file will be leaked if `consumeNextMessage` never returns.
// Plugins should do this instead:
//
// ```php
// Await::f2c(function() {
//   $file = fopen("file.txt", "a");
//   try {
//     fwrite($file, $this->std->consumeNextMessage());
//   } catch(QuitException $e) {
//     // stop running if player quits
//     return;
//   } finally {
//     fclose($file);
//   }
// });
// ```

/**
 * This exception is thrown when a player quits but a coroutine is waiting for an event.
 *
 * Plugins should always handle this exception.
 * If exception fallthrough is intended, developers are encouraged to make it explicit.
 */
final class QuitException extends Exception {
	private $event;

	public function __construct(Plugin $plugin, string $waiting, PlayerQuitEvent $event) {
		parent::__construct("Plugin {$plugin->getName()} forgot to handle QuitException when waiting for $waiting");
		$this->event = $event;
	}
}
