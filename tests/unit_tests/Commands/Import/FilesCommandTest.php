<?php

namespace Pantheon\Terminus\UnitTests\Commands\Import;

use Pantheon\Terminus\Commands\Import\FilesCommand;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;
use Pantheon\Terminus\UnitTests\Commands\WorkflowProgressTrait;

/**
 * Class FilesCommandTest
 * Testing class for Pantheon\Terminus\Commands\Import\FilesCommand
 * @package Pantheon\Terminus\UnitTests\Commands\Import
 */
class FilesCommandTest extends CommandTestCase
{
    use WorkflowProgressTrait;

    /**
     * @var Workflow
     */
    protected $workflow;

    /**
     * @inheritdoc
     */
    protected function setup()
    {
        parent::setUp();

        $this->workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->command = new FilesCommand($this->getConfig());
        $this->command->setSites($this->sites);
        $this->command->setLogger($this->logger);
        $this->command->setInput($this->input);
        $this->expectWorkflowProcessing();
    }
    
    /**
     * Exercises import:files command with a valid URL
     */
    public function testImportValidURL()
    {
        $workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
        $site_name = 'site_name';
        $this->environment->id = 'env_id';
        $valid_url = 'a_valid_url';

        $this->expectConfirmation();
        $this->environment->expects($this->once())
            ->method('importFiles')
            ->with($this->equalTo($valid_url))
            ->willReturn($this->workflow);
        $this->site->expects($this->once())
            ->method('get')
            ->with($this->equalTo('name'))
            ->willReturn($site_name);

        $this->logger->expects($this->once())
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('Imported files to {site}.{env}.'),
                $this->equalTo(['site' => $site_name, 'env' => $this->environment->id,])
            );

        $out = $this->command->import("$site_name.{$this->environment->id}", $valid_url);
        $this->assertNull($out);
    }

    /**
     * Exercises import:files command when declining the confirmation
     *
     * @todo Remove this when removing TerminusCommand::confirm()
     */
    public function testImportConfirmationDecline()
    {
        $site_name = 'site_name';
        $this->environment->id = 'env_id';
        $valid_url = 'a_valid_url';

        $this->expectConfirmation(false);
        $this->environment->expects($this->never())
            ->method('importFiles');
        $this->site->expects($this->never())
            ->method('get');
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->import("$site_name.{$this->environment->id}", $valid_url);
        $this->assertNull($out);
    }
}
