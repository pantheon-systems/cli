<?php
/**
 * @file
 * Contains Pantheon\Terminus\UnitTests\Commands\Auth\MachineTokenCommandTest
 */


namespace Pantheon\Terminus\UnitTests\Commands\Auth;

use Pantheon\Terminus\Session\Session;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;
use Psr\Log\NullLogger;
use Terminus\Collections\MachineTokens;
use Pantheon\Terminus\Models\User;

abstract class MachineTokenCommandTest extends CommandTestCase
{
    protected $session;
    protected $machine_tokens;
    protected $user;
    protected $logger;
    protected $command;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->machine_tokens = $this->getMockBuilder(MachineTokens::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->user->expects($this->any())
            ->method('getMachineTokens')
            ->willReturn($this->machine_tokens);

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->session->method('getUser')
            ->willReturn($this->user);

        $this->logger = $this->getMockBuilder(NullLogger::class)
            ->setMethods(array('log'))
            ->getMock();
    }
}
