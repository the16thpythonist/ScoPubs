<?php


namespace Scopubs\Command;


use Scopubs\Log\LogPost;
use Scopubs\Validation\DataValidator;

/**
 * Class AbstractCommand
 *
 * This is the abstract base class, which should be inherited from when creating new command classes.
 * The documentation of this class will mainly focus on how to create a custom command implementation. For further
 * information on how to then invoke this command for example by using the REST api, please see the documentation of
 * the CommandManager class.
 *
 * **WHAT IS THE COMMAND SYSTEM?**
 *
 * The "command" system can be used to easily trigger background commands to be executed on the wordpress server side.
 * This is mainly a very convenient system for quickly implementing background tasks for wordpress plugin. The main use
 * case are processes which take a long time and/or which have to be triggered manually by a user. Consider the
 * following example: You want to create a plugin which semi-regularly has to query a different web database / REST Api
 * and then update the local posts according to some rules. This process takes a long time, too much time in fact to
 * expect a user to wait around until it finishes. You also dont want to do it regularly (say automatically every night)
 * because (a) the records just dont change often or (b) you dont want to screw the third party REST api with too many
 * requests and risk running into a rate limit. So this kind of rules out creating a custom interface for the user and
 * you also dont want to use the cron. This kind of process you would want a user to trigger once in a while, be able
 * to forget about it and see the results at some point later. This is where the "command" system comes in.
 *
 * The command system offers the possibility to easily create such background commands by extending this abstract base
 * class and registering it with the CommandManager. Within the dashboard of the admin backend, there will then be a
 * widget which enables you to select between different commands, enter parameters and then trigger it's execution.
 * The commands also offer the possibility to update log messages. These log messages are written to posts of the
 * custom "Log" post type. By looking at these log posts, it is possible to track the progress of the process either in
 * real time or review it after it is already done.
 *
 * **CREATING A COMMAND**
 *
 * A new command can be created simply by creating a sub class which inherits from this abstract base class and then
 * implementing the necessary abstract fields and methods.
 *
 * There are three important STATIC fields which have to be defined for this subclass:
 * - $name: This fields is supposed to be a string SLUG (best practice is using just lower case characters and
 *   underscores). This will be the string identifier by which the command will be displayed in the frontend or which
 *   can be used to invoke it via the REST api. Important: this has to be unique. No two commands can have the same name
 * - $description: This is a string field which should contain a human readable description of the purpose of the
 *   command which will be displayed to the user when selecting the command. Sidenot: The content of this string is
 *   not cleaned for hmtl tags. So if you want to do something fancy with the description it would be possible.
 * - $parameters: This is supposed to be an associative array, which defines what kind of parameters the command
 *   expects. The keys are the string names of those parameters (all lowercase and underscores) and the values are
 *   associative arrays which define the properties of that parameter.
 *
 * The associative arrays which are the values of the $parameters array have to(!) contain the following fields:
 * - name: The string name of the command. This should be the same as the string key used for this parameter.
 * - description: A description for the purpose of this parameter.
 * - type: A string identifier for the type of the parameter. This could for example be "string" or "integer" or
 *   "boolean".
 * - default: The default value for this parameter. Important: every parameter needs a default value!
 * - validators: This is a list of either string names for the DataValidator class or callable objects. In the order in
 *   which they appear in this list, these filter functions will be applied to the value of the parameter before this
 *   is actually provided to the command. They can be used to validate, sanitize or otherwise modify the user entered
 *   values.
 *
 * Other than these static properties, the abstract base class just expects the implementation of a single abstract
 * method, which is the "run" method. This is the method which actually contains the command code. It will be executed
 * whenever the command is invoked by some means. It accepts a single parameter $args, which is an assoc array which
 * contains the entered parameters for the command. The key names will be the same as in the $parameters assoc array.
 *
 *      class ExampleCommand extends AbstractCommand {
 *
 *          public static $name = "example_command";
 *          public static $description = "A simple example";
 *          public static $parameters = [
 *              "param1" => [
 *                  "name" => "param1",
 *                  "description": "Example for defining parameter",
 *                  "type": "string",
 *                  "default": "some string",
 *                  "validators": ["validate_is_string"]
 *              ]
 *          ];
 *
 *          public function run(array $args) {
 *              $this->log->info($args["param1"]);
 *          }
 *      }
 *
 * For another example implementation see the HelloWorldCommand class.
 *
 * **LOGGING PROGRESS**
 *
 * Within the "run" method, you will have access to the "log" attribute of the class, which stores the log object which
 * was created for this command. It can be used to log messages about the progress and/or outcome of the command. A
 * error message for example can recorded by using it's "error" method and so on.
 *
 *      public function run(array $args) {
 *          $this->log->info("Making some progress");
 *          $this->log->error("Oh no! A error");
 *      }
 *
 * The "start" and "close" methods for the log post do NOT have to be called. This is automatically handled by the
 * wrapper method, as part of which "run" is being called.
 *
 * **EXCEPTIONS DURING COMMAND**
 *
 * Of course it would always be good practice to catch any expected errors within the implementation for the run method
 * but even if that is not the case and an exception would "slip through", there is a try catch block for every errors
 * and exceptions in the wrapper method. This will guarantee that the command always finished properly which means the
 * log gets saved. The error message will then also be displayed at the end of the log.
 *
 * **REGISTERING A COMMAND**
 *
 * To implement a new executable command, AbstractCommand has to be extended. But after the implementation, the command
 * still has to be registered. This can be done by calling the static "register" method of the child class. This method
 * does not have to be custom implemented, it gets inherited from the base class. But it has to be called at the top
 * level of the plugin file.
 *
 *      // MyPlugin.php
 *      // Register custom post types etc...
 *      ExampleCommand::register();
 *
 * **RUNNING A COMMAND IN CODE**
 *
 * To execute a command from within the code, you will have to create a new instance of the corresponding command class
 * and then call the "execute" function, passing it the required arguments array. Do not call the "run" method!
 * Execute is the appropriate wrapper which does error handling, saving of the log file etc. It will eventually call
 * run by itself.
 *
 *      $args = ["param1" => "my value"];
 *      $example_command = new ExampleCommand():
 *      $example_command->execute($args);
 *
 * Note that this method will run the command in the foreground! This will not spawn a new thread. Instead the call to
 * "execute" will block until the command code itself is finished. If you intend to start a new background thread from
 * the code, consider making a new REST request to the very same server to trigger a new server handling instance for
 * the command.
 * Triggering commands by REST request will be explained in CommandManager.php
 *
 * @package Scopubs\Command
 */
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

    /**
     * This function has to be called to actually run the command. It is a wrapper for the "run" method which actually
     * implements the command specific functionality.
     *
     * This function additionally does error handling, the appropriate saving of the log file at the end of the
     * execution and the processing of the arguments.
     *
     * @param array $args The array with the arguments. This array has to contain all arguments which are also
     *     defined in the $parameters static property.
     *
     * @return bool
     */
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

    /**
     * Given the unprocessed $args array passed to the command call, this method will apply all the "validators"
     * filters specified for each individual parameter of the command to validate, sanitize or otherwise transform
     * these values before actually passing them to the actual command code.
     *
     * @param array $args
     *
     * @return array
     * @throws \Scopubs\Validation\ValidationError If one of the validation functions fails for the respective
     *          parameter value
     * @throws \ArgumentCountError If one of the parameters defined in $parameters is missing in the $args array
     */
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

    /**
     * Returns an assoc array representation of the command.
     *
     * The resulting assoc array will have the following fields: 'name', 'description' and 'parameters'. This method
     * mainly exists because the information about all available commands will eventually have to be returned in
     * JSON format as a REST response.
     *
     * @return array
     */
    public static function to_array() {
        return [
            'name'              => static::$name,
            'description'       => static::$description,
            'parameters'        => static::$parameters
        ];
    }

    /**
     * Registers the command with the command management system, such that it can be triggered by a REST call.
     *
     * @return void
     */
    public static function register() {
        // The "load_commands" filter actually is not a wordpress native filter, but is a filter which is eventually
        // executed by the CommandManager class at the beginning of the "init" wordpress action. The filter value
        // is an array which should contain the classes of all commands to be registered. So the registered callback
        // method "filter_commands" here will modify this array by adding this very class to it, thereby registering
        // the command.
        add_filter('load_commands', [static::class, 'filter_commands']);
    }

    /**
     * Callback method for the filter hook "load_commands". Adds this very class to the array of known commands for the
     * command manager.
     *
     * @param array $commands Assoc array, where the keys are the string identifier names of the commands and the
     *      values are the corresponding command classes.
     *
     * @return array The modified $commands array.
     */
    public static function filter_commands(array $commands) {
        // Now here is an interesting thing: This is a static method of the AbstractCommand base class and we implement
        // this to add the class to this array. Would this not just add "AbstractCommand" as the value for every child
        // class which extends AbstractCommand? Because theoretically it is implemented here.
        // That would actually be the case if we were using self::class here. This would always return the class where
        // the method is actually implemented. But newer versions of php have a feature called "late static binding"
        // By using the static::class, The class name of the specific class from where it is CALLED is looked up at
        // runtime!
        $commands[static::$name] = static::class;
        return $commands;
    }
}