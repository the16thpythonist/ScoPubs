<?php


namespace Scopubs\Scopus;

use Closure;

use Scopus\Response\Abstracts;
use Scopus\Response\AbstractAuthor;
use Scopus\Response\AbstractCoredata;


/**
 * This class wraps access to the important information contained in the Scopus\Response\Abstracts representation of
 * scopus publications. These kinds of objects are created by the third party scopus api package which is used for the
 * network communication with the scopus database.
 *
 * **MOTIVATION**
 *
 * This package uses a third party package "kasparsj/scopus-search-api" for the actual network communication with the
 * scopus database. This package wraps the responses to different requests in specific classes. As such the class
 * "Scopus\Response\Abstracts" represents a single publication retrieved from a search request. This class does
 * ultimately contain all the information we need, but the access is partly restricted and some information first has
 * to be computed / synthesised. This is what this class wraps. It exposes a set of simpler methods, which hide the
 * potentially difficult computations required to extract this information.
 *
 * **USAGE**
 *
 * The main usage for this class is probably the "to_args" method. This method automatically creates a new insert $args
 * array, which can be directly passed to the PublicationPost::insert method to create a new publication post.
 * But before this method can be invoked, a new instance of the adapter class has to be constructed. The constructor
 * needs to arguments: The "Abstracts" instance which represents the actual publication and a list of all the
 * ObservedAuthorPost objects which are known to wordpress.
 *
 *      // Assuming $publication is known and an instance of Scopus\Response\Abstracts
 *      $author_posts = ObservedAuthorPost::all();
 *      $adapter = new ScopusPublicationAdapter($publication, $author_posts);
 *
 *      $args = $adapter->to_args();
 *      PublicationPost::insert($args);
 *
 * This greatly simplifies the process of importing a scopus publication record as a local PublicationPost.
 *
 * Class ScopusPublicationAdapter
 * @package Scopubs\Scopus
 */
class ScopusPublicationAdapter {

    public $publication;
    public $author_posts;

    public $authors;
    public $observed_authors;

    public function __construct(Abstracts $publication, array $author_posts) {
        $this->publication = $publication;
        $this->author_posts = $author_posts;

        $this->coredata = $publication->getCoredata();

        // The following properties are derived properties. They first have to be computed from the publication
        // data. We save the results here as instance properties because they are needed for calculating other
        // properties as well and it would be wasteful to redo those (fairly expensive) computations again.

        // This will be a list of assoc arrays, where each array value describes one author of the publication
        $this->authors = $this->get_authors();
        // This will be an assoc array containing all the observed author post instances for those observed authors
        // which have participated on this publication.
        $this->observed_authors = $this->get_observed_authors();
    }

    /**
     * Converts this publication to an insert $args array which can be directly used for the PublicationPost::insert
     * method to create a new publication post on the wordpress site.
     *
     * Additional to the required fields, the resulting array contains more fields however. These fields contain the
     * values which have to be set as the taxonomy terms of the publication:
     * - tags: A list of strings, where each string represents one of the tags set for this publication. May be empty
     * - topics: A list of strings, where each string represents one of the topics set for this publication
     * - observed authors: A list of ObservedAuthorPost instances, one for each observed author which has contributed
     *   to this publication.
     *
     * @return array
     */
    public function to_args() {
        $authors = $this->get_authors();
        return [
            'title'                 => $this->get_title(),
            'abstract'              => $this->get_abstract(),
            'publish_date'          => $this->get_publish_date(),
            'scopus_id'             => $this->get_scopus_id(),
            'doi'                   => $this->get_doi(),
            'eid'                   => $this->get_eid(),
            'issn'                  => '',
            'journal'               => $this->get_journal(),
            'volume'                => $this->get_volume(),
            'authors'               => $authors,
            'author_count'          => count($authors),
            // The following fields are not actually required for the insert itself, but they are information about the
            // publication which for example needs to get processed into taxonomy tags etc.
            'tags'                  => $this->get_tags(),
            'observed_authors'      => array_values($this->observed_authors),
            'topics'                => $this->get_topics(),
        ];
    }

    /**
     * Returns a list with the string topics applying to this publication. The topics are a derived property which
     * originates from the observed authors of this publication. Topics are originally assigned to observed authors.
     * A publication gets assigned the combination of all topics from each of its observed authors.
     *
     * @return array
     */
    public function get_topics() {
        $topics = [];
        foreach ($this->observed_authors as $author_post) {
            $topics += $author_post->author_topics;
        }
        return array_unique($topics);
    }

    /**
     * Returns a list of string wordpress post ids(!) for each ObservedAuthorPost belonging to this publication
     *
     * @return array
     */
    public function get_observed_author_post_ids() {
        $post_ids = [];
        foreach($this->observed_authors as $author_post) {
            $post_ids[] = $author_post->post_id;
        }
        return $post_ids;
    }

    /**
     * Returns an assoc array, which contains all the observed author posts for all those observed authors which have
     * participated on this publication. The assoc array keys are the scopus author ids and the values are the
     * corresponding ObservedAuthorPost instances.
     *
     * @return array
     */
    public function get_observed_authors() {
        $observed_authors = [];
        foreach ($this->authors as $author_spec) {
            foreach ($this->author_posts as $author_post) {
                $author_id = $author_spec['scopus_author_id'];
                if (in_array($author_id, $author_post->scopus_author_ids)) {
                    $observed_authors[$author_id] = $author_post;
                    break;
                }
            }
        }
        return $observed_authors;
    }

    /**
     * Returns a list of strings, where each string is a tag set for the publication
     *
     * @return array
     */
    public function get_tags() {
        $data = self::data_from_publication($this->publication);
        if (array_key_exists('idxterms', $data)) {
            $main_term = $data['idxterms']['mainterm'];
            $tags = array_map(function($e) { return $e['$']; }, $main_term);
            return $tags;
        } else {
            return [];
        }
    }

    /**
     * Returns the volume of the journal in which this publication was published.
     *
     * @return mixed
     */
    public function get_volume() {
        $volume = $this->coredata->getVolume();
        return ($volume !== null ? strval($volume) : '');
    }

    /**
     * Returns the name of the journal in which this publication was published
     *
     * @return mixed|null
     */
    public function get_journal() {
        return $this->coredata->getPublicationName();
    }

    /**
     * Returns the EID for the publication
     *
     * @return string
     */
    public function get_eid() {
        // Sadly, the EID does not have it's own wrapper method, thus we need to retrieve it from the raw data array
        // of the REST response for the coredata.
        $data = self::data_from_coredata($this->coredata);
        if (array_key_exists('eid', $data)) {
            return $data['eid'];
        } else {
            return '';
        }
    }

    /**
     * Returns the DOI for this publication
     *
     * @return mixed|null
     */
    public function get_doi() {
        $doi = $this->coredata->getDoi();
        return ($doi !== null ? strval($doi) : '');
    }

    /**
     * Returns the string scopus ID for this publication
     *
     * @return string
     */
    public function get_scopus_id() {
        return $this->coredata->getScopusId();
    }

    /**
     * Returns the string abstract / short description for this publication
     *
     * @return mixed
     */
    public function get_abstract() {
        $abstract = $this->coredata->getDescription();
        return ($abstract !== null ? strval($abstract) : '');
    }

    /**
     * Returns the full string title of the publication
     *
     * @return string
     */
    public function get_title() {
        return $this->coredata->getTitle();
    }

    /**
     * Returns the date of publication
     *
     * @return mixed|null
     */
    public function get_publish_date() {
        return $this->coredata->getCoverDate();
    }

    /**
     * Returns a list of assoc arrays where each assoc array element consists of key value pairs which describe one
     * author of this publication.
     *
     * The assoc array elements contain the following fields:
     * - scopus_author_id: The string scopus author id for that author
     * - indexed_name: The string indexed name for that author.
     * - (optional) affiliation_id: The string affiliation id for the author while working on this publication. Be
     *   aware, that this is an optional field. This information is not given for all authors and for those where
     *   it is missing, this field does not exist.
     *
     * @return array
     */
    public function get_authors() {
        $authors = [];
        $publication_authors = $this->publication->getAuthors();

        foreach ($publication_authors as $author) {
            $_entry = [
                'scopus_author_id'          => $author->getId(),
                'indexed_name'              => $author->getIndexedName()
            ];

            // Here we attempt to add the information about the affiliation ID to the result as well. Sadly there is
            // no wrapper method for retrieving this information. Even more sadly is that the raw data array at the
            // heart of the AbstractAuthor class is proteced and we have to retrieve it with a dirty hack. Which is
            // exactly what "data_from_author" wraps.
            $_data = self::data_from_author($author);
            // We actually have to check for the existence of these keys here because the affiliation information
            // is not always provided for all authors! This implies that also with our resulting entry the
            // "affiliation_id" field is optional.
            if (array_key_exists('affiliation', $_data) && array_key_exists('@id', $_data['affiliation'])) {
                $_entry['affiliation_id'] = $_data['affiliation']['@id'];
            }

            $authors[] = $_entry;
        }

        return $authors;
    }

    /**
     * Returns an assoc array, where the keys are the string scopus author ids and the values are their
     * corresponding affiliation ids while working on this publication.
     *
     * Note that this array may contain less entries as there are total authors for this publication! This is because
     * the affiliation information is not always given for every author and those authors where there is not affiliation
     * id are omitted.
     *
     * @return array
     */
    public function get_author_affiliations() {
        $author_affiliations = [];
        $authors = $this->get_authors();
        foreach($authors as $author_spec) {
            if (array_key_exists('affiliation_id', $author_spec)) {
                $author_id = $author_spec['scopus_author_id'];
                $affiliation_id = $author_spec['affiliation_id'];
                $author_affiliations[$author_id] = $affiliation_id;
            }
        }
        return $author_affiliations;
    }

    // -- Utility methods

    /**
     * Given one of the publications author objects, this method returns the raw data array at the heart of the author
     * representation. Sadly this is a protected field and we have to retrieve it with a dirty hack.
     *
     * @param AbstractAuthor $author The author whose "data" property to extract.
     *
     * @return array The raw "data" array derived from the REST response.
     */
    public static function data_from_author(AbstractAuthor $author) {
        // This is a nifty hack to return a protected member of an object. We define a dynamic method which returns the
        // value and then dynamically bind this function as a public method to the object and then invoke it.
        $closure = function () { return $this->data; };
        return Closure::bind($closure, $author, AbstractAuthor::class)();
    }

    /**
     * Given the the coredata object of a publication, this method returns the raw data array at the heart of the
     * coredata representation. Sadly this is a protected field and we have to retrieve it with a dirty hack.
     *
     * @param AbstractCoredata $coredata
     *
     * @return array The raw "data" array derived from the REST response.
     */
    public static function data_from_coredata(AbstractCoredata $coredata) {
        $closure = function () { return $this->data; };
        return Closure::bind($closure, $coredata, AbstractCoredata::class)();
    }

    /**
     * Given the publication instance, this method returns the raw data at the heart of the
     * publication representation. Sadly this is a protected field and we have to retrieve it with a dirty hack.
     *
     * @param Abstracts $publication
     *
     * @return array The raw "data" array derived from the REST response.
     */
    public static function data_from_publication(Abstracts $publication) {
        $closure = function () { return $this->data; };
        return Closure::bind($closure, $publication, Abstracts::class)();
    }
}