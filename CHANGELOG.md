Changelog
===

## [0.2.0] - 2021-03-04
### Added
 - PMMP 4.0.0 support

### Changed
- The following API methods has had the `$ignoreCancelled` argument changed to `$handleCancelled` (has the opposite functionality)
  - `AwaitStd->nextChat`
  - `AwaitStd->consumeNextChat`
  - `AwaitStd->nextInteract`
  - `AwaitStd->nextAttack`
  - `AwaitStd->awaitEvent`

### Removed
 - `DummyListener` class
 - PMMP 3.0.0 support

## [0.1.0]
Initial version