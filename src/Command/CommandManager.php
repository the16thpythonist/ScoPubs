<?php


namespace Scopubs\Command;

use Scopubs\Log\LogPost;

/**
 * Class CommandManager
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

    public static function get_available_commands(): array {
        $names = [];
        foreach (self::$commands as $command_name => $command) {
            return $command::$name;
        }
        return $names;
    }

    // == WORDPRESS REGISTRATION

    public static function register() {
        add_action( 'rest_api_init', [self::class, 'register_rest_routes'] );

        add_action( 'wp_dashboard_setup', [self::class, 'register_dashboard_widget'] );

        add_action( 'init', [self::class, 'load_commands'], 1);
    }

    public static function load_commands() {
        self::$commands = apply_filters('load_commands', []);
    }

    // -- The dashboard widget

    public static function register_dashboard_widget() {
        wp_add_dashboard_widget(
            self::$rest_base,
            'Execute Commands',
            [self::class, 'echo_widget']
        );
    }

    public static function echo_widget() {
        ?>
            <div id="command-widget-component">
                It seems like this vue component does not load properly
            </div>
        <?php
    }

    // -- Rest routes registration and callbacks

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

    public static function rest_execute_command( \WP_REST_Request $request ) {
        $command_name = $request['command_name'];
        $request_parameters = $request->get_json_params();
        $args = $request_parameters['args'];
        self::execute_command($command_name, $args);
    }

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