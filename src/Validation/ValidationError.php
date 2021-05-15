<?php


namespace Scopubs\Validation;


class ValidationError extends \Exception{

    public function __construct(string $key, string $msg, int $code = 0, \Throwable $previous  = null) {
        $message = "Validation failed for ${key}: ${msg}";
        parent::__construct($message, $code, $previous);
    }
}