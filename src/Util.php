<?php


namespace Scopubs;


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
     *
     *
     * @param array $source_array
     * @param array $mapping
     *
     * @return array
     */
    public static function array_mapping( array $source_array, array $mapping ) {
        $result = [];
        foreach ( $mapping as $source_key => $target_query ) {
            // This where the conditional part comes in:
            if ( ! array_key_exists( $source_key, $source_array ) ) {
                continue;
            }

            $target_keys = explode( '/', $target_query );
            // Creating array structure
            $current_index = 0;
            // https://www.php.net/manual/en/language.references.php
            $current_array = &$result;
            while ( $current_index < count( $target_keys ) - 1 ) {
                $current_key = $target_keys[ $current_index ];
                if ( ! array_key_exists( $current_key, $current_array ) || ! is_array( $current_array[ $current_key ] ) ) {
                    $current_array[ $current_key ] = [];
                }
                $current_array = &$current_array[ $current_key ];
                $current_index += 1;
            }

            // Actually setting the value on the lowest level
            $last_key                   = $target_keys[ $current_index ];
            $current_array[ $last_key ] = $source_array[ $source_key ];
            var_dump( $current_array );
        }

        return $result;
    }

}