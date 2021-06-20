<?php


namespace Scopubs\Log;

/**
 * Class VoidLog
 *
 * This is a minimal / mock implementation of the AbstractLog interface for creating log classes. It does internally
 * store the messages within the $this->entries array, but the messages are never saved / processed any further.
 *
 * @package Scopubs\Log
 */
class VoidLog extends AbstractLog{

    public $entries;
    public $running;

    public function __construct() {
        $this->entries = [];
        $this->running = False;
    }

    // -- implementing "AbstractLog"

    public function start() {
        $this->running = True;
    }

    public function close() {
        $this->running = False;
    }

    public function log( string $level, string $message ) {
        $entry = [
            'message'       => $message,
            'level'         => $level,
            'time'          => $this->datetime_now()
        ];
        array_push($this->entries, $entry);
    }
}