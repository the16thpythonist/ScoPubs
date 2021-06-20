<?php


namespace Scopubs\Scopus;


/**
 * Class ScopusMetaCache
 *
 * This class implements a cache for scopus publication meta data.
 *
 * **MOTIVATION**
 *
 * Now the first natural question would be: What for? The simple motivation is that api requests to the scopus web
 * database are expensive operations. The fact is also that the scopus database is rather stable, once information is
 * in it, it usually doesnt change. Now many publications which turn up through the observed authors will be
 * dismissed by some meta criteria. Either due to the author affiliations or due to the it being too old (referring to
 * the publishing date). If a publication was fetched once, for the sake of efficiency it really doesn't have to be
 * fetched over and over again, since the meta values will most likely not change. This is the idea: For all
 * publications, we save the relevant meta information in this cache and can then infer if it can be dismissed right
 * away without performing the expensive request.
 *
 * **OPTION IMPLEMENTATION**
 *
 * Internally the cache is realized as a wordpress option. This wordpress option will hold an assoc. array whose keys
 * are the scopus ids for the publications and the values are also assoc. arrays which contain the relevant meta
 * information about the publications. In the constructor this option is loaded into the internal "data" property,
 * which is the same assoc dict. This can then be modified / interacted with and then saved to the option again by
 * calling the "save" method.
 *
 * **USAGE**
 *
 * The scopus meta cache can be used by creating a new instance. The constructor will load the actual cache (which is
 * actually an assoc array). This class implements the "ArrayAccess" and "Countable" interfaces, which means the object
 * itself can mainly be treated like an assoc array itself. The only difference is that the custom "contains" method
 * should be used to check if a record exits for any given scopus id. It is important that at the end of using the
 * cache, the "save" method has to be called to persist all the changes into the database.
 *
 *      $meta_cache = new ScopusMetaCache();
 *      $meta_cache->contains('12');
 *      var_dump($meta_cache['12']);
 *      count($meta_cache);
 *
 *
 * @package Scopubs\Scopus
 */
class ScopusMetaCache implements \ArrayAccess, \Countable {

    public static $option_name = 'scopus_meta_cache';
    public static $datetime_format = 'Y-m-d H:i:s';
    public static $lifetime = 30;

    public $data;

    public function __construct() {
        $this->data = get_option(self::$option_name, []);
    }

    /**
     * Saves the changes made to the meta cache to the actual option value of the wordpress database. This method has
     * to be called at the end of making any modifications for those modifications to actually be persistently saved!
     *
     * @returns void
     */
    public function save() {
        update_option(self::$option_name, $this->data);
    }

    /**
     * Returns boolean value of whether or not an entry for the publication identified by $scopus_id exists.
     *
     * @param string $scopus_id The string scopus id for the publication
     *
     * @return bool
     */
    public function contains(string $scopus_id) {
        if (array_key_exists($scopus_id, $this->data)) {
            if ($this->is_lifetime_exceeded($scopus_id)) {
                unset($this->data[$scopus_id]);
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    public function is_lifetime_exceeded(string $scopus_id) {
        $added_datetime = new \DateTime($this->data[$scopus_id]['__added']);
        $current_datetime = new \DateTime(date(self::$datetime_format));
        $date_interval = $added_datetime->diff($current_datetime);
        return $date_interval->days > self::$lifetime;
    }

    /**
     * The assoc array $publication_args HAS TO contain the following fields:
     * -
     *
     * @param string $scopus_id
     * @param array $publication_args
     */
    public function update(string $scopus_id, array $publication_args) {
        $publication_meta = $publication_args;
        $publication_meta['__added'] = date(self::$datetime_format);
        $this->data[$scopus_id] = $publication_args;
    }

    // -- Implement "ArrayAccess"

    public function offsetGet( $offset ) {
        return $this->data[$offset];
    }

    public function offsetSet( $offset, $value ) {
        $this->data[$offset] = $value;
    }

    public function offsetExists( $offset ) {
        return isset($this->data[$offset]);
    }

    public function offsetUnset( $offset ) {
        unset($this->data[$offset]);
    }

    // -- Implements "Countable"

    public function count() {
        return count($this->data);
    }
}