<?php

namespace Pantheon\Terminus\UnitTests\Commands\Branch;

use Pantheon\Terminus\Commands\Branch\DeleteCommand;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;
use Pantheon\Terminus\Collections\Branches;
use Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Models\Branch;

class DeleteCommandTest extends CommandTestCase
{
    protected $command;

    public function setUp()
    {
        parent::setUp();

        $this->command = new DeleteCommand();
        $this->command->setSites($this->sites);
        $this->command->setLogger($this->logger);
    }

    public function testDeleteBranch()
    {
        $workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
        // workflow succeeded
        $workflow->expects($this->once())->method('checkProgress')->willReturn(true);
        $workflow->expects($this->once())->method('getMessage')->willReturn('successful workflow');

        $branch = $this->getMockBuilder(Branch::class)
            ->disableOriginalConstructor()
            ->getMock();
        $branch->expects($this->once())
            ->method('delete')
            ->willReturn($workflow);
        $branches = $this->getMockBuilder(Branches::class)
            ->disableOriginalConstructor()
            ->getMock();
        $branches->expects($this->once())
            ->method('get')
            ->with('branch-name')
            ->willReturn($branch);

        $this->site->expects($this->once())
            ->method('getBranches')
            ->willReturn($branches);

        $this->logger->expects($this->at(0))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('Deleting the {branch_id} branch of the site {site_id}.'),
                $this->equalTo(['branch_id' => 'branch-name', 'site_id' => 'my-site'])
            );
        $this->logger->expects($this->at(1))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('successful workflow')
            );

        $this->command->deleteBranch('my-site', 'branch-name');
    }

    public function testMasterBranch()
    {
        $this->setExpectedException(TerminusException::class, 'You cannot delete the master branch');

        $this->command->deleteBranch('my-site', 'master');
    }
    public function testTestBranch()
    {
        $this->setExpectedException(TerminusException::class, 'You cannot delete the test branch');

        $this->command->deleteBranch('my-site', 'test');
    }
    public function testLiveBranch()
    {
        $this->setExpectedException(TerminusException::class, 'You cannot delete the live branch');

        $this->command->deleteBranch('my-site', 'live');
    }
}
