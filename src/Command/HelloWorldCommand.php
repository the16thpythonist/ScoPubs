<?php


namespace Scopubs\Command;


class HelloWorldCommand extends AbstractCommand{

    public static $name = "hello_world";
    public static $description = "Simply writes Hello World into the log file";
    public static $parameters = [
        'postfix' => [
            'name'          => 'postfix',
            'description'   => 'The character which will be displayed after the main text.',
            'type'          => 'string',
            'validators'    => ['validate_is_string'],
            'default'       => '!!!'
        ]
    ];

    public function run( array $args ) {
        $this->log->info('Hello World ' . $args['postfix']);
    }
}