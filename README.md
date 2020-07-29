## Validate ##
<small>composer namespace: simplecomplex/**validate**</small>

Validate almost any kind of PHP data structure.

### Simple shallow validation ###

Test a variable against one of the validator's 70+ rule methods
- is it of a particular type or class?
- is it emptyish?
- a container (array|object|traversable)?
- a numerically indexed array?
- does it's value match a pattern?

#### Type rules vs. pattern rules ####

**> [Type rules](src/RuleTraits/TypeRulesTrait.php) check can if the variable**:
- is of a type or class, or a pseudo type like 'digital' (stringed integer)
- is directly (===) comparable, or stringable
- is 'loopable' (array|stdClass|Traversable), or numerically indexed or keyed

Type rules are guaranteed to accept any kind of variable gracefully; never to err.

**> [Pattern rules](src/RuleTraits/PatternRulesUncheckedTrait.php) rules check secondary aspects like**:
- less/shorter than, more/longer than
- is unicode, ASCII or an ISO-8601 date
- enum: value exactly same as one allowed

Pattern rules exist in two shapes:
- _un-checked_: do not check type, will err on unexpected subject type; meant to be used after a type rule check
- _checked_: do check type, internally using one of the type checking rules

### Multidimensional validation ###

#### Check against a set of rules ####

A validation ruleset is a list of rules (validator methods)  
– in effect you can combine all the above questions into a single question.

You can also declare that "well if it _isn't_ an [object|string|whatever], then null|false|0 will do just fine".

#### Validate objects/arrays recursively ####

Validation rulesets can be nested.  
```$validator->challenge($subject, $ruleSet)``` traverses the subject according to the ruleset (+ descendant rulesets),  
and checks that the subject at any level/position accords with the rules at that level/position.

#### Record reason(s) for validation failure ####

```$validator->challenge($subject, $ruleSet, ChallengerInterface::RECORD | ChallengerInterface::CONTINUE)```
memorizes all failures along the way.

**> [More about ruleset validation](README-RULESET.md)**







### Requirements ###

- PHP >=7.2 (64-bit)
- PHP extensions ctype, mbstring

### MIT licensed ###

[License and copyright](https://github.com/simplecomplex/php-validate/blob/master/LICENSE).
[Explained](https://tldrlegal.com/license/mit-license).
