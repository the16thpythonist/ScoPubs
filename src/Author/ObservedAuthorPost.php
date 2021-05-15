<?php

namespace Scopubs\Author;

/*
 * PLANNING:
 *
 * One thing which is supremely impractical at the moment is that the author affiliations are not actually saved as
 * properties of the author... But the corresponding whitelist and blacklist are... This leads to a very complicated
 * process of dealing with the affiliations. I found out that this was pretty much unnecessary, since the wordpress
 * post meta fields actually support associative arrays.
 *
 * So, an observed author should have the following attributes:
 * - first name
 * - last name
 * - a list of associated scopus author ids
 * - a list of associated categories
 * - a dict, which defines the affiliations for this author
 * - a list of affiliation IDs for the blacklist associated with this author.
 *
 * In an ideal world, the author affiliations would not be meta values but a custom taxonomy, because there could be
 * overlap between others. Strictly speaking the affiliation is not so much a unique attribute of the author but more
 * of a many to many relationship.
 * But I am choosing to do it as meta values anyways because that is easier to do at first and I just dont really see
 * the usage in having it be a taxonomy at the moment. I am fairly certain, that I could change that later on if I
 * wanted.
 */

use Scopubs\Validation\DataValidator;


/**
 * Class ObservedAuthorPost
 *
 *
 * @package Scopubs\Author
 */
class ObservedAuthorPost {

    public $post_id;
    public $post;

    public $first_name;
    public $last_name;
    public $scopus_author_ids;
    public $category_ids;
    public $affiliations;
    public $affiliation_blacklist;

    // CONSTANT VALUES
    public const INSERT_VALUE_VALIDATORS = [
        'first_name'            => ['validate_is_string'],
        'last_name'             => ['validate_is_string'],
        'scopus_author_ids'     => ['validate_is_array', 'sanitize_int_elements'],
        'category_ids'          => ['validate_is_array', 'validate_not_empty', 'sanitize_int_elements'],
        'affiliations'          => ['validate_is_array'],
        'affiliation_blacklist' => ['validate_is_array', 'sanitize_int_elements']
    ];

    public function __construct(int $post_id) {
        // This is a custom post type, but it is still based on the base wordpress post. Given a specific post ID
        // we load the corresponding post from the wordpress database.
        $this->post_id = $post_id;
        $this->post = get_post($this->post_id);

        // Loading the post meta values
        $this->first_name = get_post_meta($this->post_id, 'first_name', true);
        $this->last_name = get_post_meta($this->post_id, 'last_name', true);
        $this->scopus_author_ids = get_post_meta($this->post_id, 'scopus_author_ids', true);
        $this->category_ids = get_post_meta($this->post_id, 'category_ids', true);
        $this->affiliations = get_post_meta($this->affiliations, 'affiliations', true);
        $this->affiliation_blacklist = get_post_meta($this->affiliation_blacklist, 'affiliation_blacklist', true);
    }

    // -- Inserting new posts

    public static function insert(array $args) {
        // first of all we need to validate the array of arguments to check if every important parameter is provided.
        // In the previous version I didnt check this, but instead used an array of defaults. I think this is not a
        // good idea. When inserting you should be as explicit as possible about all values.
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        // The postarr is an array with arguments in exactly the format in which wordpress needs it to be passed to the
        // function "wp_insert_post" which will actually create the new post. The method create_postarr uses the
        // given arguments array and reformats it to fit wordpress' needs.
        $postarr = self::create_postarr($args);

        $post_id = wp_insert_post($postarr);
        // check if the insertion process was successful?
        return $post_id;
    }

    public static function create_postarr(array $args) {
        return [];
    }
}