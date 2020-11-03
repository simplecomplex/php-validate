## Validate ##
<small>composer namespace: simplecomplex/**validate**</small>

Validate almost any kind of PHP data structure.

### Simple shallow validation ###

Test a variable against one of ```Validator```s 70+ rule methods
- is it of a particular type or class?
- is it emptyish?
- does it's value match a pattern?
- a container (array|object|traversable)?
- a numerically indexed array?


### Multidimensional validation ###

// tableElements|listItems must require container.
// Ideally iterable or loopable, but that would effectively
// make tableElements|listItems incompatible with primitive class
// those members are public, but doesn't implement \Traversable.

// The 'empty' rule is not compatible with tableElements|listItems.

#### Test against a set of rules ####

A validation ruleset is a list of rules (```Validator``` methods)  
– in effect you can combine all the above questions into a single question.

You can also declare that "well if it _isn't_ an [object|string|whatever], then null|false|0 will do just fine".

#### Validate objects/arrays recursively ####

Validation rulesets can be nested.  
```$validator->challenge($subject, $ruleSet)``` traverses the subject according to the ruleset (+ descendant rulesets),  
and checks that the subject at any level/position accords with the rules at that level/position.

#### Record reason(s) for validation failure ####

```$validator->challenge($subject, $ruleSet, RecursiveValidatorInterface::RECORD | RecursiveValidatorInterface::CONTINUE)```
memorizes all failures along the way.

#### Example of use ####

[SimpleComplex Http](https://github.com/simplecomplex/php-http) uses validation rulesets to check the body of HTTP (REST) responses.  
Rule sets are defined in JSON (readable and portable), and cached as PHP ```ValidationRuleSet```s (using [SimpleComplex Cache](https://github.com/simplecomplex/php-cache)).  

### Non-rule flags ###

Multi-dimensional object/array rulesets support a little more than proper validation rules.

- **optional**: the bucket is allowed to be missing
- **nullable**: the value is allowed to be null
- **alternativeEnum**: list of alternative scalar values that should make a subject pass,  
even though another rule is violated
- **tableElements**: the subject (an object|array) must contain these keys,  
and for every key there's a sub ruleset
- **listItems**: all the buckets of the subject (an object|array) must accord with the same sub ruleset 

```tableElements``` and ```listItems``` are allowed to co-exist.  
Relevant if you have a – kind of malformed – object that may contain list items as well as non-list elements.  
Data originating from XML may easily have that structure (XML stinks ;-)

#### tableElements ####

(obj|arr) **rulesByElements**: table of rulesets by keys; for every key there's a ruleset.

Modifiers:
- (bool) **exclusive**: the object|array must only contain the keys specified by ```rulesByElements```
- (arr) **whitelist**: the object|array must – apart from ```rulesByElements``` – only contain these keys
- (arr) **blacklist**: the object|array must – apart from ```rulesByElements``` – not contain these keys

If neither rulesByElements nor any modifier then tableElements in itself will be used as rulesByElements.


#### listItems ####

(obj|arr) **itemRules**: a ruleset that every list item must comply to.

Modifiers:
- (int) **minOccur**: there must be at least that many list items
- (int) **maxOccur**: guess what

If neither itemRules nor any modifier then listItems in itself will be used as itemRules.

### Example ###

```php
// We wanna see some bicycles...
class Bicycle
{
    public $wheels = 0;
    public $saddle;
    public $sound = '';
    public $accessories = [];
    public $various;

    public function __construct($wheels, $saddle, $sound, $accessories, $various)
    {
        $this->wheels = $wheels;
        $this->saddle = $saddle;
        $this->sound = $sound;
        $this->accessories = $accessories;
        $this->various = $various;
    }
}

// Not just any kind of bicycles. They must comply to this ruleset:
$rule_set = [
    'class' => [
        'Bicycle'
    ],
    'tableElements' => [
        //'exclusive' => true,
        //'whitelist' => ['unspecified_1'],
        //'blacklist' => ['saddle', 'accessories'],
        'rulesByElements' => [
            'wheels' => [
                'integer' => true,
                'range' => [
                    1,
                    3,
                ]
            ],
            'saddle' => [
                // Rule name by value instead of key;
                // ugly but still supported.
                'integer',
                'alternativeEnum' => [
                    true,
                ]
            ],
            'sound' => [
                'string' => true,
                'enum' => [
                    'silent',
                    'swooshy',
                    'clattering',
                ]
            ],
            'accessories' => [
                'array',
                'tableElements' => [
                    'rulesByElements' => [
                        'luggageCarrier' => [
                            'boolean' => true,
                            'optional' => true,
                        ],
                        'lights' => [
                            // Rule name by value instead of key.
                            'numeric',
                            'optional'
                        ]
                    ]
                ]
            ],
            'various' => [
                'optional' => true,
                'array' => true,
                // allowNull and alternativeEnum with null
                // are two ways of allowing null.
                'allowNull' => true,
                'alternativeEnum' => [
                    null,
                ],
                'listItems' => [
                    'maxOccur' => 5,
                    'itemRules' => [
                        'string' => true,
                        'alternativeEnum' => [
                            true,
                            false,
                        ]
                    ]
                ]
            ]
        ]
    ]
];
```

#### Requirements ####

- PHP >=7.2 (64-bit)
- PHP extensions ctype, mbstring
