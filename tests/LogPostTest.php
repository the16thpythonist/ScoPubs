<?php

use Scopubs\Log\LogPost;


class LogPostTest extends WP_UnitTestCase {

    // -- Registration of the "Log" post type

    public function test_post_type_exists() {
        $this->assertTrue( post_type_exists(LogPost::$post_type) );
    }

    // -- The actual "LogPost" wrapper class

    public function test_create_postarr_basically_works() {
        $args = [
            'title'         => 'My first Log',
            'type'          => 'test',
            'running'       => false,
            'entries'       => []
        ];

        $expected_postarr = [
            'post_title'    => 'My first Log',
            'post_status'   => 'publish',
            'post_type'     => LogPost::$post_type,
            'meta_input'    => [
                'type'      => 'test',
                'running'   => false,
                'entries'   => []
            ]
        ];

        $postarr = LogPost::create_postarr($args);
        $this->assertIsArray($postarr);
        $this->assertEquals($expected_postarr, $postarr);
    }

    public function test_create_postarr_strict_error() {
        // The create_postarr method accepts the additional boolean flag strict which will cause an
        // exception if not all required keys are present. Thus we try to evoke this error with an incomplete $args
        $args = [
            'title'         => 'This is incomplete'
        ];

        $this->expectException(InvalidArgumentException::class);
        LogPost::create_postarr($args, true);
    }

    public function test_create_postarr_not_strict() {
        // If the strict flag is not set, even an incomplete array should work. this is required for the update method
        // where not all arguments have to be present
        $args = [
            'title'         => 'This is incomplete, but should work anyways'
        ];

        $postarr = LogPost::create_postarr($args, false);
        $this->assertIsArray($postarr);
    }

    public function test_insert_basically_works() {
        // if the static "insert" method works by creating a new post object and if this post object can be loaded
        // properly into a wrapper object
        $args = [
            'title'         => 'My first Log',
            'type'          => 'test',
            'running'       => false,
            'entries'       => []
        ];

        $post_id = LogPost::insert($args);
        $log_post = new LogPost($post_id);

        $this->assertEquals($args['title'], $log_post->title);
        $this->assertEquals($args['running'], $log_post->running);
        $this->assertEquals($args['entries'], $log_post->entries);
    }

    public function test_update_basically_works() {

        // Creating the base post
        $args = [
            'title'         => 'My first Log',
            'type'          => 'test',
            'running'       => false,
            'entries'       => []
        ];

        $post_id = LogPost::insert($args);
        $log_post = new LogPost($post_id);
        $this->assertEquals($args['title'], $log_post->title);

        // Updating a property, reloading and see if the change is persistent
        $update_args = [
            'title'         => 'Updated Title',
            'running'       => true
        ];
        LogPost::update($post_id, $update_args);
        $log_post = new LogPost($post_id);

        $this->assertEquals($update_args['title'], $log_post->title);
        $this->assertEquals($update_args['running'], $log_post->running);
    }

    public function test_create_basically_works() {
        // The "create" method is a convenience method for directly creating a new LogPost wrapper object for a post
        // which previously did not exist
        $title = 'New Log';
        $log_post = LogPost::create($title);

        $this->assertEquals($title, $log_post->title);
    }

    public function test_save_basically_works() {
        // The "save" method on an object should be able to save the current instance values to the database
        $args = [
            'title'         => 'My first Log',
            'type'          => 'test',
            'running'       => false,
            'entries'       => []
        ];

        $post_id = LogPost::insert($args);
        $log_post = new LogPost($post_id);

        $title = 'New title';
        $log_post->title = $title;

        array_push($log_post->entries, [
            'message'       => 'test',
            'level'         => 'info',
            'time'          => ''
        ]);
        $log_post->save();

        // Reloading and checking if the changes persist
        $log_post = new LogPost($post_id);
        $this->assertEquals($title, $log_post->title);
        $this->assertIsArray($log_post->entries);
        $this->assertEquals(1, count($log_post->entries));
        $this->assertIsArray($log_post->entries[0]);
    }

    public function test_logging_basically_works() {
        // Creating a new log post and using it like intended
        $log_post = LogPost::create('New Log');
        $post_id = $log_post->post_id;

        $log_post->start();
        $log_post->info('Hello');
        $log_post->info('World');
        $log_post->error('!!!');
        $log_post->close();

        $this->assertEquals(3, count($log_post->entries));

        // Saving and reloading
        $log_post = new LogPost($post_id);
        $this->assertEquals(3, count($log_post->entries));
        $this->assertIsArray($log_post->entries[0]);
        $this->assertEquals('Hello', $log_post->entries[0]['message']);
    }

    public function test_countable_interface_implementation() {
        // LogPost implements the Countable interface, which means that it should be possible to call the "count"
        // function directly on the instance itself
        $log_post = LogPost::create('New Log');
        $post_id = $log_post->post_id;

        $log_post->start();
        $log_post->info('Hello');
        $log_post->info('World');
        $log_post->error('!!!');
        $log_post->close();

        $this->assertEquals(3, count($log_post));
    }
}