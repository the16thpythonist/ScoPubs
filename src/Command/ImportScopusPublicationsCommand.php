<?php


namespace Scopubs\Command;


use Scopubs\Publication\PublicationPost;
use Scopubs\Scopus\ScopusPublicationFetcher;
use Scopubs\Util;


/**
 * Class ImportScopusPublicationsCommand
 *
 * This command
 *
 * @package Scopubs\Command
 */
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
        ],
        'max_author_count' => [
            'name'                  => 'max_author_count',
            'description'           => 'How many max. authors to be saved with each publication. Reducing the amount ' .
                                       'of author meta information is recommended for performance reasons.',
            'type'                  => 'int',
            'validators'            => ['validate_is_int'],
            'default'               => 20
        ]
    ];

    public function run( array $args ) {
        $publication_posts = PublicationPost::all();
        $existing_scopus_ids = array_map(function($p) {return $p->scopus_id; }, $publication_posts);
        $fetcher = new ScopusPublicationFetcher($this->log, [
            'exclude_ids'           => $existing_scopus_ids,
            'more_recent_than'      => $args['more_recent_than']
        ]);

        foreach($fetcher->next() as $insert_args) {
            $insert_args['authors'] = Util::array_limit($insert_args['authors'], $args['max_author_count']);
            $post_id = PublicationPost::insert($insert_args);
            $publication_post = new PublicationPost($post_id);

            $publication_post->tags = $insert_args['tags'];
            $publication_post->topics = $insert_args['topics'];
            $publication_post->observed_authors = $insert_args['observed_authors'];

            $this->log->info(sprintf(
                '<a href="%s">PUBLICATION "%s" (%s)</a>',
                $publication_post->get_edit_url(),
                $publication_post->title,
                $publication_post->scopus_id
            ));
            $this->log->save();
            $publication_post->save();
        }
    }

}