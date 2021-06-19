<?php


namespace Scopubs\Command;


use Scopubs\Log\LogPost;
use Scopubs\Validation\DataValidator;

abstract class AbstractCommand {

    // == TO BE IMPLEMENTED

    public static $name = null;
    public static $description = null;
    public static $parameters = null;

    abstract public function run(array $args);

    // == ATTRIBUTES

    // -- static

    public static $log_prefix = 'Command: ';

    // -- intrinsic

    public $log_name;
    public $log;

    // == METHODS

    public function __construct() {
        $this->log_name = sprintf('%s%s', self::$log_prefix, static::$name);
        $this->log = LogPost::create($this->log_name, 'command');
        $this->log->start();
        $this->log->info('Starting Command: ' . static::$name);
    }

    public function execute(array $args): bool {
        try {
            $processed_args = self::process_args($args);
            $this->run($processed_args);
            $this->log->info("Command " . static::$name . " exits with code 0!");
        } catch (\Error $e) {
            $this->log->error($e->getMessage());
            $this->log->error("Command " . static::$name . " exits with code 1");
            return false;
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            $this->log->error("Command " . static::$name . " exits with code 1");
            return false;
        } finally {
            $this->log->close();
        }

        // At the end of no error has previously caused the return of "false", we return true to indicate that the
        // command execution was successful
        return true;
    }

    public function process_args(array $args): array {
        $processed_args = [];
        foreach (static::$parameters as $parameter_name => $parameter_spec) {
            if (!array_key_exists($parameter_name, $args)) {
                throw new \ArgumentCountError(
                    "The required parameter $parameter_name for the execution of the command " . static::$name .
                    "is missing from the arguments array. Please supply the $parameter_name argument!"
                );
            }

            $value = $args[$parameter_name];
            $validated_value = DataValidator::apply_single($value, static::$parameters[$parameter_name]['validators']);
            $processed_args[$parameter_name] = $validated_value;
        }
        return $processed_args;
    }

    // -- static functions

    public static function to_array() {
        return [
            'name'              => static::$name,
            'description'       => static::$description,
            'parameters'        => static::$parameters
        ];
    }

    public static function register() {
        add_filter('load_commands', [static::class, 'filter_commands']);
    }

    public static function filter_commands(array $commands) {
        $commands[static::$name] = static::class;
        return $commands;
    }
}