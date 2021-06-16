<?php


namespace Scopubs\Log;


use Scopubs\Validation\DataValidator;
use Scopubs\Util;

class LogPost {

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

    public $title;
    public $date;

    // meta values
    public $running;
    public $entries;

    // -- class constants

    public const META_FIELDS = [
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
        'running'           => ['validate_is_boolean'],
        'entries'           => ['validate_is_array']
    ];

    public function __construct(int $post_id) {
        $this->post_id = $post_id;
        $this->post = get_post($post_id);

        $this->title = $this->post->post_title;

        // Loading the meta values
        $this->running = (bool) get_post_meta($this->post_id, 'running', true);
        $this->entries = get_post_meta($this->post_id, 'entries', true);
    }

    public function get_update_args() {
        return [
            'title'             => $this->title,
            'running'           => $this->running,
            'entries'           => $this->entries,
        ];
    }

    public function save() {
        $update_args = $this->get_update_args();
        self::update($this->post_id, $update_args);
    }

    public function start() {
        $this->running = true;
    }

    public function close() {
        $this->running = false;
        $this->save();
    }

    // -- the actual logging methods

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

    public function error(string $message) {
        $this->log(self::$log_levels['error'], $message);
    }

    public function warning(string $message) {
        $this->log(self::$log_levels['warning'], $message);
    }

    public function info(string $message) {
        $this->log(self::$log_levels['info'], $message);
    }

    public function debug(string $message) {
        $this->log(self::$log_levels['debug'], $message);
    }

    public function get_log_levels() {
        return array_values(self::$log_levels);
    }

    // == STATIC METHODS

    public static function create(string $title) {
        $args = [
            'title'         => $title,
            'running'       => false,
            'entries'       => []
        ];
        $post_id = self::insert($args);

        return new LogPost($post_id);
    }

    public static function insert(array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        $postarr = self::create_postarr($args, true);
        $post_id = wp_insert_post($postarr);

        return $post_id;
    }

    public static function update(int $post_id, array $args) {
        $args = DataValidator::apply_array($args, self::INSERT_VALUE_VALIDATORS);

        $postarr = self::create_postarr($args, false);
        $postarr['ID'] = $post_id;

        wp_update_post($postarr);
    }

    public static function create_postarr(array $args, bool $strict = true) {
        $mapping = [
            'title'             => 'post_title',
            'running'           => 'meta_input/running',
            'entries'           => 'meta_input/entries'
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