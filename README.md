## Validate ##
<small>composer namespace: simplecomplex/**validate**</small>

Validate almost any kind of PHP data structure.

#### Simple shallow validation ####

Test a variable against one of ```Validate```s 60+ rule methods
- is it of a particular type or class?
- does it's value match a pattern?
- is it emptyish?
- a container (array|object|traversable)?
- a numerically indexed array?

#### Test against a set of rules ####

A validation rule set is a list of rules (```Validate``` methods)  
– in effect you can combine all the above questions into a single questions.

You can also declare that "well if it _isn't_ an [object|string|whatever], then null|false|0 will do just fine".

#### Validate objects/arrays recursively ####

Validation rule sets can be nested – indefinitely.  
```$validate->challenge($subject, $ruleSet)``` traverses the subject according to the rule set (+ descendant rule sets),  
and checks that the subject at any level/position accords with the rules at that level/position.

#### Record reason(s) for validation failure ####

```$validate->challengeRecording($subject, $ruleSet)``` memorizes all wrongdoings along the way,  
and returns a list of violations (including where detected, and what was wrong).

#### Example of use ####

[SimpleComplex Http](https://github.com/simplecomplex/php-http) uses validation rule sets to check the body of HTTP (REST) responses.  
Rule sets are defined in JSON (readable and portable), and cached as PHP ```ValidationRuleSet```s (using [SimpleComplex Cache](https://github.com/simplecomplex/php-cache)).  

### Non-rule flags ###

Multi-dimensional object/array rule sets support a little more than proper validation rules.

- **optional**: the bucket is allowed to be missing
- **allowNull**: the value is allowed to be null
- **alternativeEnum**: list of alternative (scalar|null) values that should make a subject pass,  
even though another rule is violated
- **tableElements**: the subject (an object|array) must contain these keys,  
and for every key there's a sub rule set
- **listItems**: the subject (an object|array) must be a list consisting of a number of buckets,  
which all have the same type/pattern/structure 

```tableElements``` and ```listItems``` are allowed to co-exist.  
Relevant if you have a – kind of malformed – object that may contain list items as well as non-list elements.  
Data originating from XML may easily have that structure (XML stinks ;-)

#### tableElements ####

(obj|arr) **rulesByElements**: table of rule sets by keys; for every key there's a rule set.

Modifiers:
- (bool) **exclusive**: the object|array must only contain the keys specified by ```rulesByElements```
- (arr) **whitelist**: the object|array must – apart from ```rulesByElements``` – only contain these keys
- (arr) **blacklist**: the object|array must – apart from ```rulesByElements``` – not contain these keys

If neither rulesByElements nor any modifier then tableElements in itself will be used as rulesByElements.


#### listItems ####

(obj|arr) **itemRules**: a rule set that every list item must comply to.

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

// Not just any kind of bicycles. They must comply to this rule set:
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

- PHP >=7.2
- PHP extensions ctype, mbstring
