<?php


namespace Scopubs\Scopus;

use Closure;

use Scopus\Response\Abstracts;
use Scopus\Response\AbstractAuthor;
use Scopus\Response\AbstractCoredata;


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
            'observed_authors'      => $this->get_observed_authors(),
            'topics'                => $this->get_topics(),
        ];
    }

    public function get_topics() {
        $topics = [];
        foreach ($this->observed_authors as $author_post) {
            $topics += $author_post->author_topics;
        }
        return array_unique($topics);
    }

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

    public function get_tags() {
        $data = $this->data_from_publication($this->publication);
        if (array_key_exists('idxterms', $data)) {
            $main_term = $data['idxterms']['mainterm'];
            $tags = array_map(function($e) { return $e['$']; }, $main_term);
            return $tags;
        } else {
            return [];
        }
    }

    public function get_volume() {
        return $this->coredata->getVolume();
    }

    public function get_journal() {
        return $this->coredata->getPublicationName();
    }

    public function get_eid() {
        $data = $this->data_from_coredata($this->coredata);
        if (array_key_exists('eid', $data)) {
            return $data['eid'];
        } else {
            return '';
        }
    }

    public function get_doi() {
        return $this->coredata->getDoi();
    }

    public function get_scopus_id() {
        return $this->coredata->getScopusId();
    }

    public function get_abstract() {
        return $this->coredata->getDescription();
    }

    /**
     * Returns the full string title of the publication
     *
     * @return mixed|null
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
            $_data = $this->data_from_author($author);
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
    public function data_from_author(AbstractAuthor $author) {
        // This is a nifty hack to return a protected member of an object. We define a dynamic method which returns the
        // value and then dynamically bind this function as a public method to the object and then invoke it.
        $closure = function () { return $this->data; };
        return Closure::bind($closure, $author, AbstractAuthor::class)();
    }

    public function data_from_coredata(AbstractCoredata $coredata) {
        $closure = function () { return $this->data; };
        return Closure::bind($closure, $coredata, AbstractCoredata::class)();
    }

    public function data_from_publication(Abstracts $publication) {
        $closure = function () { return $this->data; };
        return Closure::bind($closure, $publication, Abstracts::class)();
    }
}