<?php


namespace Scopubs\Scopus;

use DateTime;

use Scopubs\Author\ObservedAuthorPost;
use Scopubs\Publication\AbstractPublicationFetcher;
use Scopus\ScopusApi;
use Scopus\Response\Abstracts;
use Scopus\Response\AbstractAuthor;

use Scopubs\Options;


/**
 * Class ScopusPublicationFetcher
 *
 * **DESIGN CHOICE**
 *
 * Usually I would argue about separation of concerns in the sense that a publication "fetcher" should only be
 * concerned about, well, fetching publications. The best thing would be to keep the input output system as simple as
 * possible. A list of publication ids goes in, the fetcher makes the api requests and returns the results. It should
 * not have to worry about whether or not a publication meets all the requirements, caching meta properties etc.
 * But from experience I know that this process is extremely slow and every single publication that does not actually
 * have to be fetched makes the difference. In this case, the performance considerations are actually more important
 * than the simplicity... This is why the meta cache exists, why the fetcher has a parameter that allows the
 * exclusion of already posted publications and so on.
 *
 * @package Scopubs\Scopus
 */
class ScopusPublicationFetcher extends AbstractPublicationFetcher{

    public static $parameters = [
        'exclude_ids' => [
            'type'              => 'string',
            'default'           => [],
            'validators'        => ['validate_is_array']
        ],
        'step_size' => [
            'type'              => 'int',
            'default'           => 100,
            'validators'        => ['validate_is_int']
        ],
        'more_recent_than' => [
            'type'              => 'string',
            'default'           => '2010-01-01',
            'validators'        => ['validate_is_string']
        ]
    ];

    public $scopus_api;
    public $meta_cache;
    public $id_author_map;

    // -- Current state of generator
    public $current_id;
    public $current_publication;
    public $current_adapter;

    public $scopus_authors = [];
    public $scopus_ids = [];

    public function __construct($log, $args) {
        parent::__construct($log, $args);

        foreach ($this->observed_authors as $author_post) {
            foreach ($author_post->scopus_author_ids as $author_id) {
                $this->id_author_map[$author_id] = $author_post;
            }
        }

        // Creating a new ScopusApi instance
        $this->scopus_api = new ScopusApi(Options::get('scopus_api_key'));
        // Creating a new instance of the meta cache
        $this->meta_cache = new ScopusMetaCache();

        $this->log->info(sprintf(
           'Fetching publications for %s observed authors',
            count($this->observed_authors)
        ));
    }

    public function next() {
        // So what is the general idea of this process?
        // 1. We have the list of all observed authors. We can use this list and the scopus author IDs of each author
        //    to query the scopus API and get a list of all publications scopus IDs.
        // 2. Then we first attempt to retrieve the meta information about the publication from the scopus meta cache
        //    and then check based on this meta data if the publication even needs to be retrieved. If not we skip it
        // 3. If all checks return that this is really a publication we want to retrieve, we actually query scopus
        //    to retrieve all details. This scopus representation of the publication we insert into the meta cache and
        //    then check again if we really want it based on the meta data.
        // 4. Only if these seconds checks also come back positive, we plug the scopus representation into the
        //    appropriate adapter to derive the appropriate PublicationPost $args insert array from it.

        $this->fetch_scopus_ids();

        $this->exclude_scopus_ids();

        foreach($this->scopus_ids as $scopus_id) {
            $this->current_id = $scopus_id;

            $in_cache = $this->meta_cache->contains($scopus_id);
            $is_valid = $this->check_publication($scopus_id);
            if (!$in_cache || $is_valid) {
                try {
                    $this->fetch_publication($scopus_id);
                } catch (\Error $e) {
                    $this->log->warning(sprintf(
                        'The publication with the scopus id %s could not be fetched because of error: %s',
                        $scopus_id,
                        $e->getMessage()
                    ));
                    continue;
                }
            }

            $this->current_adapter = new ScopusPublicationAdapter(
                $this->current_publication,
                $this->observed_authors
            );
            // Now we update the meta cache with this publication and since the publication is then in the cache we
            // can simple recycle the existing functionality of "check_publication" (which checks based on the cache)
            // to again determine if we really want to insert this publication
            $this->update_cache();
            $is_valid = $this->check_publication($scopus_id);
            if ($is_valid) {
                yield $this->current_adapter->to_args();
            }
        }
    }

    public function update_cache() {
        $cache_args = [
            'publish_date'              => $this->current_adapter->get_publish_date(),
            'author_affiliations'       => $this->current_adapter->get_author_affiliations(),
            'observed_author_post_ids'  => $this->current_adapter->get_observed_author_post_ids()
        ];
        $this->meta_cache->update($this->current_id, $cache_args);

        $this->log->debug(sprintf(
            'Updating meta cache for publication %s',
            $this->current_id
        ));
    }

    public function fetch_publication($scopus_id) {
        $this->current_publication = $this->scopus_api->retrieveAbstract($scopus_id);
    }

    public function check_publication($scopus_id): bool {
        if ($this->meta_cache->contains($scopus_id)) {
            // Checking for blacklist
            $is_blacklisted = $this->check_publication_blacklisted($scopus_id);
            if ($is_blacklisted) { return $is_blacklisted; }

            // Checking for age
            $too_old = $this->check_publication_too_old($scopus_id);
            if ($too_old) { return $too_old; }
        }

        return True;
    }

    public function check_publication_blacklisted($scopus_id) {
        $apply_blacklist = $this->args['apply_blacklist'];
        $is_blacklisted = False;

        $author_posts = [];
        foreach ($this->meta_cache[$scopus_id]['observed_author_post_ids'] as $post_id) {
            $author_posts[] = new ObservedAuthorPost($post_id);
        }

        foreach ($this->meta_cache[$scopus_id]['author_affiliation'] as $author_id => $affiliation_id) {
            foreach ($author_posts as $author_post) {
                if (in_array($author_id, $author_post->scopus_author_ids) &&
                    in_array($affiliation_id, $author_post->affiliation_blacklist)) {
                    $is_blacklisted = True;
                    break;
                }
            }
        }

        $value = $apply_blacklist && $is_blacklisted;
        if ($value) {
            $this->log->debug(sprintf(
                'BLACKLISTED "%s" (%s)',
                $this->current_adapter->get_title(),
                $this->current_id
            ));
        }

        return $value;
    }

    public function check_publication_too_old($scopus_id) {
        $publish_datetime = new DateTime($this->meta_cache[$scopus_id]['publish_date']);
        $limit_datetime = new DateTime($this->args['more_recent_than']);
        return $limit_datetime > $publish_datetime;
    }

    public function exclude_scopus_ids() {
        $exclude_ids = $this->args['exclude_ids'];
        $this->scopus_ids = array_unique(array_diff($this->scopus_ids, $exclude_ids));
        $this->log->info(sprintf(
            'After applying the exclude list to the scopus ids, %s publications will ultimately be fetched.',
            count($this->scopus_ids)
        ));
    }

    public function fetch_scopus_ids() {

        foreach ($this->observed_authors as $author_post) {
            $this->log->info(sprintf(
                "Fetching scopus profile for observed author %s with %s scopus ids: %s",
                $author_post->get_full_name(),
                count($author_post->scopus_author_ids),
                implode(', ', $author_post->scopus_author_ids)
            ));

            foreach($author_post->scopus_author_ids as $author_id) {
                $scopus_ids = $this->fetch_scopus_ids_for_author($author_id);
                $this->scopus_ids += $scopus_ids;
                $this->log->info(sprintf(
                    'Found %s publications for author ID %s',
                    count($scopus_ids),
                    $author_id
                ));
            }
        }
    }

    /**
     * Given the scopus $author_id for some author, this method returns a list of string publication scopus ids
     * for all publications associated with this author.
     *
     * Note that this method might take some while, because it may need to make multiple network requests to the scopus
     * web api to retrieve this information.
     *
     * @param string $author_id The string representation of the unique scopus author id
     *
     * @return array list of string publication author ids
     */
    public function fetch_scopus_ids_for_author(string $author_id) {
        // Now one could reasonably ask why this method even exists and why it is this long, because the scopus api
        // object has a method "retrieveAuthor" which given the author id returns the author profile. And could we not
        // just call a method like "getAllPublicationIDs" or something the like on this?
        // the sad answer is NO, this feature does not exist. Instead we need to submit a generic publication search
        // query where we specific the author id as the search criteria and we have to derive the publication ids from
        // those search results.
        $scopus_ids = [];
        $search_string = sprintf('AU-ID(%s)', $author_id);
        // Even more sadly, this search functionality is paginated, which means that the amount of results to be
        // retrieved with a single request are limited, which is why we need to do this in a loop, where we send more
        // requests until we have all results.
        $results_remaining = true;
        $step = $this->args['step_size'];
        $index = 0;

        while ($results_remaining) {
            // "query_scopus" creates a new query object based on the search string, which we can use to then make the
            // actual request to the scopus api. We have to catch potential network errors here!
            try {
                $query = $this->query_scopus($search_string);
                $search = $query->start($index)->count($step)->viewStandard()->search();
                $entries = $search->getEntries();
            } catch (\Error $e) {
                $this->log->warning(sprintf(
                    'The scopus search query for the search string %s, index %s and step size %s failed! Thereby ' .
                    'terminating the publication id retrieval for author %s',
                    $search_string,
                    $index,
                    $step,
                    $author_id
                ));
                break;
            }

            foreach($entries as $entry) {
                array_push($scopus_ids, $entry->getScopusId());
            }

            if (count($entries) < $step) {
                $results_remaining = false;
            } else {
                $index += $step;
            }
        }

        return $scopus_ids;
    }

    /**
     * This method creates a scopus search query object based on a search string. This search query can be used to
     * search for publications within the scopus database which all suffice some criteria defined by the search
     * string.
     *
     * @param string $search_string
     *
     * @return mixed The query object which can be used to actually retrieve the
     * @throws \ReflectionException ?
     */
    public function query_scopus(string $search_string) {
        // What we need to do here is a very unfortunate hack. Sadly, the "query" method of the scopus ID which we
        // intend to do here is a protected method. This means we wouldn't normally be able to access it, but there is
        // a workaround as seen here. By creating a reflection class instance we are able to change the accessiblity
        // status for this method and then invoke it nonetheless
        $class = new \ReflectionClass($this->scopus_api);
        $query_method = $class->getMethod('query');
        $query_method->setAccessible(True);
        // Note that this query object does not yet actually perform a network request. The query object itself still
        // has to be invoked the "search" method which then actually sends the request.
        $query = $query_method->invokeArgs($this->scopus_api, [$search_string]);
        return $query;
    }
}