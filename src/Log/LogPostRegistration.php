<?php


namespace Scopubs\Log;


/**
 * Class LogPostRegistration
 *
 * This class handles the registration of the "Log" post type. This post type represents a log of some sort, which
 * contains string log messages about the progress of some computational process on the server. For more information
 * see the LogPost class.
 *
 * **PRIVATE POST TYPE**
 *
 * This post type is not publicly queryable. It is only meant to be viewable by registered users / admins of the
 * wordpress installation. The log post type in general is a debug tool which is mainly for the maintainer of the
 * website.
 *
 * **REST API**
 *
 * The log post type is accessible via the REST api, but only for logged in users. The rest response for this post type
 * is modified such that the top level structure contains additional fields which contain the meta values for the post
 * type. These are "running", "type", "title", "entries". Additionally the field "log_levels" contains a list of strings
 * where each string is the identifier for one of the supported log levels.
 *
 * **USAGE**
 *
 * To register the log post simply create a new instance of this
 * registration class and call the "register" method. This will take care of calling all the necessary wordpress
 * functions and hooking into the appropriate actions and filters.
 *
 *      $log_post_registration = new LogPostRegistration();
 *      $log_post_registration->register();
 *
 * This code has to be executed top level in the main file of the plugin!
 *
 * @package Scopubs\Log
 */
class LogPostRegistration {

    public $post_type;

    public function __construct() {
        $this->post_type = LogPost::$post_type;
    }

    /**
     * This function hooks the necessary callbacks into the appropriate wordpress hooks to register the log post type.
     * This method should be called top-level in the main file of the plugin!
     *
     * @return void
     */
    public function register() {
        // This registers the core post type so that it is correctly displayed in the admin backend by wordpress
        add_action( 'init', [$this, 'register_post_type'] );

        // Registers the post meta fields
        add_action( 'init', [$this, 'register_post_meta'] );

        // Registers the meta box, which is then displayed in in the admin edit screen for this post type
        add_action( 'add_meta_boxes_' . $this->post_type, [$this, 'register_meta_box'] );

        // Modifying the JSON response for this post type to also contain the custom meta fields
        // https://wordpress.stackexchange.com/questions/227506/how-to-get-custom-post-meta-using-rest-api
        add_filter( 'rest_prepare_' . $this->post_type, [$this, 'filter_rest_json'], 10, 3);
    }

    /**
     * This function registers the base post type by calling the wordpress function "register_post_type" with the set
     * of appropriate arguments for the log post type.
     *
     * @return void
     */
    public function register_post_type() {
        register_post_type( $this->post_type, [
                'public'                            => false,
                'publicly_queryable'                => false,
                'show_in_rest'                      => true, # This could be more?
                'show_in_nav_menus'                 => true,
                'show_in_admin_bar'                 => true,
                'exclude_from_search'               => true,
                'show_ui'                           => true,
                'show_in_menu'                      => true,
                'menu_icon'                         => 'dashicons-open-folder',
                'hierarchical'                      => false,
                'has_archive'                       => 'publications',
                'query_var'                         => 'publication',
                'map_meta_cap'                      => true,

                // Handles the URL structure
                'rewrite' => [
                    'slug'                          => 'logs',
                    'with_front'                    => false,
                    'pages'                         => true,
                    'feeds'                         => true,
                    'ep_mask'                       => EP_PERMALINK,

                ],

                // Features which the post type supports
                'supports' => [
                    'title',
                    'custom-fields'
                ],

                // Text labels
                'labels' => [
                    'name'                          => 'Logs',
                    'singular_name'                 => 'Log',
                    'add_new'                       => 'Add New',
                    'add_new_item'                  => 'Add New Log',
                    'edit_item'                     => 'Edit Log',
                    'view_item'                     => 'View Log',
                    'view_items'                    => 'View Logs',
                    'search_items'                  => 'Search Logs',
                    'not_found'                     => 'No Logs found',
                    'not_found_in_trash'            => 'No Logs found in Trash.',
                    'all_items'                     => 'All Logs',
                    'archives'                      => 'Log Archives',
                    'attributes'                    => 'Log Attributes',
                    'insert_into_item'              => 'Append to Log',
                    'uploaded_to_this_item'         => 'Uploaded to this Log',
                    'featured_image'                => 'Log Image',
                    'set_featured_image'            => 'Set Log image',
                    'remove_featured_image'         => 'Remove Log image',
                    'use_featured_image'            => 'Use as Log image',
                    'filter_items_list'             => 'Filter Logs list',
                    'items_list_navigation'         => 'Logs list navigation',
                    'items_list'                    => 'Logs list',
                    'item_published'                => 'Log added',
                    'item_published_privately'      => 'Log added privately',
                    'item_reverted_to_draft'        => 'Log deactivated',
                    'item_scheduled'                => '-',
                    'item_updated'                  => 'Log data updated'
                ]
            ]
        );
    }

    /**
     * This method registers all the meta fields for the log post type.
     *
     * @return void
     */
    public function register_post_meta() {
        // Strictly speaking, registering post meta is not necessary. You could just dynamically add any value as any
        // kind of post meta and that would technically work. But especially when we want these meta fields to appear
        // in the REST response for this post type, we basically have to register them. Because the REST response will
        // only contain the meta value if the meta registration "show_in_rest" was set appropriately.
        foreach ( LogPost::META_FIELDS as $meta_field => $args ) {
            register_post_meta( $this->post_type, $meta_field, $args );
        }
    }

    // -- The meta box
    // A meta box is an additional widget which shows up on the edit page for posts of this type. Since this is a
    // custom post type which should work quite differently from normal "posts", we will use this meta box to display
    // a custom frontend widget which will display all the log entries instead of providing the default editor for
    // editing a "normal post"

    /**
     * This method calls the wordpress function "add_meta_box" with the appropriate arguments to register a new meta
     * box for this post type.
     *
     * @return void
     */
    public function register_meta_box() {
        add_meta_box(
            $this->post_type . '_meta',
            'Log Entries',
            [$this, 'echo_meta_box'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    /**
     * This function is the callback for actually displaying the meta box. It has to ECHO the html code which is then
     * put into the metabox widget within the edit page of the post type.
     *
     * As we can see, this method does not actually echo a lot of HTML. In fact, it effectively only uses a single div
     * as the content for the meta box. The important thing about this div is it's ID. Exactly this ID will be used by
     * the fancy VUE frontend code of this widget to mount the frontend widget onto.
     *
     * @return void
     */
    public function echo_meta_box( \WP_Post $post ) {
        ?>
            <script>
                // By using "var" here we are making this a globally accessible variable. It is important that the
                // information about which post ID the current post has is passed to the frontend code this way.
                var POST_ID = <?php echo $post->ID; ?>;
            </script>
            <div id="scopubs-log-meta-component">
                This Vue component apparently could not be loaded properly.
            </div>
        <?php
    }

    // -- Modify REST response

    /**
     * This method will be registered as the filter for the content of the REST response of this post type. For a given
     * post this method offers the option to modify the fields which the response to a REST request will contain.
     *
     * We use this to add the custom meta fields for the log post such as the "entries" and "type" to the top level
     * fields of the rest response, so that it is easier for the frontend to interact with these values.
     *
     * @return object The modified "data" object for the REST response.
     */
    public function filter_rest_json( object $data, \WP_Post $post, $context ) {
        $log_post = new LogPost($post->ID);

        $data->data['title'] = $log_post->title;
        $data->data['running'] = $log_post->title;
        $data->data['entries'] = $log_post->entries;
        $data->data['type'] = $log_post->type;
        $data->data['log_levels'] = $log_post::get_log_levels();

        return $data;
    }
}