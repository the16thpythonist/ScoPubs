<?php


namespace Scopubs\Author;


class ObservedAuthorPostRegistration
{
    public $post_type;

    /**
     * ObservedAuthorPostRegistration constructor.
     *
     * @param string $post_type This is supposed to be the string which is to be used as the post type identifier.
     *      this is an argument, because I want to preserve the possibility to change this in case it collided with
     *      the identifier of another plugin. Default is "observed-author"
     */
    public function __construct(string $post_type = 'observed-author') {
        // The actual string to be used as the post type identifier / handle is defined as a static attribute of the
        // ObservedAuthorPost class, which is the actual wrapper class for this post type.
        $this->post_type = ObservedAuthorPost::$post_type;
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

        // Registers all the meta fields defined by ObservedAuthorPost
        add_action( 'init', [$this, 'register_post_meta'] );
    }

    /**
     * Calls the wordpress "register_post_type" function with the appropriate arguments for this post type. Should be
     * hooked into the "init" action.
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
                'menu_icon'                         => 'dashicons-businessperson',
                'hierarchical'                      => false,
                'has_archive'                       => 'observed-authors',
                'query_var'                         => 'observed-author',
                'map_meta_cap'                      => true,

                // Handles the URL structure
                'rewrite' => [
                    'slug'                          => 'observed-authors',
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
                    'name'                          => 'Observed Authors',
                    'singular_name'                 => 'Observed Author',
                    'add_new'                       => 'Add New',
                    'add_new_item'                  => 'Add New Author',
                    'edit_item'                     => 'Edit Author',
                    'view_item'                     => 'View Author',
                    'view_items'                    => 'View Observed Authors',
                    'search_items'                  => 'Search Observed Authors',
                    'not_found'                     => 'No Authors found',
                    'not_found_in_trash'            => 'No Authors found in Trash.',
                    'all_items'                     => 'All Observed Authors',
                    'archives'                      => 'Observed Author Archives',
                    'attributes'                    => 'Observed Author Attributes',
                    'insert_into_item'              => 'Append to Author',
                    'uploaded_to_this_item'         => 'Uploaded to this Author',
                    'featured_image'                => 'Author Profile Picture',
                    'set_featured_image'            => 'Set Author Profile Picture',
                    'remove_featured_image'         => 'Remove Profile Picture',
                    'use_featured_image'            => 'Use as Author Profile Picture',
                    'filter_items_list'             => 'Filter Authors list',
                    'items_list_navigation'         => 'Authors list navigation',
                    'items_list'                    => 'Authors list',
                    'item_published'                => 'Observed Author added',
                    'item_published_privately'      => 'Observed Author added privately',
                    'item_reverted_to_draft'        => 'Observed Author deactivated',
                    'item_scheduled'                => '-',
                    'item_updated'                  => 'Observed Author information updated'
                ]
            ]
        );
    }

    /**
     * Registers all the meta fields for the observed author post type with wordpress. Strictly speaking the
     * registration of meta fields is not necessary. One could just simply add/manipulate meta fields for a post
     * without "prior notification" for wordpress, but it is good practice and allows to define additional options for
     * the meta fields such as a description and the REST visibility.
     *
     * @return void
     */
    public function register_post_meta() {
        // The specific arguments for each meta field are actually defined in the class variable "META_FIELDS" of the
        // ObservedAuthorPost class. This design choice has been made to be able to only have to modifiy the post
        // meta in a single class (Since the post wrapper class is the one which directly handles the loading of the
        // meta values, they should be managed there)
        foreach (ObservedAuthorPost::META_FIELDS as $meta_field => $args) {
            register_post_meta( $this->post_type, $meta_field, $args );
        }
    }
}