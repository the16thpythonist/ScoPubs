<?php


namespace Scopubs;


use Scopubs\Validation\DataValidator;
use Scopubs\Validation\ValidationError;

/**
 * Class Options
 *
 * This static class is used to manage the options of the plugin.
 *
 * **USAGE**
 *
 * The options are entirely manipulated through the static "get" and "set" methods of this class. The options are
 * identified by unique string names. These names should consist of only lower case characters and underscores. Note
 * that with wordpress internally, the general PLUGIN_PREFIX is prepended to these option names to avoid possible
 * collision with names from other plugins. This means that also common names should be save to be used.
 *
 *      // Retrieving an options value
 *      Options::get("my_option");
 *
 *      // Setting a new value
 *      Options::set("my_option", "a new value");
 *
 * Also note that only options which are listed in the static assoc array $options can be used here. So this class does
 * not support adding options dynamically.
 *
 * **REST INTERFACE**
 *
 * This class automatically exposes a REST interface for retrieving and manipulating the options defined in the static
 * $options array. As the base namespace "{PLUGIN_PREFIX}/v1". The three following methods are defined:
 * - GET /options: This returns an object where the keys are the string option names and the values are again objects
 *   which contain the details about this option. These detail objects contain the same information as the corresponding
 *   entry in the static $options array and additionally the field "value" which then contains the actual value of the
 *   option as well.
 * - POST /options: This is used to modify all the options at the same time. The payload has to contain the single
 *   value "options" which is an object, where the keys are the option names and the value the new values for these
 *   options. Note that this object has to contain entries for all options which appear in $options.
 * - POST /options/<slug:option>: This can be used to modify a single option value. The payload has to contain only the
 *   single field "value"
 *
 * **ADDING NEW OPTIONS**
 *
 * Additional options can be added by adding an according entry to the static $options dict. The key has to be the
 * string option name by which the option will be identified for the "get" and "set" methods. The value has to be an
 * assoc array with the following fields:
 * - name: The key name again.
 * - label: A human readable name for the option. May contain whitespaces etc. This will be displayed as the name of
 *   the input field for this option in the frontend.
 * - description: The string description for the option to be displayed in the frontend widget.
 * - type: A string identifier about what type of value this is supposed to be. This will be used in the frontend
 *   widget to determine the type of input widget to be used.
 * - default: The default value to be returned if no value exists yet.
 * - validators: A list of strings or callables which define filters for the value to validate / sanitize it.
 *
 * If an appropriate entry of the option is added to the $options array, it will automatically appear in the REST
 * interface and the frontend will automatically generate the according input widget...
 *
 * **REGISTRATION**
 *
 * To provide all these features, certain functions have to be registered with wordpress first. To do this, simply call
 * the static "register" method on the top level of the main plugin file:
 *
 *      Options::register();
 *
 * @package Scopubs
 */
class Options {

    public static $rest_namespace = PLUGIN_PREFIX . '/v1';
    public static $rest_base = 'options';
    public static $name = PLUGIN_PREFIX . ' Options';
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

    /**
     * Returns the current value of the option which is identified with the given $option_name
     *
     * @param string $option_name The unique string identifier for the option
     *
     * @throws \InvalidArgumentException If the $option_name is not a registered option in the Options::$options array
     * @return bool|mixed
     */
    public static function get(string $option_name) {
        if (!array_key_exists($option_name, self::$options)) {
            throw new \InvalidArgumentException(sprintf(
                'Attempting to retrieve the option with the name "%s" but this option is not registered in the ' .
                'Options::$options array! Please add it an entry if you want to manage this option.',
                $option_name
            ));
        }

        return get_option(PLUGIN_PREFIX . '_' . $option_name, self::$options[$option_name]['default']);
    }

    /**
     * Sets the value of the option identified by $option_name to the given $value.
     *
     * @param string $option_name The string identifier for the option
     * @param mixed $value The new value to set it to
     *
     * @throws \InvalidArgumentException If the $option_name is not a registered option in the Options::$options array
     * @throws ValidationError If the value violates one of the validation filters.
     * @return void
     */
    public static function set(string $option_name, $value) {
        if (!array_key_exists($option_name, self::$options)) {
            throw new \InvalidArgumentException(sprintf(
                'Attempting to retrieve the option with the name "%s" but this option is not registered in the ' .
                'Options::$options array! Please add it an entry if you want to manage this option.',
                $option_name
            ));
        }

        $validated_value = DataValidator::apply_single($value, self::$options[$option_name]['validators']);
        update_option(PLUGIN_PREFIX . '_' . $option_name, $validated_value);
    }

    # == WORDPRESS REGISTRATION

    /**
     * This method registers the functionality of this class with wordpress. This includes the addtional options page
     * in the wordpress admin area and the REST interface.
     *
     * @return void
     */
    public static function register() {
        // Registering the actual options page to be part of the admin area
        add_action( 'admin_menu', [self::class, 'register_options_page'] );

        // Registering the REST interface for the options
        add_action( 'rest_api_init', [self::class, 'register_rest_routes'] );

        // This filter hook is not wp native but instead defined by this very plugin. It is applied during the "init"
        // action by the VueFrontendRegistration class to modify the assoc dict, which will be converted into a JS
        // object to pass important values from the server to the frontend.
        add_filter( PLUGIN_PREFIX . '_frontend_data', [self::class, 'add_frontend_data'] );
    }

    public static function add_frontend_data(array $frontend_data) {
        $frontend_data['options_endpoint'] = self::$rest_namespace . '/' . self::$rest_base;
        return $frontend_data;
    }

    // -- Register the options page

    /**
     * Registers the new options page. Should be called during the "admin_menu" action.
     *
     * @return void
     */
    public static function register_options_page() {
        add_options_page(
            self::$name,
            self::$name,
            'manage_options',
            'scopubs_plugin',
            [self::class, 'echo_options_page']
        );
    }

    /**
     * The callback for actually ECHOing the html code for the additional options page. The html code for the options
     * page consists only of a single div element with a specific id, which allow the vue Options component to be
     * mounted to it on page load. The actual options page is implemented as a dynamic Vue frontend widget.
     *
     * @return void
     */
    public static function echo_options_page() {
        ?>
            <div id="scopubs-options-component">
                Seems like the Vue component could not be mounted properly
            </div>
        <?php
    }

    // -- Registering REST interface

    /**
     * Registers the REST routes required for interfacing with the options. Should be called during the
     * "rest_api_init" action.
     *
     * @return void
     */
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

    /**
     * The callback for a retrieving the options list information.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
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

    /**
     * The callback for updating all the options values using the REST POST request.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
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

    /**
     * The callback for updating a single option value using REST POST.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
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