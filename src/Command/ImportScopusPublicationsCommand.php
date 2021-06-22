<?php


namespace Scopubs\Command;


use Scopubs\Publication\PublicationPost;
use Scopubs\Scopus\ScopusPublicationFetcher;

class ImportScopusPublicationsCommand extends AbstractCommand {

    public static $name = 'import_scopus_publications';
    public static $description = 'This command will attempt to import all publications by all the observed authors ' .
                                 'from the scopus web publication database, which satisfy the conditions. The ' .
                                 'detailed information will be retrieved from the scopus REST api and posted as ' .
                                 'Publications Posts on this site';
    public static $parameters = [
        'more_recent_than' => [
            'name'                  => 'more_recent_than',
            'description'           => 'Import only publications which were published after this date',
            'type'                  => 'string',
            'validators'            => ['validate_is_string'],
            'default'               => '2010-01-01'
        ],
        'apply_blacklist' => [
            'name'                  => 'apply_blacklist',
            'description'           => 'Whether or not to take the authors affiliation blacklist into account',
            'type'                  => 'boolean',
            'validators'            => ['validate_is_boolean'],
            'default'               => True
        ]
    ];

    public function run( array $args ) {

        $publication_posts = PublicationPost::all();
        $existing_scopus_ids = array_map(function($p) {return $p->scopus_id; }, $publication_posts);
        $fetcher = new ScopusPublicationFetcher($this->log, [
            'exclude_ids'           => $existing_scopus_ids
        ]);

        foreach($fetcher->next() as $args) {
            $post_id = PublicationPost::insert($args);
            $publication_post = new PublicationPost($post_id);

            $publication_post->tags = $args['tags'];
            $publication_post->topics = $args['topics'];
            $publication_post->observed_authors = $args['observed_authors'];
        }
    }

}