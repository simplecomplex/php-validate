<?php

namespace SimpleComplex\Tests\Validate;

class BicycleRuleSets
{

    public static function original()
    {
        return [
            'class' => [
                'Bicycle'
            ],
            'tableElements' => [
                //'exclusive' => true,
                //'whitelist' => ['unspecified_1'],
                //'blacklist' => ['saddle', 'accessories'],
                'rulesByElements' => [
                    'wheels' => [
                        'integer',
                        'range' => [
                            1,
                            3
                        ]
                    ],
                    'saddle' => [
                        'integer',
                        'alternativeEnum' => [
                            // Wrongly defined as nested; confused by enum which
                            // formally requires to be nested.
                            // But ValidationRuleSet fixes this, as it does with
                            // the opposite for (un-nested) enum.
                            [
                                true,
                            ]
                        ]
                    ],
                    'sound' => [
                        'string' => true,
                        'enum' => [
                            // Counterintuitive nesting, but formally correct because
                            // the allowed values array is second arg to the enum()
                            // method.
                            // ValidationRuleSet fixes un-nested instance.
                            [
                                'silent',
                                'swooshy',
                                'clattering',
                            ]
                        ]
                    ],
                    'accessories' => [
                        'array',
                        'tableElements' => [
                            'rulesByElements' => [
                                'luggageCarrier' => [
                                    'boolean',
                                    'optional'
                                ],
                                'lights' => [
                                    'numeric',
                                    'optional'
                                ]
                            ]
                        ]
                    ],
                    'various' => [
                        'array',
                        'optional',
                        'alternativeEnum' => [
                            null,
                        ],
                        'listItems' => [
                            'maxOccur' => 5,
                            'itemRules' => [
                                'string',
                                'alternativeEnum' => [
                                    // Correctly defined as un-nested array.
                                    true,
                                    false,
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
