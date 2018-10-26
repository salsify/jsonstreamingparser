# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Added php-cs-fixer to CI analysis
- Added phpstan to CI analysis
- Added `PositionAwareInterface` to expose `setFilePosition()` method
- Added PHP DocBlock and typehints

### Changed
- Updated PHP min requirements to 7.1
- Applied php-cs-fixer fixes
- Moved `ParsingError` class to `Exception\ParsingException`
- Moved `ListenerInterface` class to `Listener\ListenerInterface`
- Updated Travis integration

### Deprecated
### Removed
### Fixed
### Security
