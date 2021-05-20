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
 * One essential with the affiliations is also this: Their management within the scopus database itself is not very
 * consistent there might be duplications which all refer to the same institution and a lot of times the names are
 * spelled wrong etc.
 */

use Scopubs\Validation\DataValidator;


/**
 * Class ObservedAuthorPost
 *
 *
 * @package Scopubs\Author
 */
class ObservedAuthorPost {

    // -- Static values
    public static $post_type = 'observed-author';

    // -- Instance attributes

    public $post_id;
    public $post;

    public $first_name;
    public $last_name;
    public $scopus_author_ids;
    public $category_ids; // ACTUALLY: CATEGORY IDS SHOULD BE
    public $affiliations;
    public $affiliation_blacklist;

    // -- Class constants

    public const META_FIELDS = [
        'first_name'            => [
            'type'                  => 'string',
            'description'           => 'The first name of the author',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'last_name'             => [
            'type'                  => 'string',
            'description'           => 'The last name / given name of the author',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'scopus_author_ids'     => [
            'type'                  => 'array',
            'description'           => 'A list of the scopus author IDs associated with the author',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'affiliations'          => [
            'type'                  => 'array',
            'description'           => 'An associative array defining the affiliations of the author. The key is the '.
                                       'int affiliation ID and the values are assoc. arrays themselves which describe '.
                                       'the attributes of the affiliation',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'affiliation_blacklist' => [
            'type'                  => 'array',
            'description'           => 'An array with the int affiliation IDs which are blacklisted for this author',
            'single'                => true,
            'show_in_rest'          => true
        ]
    ];

    public const INSERT_VALUE_VALIDATORS = [
        'first_name'            => ['validate_is_string'],
        'last_name'             => ['validate_is_string'],
        'scopus_author_ids'     => ['validate_is_array', 'sanitize_int_elements'],
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
        // $this->category_ids = get_post_meta($this->post_id, 'category_ids', true);
        $this->affiliations = get_post_meta($this->affiliations, 'affiliations', true);
        $this->affiliation_blacklist = get_post_meta($this->affiliation_blacklist, 'affiliation_blacklist', true);
    }

    // == STATIC METHODS
    // The static methods will be used to perform general operations for this custom post type. These general operations
    // affect the post type as a whole and are not bound to a specific instance. This includes things like inserting
    // a new post.

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
        $post_title = self::create_post_title($args['first_name'], $args['last_name']);
        return [
            'post_type'             => self::$post_type,
            'post_title'            => $post_title,
            'meta_input'            => [
                'first_name'            => $args['first_name'],
                'last_name'             => $args['last_name'],
                'scopus_author_ids'     => $args['scopus_author_ids'],
                'affiliations'          => $args['affiliations'],
                'affiliation_blacklist' => $args['affiliation_blacklist']
            ]
        ];
    }

    public static function create_post_title(string $first_name, string $last_name) {
        return "${last_name}, ${first_name}";
    }
}