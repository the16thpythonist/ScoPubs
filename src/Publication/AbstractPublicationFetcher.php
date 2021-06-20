<?php


namespace Scopubs\Publication;

use Scopubs\Author\ObservedAuthorPost;
use Scopubs\Log\AbstractLog;
use Scopubs\Validation\DataValidator;
use Scopubs\Validation\ValidationError;


abstract class AbstractPublicationFetcher {

    // TO BE IMPLEMENTED

    public static $parameters;

    abstract public function next();

    // PRE-IMPLEMENTED FUNCTIONALITY

    public $observed_authors;
    public $log;
    public $args;

    /**
     * AbstractPublicationFetcher constructor.
     *
     * @param AbstractLog $log This should be an existing and running log object which implements the AbstractLog
     *      interface and which will be used to log the progress of the fetcher.
     * @param array $args This is an array of arguments which potentially modify the behavior of the fetching process.
     *
     * @throws ValidationError If one of the argument validators fails.
     */
    public function __construct( $log, $args ) {
        $this->observed_authors = ObservedAuthorPost::all();
        $this->log = $log;
        $this->args = self::process_args($args);
    }

    /**
     *
     *
     * @param array $args
     *
     * @return array
     * @throws \Scopubs\Validation\ValidationError
     */
    public static function process_args( array $args ) {
        $processed_args = [];
        foreach (static::$parameters as $key => $parameter_spec) {
            if (array_key_exists($key, $args )) {
                $validators =  $parameter_spec['validators'];
                $processed_args[$key] = DataValidator::apply_single($args[$key], $validators);
            } else {
                $processed_args[$key] = $parameter_spec['default'];
            }
        }
        return $processed_args;
    }

}