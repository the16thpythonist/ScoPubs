<?php

use PHPUnit\Framework\TestCase;
use Scopubs\Author\ObservedAuthorPost;

class ObservedAuthorPostTest extends TestCase {

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
}
