<?php

use PHPUnit\Framework\TestCase;
use Scopubs\Validation\DataValidator;
use Scopubs\Validation\ValidationError;


class DataValidatorTest extends TestCase {

    public function test_validate_is_string() {
        $valid = 'Hi, I am actually a string';
        DataValidator::apply_single($valid, ['validate_is_string']);
        $this->assertTrue(is_string($valid));

        $invalid = 3141;
        $this->expectException(ValidationError::class);
        DataValidator::apply_single($invalid, ['validate_is_string']);
    }

    public function test_validate_is_array() {
        // Empty array should be fine
        $valid = [];
        DataValidator::apply_single($valid, ['validate_is_array']);
        $this->assertTrue(is_array($valid));

        // associative array should be fine.
        $valid = [];
        DataValidator::apply_single($valid, ['validate_is_array']);
        $this->assertTrue(is_array($valid));

        $invalid = 'array';
        $this->expectException(ValidationError::class);
        DataValidator::apply_single($invalid, ['validate_is_array']);
    }

    public function test_custom_validation_function() {
        // This is a custom validation function which checks if an integer is greater 10, realized as an
        // anonymous function
        $validate_is_greater_10 = function($key, $value) {
            if ( is_numeric($value) && $value > 10 ) {
                return $value;
            } else {
                throw new ValidationError($key, 'should be greater 10!');
            }
        };

        $valid = 12;
        DataValidator::apply_single($valid, [$validate_is_greater_10]);
        $this->assertTrue($valid > 10);

        $invalid = 8;
        $this->expectException(ValidationError::class);
        DataValidator::apply_single($invalid, [$validate_is_greater_10]);
    }

    public function test_apply_array_works() {
        $args = [
            'first'     => 'Hello I am string',
            'second'    => 'I am also a string, nice to meet you',
            'third'     => [3, 1, 4, 1]
        ];

        $validators = [
            'first'     => ['validate_is_string'],
            'second'    => ['validate_is_string'],
            'third'     => ['validate_is_array']
        ];

        $args = DataValidator::apply_array($args, $validators);
        $this->assertTrue(count($args) == 3);
    }
}