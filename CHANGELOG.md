# Changelog

All notable changes to **simplecomplex/validate** will be documented in this file,
using the [Keep a CHANGELOG](https://keepachangelog.com/) principles.


## [Unreleased]

### Added
* Require PHP mbstring extension.
* Common ValidationException; catchable \Throwable interface implemented
  by all in-package exceptions.

### Changed
* Validator class renamed; from Validate. New deprecated class Validate, which
  extends Validator.
* The 'bit' rule must _not_ allow string 0|1. To allow strings use ruleset
  [bit:true,alternativeEnum['0','1']].
  
* ValidationFailureException no longer extends (SimpleComplex\Utils\Exception\)
  UserMessageException; extends \RuntimeException directly.
  
* Pseudo-rule nullable renamed; from allowNull. Backwards compatible; ruleset
  generator still supports allowNull.
  
* regex() no longers passes boolean subject, no matter what pattern provided.
* numeric() no longer passes stringed negative zero.

* tableElements, listItems combined is legal, but if tableElements pass then
  listItems won't be used/checked.

* Recursive validation (ValidateAgainstRuleSet) now iterates tableElements
  by subject buckets (instead of tableElements.rulesByElements), and then checks
  for missing non-optional keys of subject afterwards.  
  That alters the sequence of failure records, from (pretty) order given
  by tableElements to arbitrary order given by subject.
  
* Class ValidationRuleSet tableElements|listItems properties are now instances
  of TableElements|ListItems, and enum value is now an un-nested array.  
  Existing <v3.0 ValidationRuleSet instances, whether declared in code or cached
  as PHP serials, will hardly be compatible; whereas PHP array|stdClass rulesets
  and JSON-formatted rulesets should work fine.

* unicodePrintable() now checks if unicode.
* ISO-8601 date/time/datetime rules renamed; '8601' removed.

* Deprecated ValidationRuleSet::ruleMethodsAvailable() removed.
* Removed class RuleProviderInfo.
* Removed RuleProviderInterface|Validate::getNonRuleMethods().
* Removed RuleProviderInterface|Validate::getParameterMethods().
  
* Require PHP 64-bit >=7.2.
* Don't require simplecomplex/utils.
* Changelog in standard keepachangelog format; previous was idiosyncratic.

### Fixed
* enum/alternativeEnum no longer allows float; must be bool|int|string|null.
  That behaviour can be changed by overriding the enum rule.
* ValidateAgainstRuleSet must use the rule-providers enum() for enum 
  and alternativeEnum; not own custom method.
* Ruleset without type-checking rule will now default to a rule fitting
  the pattern rule; not simply default to string.


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
The 'bit' rule must allow string 0|1.


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
