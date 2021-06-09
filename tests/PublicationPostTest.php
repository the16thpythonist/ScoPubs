<?php

use PHPUnit\Framework\TestCase;
use Scopubs\Author\ObservedAuthorPost;
use Scopubs\Publication\PublicationPost;


class PublicationPostTest extends WP_UnitTestCase {

    // -- Registration of the "Publication" post type

    public function test_post_type_exists() {
        $this->assertTrue( post_type_exists(PublicationPost::$post_type) );
    }

    public function test_publication_topic_taxonomy_exists() {
        $this->assertTrue( taxonomy_exists(PublicationPost::$publication_topic_taxonomy) );
    }

    public function test_publication_tag_taxonomy_exists() {
        $this->assertTrue( taxonomy_exists(PublicationPost::$publication_tag_taxonomy) );
    }

    public function test_publication_observed_author_taxonomy_exists() {
        $this->assertTrue( taxonomy_exists(PublicationPost::$publication_observed_author_taxonomy) );
    }

    // -- The actual "PublicationPost" class



}