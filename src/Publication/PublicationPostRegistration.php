<?php


namespace Scopubs\Publication;


class PublicationPostRegistration
{
    public $post_type;

    public $publication_topic_taxonomy;
    public $publication_tag_taxonomy;
    public $publication_observed_author_taxonomy;

    /**
     * ObservedAuthorPostRegistration constructor.
     */
    public function __construct() {
        $this->post_type = PublicationPost::$post_type;

        $this->publication_topic_taxonomy = PublicationPost::$publication_topic_taxonomy;
        $this->publication_tag_taxonomy = PublicationPost::$publication_tag_taxonomy;
        $this->publication_observed_author_taxonomy = PublicationPost::$publication_observed_author_taxonomy;
    }

    /**
     * This method actually performs all the registration tasks. It should be called in the top level plugin code.
     *
     * @return void
     */
    public function register(){
        // The method "register_post_type" actually calls the "register_post_type" function of wordpress with all the
        // necessary arguments for the author post type. New post types need to be registered during the "init" action
        add_action( 'init', [$this, 'register_post_type'] );

        // Registers all post meta
        add_action( 'init', [$this, 'register_post_meta'] );

        // Registers the "Topic" taxonomy for publications
        add_action( 'init', [$this, 'register_publication_topic_taxonomy'] );

        // Registers the "Tag" taxonomy for publications
        add_action( 'init', [$this, 'register_publication_tag_taxonomy'] );

        // Registers the "Observed Author" taxonomy for publications
        add_action( 'init', [$this, 'register_publication_observed_author_taxonomy'] );

        // Registers the meta box for the dashboard edit page
        add_action( 'add_meta_boxes_' . $this->post_type, [$this, 'register_meta_box']);
        // Modifying the JSON response for this post type to also contain the custom meta fields
        // https://wordpress.stackexchange.com/questions/227506/how-to-get-custom-post-meta-using-rest-api
        add_filter( 'rest_prepare_' . $this->post_type, [$this, 'filter_rest_json'], 10, 3);

    }

    /**
     * Calls the wordpress "register_post_type" function with the appropriate arguments for this post type. Should be
     * hooked into the "init" action
     *
     * @return void
     */
    public function register_post_type() {
        register_post_type( $this->post_type, [
                'public'                            => true,
                'publicly_queryable'                => true,
                'show_in_rest'                      => true, # This could be more?
                'show_in_nav_menus'                 => true,
                'show_in_admin_bar'                 => true,
                'exclude_from_search'               => false,
                'show_ui'                           => true,
                'show_in_menu'                      => true,
                'menu_icon'                         => 'dashicons-media-document',
                'hierarchical'                      => false,
                'has_archive'                       => 'publications',
                'query_var'                         => 'publication',
                'map_meta_cap'                      => true,

                // Handles the URL structure
                'rewrite' => [
                    'slug'                          => 'publications',
                    'with_front'                    => false,
                    'pages'                         => true,
                    'feeds'                         => true,
                    'ep_mask'                       => EP_PERMALINK,

                ],

                // Features which the post type supports
                'supports' => [
                    'title',
                    'editor',
                    'custom-fields'
                ],

                // Text labels
                'labels' => [
                    'name'                          => 'Publications',
                    'singular_name'                 => 'Publication',
                    'add_new'                       => 'Add New',
                    'add_new_item'                  => 'Add New Publication',
                    'edit_item'                     => 'Edit Publication',
                    'view_item'                     => 'View Publication',
                    'view_items'                    => 'View Publications',
                    'search_items'                  => 'Search Publications',
                    'not_found'                     => 'No Publications found',
                    'not_found_in_trash'            => 'No Publications found in Trash.',
                    'all_items'                     => 'All Publications',
                    'archives'                      => 'Publication Archives',
                    'attributes'                    => 'Publication Attributes',
                    'insert_into_item'              => 'Append to Publication',
                    'uploaded_to_this_item'         => 'Uploaded to this Publication',
                    'featured_image'                => 'Publication image',
                    'set_featured_image'            => 'Set Publication image',
                    'remove_featured_image'         => 'Remove Publication image',
                    'use_featured_image'            => 'Use as Publication image',
                    'filter_items_list'             => 'Filter Publications list',
                    'items_list_navigation'         => 'Publications list navigation',
                    'items_list'                    => 'Publications list',
                    'item_published'                => 'Publication added',
                    'item_published_privately'      => 'Publication added privately',
                    'item_reverted_to_draft'        => 'Publication deactivated',
                    'item_scheduled'                => '-',
                    'item_updated'                  => 'Publication data updated'
                ]
            ]
        );
    }

    /**
     * Registers the "Topic" taxonomy. Publication posts are usually imported automatically so all of its attributes
     * derived somehow. A publication may be imported based on one or more observed authors and each of these authors
     * was assigned a "author topic", these are string descriptors of this authors field of research. Upon importing
     * a publication it will be assigned all the author topics of all it's observed authors and those will end up in
     * this taxonomy.
     *
     * @return void
     */
    public function register_publication_topic_taxonomy() {
        register_taxonomy( $this->publication_topic_taxonomy, $this->post_type, [
            'public'                                => true,
            'show_in_rest'                          => true,
            'show_ui'                               => true,
            'show_in_nav_menus'                     => true,
            'show_tagcloud'                         => true,
            'show_admin_column'                     => true,
            'hierarchical'                          => false,
            'query_var'                             => $this->publication_topic_taxonomy,
            'labels' => [
                'name'                              => 'Topics',
                'single_name'                       => 'Topic',
                'menu_name'                         => 'Topics',
                'name_admin_bar'                    => 'Topic',
                'search_items'                      => 'Search Topics',
                'popular_items'                     => 'Popular Topics',
                'all_items'                         => 'All Topics',
                'edit_item'                         => 'Edit Topic',
                'view_item'                         => 'View Topic',
                'update_item'                       => 'Update Topic',
                'add_new_item'                      => 'Add New Topic',
                'new_item_name'                     => 'New Topic Name',
                'not_found'                         => 'No Topics Found',
                'items_list_navigation'             => 'Topic List Navigation',
                'items_list'                        => 'Topics List'
            ]
        ]);
    }

    /**
     * Registers the "Tag" taxonomy. Sometimes the scopus response for a publication has a list of tags attached
     * these are being translated into taxonomy terms of this taxonomy.
     *
     * @return void
     */
    public function register_publication_tag_taxonomy() {
        register_taxonomy( $this->publication_tag_taxonomy, $this->post_type, [
            'public'                                => true,
            'show_in_rest'                          => true,
            'show_ui'                               => true,
            'show_in_nav_menus'                     => true,
            'show_tagcloud'                         => true,
            'show_admin_column'                     => true,
            'hierarchical'                          => false,
            'query_var'                             => $this->publication_tag_taxonomy,
            'labels' => [
                'name'                              => 'Tags',
                'single_name'                       => 'Tag',
                'menu_name'                         => 'Tags',
                'name_admin_bar'                    => 'Tag',
                'search_items'                      => 'Search Tags',
                'popular_items'                     => 'Popular Tags',
                'all_items'                         => 'All Tags',
                'edit_item'                         => 'Edit Tag',
                'view_item'                         => 'View Tag',
                'update_item'                       => 'Update Tag',
                'add_new_item'                      => 'Add New Tag',
                'new_item_name'                     => 'New Tag Name',
                'not_found'                         => 'No Tag Found',
                'items_list_navigation'             => 'Tag List Navigation',
                'items_list'                        => 'Tags List'
            ]
        ]);
    }

    /**
     * Registers the "Observed Author" taxonomy for the publication post type. Each publication was imported because
     * at least one observed author is an author of it. The terms of this taxonomy present a link between the two post
     * types where each element is a post ID of an observed author which has collaborated on the publication.
     *
     * @return void
     */
    public function register_publication_observed_author_taxonomy() {
        register_taxonomy( $this->publication_observed_author_taxonomy, $this->post_type, [
            'public'                                => true,
            'show_in_rest'                          => true,
            'show_ui'                               => true,
            'show_in_nav_menus'                     => true,
            'show_tagcloud'                         => true,
            'show_admin_column'                     => true,
            'hierarchical'                          => false,
            'query_var'                             => $this->publication_observed_author_taxonomy,
            'labels' => [
                'name'                              => 'Observed Authors',
                'single_name'                       => 'Observed Author',
                'menu_name'                         => 'Observed Authors',
                'name_admin_bar'                    => 'Observed Author',
                'search_items'                      => 'Search Observed Authors',
                'popular_items'                     => 'Popular Observed Authors',
                'all_items'                         => 'All Observed Authors',
                'edit_item'                         => 'Edit Observed Author',
                'view_item'                         => 'View Observed Author',
                'update_item'                       => 'Update Observed Author',
                'add_new_item'                      => 'Add New Observed Author',
                'new_item_name'                     => 'New Observed Author name',
                'not_found'                         => 'No Observed Author Found',
                'items_list_navigation'             => 'Observed Author List Navigation',
                'items_list'                        => 'Observed Author List'
            ]
        ]);
    }

    /**
     * Registers the post meta fields with wordpress. This is generally not required but it is good practice.
     * (Actually I dont know if it is required when intending to use the REST API?)
     *
     * @return void
     */
    public function register_post_meta() {
        foreach ( PublicationPost::META_FIELDS as $meta_field => $args ) {
            register_post_meta( $this->post_type, $meta_field, $args );
        }
    }

    // -- Registering the meta box --

    public function register_meta_box() {
        add_meta_box(
            $this->post_type . '_meta',
            'Publication Meta Information',
            [$this, 'echo_meta_box'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function echo_meta_box( \WP_Post $post ) {
        ?>
            <script>
                // By using "var" here we are making this a globally accessible variable. It is important that the
                // information about which post ID the current post has is passed to the frontend code this way.
                var POST_ID = <?php echo $post->ID; ?>;
            </script>
            <div id="scopubs-publication-meta-component">
                This Vue component apparently could not be loaded properly.
            </div>
        <?php
    }

    // -- Modify REST response --

    public function filter_rest_json( object $data, \WP_Post $post, $context ) {
        $publication_post = new PublicationPost($post->ID);

        $data->data['title'] = $publication_post->title;
        $data->data['abstract'] = $publication_post->abstract;
        $data->data['scopus_id'] = $publication_post->scopus_id;
        $data->data['kitopen_id'] = $publication_post->kitopen_id;
        $data->data['doi'] = $publication_post->doi;
        $data->data['eid'] = $publication_post->eid;
        $data->data['issn'] = $publication_post->issn;
        $data->data['journal'] = $publication_post->journal;
        $data->data['volume'] = $publication_post->volume;
        $data->data['author_count'] = $publication_post->author_count;
        $data->data['authors'] = $publication_post->authors;

        return $data;
    }

}