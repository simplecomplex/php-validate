# Changelog

All notable changes to **simplecomplex/validate** will be documented in this file,
using the [Keep a CHANGELOG](https://keepachangelog.com/) principles.


## [Unreleased]

### Added

### Changed
* Deprecated ValidationRuleSet::ruleMethodsAvailable() removed.
* Changelog in standard keepachangelog format; previous was idiosyncratic.

### Fixed
* Ruleset without type-checking rule must default to 'container' if has
  tableElements or listItems; otherwise to 'string'.


## [2.5.1] - 2019-07-08

### Changed
* Rule method unicodeMaxLength() must pass empty string.


## [2.5] - 2019-06-10

### Added
* Clarify which rule methods promise type safety; and thus continually don't
  accept stringable object as subject.
* ValidationFailureException support more fine-grained retrieval of failures.

### Fixed
* Non-stringable object as subject for (essentially string) rule method
  must test false; not produce error.
  But keep allowing stringable object for such methods.


## [2.4.1] - 2019-01-25

### Changed
The 'bit' rule must allow string 0|1


## [2.4] - 2019-01-24

### Added
* New rule camelName().
* ValidationRuleSet must have (same) recursion limit as well.
* New non-provider rule alternativeRuleSet, supporting a fallback rule set
  when main rules - and alternativeEnum - fails.


## [2.3] - 2019-01-21

### Added
* New dateISO8601() ultimate catch-all date/datetime ISO-8601.
* RuleProviderInterface new method getParameterMethods().

### Changed
* ISO-8601 rules with subSeconds parameter now default to class constant
  for maximum sub seconds, and sub-zero value of that parameter no longer
  spells exception.
* challengeRecording() allow for top level key-path name,
  and record arguments passed on validation failure.
  
### Fixed
* Ensure that rule set values accord with whether rule methods accept
  (or even require) more arguments than subject self.


## [2.2] - 2019-01-08

### Added
* RuleProviderInterface new method getTypeMethods().
* New non-rule method 'allowNull', for nested (object|array bucket) elements.
* New rule method 'bit'.
* Type-checking rule methods.

### Changed
* ValidationRuleSet constructor's second argument is (preferably) now
  a rule provider or a rule provider info object; not rule-methods-available.
* ValidationRuleSet::ruleMethodsAvailable() is now deprecated.

### Fixed
* No rules methods but 'empty' and 'null' must allow (pass) null.


## [2.1] - 2018-08-04

### Added
* timeISO8601().


## [2.0] - 2018-06-03

### Added
* Datetime ISO-8601 rules with timezone vs. UTC (Z), and Datetime ISO-8601 local

### Changed
* empty() shan't check for ArrayObject|ArrayIterator upon checkíng for Countable
  because they are both Countable.
* Date/Datetime rules have to be named using strict acronym camelCasing because
  native \DateTime does (setISODate());
  renamed dateISO8601Local and dateTimeISO8601.
### Fixed
* ISO-8601 datetime rules allowing sub seconds must default to max 6 digits,
  otherwise native \DateTime will fail.


## [1.0] - 2017-09-24

### Added
* Validation rule set class.
* All type et al checks moved from internal challenge method
  to ValidationRuleSet (constructor).
* ValidateByRules::challenge() now converts non-ValidationRuleSet
  arg ruleSet to ValidationRuleSet.
* Implemented tableElements subsidary rules exclusive, whitelist, blacklist.
* 'loopable' is 'iterable' that allows non-Traversable object
  except if (also) ArrayAccess.
* Validate recording do (at least) log failing subject's type.
* challengeRecording: record invalid value when scalar.

### Changed
* Non-provider rule listItems replaces listItemPrototype.
* Abandoned attempts to make listItems support un-nested singleton item;
  that awful XML pattern. Users must instead use two (similar) rule sets.
* Renamed class ValidateAgainstRuleSet; from ValidateByRules.
* ValidationRuleSet constructor shan't have any required parameters,
  to allow Utils::cast()'ing to it.
* Interfaces moved to sub dir/namespace.

### Fixed
* Fixed that optional is to be ignored for root ruleSet; not the opposite.
* Fixed that recording validation must record missing non-optional bucket.
* Fixed that listItems subsidary rules minOccur/maxOccur shan't stop
  validation on failure when recording failures.
* Fixed that empty() didn't handle Traversable and ArrayAccess correctly at all.
* ValidateAgainstRuleSet::getInstance() must refer and return by rule provider
  class name, because otherwise there's no guarantee that requested and returned
  instance are effectively identical.
* Validate is not allowed to have state, because challenge() calls otherwise
  could leak state to eachother.
* ValidateAgainstRuleSet is not allowed to have state (except when recording),
  because that would void ::getInstance() warranty.
* Validate->numeric() didn't accept leading hyphen, and (not really a bug)
  simple emptyness checks relied on ctype_digit().
* Validate constructor do not have to populate non-rule methods instance var.
* Validate getInstance() must be class aware.
* Don't cast filter_var() to boolean when filtering (not validating); doh.


## [0.9] - 2017-07-12

### Added
* First release.