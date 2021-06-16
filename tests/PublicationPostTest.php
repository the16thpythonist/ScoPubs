<?php

use PHPUnit\Framework\TestCase;
use Scopubs\Author\ObservedAuthorPost;
use Scopubs\Publication\PublicationPost;


class PublicationPostTest extends WP_UnitTestCase {

    static $PUBLICATION_ARGS = [
        'title'             => 'Towards interactive coordination of heterogeneous robotic teams - ' .
                               'Introduction of a reoptimization framework',
        'abstract'          => 'The coordination of robotic teams demands suitable planning algorithms based on ' .
                               'appropriate models of the problem instances...',
        'publish_date'      => '2021-09-22',
        'scopus_id'         => '100',
        'kitopen_id'        => '',
        'doi'               => '100',
        'eid'               => '100',
        'issn'              => '100',
        'journal'           => 'Systems, Man and Cybernetics',
        'volume'            => '2021',
        'author_count'      => 4,
        'authors'           => [
            [
                'full_name'         => 'Esther Bischoff',
                'affiliation_id'    => '20'
            ],
            [
                'full_name'         => 'Jonas Teufel',
                'affiliation_id'    => '20'
            ],
            [
                'full_name'         => 'Jairo Inga',
                'affiliation_id'    => '20'
            ],
            [
                'full_name'         => 'Sören Hohmann',
                'affiliation_id'    => '20'
            ]
        ]
    ];

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

    public function test_static_insert_method_works() {
        // If the class method "insert" works to create an actual post
        $post_id = PublicationPost::insert(self::$PUBLICATION_ARGS);

        // General checks for the wordpress post
        $this->assertNotEquals(0, $post_id);
        $this->assertEquals('publish', get_post_status($post_id));
        // Attempting to load the same post through the wrapper class
        $publication_post = new PublicationPost($post_id);
        $this->assertEquals(self::$PUBLICATION_ARGS['title'], $publication_post->title);
        $this->assertEquals(self::$PUBLICATION_ARGS['abstract'], $publication_post->abstract);
        $this->assertEquals(self::$PUBLICATION_ARGS['scopus_id'], $publication_post->scopus_id);
        $this->assertIsInt($publication_post->author_count);
        $this->assertEquals(self::$PUBLICATION_ARGS['author_count'], $publication_post->author_count);
        $this->assertIsArray($publication_post->authors);
        $this->assertEquals(4, count($publication_post->authors));
    }

    public function test_save_basically_works() {
        $post_id = PublicationPost::insert(self::$PUBLICATION_ARGS);
        $publication_post = new PublicationPost($post_id);

        // Now we modify some of the values of this wrapper instance, call the save method and try if the changes
        // are persistent by reloading from the database
        $new_title = 'How to test';
        $publication_post->title = $new_title;

        $new_kitopen_id = '120';
        $publication_post->kitopen_id = $new_kitopen_id;

        $publication_post->save();
        $publication_post = new PublicationPost($post_id);

        $this->assertEquals($new_title, $publication_post->title);
        $this->assertEquals($new_kitopen_id, $publication_post->kitopen_id);
    }

    public function test_save_taxonomy_terms_works() {
        $post_id = PublicationPost::insert(self::$PUBLICATION_ARGS);
        $publication_post = new PublicationPost($post_id);

        // Adding topic and term and observed author.
        array_push($publication_post->topics, 'Computer Science');
        array_push($publication_post->tags, 'Optimization');
        array_push($publication_post->tags, 'Genetic Algorithms');

        $publication_post->save();
        $publication_post = new PublicationPost($post_id);

        $this->assertIsArray($publication_post->topics);
        $this->assertEquals(1, count($publication_post->topics));
        $this->assertEquals('Computer Science', $publication_post->topics[0]);

        $this->assertIsArray($publication_post->tags);
        $this->assertEquals(2, count($publication_post->tags));
    }

}