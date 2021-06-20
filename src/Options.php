<?php


namespace Scopubs;


use Scopubs\Validation\DataValidator;
use Scopubs\Validation\ValidationError;

class Options {

    public static $rest_namespace = PLUGIN_PREFIX . '/v1';
    public static $rest_base = 'options';
    public static $options = [
        'scopus_api_key' => [
            'name'              => 'scopus_api_key',
            'label'             => 'Scopus API key',
            'description'       => 'Your personal API key for the scopus database. This is absolutely necessary to ' .
                                   'make any request to the scopus database!',
            'type'              => 'string',
            'default'           => '',
            'validators'        => ['validate_is_string']
        ]
    ];

    # == ACCESSING OPTIONS

    public static function get(string $option_name) {
        return get_option(PLUGIN_PREFIX . '_' . $option_name, self::$options[$option_name]['default']);
    }

    public static function set(string $option_name, $value) {
        $validated_value = DataValidator::apply_single($value, self::$options[$option_name]['validators']);
        update_option(PLUGIN_PREFIX . '_' . $option_name, $validated_value);
    }

    # == WORDPRESS REGISTRATION

    public static function register() {
        // Registering the actual options page to be part of the admin area
        add_action( 'admin_menu', [self::class, 'register_options_page'] );

        // Registering the REST interface for the options
        add_action( 'rest_api_init', [self::class, 'register_rest_routes'] );

        add_filter( PLUGIN_PREFIX . '_frontend_data', [self::class, 'add_frontend_data'] );
    }

    public static function add_frontend_data(array $frontend_data) {
        $frontend_data['options_endpoint'] = self::$rest_namespace . '/' . self::$rest_base;
        return $frontend_data;
    }

    // -- Register the options page

    /**
     *
     */
    public static function register_options_page() {
        add_options_page(
            'Scopubs Plugin Settings',
            'Scopubs Settings',
            'manage_options',
            'scopubs_plugin',
            [self::class, 'echo_options_page']
        );
    }

    public static function echo_options_page() {
        ?>
            <div id="scopubs-options-component">
                Seems like the Vue component could not be mounted properly
            </div>
        <?php
    }

    // -- Registering REST interface

    public function register_rest_routes() {
        // We essentially need three functionalities:
        // - GET list: return the values / specifications for all the options
        // - POST list: overwrite the value for all the options
        // -> Both of these are essential for the implemenation of the actual options page.
        // - POST single: Write the value of a single option. This might be convenient in the future

        register_rest_route(self::$rest_namespace, '/' . self::$rest_base . '/', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'rest_get_options_list'],
                'permissions_callback' => [Util::class, 'current_user_admin']
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'rest_post_options_list'],
                'permissions_callback' => [Util::class, 'current_user_admin'],
                'args' => [
                    'options'
                ]
            ]
        ]);

        register_rest_route(self::$rest_namespace, '/' . self::$rest_base . '/(?P<option>[a-zA-Z0-9-_]+)', [
            [
                'methods' => 'POST',
                'callback' => [self::class, 'rest_post_options_detail'],
                'permissions_callback' => [Util::class, 'current_user_admin'],
                'args' => [
                    'value'
                ]
            ]
        ]);
    }

    public function rest_get_options_list( \WP_REST_Request $request ) {
        $options = [];
        foreach (self::$options as $option_name => $option_spec) {
            $value = self::get($option_name);
            $option_spec['value'] = $value;
            // We need to unset the validators field here because this is an array which may contain strings and
            // callable objects. Since this ultimately has to be converted to JSON, callable objects may present a
            // problem and since we dont need this information anyways, better safe than sorry.
            unset($option_spec['validators']);
            $options[$option_name] = $option_spec;
        }
        return new \WP_REST_Response($options);
    }

    public static function rest_post_options_list( \WP_REST_Request $request ) {
        try {
            $options = $request['options'];
            Util::require_array_keys($options, array_keys(self::$options));
        } catch (\InvalidArgumentException $e) {
            return new \WP_REST_Response(
                'Some options are missing from the request! All options have to be specified for this POST operation',
                400
            );
        }

        try {
            foreach ($options as $option_name => $value) {
                self::set($option_name, $value);
            }
        } catch (ValidationError $e) {
            return new \WP_REST_Response($e->getMessage(), 400);
        }


        return new \WP_REST_Response(null, 200);
    }

    public static function rest_post_options_detail( \WP_REST_Request $request ) {
        $option_name = $request['option'];
        $value = $request['value'];

        try {
            self::set($option_name, $value);
        } catch (ValidationError $e) {
            return new \WP_REST_Response($e->getMessage(), 400);
        }

        return new \WP_REST_Response(null, 200);
    }

}