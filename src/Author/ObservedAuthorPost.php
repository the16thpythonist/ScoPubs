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
 * DESIGN CHOICE: TERMS - META VALUES
 *
 * So there are two ways to define custom attributes for a custom post type like this "observed author" post type:
 * Using wordpress custom meta values or using custom taxonomy terms. As a general rule meta values are better if
 * the values are unique to each post and terms are generally used when there is overlap between the posts. Also terms
 * should be used if at some point one wants to search and categorize by these values with reasonable efficiency.
 * For this class most things are mapped as meta values, but the author topics have heavy overlap and it would be good
 * to be able to categorize authors by those.
 *
 * But thats not what this is actually about this is about the design choice of how these two different systems are
 * handled idiomatically by this wrapper implementation: When creating a new wrapper instance by supplying the post id,
 * both the meta values and the terms are loaded in the constructor and can then be accessed as instance attributes.
 * This is totally fine for the meta values but for the terms this is a reduction of information: Terms are represented
 * as term objects which contain multiple fields such as the actualy string content, the description, slug etc. But
 * for the wrapper, the attribute value is simply a list of the string contents. Thus, I have decided to also add
 * methods to the wrapper which explicitly load the list of term objects. In reality, needing the whole term object is
 * probably an edge case.
 *
 * @package Scopubs\Author
 */
class ObservedAuthorPost {

    // -- Static values
    public static $post_type = 'observed_author';
    public static $author_topic_taxonomy = 'author_topic';

    // -- Instance attributes

    // intrinsic
    public $post_id;
    public $post;

    // meta values
    public $first_name;
    public $last_name;
    public $scopus_author_ids;
    public $affiliations;
    public $affiliation_blacklist;

    // taxonomy terms
    public $author_topics;

    // -- Class constants

    public const META_FIELDS = [
        'first_name'            => [
            'type'                  => 'string',
            'description'           => 'The first name of the author',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'last_name'             => [
            'type'                  => 'string',
            'description'           => 'The last name / given name of the author',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        // Interesting fact about these complex data types: I just spend an hour to figure out that to actually make
        // them appear in the REST response it is not enough to simply supply show_in_rest as true. You have to
        // give the whole array specification of the REST schema including all of the information about type and (!)
        // item type.
        'scopus_author_ids'     => [
            'type'                  => 'array',
            'description'           => 'A list of the scopus author IDs associated with the author',
            'default'               => [],
            'single'                => true,
            'show_in_rest'          => [
                'schema' => [
                    'type'          => 'array',
                    'items'         => [
                        'type'      => 'int'
                    ]
                ]
            ]
        ],
        'affiliations'          => [
            'type'                  => 'object', // Dictated by REST compatibility, this HAS TO be an object
            'description'           => 'An associative array defining the affiliations of the author. The key is the '.
                                       'int affiliation ID and the values are assoc. arrays themselves which describe '.
                                       'the attributes of the affiliation',
            'default'               => [],
            'single'                => true,
            'show_in_rest'          => [
                'schema' => [
                    'type'          => 'object',
                    # https://make.wordpress.org/core/2019/10/03/wp-5-3-supports-object-and-array-meta-types-in-the-rest-api/
                    # This is absolutely necessary if I want to use objects as kind of "dicts" where arbitrary
                    # key value pairs are supported
                    'additionalProperties' => true
                ]
            ]
        ],
        'affiliation_blacklist' => [
            'type'                  => 'array',
            'description'           => 'An array with the int affiliation IDs which are blacklisted for this author',
            'default'               => [],
            'single'                => true,
            'show_in_rest'          => [
                'schema' => [
                    'type'          => 'array',
                    'items'         => [
                        'type'      => 'int'
                    ]
                ]
            ]
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
        $this->affiliations = get_post_meta($this->post_id, 'affiliations', true);
        $this->affiliation_blacklist = get_post_meta($this->post_id, 'affiliation_blacklist', true);

        // Loading the terms
        $this->author_topics = array_map(function ($term) { return $term->name; }, $this->get_author_topic_terms());
    }

    /**
     * This method saves the current values of the observed author instance to the database record of the corresponding
     * wordpress post.
     *
     * This saving only applies to the custom meta values like the author first and last name, affiliations array etc.
     * It does not apply to changing the attributes directly related to the wordpress post like the post title for
     * example.
     *
     * It DOES also save the custom taxonomy terms like the values of the "author_topics" array. The string values
     * each will be saved as a term.
     *
     * @throws \Scopubs\Validation\ValidationError
     *
     * @return void
     */
    public function save() {
        // Saving the meta values
        $update_args = $this->get_update_args();
        self::update($this->post_id, $update_args);

        // Saving the taxonomy terms
        wp_set_post_terms($this->post_id, $this->author_topics, self::$author_topic_taxonomy, false);
    }

    /**
     * Returns an array, which can be used as the arguments array for the static "update" method. The values of this
     * array are the currently set values of the corresponding attributes of the instance.
     *
     * @return array The $args array for the "update" method
     */
    public function get_update_args() {
        return [
            'first_name'                => $this->first_name,
            'last_name'                 => $this->last_name,
            'scopus_author_ids'         => $this->scopus_author_ids,
            'affiliations'              => $this->affiliations,
            'affiliation_blacklist'     => $this->affiliation_blacklist
        ];
    }

    public function get_author_topic_terms() {
        // https://developer.wordpress.org/reference/functions/wp_get_post_terms/
        return wp_get_post_terms( $this->post_id, self::$author_topic_taxonomy, ['fields' => 'all']);
    }

    // == STATIC METHODS
    // The static methods will be used to perform general operations for this custom post type. These general operations
    // affect the post type as a whole and are not bound to a specific instance. This includes things like inserting
    // a new post.

    // -- Inserting new posts

    /**
     * Inserts a new observed author into the database based on the provided $args.
     * This method should always be used to insert a new observed author! The wordpress post object which represents
     * the author post needs to be created with some derived properties which can only be guaranteed to be handled
     * correctly when this method is used.
     *
     * The assoc. $arg array HAS TO contain the following keys:
     *
     * - first_name: The string first name of the author
     * - last_name: The string last name of the author
     * - scopus_author_ids: An array with the integer author ids of the author
     * - affiliations: An associative array whose keys are the int ids of the authors scopus affiliations and the
     *      values are themselves assoc arrays which describe the properties "name", "id" and "city" of the affiliation
     * - affiliation_blacklist: An array with the int ids of those affiliations which are to be considered blacklisted
     *      for this author
     *
     * @param array $args
     *
     * @return int|\WP_Error
     * @throws \Scopubs\Validation\ValidationError
     */
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

    /**
     * Updates the observed author post with the given $post_id with the given $args.
     *
     * The assoc $args array CAN contain the following fields:
     *
     * - first_name: The string first name of the author
     * - last_name: The string last name of the author
     * - scopus_author_ids: An array with the integer author ids of the author
     * - affiliations: An associative array whose keys are the int ids of the authors scopus affiliations and the
     *      values are themselves assoc arrays which describe the properties "name", "id" and "city" of the affiliation
     * - affiliation_blacklist: An array with the int ids of those affiliations which are to be considered blacklisted
     *      for this author
     *
     * @param int $post_id The post id of the observed author post whose attributes to modify
     * @param array $args An assoc array defining the new values which are to be used to replace the values of the
     *      specified post.
     *
     * @return int The post id of the post which was updated.
     * @throws \Scopubs\Validation\ValidationError
     */
    public static function update(int $post_id, array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        // Should we check if this post exists before we insert it?
        // This could also probably go into its own method...
        $postarr = [
            'ID'            => $post_id,
            'meta_input'    => []
        ];
        foreach ($args as $arg => $value) {
            $postarr['meta_input'][$arg] = $value;
        }

        return wp_update_post($postarr);
    }

    /**
     * Given the insert/update arguments array, this method creates the $postarr array which is needed to actually
     * perform the post insertion with wordpress.
     *
     * @param array $args The arguments array as defined for the update/insert method.
     *
     * @return array
     */
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

    /**
     * Given the $first_name and the $last_name of an author this method creates the corresponding string which is to
     * be used as the post title.
     *
     * The post title will be the last name comma the first name.
     *
     * @param string $first_name The string first name of the author
     * @param string $last_name The string last name of the author
     *
     * @return string
     */
    public static function create_post_title(string $first_name, string $last_name) {
        return "${last_name}, ${first_name}";
    }
}