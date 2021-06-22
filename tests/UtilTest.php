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

    public function test_comparing_date_strings() {
        $first = '2020-01-01';
        $second = '2010-01-01';

        $this->assertTrue($first > $second);

        $first_datetime = new DateTime($first);
        $second_datetime = new DateTime($second);
        $this->assertTrue($first_datetime > $second_datetime);
    }
}