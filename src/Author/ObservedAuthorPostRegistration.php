<?php


namespace Scopubs\Author;


class ObservedAuthorPostRegistration
{
    public $post_type;

    public $author_topic_taxonomy;

    /**
     * ObservedAuthorPostRegistration constructor.
     */
    public function __construct() {
        // The actual string to be used as the post type identifier / handle is defined as a static attribute of the
        // ObservedAuthorPost class, which is the actual wrapper class for this post type.
        $this->post_type = ObservedAuthorPost::$post_type;

        $this->author_topic_taxonomy = ObservedAuthorPost::$author_topic_taxonomy;
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

        // Registers the "Author Topic" taxonomy.
        add_action( 'init', [$this, 'register_author_topic_taxonomy'] );

        // Adding the meta box, which displays the Vue component for the custom widgets for inputting the author
        // meta information.
        add_action( 'add_meta_boxes_' . $this->post_type, [$this, 'register_meta_box'] );

        // Disable the gutenberg block editor for this post type
        add_filter( "use_block_editor_for_post", 'use_block_editor', 10 );

        // Modifying the JSON response for this post type to also contain the custom meta fields
        // https://wordpress.stackexchange.com/questions/227506/how-to-get-custom-post-meta-using-rest-api
        add_filter( 'rest_prepare_' . $this->post_type, [$this, 'filter_rest_json'], 10, 3);
    }

    public function use_block_editor( $is_enabled, $post_type ) {
        if ( $post_type == $this->post_type ) {
            return false;
        }

        return $is_enabled;
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
                    'excerpt',
                    // https://stackoverflow.com/questions/56460557/how-to-include-meta-fields-in-wordpress-api-post
                    // Holy moly, they could be more transparent about this. I was searching for ages why I could not
                    // interact with my meta data even though the meta fields were registered as "show_in_rest=true"
                    // Apparently you also need this...
                    'custom-fields'
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

    /**
     * Registers the "Author Topic" taxonomy. An author can be assigned with multiple author topics. These topics
     * are essentially short string summaries of what kind of research the author is concerned with. When any
     * publication of this author is being imported from scopus and then published, all the topics from all the
     * observed authors which are authors of that publication will be saved as a meta info for that publication. This
     * is an easy way of automatically sorting the automatically published publications into categories.
     *
     * @return void
     */
    public function register_author_topic_taxonomy() {
        register_taxonomy( $this->author_topic_taxonomy, $this->post_type, [
            'public'                                => true,
            'show_in_rest'                          => true,
            'show_ui'                               => true,
            'show_in_nav_menus'                     => true,
            'show_tagcloud'                         => true,
            'show_admin_column'                     => true,
            'hierarchical'                          => false,
            'query_var'                             => 'author_topic',
            'labels' => [
                'name'                              => 'Author Topics',
                'single_name'                       => 'Author Topic',
                'menu_name'                         => 'Author Topics',
                'name_admin_bar'                    => 'Author Topic',
                'search_items'                      => 'Search Author Topics',
                'popular_items'                     => 'Popular Author Topics',
                'all_items'                         => 'All Author Topics',
                'edit_item'                         => 'Edit Author Topic',
                'view_item'                         => 'View Author Topic',
                'update_item'                       => 'Update Author Topic',
                'add_new_item'                      => 'Add New Author Topic',
                'new_item_name'                     => 'New Author Topic Name',
                'not_found'                         => 'No Author Topics Found',
                'items_list_navigation'             => 'Author Topic List Navigation',
                'items_list'                        => 'Author Topics List'
            ]
        ]);
    }

    // -- Registering the meta box --

    /**
     *
     */
    public function register_meta_box() {
        add_meta_box(
            $this->post_type . '_meta',
            'Observed Author Meta Information',
            [$this, 'echo_meta_box'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function echo_meta_box($post) {
        ?>
            <script>var POST_ID = <?php echo $post->ID; ?>;</script>
            <div id="scopubs-author-meta-component">
                This Vue component apparently could not be loaded properly.
            </div>
        <?php
    }

    public function filter_rest_json( $data, $post, $context ) {
        $author_post = new ObservedAuthorPost($post->ID);

        $data->data['first_name'] = $author_post->first_name;
        $data->data['last_name'] = $author_post->last_name;
        $data->data['scopus_author_ids'] = $author_post->scopus_author_ids;
        $data->data['affiliations'] = $author_post->affiliations;
        $data->data['affiliation_blacklist'] = $author_post->affiliation_blacklist;

        return $data;
    }
}