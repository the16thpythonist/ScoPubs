<?php


namespace Scopubs\PostTypes;


class PublicationPostRegistration
{
    public $post_type;

    /**
     * ObservedAuthorPostRegistration constructor.
     *
     * @param string $post_type This is supposed to be the string which is to be used as the post type identifier.
     *      this is an argument, because I want to preserve the possibility to change this in case it collided with
     *      the identifier of another plugin. Default is "observed-author"
     */
    public function __construct(string $post_type = "observed-author") {
        $this->post_type = $post_type;
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
                    'excerpt'
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
}