<?php

namespace Pantheon\Terminus\UnitTests\Commands\Redis;

use Pantheon\Terminus\Commands\Redis\DisableCommand;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;
use Terminus\Models\Redis;

class DisableCommandTest extends CommandTestCase
{
    public function testDisableRedis()
    {
        $workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
        // workflow succeeded
        $workflow->expects($this->once())->method('checkProgress')->willReturn(true);
        $workflow->expects($this->once())->method('getMessage')->willReturn('successful workflow');

        $this->site->redis = $this->getMockBuilder(Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->site->redis->expects($this->once())
            ->method('disable');
        $this->site->expects($this->once())
            ->method('converge')
            ->willReturn($workflow);

        $this->logger->expects($this->at(0))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('Redis disabled. Converging bindings.')
            );
        $this->logger->expects($this->at(1))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('successful workflow')
            );

        $command = new DisableCommand();
        $command->setSites($this->sites);
        $command->setLogger($this->logger);
        $command->disableRedis('mysite');
    }
}
