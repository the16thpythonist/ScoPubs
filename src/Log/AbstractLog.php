<?php


namespace Scopubs\Log;

/**
 * Class AbstractLog
 *
 * The abstract base class for implementing a "log" class. This abstract base class defines a common interface for log
 * objects. All child classes which extend this class and implement its abstract methods can be used interchangeably
 * to log messages about the progress of some process. Additionally to providing a common interface, this class also
 * provided some default implementations for utility and wrapper functions.
 *
 * **CREATING CUSTOM LOG CLASS**
 *
 * To create a custom log implementation, the child class needs to implement the following:
 *
 * - start(): This method should contain all the necessary steps to activate the log such that all subsequent calls to
 *   the "log" method will work.
 * - close(): This method should contain all steps to finalize the log. This would for example include saving the
 *   entries to some persistent record such as a file or database. After this method has been called. Subsequent calls
 *   to "log" should fail.
 * - log($level, $message): This method should actually insert a log entry to the log. The first parameter is a string
 *   identifier for the log level.
 *
 * For a minimal example implementation see VoidLog.php
 *
 * **INTERNAL REPRESENTATION**
 *
 * Internally each log instance is supposed to store the log information within the "$this->entries" variable. This is
 * supposed to be a sorted list array, where each element is an assoc array. These assoc array elements each represent
 * one entry to the log. They should contain the following fields:
 * - message: The actual string log message for this entry
 * - level: The string identifier for the level under which this entry was made
 * - time: A string of the datetime at which the entry was added to the log.
 *
 * @package Scopubs\Log
 */
abstract class AbstractLog {

    // TO BE IMPLEMENTED


    abstract public function start();

    abstract public function close();

    abstract public function log(string $level, string $message);

    // == PRE-IMPLEMENTED FUNCTIONALITY

    public $entries;
    public $datetime_format = 'Y-m-d H:i:s';

    /**
     * Returns a string of the current datetime. The format of this datetime string is based on the instance value of
     * $this->datetime_format.
     *
     * @return string
     */
    public function datetime_now() {
        return date($this->datetime_format, time());
    }

    /**
     * Adds an error $message to the log
     *
     * @param string $message
     */
    public function error(string $message) {
        $this->log('error', $message);
    }

    /**
     * Adds a warning $message to the log
     *
     * @param string $message
     */
    public function warning(string $message) {
        $this->log('warning', $message);
    }

    /**
     * Adds a info $message to the log
     *
     * @param string $message
     */
    public function info(string $message) {
        $this->log('info', $message);
    }

    /**
     * Adds a debug $message to the log
     *
     * @param string $message
     */
    public function debug(string $message) {
        $this->log('debug', $message);
    }

}