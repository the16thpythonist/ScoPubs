<?php

use Scopubs\Scopus\ScopusMetaCache;


class ScopusMetaCacheTest extends WP_UnitTestCase{

    public function test_construction() {
        // If a new instance can be created without errors
        $meta_cache = new ScopusMetaCache();
        $this->assertIsArray($meta_cache->data);
    }

    public function test_array_access() {
        // If the array access on the meta cache object itself works
        // -> The ScopusMetaCache implements the interface "ArrayAccess" which should make it possible to access the
        // elements of the cache by using array indexing on the object itself.

        $meta_cache = new ScopusMetaCache();
        // Adding a new value
        $meta_cache['1'] = true;
        $this->assertTrue($meta_cache->data['1']);
        // Retrieving a value by index
        $this->assertTrue($meta_cache['1']);
        // Checking if an entry exists
        $this->assertTrue(isset($meta_cache['1']));
        $this->assertFalse(isset($meta_cache['2']));
        // Deleting an element
        unset($meta_cache['1']);
        $this->assertFalse(isset($meta_cache['1']));
    }

    public function test_lifetime_exceeded() {
        // First we check if the lifetime exceeded is false for an entry where that absolutely cannot be the case
        // which would be an entry with the current time and if we check immediately after, there should barely be any
        // time differential at all.
        $scopus_id = '1';
        $meta_cache = new ScopusMetaCache();
        $meta_cache[$scopus_id] = ['__added' => date($meta_cache::$datetime_format)];
        $this->assertFalse($meta_cache->is_lifetime_exceeded($scopus_id));
        // Now as a second case we construct an entry where the lifetime is definitely exceeded. Something like a few
        // years ago
        $date_time = new DateTime('2000-01-01 00:00:00');
        $meta_cache[$scopus_id] = ['__added' => $date_time->format($meta_cache::$datetime_format)];
        $this->assertTrue($meta_cache->is_lifetime_exceeded($scopus_id));
    }

    public function test_contains() {
        // First we check if the lifetime exceeded is false for an entry where that absolutely cannot be the case
        $scopus_id = '1';
        $meta_cache = new ScopusMetaCache();
        $meta_cache[$scopus_id] = ['__added' => date($meta_cache::$datetime_format)];
        $this->assertTrue($meta_cache->contains($scopus_id));

        // Now as a second case we construct an entry where the lifetime is definitely exceeded. Something like a few
        // years ago
        $date_time = new DateTime('2000-01-01 00:00:00');
        $meta_cache[$scopus_id] = ['__added' => $date_time->format($meta_cache::$datetime_format)];
        $this->assertFalse($meta_cache->contains($scopus_id));

        // should also return false for an entry that it generally does not know
        $this->assertFalse($meta_cache->contains('2'));
    }

    public function test_saving_and_loading_option() {
        $scopus_id = '1';
        $meta_cache = new ScopusMetaCache();
        $meta_cache[$scopus_id] = ['__added' => date($meta_cache::$datetime_format)];
        $this->assertEquals(1, count($meta_cache));

        // Now if we save and reload, the entry should still be in there
        $meta_cache->save();
        $meta_cache = new ScopusMetaCache();
        $this->assertEquals(1, count($meta_cache));
        $this->assertTrue($meta_cache->contains($scopus_id));
    }

}