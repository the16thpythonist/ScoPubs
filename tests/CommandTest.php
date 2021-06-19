<?php

use Scopubs\Command\CommandManager;
use Scopubs\Command\HelloWorldCommand;

class CommandTest extends WP_UnitTestCase{

    // -- The mock implementation "HelloWorldCommand"

    public function test_construct_hello_world_command() {
        // If a new instance of hello world command can be constructed properly and if
        // the corresponding log post is also created.
        $command = new HelloWorldCommand();
        $this->assertIsString($command::$name);
        $this->assertIsString($command::$description);
        $this->assertIsArray($command::$parameters);
        $this->assertNotEquals(false, get_post_status($command->log->post_id));
    }

    public function test_hello_world_command_to_array() {
        $command_array = HelloWorldCommand::to_array();
        $this->assertEquals(3, count($command_array));
        $this->assertArrayHasKey('name', $command_array);
        $this->assertArrayHasKey('parameters', $command_array);
    }

    public function test_hello_world_command_execute() {
        $command = new HelloWorldCommand();
        $success = $command->execute(['postfix' => '!11!!']);
        $this->assertTrue($success);
        $this->assertEquals(3, count($command->log));
    }

    public function test_hello_world_command_fails_with_too_few_parameters() {
        $command = new HelloWorldCommand();
        // The command requires one argument, but since we are not passing any this should fail
        $success = $command->execute([]);
        $this->assertFalse($success);
    }

    public function test_register_hello_world_command_and_invoke_through_manager() {
        CommandManager::add_command('hello_world', HelloWorldCommand::class);
        $success = CommandManager::execute_command('hello_world', ['postfix' => '']);
        $this->assertTrue($success);
    }
}