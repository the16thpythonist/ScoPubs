<?php


namespace Scopubs\Command;

use Scopubs\Log\LogPost;


// TODO: method "schedule_command" which runs a command after a certain time
// TODO: Rest permissions.
// TODO: recent logs: url parameter for how many
/**
 * Class CommandManager
 *
 * For a basic introduction to the command system see the documentation of AbstractCommand.php
 *
 * This is a static class which functions as the manager for the known commands. All commands which are supposed to be
 * accessible by the frontend have to be registered with this static class. Internally the commands are managed by a
 * static assoc array, which stores the command names as the keys and the corresponding class implementations as the
 * values. This class is also the place where the REST interface to the command system is implemented.
 *
 * **THE COMMAND REST INTERFACE**
 *
 * The command system provides a total of 3 REST routes through which interaction with the commands happen.
 *
 * >> GET /wp/v2/commands/
 *
 * This will return a list of all available commands. Specifically this will be a JSON object, where the keys are the
 * command string identifier names and the values are again object which describe the corresponding command. These
 * contain the keys "name", "description" and "parameters".
 *
 * >> POST /wp/v2/commands/<slug:command_name>
 *
 * Sending a Post request to a specific command will trigger it's execution. The POST payload has to contain a single
 * field "parameters" which is supposed to be an object, which contains a field for each parameter expected by this
 * command or else the command will not be executed. This Post request will also not return a response.
 *
 * >> GET /wp/v2/commands/recent
 *
 * This will return a list of information about the 5 most recently executed commands and their logs.
 *
 * **DASHBOARD WIDGET**
 *
 * This class also registers a dashboard widget. This widget can be interacted with by the user to select a command
 * enter parameter values and trigger it's execution from within the admin area of the wordpress installation.
 *
 * **REGISTRATION**
 *
 * The command manager has to be registered first thing in the main plugin file by calling its static method "register".
 * This method will hook the appropriate callbacks to register the REST interface, the dashboard widget etc.
 *
 *      CommandManager::register();
 *      // register custom post types etc. after
 *
 * **FUNCTIONALITY VS REGISTRATION**
 *
 * For this class it does not make sense to separate the wordpress registration from the actual functionality because
 * it is a static class and that would cause too much coupling between registration and main class so we can just do it
 * all in this one class.
 *
 * @package Scopubs\Command
 */
class CommandManager {

    public static $rest_base = 'commands';
    public static $register_hook = 'init';

    public static $commands = [];

    // == COMMAND FUNCTIONALITY

    /**
     * This method can be used to manually register a new command by passing its string identifier $name and the
     * corresponding $class which implements the command functionality. This will be saved as an additional entry in
     * the internal static $commands assoc array.
     *
     * @param string $name The unique string identifier for the command
     * @param $class
     *
     * @throws \InvalidArgumentException If a command with the given $name is already registered.
     * @return void
     */
    public static function add_command(string $name, $class) {
        if (array_key_exists($name, self::$commands)) {
            throw new \InvalidArgumentException(
                "You are attempting to add the command of the class $class to the command manager by using " .
                "the name $name. This name however is already used! Please make sure the name is spelled correctly " .
                "or change it so that it does not collide with other already existing commands!"
            );
        }

        self::$commands[$name] = $class;
    }

    /**
     * This method executes the command which is registered by the string identifier $name, by passing the $args array
     * as the command parameters.
     *
     * Note that this method obviously only works if the command has previously already been registered.
     *
     * @param string $name The unique string identifier of the command
     * @param array $args
     *
     * @return bool
     */
    public static function execute_command(string $name, array $args): bool {
        if (!array_key_exists($name, self::$commands)) {
            throw new \InvalidArgumentException(
              "You are attempting to execute a command by the name $name. But a command with this name is " .
              "not registered in the command manager. Please make sure the name is spelled correctly. Also make sure " .
              "you are not attempting to call this method too early in the wordpress lifetime, as the commands are " .
              "not registered until at least the " . self::$register_hook . " was called!"
            );
        }

        $command_class = self::$commands[$name];
        $command = new $command_class();
        return $command->execute($args);
    }

    /**
     * Returns a list of strings, where each element is the string identifier of one of the already registered commands
     *
     * @return array
     */
    public static function get_available_commands(): array {
        $names = [];
        foreach (self::$commands as $command_name => $command) {
            return $command::$name;
        }
        return $names;
    }

    // == WORDPRESS REGISTRATION

    /**
     * Wraps all necessary hook registrations which are required to make the command system work.
     * This should be called as the first thing in the main plugin file.
     *
     * @return void
     */
    public static function register() {
        // Registering the rest api
        add_action( 'rest_api_init', [self::class, 'register_rest_routes'] );

        // Register the dashboard widget
        add_action( 'wp_dashboard_setup', [self::class, 'register_dashboard_widget'] );

        // Actually loading the commands into the internal reference $commands assoc array.
        add_action( 'init', [self::class, 'load_commands'], 1);
    }

    /**
     * This method loads all the commands by applying the "load_commands" custom filter.
     *
     * **HOW COMMAND REGISTRATION WORKS**
     *
     * This function applies the filter "load_commands" and passes an empty array as a value. Now if a specific command
     * registration wants to be registered, it will hook a callback to this filter. This callback will modify the array
     * by adding an additional key value pair, where the key is the unique string identifier of the command and the
     * value is the command class itself. After all these callbacks are applied, the result is an assoc array which
     * contains references to all known command classes.
     *
     * @return void
     */
    public static function load_commands() {
        self::$commands = apply_filters('load_commands', []);
    }

    // -- The dashboard widget

    /**
     * Registers the dashboard widget for interacting with the command system in wordpress. Should be executed in the
     * action "wp_dashboard_setup".
     *
     * @return void
     */
    public static function register_dashboard_widget() {
        wp_add_dashboard_widget(
            self::$rest_base,
            'Execute Commands',
            [self::class, 'echo_widget']
        );
    }

    /**
     * Actually ECHOS the html code for the widget.
     *
     * The widget itself is implemented as VUE component, the only thing we have to do here is to create a html element
     * with the correct id, so that when loading the page Vue can discover this element and use it to mount the actual
     * widget.
     *
     * @return void
     */
    public static function echo_widget() {
        ?>
            <div id="command-widget-component">
                It seems like this vue component does not load properly
            </div>
        <?php
    }

    // -- Rest routes registration and callbacks

    /**
     * Registers all the rest routes
     *
     * @return void
     */
    public static function register_rest_routes() {
        // For the command functionality we will need two routes. The first route, which we are registering here is the
        // "list view". It refers to the entirety of the commands and not any one in particular yet. This route should
        // respond to GET requests and return a list of all available commands.
        register_rest_route('wp/v2', '/' . self::$rest_base . '/', [
            'methods' => 'GET',
            'callback' => [self::class, 'rest_commands_list']
        ]);

        // There is actually an additional command, which will be important: A route which responds to a GET request
        // and provides information about the most recent command executions.
        register_rest_route( 'wp/v2' , '/' . self::$rest_base . '/recent', [
            'methods' => 'GET',
            'callback' => [self::class, 'rest_recent_commands']
        ]);

        // The second route is for specific commands. This route should respond to GET and POST. the GET response
        // should simply provide the information about the command such as its description parameter information etc.
        // The POST request will be used to actually execute the command. The JSON payload is supposed to be the actual
        // parameter values.
        register_rest_route( 'wp/v2', '/' . self::$rest_base . '/(?P<command_name>[a-zA-Z0-9-_]+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'rest_command_detail']
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'rest_execute_command']
            ]
        ]);

    }

    /**
     * Callback for the REST route "GET /wp/v2/commands/". This method will produce a json response which contains an
     * object that represents a list of all available/registered commands.
     *
     * @param \WP_REST_Request $request
     *
     * @return string The json string
     */
    public static function rest_commands_list( \WP_REST_Request $request ): string {
        $commands = [];
        foreach (self::$commands as $command_name => $command) {
            $command_array = $command::to_array();
            $commands[$command_name] = $command_array;
        }

        return json_encode($commands);
    }

    public static function rest_command_detail( \WP_REST_Request $request ): string {
        $command_name = $request['command_name'];
        $command_class = self::$commands[$command_name];

        return json_encode($command_class::to_array());
    }

    /**
     * Callback for the REST route "POST /wp/v2/commands/<slug:command_name>". This method will trigger the execution
     * of the command if it exists.
     *
     * @param \WP_REST_Request $request Should contain the payload field "parameter" which in turn is an assoc array
     *      that defines the values of the command parameters entered by the user.
     *
     * @return void
     */
    public static function rest_execute_command( \WP_REST_Request $request ) {
        $command_name = $request['command_name'];
        $request_parameters = $request->get_json_params();
        $args = $request_parameters['args'];
        self::execute_command($command_name, $args);
    }

    /**
     * Callback for the REST route "GET /wp/v2/commands/recent". This will return a list of objects, where each object
     * describes one of the recently executed commands. Contains for example a URL to the edit page of the command log.
     *
     * @param \WP_REST_Request $request
     *
     * @return false|string
     */
    public static function rest_recent_commands( \WP_REST_Request $request ) {
        $query = new \WP_Query([
            'post_type'         => LogPost::$post_type,
            'meta_query'        => [
                [
                    'key'       => 'type',
                    'value'     => 'command',
                    'type'      => 'CHAR',
                    'compare'   => '='
                ]
            ],
            'order'             => 'DESC',
            'order_by'          => 'date',
            'posts_per_page'    => 5
        ]);
        $recent_commands = [];
        foreach ($query->posts as $post) {
            $log_post = new LogPost($post->ID);
            // https://developer.wordpress.org/reference/functions/get_edit_post_link/

            $post_type_object = get_post_type_object( $post->post_type );
            array_push($recent_commands, [
                'post_id'       => $log_post->post_id,
                'edit_url'      => admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post->ID ) ),
                'title'         => $log_post->title,
                'command_name'  => str_replace(AbstractCommand::$log_prefix, '', $log_post->title),
                'length'        => count($log_post),
                'date'          => $log_post->post->post_date
            ]);
        }
        return json_encode($recent_commands);
    }
}