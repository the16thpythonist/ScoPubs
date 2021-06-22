<?php


namespace Scopubs\Author;

use WP_Post;
use Scopubs\Publication\PublicationPost;

/**
 * Class ObservedAuthorPostRegistration
 *
 * This class wraps all tasks which need to be performed to properly register the "observed author" post type in
 * wordpress. The main method responsible for these registering the custom callbacks to the appropriate hooks is the
 * "register" method. This method should be called on the top level during the loading of the plugin.
 *
 *      $author_post_registration = new ObservedAuthorPostRegistration();
 *      $author_post_registration->register();
 *
 * @package Scopubs\Author
 */
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

        // https://www.smashingmagazine.com/2017/12/customizing-admin-columns-wordpress/
        // This filter modifies which admin columns are shown for this post type
        add_filter( 'manage_' . $this->post_type . '_posts_columns', [$this, 'manage_posts_columns'], 10, 1);
        // This action hook then actually echoes the content for these custom admin columns
        add_action( 'manage_' . $this->post_type . '_posts_custom_column', [$this, 'echo_post_column'], 10, 2);

        // https://developer.wordpress.org/reference/hooks/save_post_post-post_type/
        // Here we hook in custom actions which are supposed to be executed whenever a new post is inserted. Currently
        // this callback mainly automatically creates an appropriate "observed authors" tax term for publications posts
        add_action('save_post_' . $this->post_type, [$this, 'save_post'], 10, 3);
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
                    // 'editor', // Actually I really dont need the editor at this point..
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
     * This method registers the custom meta box for this post type.
     *
     * @return void
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

    /**
     * This function echoes the necessary html code which will create the author meta box in the edit page of this post
     * type. This meta box will be used to enter and modify the custom meta fields for this post type such as the
     * affiliations and the list of scopus author ids.
     *
     * @param \WP_Post $post The wordpress post object for the post in question.
     */
    public function echo_meta_box( \WP_Post $post ) {
        // Since this widget will be managed by the Vue frontend, this is not a lot of html code, we merely need an
        // element with the correct id, which will then be used by Vue to mount the frontend widget onto.
        ?>
            <script>
                // By using "var" here we are making this a globally accessible variable. It is important that the
                // information about which post ID the current post has is passed to the frontend code this way.
                var POST_ID = <?php echo $post->ID; ?>;
            </script>
            <div id="scopubs-author-meta-component">
                This Vue component apparently could not be loaded properly.
            </div>
        <?php
    }

    // -- Modifying the REST response --

    /**
     *
     *
     * @param $data
     * @param $post
     * @param $context
     *
     * @return mixed
     */
    public function filter_rest_json( object $data, \WP_Post $post, $context ) {
        $author_post = new ObservedAuthorPost($post->ID);

        $data->data['first_name'] = $author_post->first_name;
        $data->data['last_name'] = $author_post->last_name;
        $data->data['scopus_author_ids'] = $author_post->scopus_author_ids;
        $data->data['affiliations'] = $author_post->affiliations;
        $data->data['affiliation_blacklist'] = $author_post->affiliation_blacklist;

        return $data;
    }

    // -- Modifying the admin columns --

    /**
     * Callback for the filter "manage_{posttype}_posts_columns". This filter method is supposed to modify the array,
     * which defines the admin columns for the post type. for each key value pair in the returned array, the admin
     * list view has one column, where the value of the array defines the string name of the column header.
     *
     * @param array $columns An assoc array, where the string key is the identifier for an admin column and the string
     *      value is the header displayed.
     *
     * @return array
     */
    public function manage_posts_columns( array $columns ) {
        $columns = [
            'cb'            => $columns['cb'],
            'title'         => $columns['title'],
            'scopus_id'     => __( 'Scopus ID' ),
            'topics'        => __( 'Topics' ),
            'date'          => $columns['date']
        ];

        return $columns;
    }

    /**
     * This method is the callback for the action hook "manage_{posttype}_posts_custom_column". This action hook is
     * invoked for every column of every row (where each row represents one post) and it is responsible for echoing
     * the actual value to be displayed.
     *
     * @param string $column The string identifier for which column
     * @param int $post_id The ID of the post for the current row
     */
    public function echo_post_column( string $column, int $post_id ) {
        $author_post = new ObservedAuthorPost($post_id);

        if ( $column === 'first_name' ) {
            echo $author_post->first_name;
        }
        else if ( $column === 'last_name' ) {
            echo $author_post->last_name;
        }
        else if ( $column === 'scopus_id' ) {
            echo implode(', ', $author_post->scopus_author_ids);
        }
        else if ( $column === 'topics' ) {
            echo implode(', ', $author_post->author_topics);
        }
    }

    // -- custom actions when saving

    // https://developer.wordpress.org/reference/hooks/save_post_post-post_type/
    /**
     * The callback for after an observed author post has been saved. Should be hooked into "save_post_{posttype}"
     * action. What this method does is it automatically creates a new "observed author" PublicationPost taxonomy term
     * for that saved author. This term is identified by the full name. Therefore a new term is only inserted if the
     * name has changed with this saving operation.
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     */
    public function save_post(int $post_id, WP_Post $post, bool $update) {
        // Now here we need to do some fancy stuff...
        // https://wordpress.stackexchange.com/questions/163541/inserting-a-term-into-a-custom-taxonomy
        $first_name = get_post_meta($post_id, 'first_name', true);
        $last_name = get_post_meta($post_id, 'last_name', true);
        $full_name = sprintf('%s %s', $first_name, $last_name);

        if (!term_exists($full_name, PublicationPost::$publication_observed_author_taxonomy)) {
            wp_insert_term(
                $full_name,
                PublicationPost::$publication_observed_author_taxonomy,
                [
                    'description'   => $post_id,
                    'slug'          => strtolower($first_name) . '_' . strtolower($last_name)
                ]
            );
        }
    }
}