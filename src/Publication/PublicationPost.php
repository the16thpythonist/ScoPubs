<?php


namespace Scopubs\Publication;

use Scopubs\Validation\DataValidator;
use Scopubs\Author\ObservedAuthorPost;
use Scopubs\Util;

/**
 * Class PublicationPost
 *
 * This class implements the wrapper to interface with the publication post type
 *
 * DESIGN CHOICE: TERMS - META VALUES
 *
 * I already made the general remarks about this topic in the "ObservedAuthorPost.php" file, but I feel like for this
 * class there are individual instances where I want to explain my reasoning. First off there are the obvious choices:
 * The scopus ids and in general all kinds of ids are meta values because they are unique properties of the individual
 * publication. Same goes for the publishing date etc.
 *
 * Now a more interesting choice is the journal. Here each publication is assigned just a single journal and they can
 * overlap. In the previous version of this plugin I realized this as a taxonomy for exactly that reason: It may be
 * interesting to filter by journal and a taxonomy is the most idiomatic way to do that. But there is a very practical
 * problem: It doesnt really work. The issue is that the scopus response contains the journal as a string. And even for
 * the same journal these strings tend to deviate. This might be a typo, an abbreviation, additional space etc...
 * All in all string matching them to the same taxonomy term just doesnt work. So this time the journal will just be
 * a meta value.
 *
 * Even more interesting is the authors. Authors and publications is a many to many mapping, there can be significant
 * overlap and there is a lot information to be gained from filtering by author. All of this make it the prime example
 * for when to use a taxonomy over a meta value, but the same problem applies: Author name strings tend to be so
 * different including everything from typos to different abbreviation styles to different order of last and first name.
 * Thus, generally I would map the authors as a meta list for every post.
 * BUT: This does not go for the observed authors. For observed authors we can determine all which have
 * collaborated on the same paper with relative ease. So those I'll be mapping as a taxonomy. Specifically the post ID
 * related to that author.
 *
 * Now there is also the case of tags. For
 *
 * The bottom line is that everything except the topics and observed authors will be mapped as meta values.
 *
 * @package Scopubs\Publication
 */
class PublicationPost {

    // -- Static values
    public static $post_type = 'publication';

    public static $publication_topic_taxonomy = 'publication_topic';
    public static $publication_tag_taxonomy = 'publication_tag';
    public static $publication_observed_author_taxonomy = 'publication_observed_author';

    // -- Instance attributes

    // intrinsic
    public $post_id;
    public $post;

    public $title;
    public $abstract;

    // meta values
    public $publish_date;
    public $scopus_id;
    public $kitopen_id;
    public $doi;
    public $eid;
    public $issn;
    public $authors;
    public $author_count;
    public $journal;
    public $volume;

    // taxonomy terms
    public $observed_authors;
    public $topics;
    public $tags;

    // -- class constants

    public const META_FIELDS = [
        'publish_date' => [
            'type'                  => 'string',
            'description'           => 'The date at which the publication was published',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'scopus_id' => [
            'type'                  => 'string',
            'description'           => 'The scopus ID of the publication. This ID uniquely identifies the ' .
                                       'publication with scopus.',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'kitopen_id' => [
            'type'                  => 'string',
            'description'           => 'The KitOpen ID of the publication.',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'doi' => [
            'type'                  => 'string',
            'description'           => 'The "digital object identifier" of the publication',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'eid' => [
            'type'                  => 'string',
            'description'           => 'yet another ID',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'issn' => [
            'type'                  => 'string',
            'description'           => 'yet another ID',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'journal' => [
            'type'                  => 'string',
            'description'           => 'The name of the journal in which the publication was published',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'volume' => [
            'type'                  => 'string',
            'description'           => 'The volume of the journal in which it is published.',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true
        ],
        'author_count' => [
            'type'                  => 'integer',
            'description'           => 'The total count of authors which have worked on the publication. This field ' .
                                       'should be used inquiring about the amount of authors contrary to the length ' .
                                       'of the "authors" field because potentially not all authors are saved due to ' .
                                       'performance reasons.',
            'default'               => 0,
            'single'                => true,
            'show_in_rest'          => true
        ],
        'authors' => [
            'type'                  => 'array',
            'description'           => 'An array containing information about the authors. The elements of this ' .
                                       'array are associative arrays which contain the information about the author ' .
                                       'as key value pairs',
            'default'               => [],
            'single'                => true,
            'show_in_rest'          => [
                'schema' => [
                    'type'          => 'array',
                    'items'         => [
                        'type'      => 'object',
                        // WOW. Apparently this is necessary even here...
                        'additionalProperties' => true,
                    ]
                ]
            ]
        ]
    ];

    public const INSERT_VALUE_VALIDATORS = [
        'title'                     => ['validate_is_string'],
        'abstract'                  => ['validate_is_string'],
        'publish_date'              => ['validate_is_string'],
        'scopus_id'                 => ['validate_is_string'],
        'kitopen_id'                => ['validate_is_string'],
        'doi'                       => ['validate_is_string'],
        'eid'                       => ['validate_is_string'],
        'issn'                      => ['validate_is_string'],
        'journal'                   => ['validate_is_string'],
        'volume'                    => ['validate_is_string'],
        'author_count'              => ['validate_is_int'],
        'authors'                   => ['validate_is_array']
    ];

    public function __construct(int $post_id) {
        $this->post_id = $post_id;
        $this->post = get_post($this->post_id);

        $this->title = $this->post->post_title;
        $this->abstract = $this->post->post_content;

        // Loading post meta values
        $this->publish_date = get_post_meta($this->post_id, 'publish_date', true);
        $this->scopus_id = get_post_meta($this->post_id, 'scopus_id', true);
        $this->kitopen_id = get_post_meta($this->post_id, 'kitopen_id', true);
        $this->doi = get_post_meta($this->post_id, 'doi', true);
        $this->eid = get_post_meta($this->post_id, 'eid', true);
        $this->issn = get_post_meta($this->post_id, 'issn', true);
        $this->journal = get_post_meta($this->post_id, 'journal', true);
        $this->volume = get_post_meta($this->post_id, 'volume', true);
        $this->author_count = (int) get_post_meta($this->post_id, 'author_count', true);
        $this->authors = get_post_meta($this->post_id, 'authors', true);

        // Loading taxonomy terms
        $this->topics = array_map(function($term) { return $term->name; }, $this->get_topic_terms());
        $this->tags = array_map(function($term) {return $term->name; }, $this->get_tag_terms());
        // Now for the observed authors we need to do a little bit more, this method actually
        // returns a list of ObservedAuthorPost wrapper objects for the according observed authors!
        $this->observed_authors = $this->get_observed_authors();
    }

    // -- Managing the taxonomy fields --

    /**
     * Returns an array of WP_Term objects where each term is one "Topic" set for this publication.
     *
     * @return array
     */
    public function get_topic_terms() {
        return wp_get_post_terms( $this->post_id, self::$publication_topic_taxonomy, ['fields' => 'all'] );
    }

    /**
     * Returns an array of WP_Term objects, where each term represents one "Tag" set for this publication
     *
     * @return array
     */
    public function get_tag_terms() {
        return wp_get_post_terms( $this->post_id, self::$publication_tag_taxonomy, ['fields' => 'all'] );
    }

    /**
     * Returns an arry of WP_Term object, where each term represents one "Observed Author" term set for this publication
     *
     * @return array
     */
    public function get_observed_author_terms() {
        return wp_get_post_terms( $this->post_id, self::$publication_observed_author_taxonomy, ['fields' => 'all'] );
    }

    /**
     * Returns an array of ObservedAuthorPost instances, where each instance represents one observed author which is
     * registered in the wordpress database and is listed as an author of this publication.
     *
     * The publication post type can be extended with terms of the "Observed Author" taxonomy. Each term of this
     * taxonomy references one observed author post in the wordpress database. This is a method of linking the two post
     * types together. Each Observed Author taxonomy term should contain the wordpress post id of the
     * corresponding observed author post as the term description. This is how this method works: By retrieving all the
     * observed author terms for this publication and using the post ids in their descriptions, the corresponding
     * ObservedAuthorPost wrapper instances can be created and are loaded into the returned array list.
     *
     * @return array Array of all ObservedAuthorPost instances also listed as authors of this publication
     */
    public function get_observed_authors() {
        // First we need to get the terms which represent the observed authors. These contain all the information we
        // need to actually load the observed author posts. Specifically the description is the int post id of the
        // observed author term which describes that author
        $observed_author_terms = $this->get_observed_author_terms();
        $observed_authors = [];
        foreach ($observed_author_terms as $term) {
            $post_id = (int) $term->description;
            $observed_author = new ObservedAuthorPost($post_id);
            array_push($observed_authors, $observed_author);
        }

        return $observed_authors;
    }

    /**
     * Returns an array, which can be used as the arguments array for the static "update" method. The values of this
     * array are the currently set values of the corresponding attributes of the instance.
     *
     * @return array The $args array for the "update" method
     */
    public function get_update_args() {
        return [
            'title'             => $this->title,
            'abstract'          => $this->abstract,
            'publish_date'      => $this->publish_date,
            'scopus_id'         => $this->scopus_id,
            'kitopen_id'        => $this->kitopen_id,
            'doi'               => $this->doi,
            'eid'               => $this->eid,
            'issn'              => $this->issn,
            'journal'           => $this->journal,
            'volume'            => $this->volume,
            'author_count'      => $this->author_count,
            'authors'           => $this->authors
        ];
    }

    /**
     * This method saves the current values of this publication post instance to the database record of the
     * corresponding wordpress post.
     *
     * This method saves the following attributes:
     * - All the custom meta fields which are specific to this post type, like the scopus id, the authors etc.
     * - The taxonomy terms for the topics, tags etc.
     * - The "title" attribute as the post title and the "abstract" attribute as the post content.
     *
     * It does NOT save any other modified values of the internal "post" attribute!
     *
     * @returns void
     */
    public function save() {
        $args = $this->get_update_args();
        self::update($this->post_id, $args);

        // Updating the "Topic" taxonomy terms
        wp_set_post_terms($this->post_id, $this->topics, self::$publication_topic_taxonomy, false);
        // Updating the "Tag" taxonomy terms
        wp_set_post_terms($this->post_id, $this->tags, self::$publication_tag_taxonomy, false);
        // Updating the "Observed Author" taxonomy terms
        wp_set_post_terms(
            $this->post_id,
            array_map(function($a) { return $a->get_full_name(); }, $this->observed_authors),
            self::$publication_observed_author_taxonomy,
            false
        );
    }

    // == STATIC METHODS
    // The static methods will be used to perform general operations for this custom post type. These general operations
    // affect the post type as a whole and are not bound to a specific instance. This includes things like inserting
    // a new post.

    // -- Inserting new posts

    /**
     * Inserts a new publication post into the database based on the provided $args.
     *
     * The assoc. $args array HAS TO contain the following keys:
     *
     * - title: The string title of the publication
     * - abstract: The abstract / a short description of the publication
     * - scopus_id: A string representation of the scopus ID by which this publication is uniquely identified in the
     *   Scopus online publications database
     * - kitopen_id: A string representation of the KITOpenID
     * - eid: string ID
     * - doi: string ID
     * - issn: string ID
     * - journal: The string name of the journal in which the publication was published
     * - volume: string name of the volume of the journal.
     * - author_count: The int amount of authors which are listed for this publication
     * - authors: An array of associative arrays. Each assoc array element describes one of the authors of the
     *   publication
     *
     * @param array $args
     *
     * @return int|\WP_Error
     * @throws \Scopubs\Validation\ValidationError
     */
    public static function insert(array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        $postarr = self::create_postarr($args);
        $post_id = wp_insert_post($postarr);

        return $post_id;
    }

    /**
     * Updates the publication post with the given $post_id with the values within the $args array
     *
     * The assoc. $args array CAN contain the following keys:
     *
     * - title: The string title of the publication
     * - abstract: The abstract / a short description of the publication
     * - scopus_id: A string representation of the scopus ID by which this publication is uniquely identified in the
     *   Scopus online publications database
     * - kitopen_id: A string representation of the KITOpenID
     * - eid: string ID
     * - doi: string ID
     * - issn: string ID
     * - journal: The string name of the journal in which the publication was published
     * - volume: string name of the volume of the journal.
     * - author_count: The int amount of authors which are listed for this publication
     * - authors: An array of associative arrays. Each assoc array element describes one of the authors of the
     *   publication
     *
     * @param int $post_id
     * @param array $args
     *
     * @return int|\WP_Error
     * @throws \Scopubs\Validation\ValidationError
     */
    public static function update(int $post_id, array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        $postarr = Util::array_mapping($args, [
            'title'                 => 'post_title',
            'abstract'              => 'post_content',
            'publish_date'          => 'meta_input/publish_data',
            'scopus_id'             => 'meta_input/scopus_id',
            'kitopen_id'            => 'meta_input/kitopen_id',
            'doi'                   => 'meta_input/doi',
            'eid'                   => 'meta_input/eid',
            'issn'                  => 'meta_input/issn',
            'journal'               => 'meta_input/journal',
            'volume'                => 'meta_input/volume',
            'author_count'          => 'meta_input/author_count',
            'authors'               => 'meta_input/authors'
        ]);
        $postarr['ID'] = $post_id;

        return wp_update_post($postarr);
    }

    /**
     * Returns a list of ObservedAuthorPost instances, one wrapper instance for every author post in the database.
     *
     * @return array
     */
    public static function all() {
        $query = new \WP_Query([
            'post_type'         => static::$post_type,
            'post_status'       => 'publish'
        ]);
        $wrappers = [];
        foreach ($query->get_posts() as $post) {
            $wrapper = new static($post->ID);
            array_push($wrappers, $wrapper);
        }
        return $wrappers;
    }

    /**
     * Given the insert/update arguments array $args, this method creates the $postarr array which is needed to
     * actually perform the post insertion with wordpress.
     *
     * For information about which values $args is supposed contain see the insert method.
     *
     * @param array $args The arguments array defines for the update/insert method
     *
     * @return array
     */
    public static function create_postarr(array $args) {
        return [
            'post_type'             => self::$post_type,
            'post_title'            => $args['title'],
            'post_content'          => $args['abstract'],
            'post_status'           => 'publish',
            'meta_input' => [
                'publish_date'      => $args['publish_date'],
                'scopus_id'         => $args['scopus_id'],
                'kitopen_id'        => $args['kitopen_id'],
                'doi'               => $args['doi'],
                'eid'               => $args['eid'],
                'issn'              => $args['issn'],
                'journal'           => $args['journal'],
                'volume'            => $args['volume'],
                'author_count'      => $args['author_count'],
                'authors'           => $args['authors']
            ]
        ];
    }
}