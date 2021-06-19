<?php


namespace Scopubs\Log;


use Scopubs\Validation\DataValidator;
use Scopubs\Util;
use Countable;


// TODO: make "type" a taxonomy?
/**
 * Class LogPost
 *
 * This class is a wrapper for the new "Log" post type. Posts of this type represent process logs of some kind. This
 * means that they contain multiple timestamped, human-readable messages about the progress of some computational
 * process on the server, which can later be reviewed by an admin to check for potential errors. This is an important
 * aspect: On default this post type is not public. It is only meant to be interacted with from the admin area of the
 * wordpress installation.
 *
 * **STRUCTURE OF POST TYPE**
 *
 * This custom post type uses the post title as the log title as well. The post content is not used! In fact the edit
 * page does not even show the post content editor since the content is irrelevant. All the properties which make this
 * custom post type actually resemble a log are mapped as meta fields.
 *
 * The most important meta field is "entries", this is a list array containing associative arrays each of these assoc
 * array elements in this list represent one log entry. These assoc arrays have the following fields:
 * - message: The actual string log message
 * - level: A string representation of the log level (Logs can have different levels of severity, like normal info logs
 *   warning or even error logs. These levels can then be filtered for display in the frontend)
 * - time: The string representation of the time, at which the message was added.
 *
 * Aside from "entries" there are two other important meta fields: "running" is a boolean flag which indicates if the
 * log is currently active, which would indicate that the process which uses the log is also still running in another
 * thread. "type" is a string field which can be used to differentiate different types of processes which use a log.
 * This field is mainly important for retrieving for retrieving the posts later on.
 *
 * **TYPICAL USAGE**
 *
 * This wrapper class provided a series of convenience functions, which make it possible to use this class within the
 * code like a log object. A new instance CANNOT be created with the constructor. The constructor requires the
 * wordpress post ID of an already existing log post. Instead the static method "create" can be used to create a new
 * log post instance from scratch. It requires the title of the log as the first argument and the type of as optional
 * second argument:
 *
 *      $log_post = LogPost::create('My first log', 'test');
 *
 * From the moment, the create method is called this post will exist within the database. But the actual log messages
 * are not saved to the database until the "save" method is called on the instance. The "log" method can be used to
 * create any log message of any level directly. Alternatively there are also wrapper methods for the different log
 * levels. Before being able to save any logs, the "start" method has to be called on the log post though. At the end
 * of the process, the "close" method has to be called. This method will internally call "save" and thus actually save
 * all the messages to the database.
 *
 *      $log_post->start();
 *      $log_post->log('info', 'My first info!');
 *      $log_post->info('This comes down to the same');
 *      $log_post->error('The most important log level');
 *      $log_post->close();
 *
 * @package Scopubs\Log
 */
class LogPost implements Countable{

    // -- Static values
    public static $post_type = 'log';
    public static $datetime_format = 'Y-m-d H:i:s';
    public static $log_levels = [
        'error'                     => 'error',
        'warning'                   => 'warning',
        'info'                      => 'info',
        'debug'                     => 'debug'
    ];

    // -- Instance attributes

    // intrinsic
    public $post_id;
    public $post;

    public $type;
    public $title;
    public $date;

    // meta values
    public $running;
    public $entries;

    // -- class constants

    public const META_FIELDS = [
        'type' => [
            'type'                  => 'string',
            'description'           => 'The type of log file. This is supposed to indicate which type of process has ' .
                                       'created the log. An example would be the type "command" which indicates ' .
                                       'that the log was the result of a command execution.',
            'default'               => '',
            'single'                => true,
            'show_in_rest'          => true,
        ],
        'running' => [
            'type'                  => 'boolean',
            'description'           => 'whether or not the log is currently active',
            'default'               => false,
            'single'                => true,
            'show_in_rest'          => true
        ],
        'entries' => [
            'type'                  => 'array',
            'description'           => 'The list of all log entries. Each entry is a string.',
            'default'               => [],
            'single'                => true,
            'show_in_rest' => [
                'schema' => [
                    'type'          => 'array',
                    'items'         => [
                        'type'      => 'object'
                    ]
                ]
            ]
        ]
    ];

    public const INSERT_VALUE_VALIDATORS = [
        'title'             => ['validate_is_string'],
        'type'              => ['validate_is_string'],
        'running'           => ['validate_is_boolean'],
        'entries'           => ['validate_is_array'],
    ];

    public function __construct(int $post_id) {
        $this->post_id = $post_id;
        $this->post = get_post($post_id);

        $this->title = $this->post->post_title;

        // Loading the meta values
        $this->type = get_post_meta($this->post_id, 'type', true);
        $this->running = (bool) get_post_meta($this->post_id, 'running', true);
        $this->entries = get_post_meta($this->post_id, 'entries', true);
    }

    public function get_update_args() {
        return [
            'title'             => $this->title,
            'type'              => $this->type,
            'running'           => $this->running,
            'entries'           => $this->entries,
        ];
    }

    /**
     * Saved all the changes made to the local instance of the log post object persistently to the database record of
     * the corresponding wordpress post.
     *
     * @return void
     */
    public function save() {
        $update_args = $this->get_update_args();
        self::update($this->post_id, $update_args);
    }

    /**
     * Starts the "running" state of the log post. This method has to be called once before any log entry can
     * be added.
     *
     * @return void
     */
    public function start() {
        $this->running = true;
    }

    /**
     * This method stops the "running" state of the log post. This method HAS TO be called at the end of using the log
     * post. This method internally calls "save" which is required to actually store all the log messages
     * persistently to the database record.
     *
     * @return void
     */
    public function close() {
        $this->running = false;
        $this->save();
    }

    // -- the actual logging methods

    /**
     * Adds the log $message to this log post using the $log_level.
     *
     * This method can be used to add any message using any log level. This could even be a custom log level, which is
     * not defined in the log_levels static variable. But be aware that a dynamic custom log level will most likely not
     * be handled correctly by the front end.
     *
     * @param string $log_level The log level string identifier should be one of the values given in static::$log_levels
     * @param string $message The message to be recorded
     *
     * @throws LogError
     * @return void
     */
    public function log(string $log_level, string $message) {
        if (!$this->running) {
            throw new LogError(
                "You are attempting to append a log message to the log '$this->title'. This is not possible " .
                "because this log is not currently active/running!"
            );
        }

        $entry = [
            'message'       => $message,
            'level'         => $log_level,
            'time'          => date(self::$datetime_format)
        ];
        array_push($this->entries, $entry);
    }

    /**
     * Logs an error message.
     *
     * @param string $message
     *
     * @throws LogError
     */
    public function error(string $message) {
        $this->log(self::$log_levels['error'], $message);
    }

    /**
     * Logs a warning message
     *
     * @param string $message
     *
     * @throws LogError
     */
    public function warning(string $message) {
        $this->log(self::$log_levels['warning'], $message);
    }

    /**
     * Logs an info message
     *
     * @param string $message
     *
     * @throws LogError
     */
    public function info(string $message) {
        $this->log(self::$log_levels['info'], $message);
    }

    /**
     * Logs a debug message.
     *
     * @param string $message
     *
     * @throws LogError
     */
    public function debug(string $message) {
        $this->log(self::$log_levels['debug'], $message);
    }

    /**
     * This method returns a list with all the possible log levels supported by the class.
     *
     * @return string[]
     */
    public function get_log_levels() {
        return array_values(self::$log_levels);
    }

    // -- implements "Countable"

    /**
     * This method gets called when the "count" built-in function is used on the log post object. It will return the
     * length of the log post. This is simply defined as the number of entries which are part of the log.
     *
     * @return int
     */
    public function count() {
        return count($this->entries);
    }

    // == STATIC METHODS

    /**
     * Creates and returns a new LogPost object instance, where the log is of $type and has $title.
     *
     * This method creates a NEW log post from scratch. This should be the preferred method of creating new log posts,
     * since the constructor of this wrapper class expects an already existent post ID. After this method has been
     * called, the wordpress post record will already exist in the database. But other than the type and the title,
     * the log post will be created empty -> without entries and running=False.
     *
     * @param string $title The string title of the log
     * @param string $type The string identifier for the type
     *
     * @return LogPost
     */
    public static function create(string $title, string $type = "") {
        $args = [
            'title'         => $title,
            'running'       => false,
            'entries'       => [],
            'type'          => $type
        ];
        $post_id = self::insert($args);

        return new LogPost($post_id);
    }

    /**
     * Given an array of arguments $args, this method will insert a new log post into the database. Returns the int
     * wordpress post ID of this newly created post.
     *
     * The $args array MUST include the following fields.
     * - title: the string title/name of the log
     * - running: a boolean flag indicating if log is still active (new entries are still added)
     * - type: the string type identifier for the log post
     * - entries: A list array of assoc arrays, where each assoc array item represents one entry to the log post
     *
     * @param array $args
     *
     * @return int
     * @throws \Scopubs\Validation\ValidationError
     */
    public static function insert(array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        $postarr = self::create_postarr($args, true);
        $post_id = wp_insert_post($postarr);

        return $post_id;
    }

    /**
     * Given the wordpress $post_id of an existing log post and an array of $args, this method changes the values of
     * that post and replaces them with the new values from $args.
     *
     * The $args array CAN contain any of the following fields:
     * - title: the string title/name of the log
     * - running: a boolean flag indicating if log is still active (new entries are still added)
     * - type: the string type identifier for the log post
     * - entries: A list array of assoc arrays, where each assoc array item represents one entry to the log post
     *
     * @param int $post_id
     * @param array $args
     *
     * @throws \Scopubs\Validation\ValidationError
     */
    public static function update(int $post_id, array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        $postarr = self::create_postarr($args, false);
        $postarr['ID'] = $post_id;

        wp_update_post($postarr);
    }

    /**
     * Given an $args array as it is needed by the "insert" and "update" methods of this class, this method converts
     * it and returns the corresponding $postarr array which is the actual format that has to be passed to
     * wp_insert_post.
     *
     * The $strict boolean flag indicates whether or not the entire $args array is needed. If it is set to true, the
     * given $args array has to contain all the necessary fields or an error will be thrown. If it is false, the args
     * array may also be any subset.
     *
     * @param array $args
     * @param bool $strict
     *
     * @return array
     */
    public static function create_postarr(array $args, bool $strict = true) {
        $mapping = [
            'title'             => 'post_title',
            'running'           => 'meta_input/running',
            'entries'           => 'meta_input/entries',
            'type'              => 'meta_input/type'
        ];

        if ($strict) {
            $keys = array_keys($mapping);
            Util::require_array_keys($args, $keys);
        }

        $postarr = Util::array_mapping($args, $mapping);
        $postarr['post_status'] = 'publish';
        $postarr['post_type'] = self::$post_type;

        return $postarr;
    }
}