<?php

use PHPUnit\Framework\TestCase;
use Scopubs\Author\ObservedAuthorPost;

class ObservedAuthorPostTest extends WP_UnitTestCase {

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



    // -- Utility methods


}
