<?php

use Scopubs\Util;
use PHPUnit\Framework\TestCase;


class UtilTest extends TestCase{

    public function test_optional_array_mapping_works() {
        $source_array = [
            'name'          => 'Jonas',
            'fruit'         => 'Melon',
            'veggies'       => 'Broccoli',
            'phone'         => 'as if'
        ];

        $mapping = [
            'name'          => 'name',
            'fruit'         => 'likes/fruit',
            'veggies'       => 'likes/vegetable',
            'phone'         => 'meta/secret/phone'
        ];

        $expected_array = [
            'name'          => 'Jonas',
            'likes' => [
                'fruit'     => 'Melon',
                'vegetable' => 'Broccoli'
            ],
            'meta' => [
                'secret' => [
                    'phone' => 'as if'
                ]
            ]
        ];

        $target_array = Util::array_mapping($source_array, $mapping);
        $this->assertEquals($expected_array, $target_array);
    }
}