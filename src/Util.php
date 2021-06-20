<?php


namespace Scopubs;


use http\Exception\InvalidArgumentException;
use Scopubs\Validation\ValidationError;

/**
 * This is a static class which simply acts as a container for all the misc. utility functions.
 */
class Util {

    /**
     * Maps the values of the given un-nested assoc $source_array into a potentially nested assoc array with
     * potentially different key names as defined by the instructions given as the assoc array $mapping.
     *
     * MOTIVATION
     *
     * If you just read the short description of this function you might be tempted to ask "Why? That sounds so
     * overly specific". But there is actually a pretty good use case for this. Consider the following problem with
     * wordpress posts: When intending to insert a new post with "wp_insert_post" this function expects $postarr. This
     * is a nested assoc array structure and the field names are not really intuitive. When designing the "insert"
     * method for a custom post type we would like to keep it simple: No nested array and more descriptive key names
     * now we have the use case: A generalized function which translates one assoc array into a different nested array
     * structure!
     *
     * USAGE
     *
     * To illustrate how this function works consider the following example.
     *
     *      $source_array = [
     *          'name': 'Max',
     *          'city': 'Rome'
     *      ];
     *
     *      $mapping = [
     *          'name' => 'first_name',
     *          'city' => 'info/city'
     *      ];
     *
     *      $target_array = Util::array_mapping($source_array, $mapping);
     *      // Equals the following
     *      $target_array = [
     *          'first_name' => 'Max',
     *          'info' => [
     *              'city' => 'Rome'
     *          ]
     *      ];
     *
     * @param array $source_array
     * @param array $mapping
     *
     * @return array
     */
    public static function array_mapping( array $source_array, array $mapping ) {
        $result = [];
        // $mapping is an associative array, where keys and values are strings. The keys describe the keys
        // of the source array and the values define the structure at which the corresponding values of the source
        // array should have in the resulting target array.
        // "Structure" means a query string, where an unknown number of multiple keys are separated by slash characters
        // to define an additional layer of assoc array nesting.
        foreach ( $mapping as $source_key => $target_query ) {
            // This where the optional part comes in: If the mapping is not supposed to be strict we will simply
            // ignore any entries where the mapping defines a source key which is not actually present in the given
            // source array.
            if ( ! array_key_exists( $source_key, $source_array ) ) {
                continue;
            }

            $target_keys = explode( '/', $target_query );
            // Creating array structure. This whole upcoming while loop serves the single purpose of creating the
            // the necessary nesting structure for the given query
            $current_index = 0;
            // https://www.php.net/manual/en/language.references.php
            // I only recently found out, that php does not pass references by default. You actually have to specify
            // with the special & character that a variable is to refer to an actual reference of an object.
            $current_array = &$result;
            while ( $current_index < count( $target_keys ) - 1 ) {
                $current_key = $target_keys[ $current_index ];
                // If the array for the nesting does not exist create it and move one level deeper
                if ( ! array_key_exists( $current_key, $current_array ) || ! is_array( $current_array[ $current_key ] ) ) {
                    $current_array[ $current_key ] = [];
                }
                $current_array = &$current_array[ $current_key ];
                $current_index += 1;
            }

            // Actually setting the value on the lowest level.
            // After the previous section we can assume that $current_array actually contains a reference to the
            // deepest level specified by the query.
            $last_key                   = $target_keys[ $current_index ];
            $current_array[ $last_key ] = $source_array[ $source_key ];
        }

        return $result;
    }

    /**
     * This function makes sure that the given associative $source_array contains all the keys which are specified as
     * a list $keys of strings. If the $source_array does indeed have all the correct keys, nothing happens. If even
     * one key is missing, this function throws a InvalidArgumentException
     *
     * @param array $source_array The associative array whose keys to check
     * @param array $keys A list of strings, which are all supposed to be keys of the source array.
     */
    public static function require_array_keys( array $source_array, array $keys ) {
        foreach ($keys as $key) {
            if (!array_key_exists( $key, $source_array )) {
                throw new \InvalidArgumentException(
                    "The provided array does not contain the required key ${key}!"
                );
            }
        }
    }

    // -- Permission callback helpers

    /**
     * Returns the boolean value of whether or not the current user is an administrator for the wordpress installation.
     *
     * @return bool
     */
    public static function current_user_admin() {
        return current_user_can('administrator');
    }

}