<?php


namespace Scopubs\Log;


class LogPostRegistration {

    public $post_type;

    public function __construct() {
        $this->post_type = LogPost::$post_type;
    }

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

    public function register_post_meta() {
        foreach ( LogPost::META_FIELDS as $meta_field => $args ) {
            register_post_meta( $this->post_type, $meta_field, $args );
        }
    }

    // -- The meta box

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

    public function filter_rest_json( object $data, \WP_Post $post, $context ) {
        $log_post = new LogPost($post->ID);

        $data->data['title'] = $log_post->title;
        $data->data['running'] = $log_post->title;
        $data->data['entries'] = $log_post->entries;
        $data->data['log_levels'] = $log_post->get_log_levels();

        return $data;
    }
}