<?php


namespace Scopubs\Validation;


/**
 * Class DataValidator
 *
 * This is a static class which wraps the functionality to validate data. It offers various methods to perform certain
 * data validation and sanitation operations. The most useful functionality however is to conveniently apply
 * piplelines of multiple validation operations on whole associative arrays:
 *
 * Given an associative arrays with string key names and any kind of values, the method "apply_array" can be used to
 * specify which validation operations are supposed to be executed on each element:
 *
 *    // The array to be validated
 *    $args = [
 *          'name'          => 'John Doe',
 *          'favorites'     => ['banana', 'apple'],
 *          'unsafe'        => 'Potentially malicious content'
 *    ];
 *
 *    // An array specifying validation operations for every element
 *    $validators = [
 *          'name'          => ['validate_is_string', 'validate_not_empty', 'sanitize_special_characters'],
 *          'favorites'     => ['validate_is_array', 'validate_not_empty'],
 *          'unsafe'        => ['validate_is_string', 'sanitize_all_html']
 *    ]
 *
 *    $validated_args = DataValidator::apply_array($args, $validators);
 *
 * @package Scopubs\Validation
 */
class DataValidator {

    // -- Public methods

    /**
     * Applies pipelines of validation operations specified by $validators to the elements of the associative $arr
     *
     * @param array $arr An associative array, where keys are string identifiers and any values to be validated
     * @param array $validators An associative array with the SAME string keys as $arr. The values are lists of
     *      either callables or strings. If it is a callable, it will be directly used as a validation function. If
     *      it is strings, the strings are interpreted as method names to this very class. These methods are then
     *      applied to validate the data of the corresponding key.
     *
     * @throws ValidationError If one of the validation checks fails
     *
     * @return array
     */
    public static function apply_array(array $arr, array $validators) {
        // "get_called_class" returns the string class name of the class from whose static method it was called. It is
        // actually pretty cool it even does it with late binding and would thus also always return the child class
        // even if the implementation is in one of the parent classes...
        // We need the class name because we want to dynamically call other static methods of this class.
        $class_name = get_called_class();

        $validated_arr = [];

        // The idea is that "$arr" is an associative array, whose values need some validation. The kind of validation
        // and or sanitation methods which should be applied to it are specified in the "$validators" array. This is
        // also an assoc. array with the SAME keys. Here each value is a list of strings, where each string is the
        // name of one of the validation/sanitation methods offered by this class.
        // We simply loop through these and apply them in this order to the actual value from $arr.
        foreach ( $arr as $key => $value ) {
            $validation_methods = $validators[$key];

            $validated_value = $value;
            foreach ( $validation_methods as $validation_method ) {
                // Actually to make it even better we can check here first if the validation method is actually a
                // callable itself. This way we also support custom validation functions, which are not general
                // enough to be added as separate methods of this class
                if ( is_callable($validation_method) ){
                    call_user_func_array($validation_method, [$key, $validated_value]);
                } else {
                    $validated_value = call_user_func_array([$class_name, $validation_method], [$key, $validated_value]);
                }
            }
            $validated_arr[$key] = $validated_value;
        }
        return $validated_arr;
    }

    /**
     * Validates $value with the validation pipeline specified by $validators.
     *
     * @param mixed $value The value to be validated
     * @param array $validators A list of either strings or callables which specify the validation operation to be
     *      performed on the value.
     *
     * @throws ValidationError If one of the validation checks fails
     *
     * @return mixed
     */
    public static function apply_single($value, array $validators) {
        $class_name = get_called_class();

        $validated = $value;

        $validation_methods = $validators;
        foreach ( $validation_methods as $validation_method ) {
            // Actually to make it even better we can check here first if the validation method is actually a
            // callable itself. This way we also support custom validation functions, which are not general
            // enough to be added as separate methods of this class
            $args = ['', $validated];
            if ( is_callable($validation_method) ){
                call_user_func_array($validation_method, $args);
            } else {
                $validated = call_user_func_array([$class_name, $validation_method], $args);
            }
        }

        return $validated;
    }

    // -- Validation methods

    public static function validate_is_string(string $key, $value) {
        if (is_string($value)) {
            return $value;
        } else {
            throw new ValidationError( $key, 'supposed to be string!' );
        }
    }

    public static function validate_is_array(string $key, $value) {
        if(is_array($value)) {
            return $value;
        } else {
            throw new ValidationError( $key, 'supposed to be array!' );
        }
    }

    public static function validate_not_empty(string $key, $value) {
        if (count($value) != 0) {
            return $value;
        } else {
            throw new ValidationError( $key, 'not supposed to be empty!' );
        }
    }

    // -- Sanitation methods

    public static function sanitize_int_elements(string $key, array $value) {
        return array_map('intval', $value);
    }

}