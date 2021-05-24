<?php

use PHPUnit\Framework\TestCase;
use Scopubs\Author\ObservedAuthorPost;

class ObservedAuthorPostTest extends WP_UnitTestCase {

    # -- Registration of the "Observed Author" post type

    public function test_author_topic_taxonomy_exists() {
        // If the "Author Topic" custom taxonomy is properly registered in wordpress
        $this->assertTrue( taxonomy_exists(ObservedAuthorPost::$author_topic_taxonomy) );
    }

    # -- The actual "ObservedAuthorPost" class

    public function test_create_postarr_working() {
        // This is a pretty standard set of arguments for creating a new author post, this should be working
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [31415],
            'category_ids'              => [12, 14],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];

        $postarr = ObservedAuthorPost::create_postarr($args);
        $this->assertIsArray($postarr);
    }

    public function test_insert_observed_author_working() {
        // Whether inserting a new observed author post works without an error
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [1, 2],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];
        $post_id = ObservedAuthorPost::insert($args);
        $this->assertNotEquals(0, $post_id);
    }

    public function test_insert_and_loading_observed_author_working() {
        // Whether the creation of a post wrapper object generally works
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [1, 2],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];
        // Inserting the post
        $post_id = ObservedAuthorPost::insert($args);

        // Loading the post
        $observed_author_post = new ObservedAuthorPost($post_id);
        $this->assertInstanceOf(ObservedAuthorPost::class, $observed_author_post);
    }

    public function test_correctly_insert_and_load_observed_author_meta() {
        // Whether the post meta values are generally inserted and loaded again properly
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [1, 2],
            'affiliations'              => [
                1203930 => [
                    'name'              => 'Karlsruhe Institute for technology',
                    'id'                => 1203930,
                    'city'              => 'Karlsruhe'
                ],
                3495220 => [
                    'name'              => 'Hochschule Offenburg',
                    'ids'               => 3495220,
                    'city'              => 'Offenburg'
                ]
            ],
            'affiliation_blacklist'     => [3495220]
        ];
        $post_id = ObservedAuthorPost::insert($args);

        $observed_author_post = new ObservedAuthorPost($post_id);

        $this->assertEquals($args['first_name'], $observed_author_post->first_name);
        $this->assertEquals($args['last_name'], $observed_author_post->last_name);

        $this->assertIsArray($observed_author_post->scopus_author_ids);
        $this->assertEquals($args['scopus_author_ids'], $observed_author_post->scopus_author_ids);

        $this->assertIsArray($observed_author_post->affiliations);
        $this->assertEquals($args['affiliations'], $observed_author_post->affiliations);

        $this->assertEquals($args['affiliation_blacklist'], $observed_author_post->affiliation_blacklist);
    }

    public function test_static_update_method_works() {
        // If using the static "update" method generally works to modify the data of an existing observed author post
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [1],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];
        $post_id = ObservedAuthorPost::insert($args);

        // Loading the just inserted author again to verify that the information was actually saved
        $observed_author = new ObservedAuthorPost($post_id);
        $this->assertEquals($args['last_name'], $observed_author->last_name);
        $this->assertEquals($args['scopus_author_ids'], $observed_author->scopus_author_ids);

        // Updating with new values and then checking again if they have actually been saved
        $updated_args = [
            'last_name'                 => 'Lefuet',
            'scopus_author_ids'         => [2, 3]
        ];
        ObservedAuthorPost::update($post_id, $updated_args);

        $observed_author = new ObservedAuthorPost($post_id);
        $this->assertEquals($updated_args['last_name'], $observed_author->last_name);
        $this->assertEquals($updated_args['scopus_author_ids'], $observed_author->scopus_author_ids);
        $this->assertEquals($args['affiliation_blacklist'], $observed_author->affiliation_blacklist);
    }

    public function test_using_the_save_instance_method_to_update_post() {
        // If the "save" method of an instance generally works to update the data of an existing post.
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];
        $post_id = ObservedAuthorPost::insert($args);
        
        // verifying that the data is actually saved correctly
        $observed_author = new ObservedAuthorPost($post_id);
        $this->assertEquals($args['first_name'], $observed_author->first_name);
        $this->assertEquals($args['affiliations'], $observed_author->affiliations);
        $this->assertEquals($args['scopus_author_ids'], $observed_author->scopus_author_ids);
        
        // Modifying the instance attributes and then calling "save"
        $observed_author->first_name = 'Peter';
        array_push($observed_author->scopus_author_ids, 1);
        $observed_author->affiliations[192834] = [
            'id'                        => 192834,
            'name'                      => 'KIT',
            'city'                      => 'Karlsruhe'
        ];
        $observed_author->save();

        $new_first_name = $observed_author->first_name;
        $new_scopus_author_ids = $observed_author->scopus_author_ids;
        $new_affiliations = $observed_author->affiliations;
        
        // Reloading from the database to verify new values were actually saved
        $observed_author = new ObservedAuthorPost($post_id);
        $this->assertEquals($new_first_name, $observed_author->first_name);
        $this->assertEquals($new_scopus_author_ids, $observed_author->scopus_author_ids);
        $this->assertEquals($new_affiliations, $observed_author->affiliations);
    }

    public function test_using_the_save_instance_to_save_author_topic_terms() {

        // Inserting a new author post
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];
        $post_id = ObservedAuthorPost::insert($args);

        // Loading this post as a wrapper instance and adding some author topic terms to it. Then we try to save it.
        $observed_author = new ObservedAuthorPost($post_id);
        $this->assertIsArray($observed_author->author_topics);
        $this->assertEquals(0, count($observed_author->author_topics));

        $observed_author->author_topics = [
            'Electrical Engineering',
            'Computer Science'
        ];
        $new_author_topics = $observed_author->author_topics;
        $observed_author->save();

        // Loading the data from the database again and checking if the values are actually the same
        // https://stackoverflow.com/questions/3838288/phpunit-assert-two-arrays-are-equal-but-order-of-elements-not-important
        $observed_author = new ObservedAuthorPost($post_id);
        $this->assertEqualsCanonicalizing($new_author_topics, $observed_author->author_topics);
    }

    public function test_getting_author_topic_terms() {
        // Inserting an author and manually adding some taxonomy terms to it
        $args = [
            'first_name'                => 'Jonas',
            'last_name'                 => 'Teufel',
            'scopus_author_ids'         => [],
            'affiliations'              => [],
            'affiliation_blacklist'     => []
        ];
        $post_id = ObservedAuthorPost::insert($args);

        $author_topics = [
            'Electrical Engineering',
            'Computer Science',
            'Cooking'
        ];
        // https://developer.wordpress.org/reference/functions/wp_set_post_terms/
        wp_set_post_terms($post_id, $author_topics, ObservedAuthorPost::$author_topic_taxonomy, false);

        // Attempting to load them with the appropriate method
        $observed_author = new ObservedAuthorPost($post_id);
        $author_topic_terms = $observed_author->get_author_topic_terms();
        $this->assertIsArray($author_topics);
        $this->assertEquals(count($author_topics), count($author_topic_terms));
        $this->assertInstanceOf(\WP_Term::class, $author_topic_terms[0]);
    }
    
    // -- Utility methods


}
