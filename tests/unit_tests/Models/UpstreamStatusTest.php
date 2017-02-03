<?php

namespace Pantheon\Terminus\UnitTests\Models;

use Pantheon\Terminus\Models\Environment;
use Pantheon\Terminus\Models\UpstreamStatus;

/**
 * Class UpstreamStatusTest
 * Testing class for Pantheon\Terminus\Models\UpstreamStatus
 * @package Pantheon\Terminus\UnitTests\Models
 */
class UpstreamStatusTest extends ModelTestCase
{
    /**
     * @var Environment
     */
    protected $environment;
    /**
     * @var UpstreamStatus
     */
    protected $model;
    /**
     * @var string
     */
    protected $request_url;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->environment = $this->getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->environment->id = 'environment id';
        $this->environment->site = (object)['id' => 'site id',];
        $base_branch = "refs/heads/{$this->environment->id}";
        $this->request_url = "sites/{$this->environment->site->id}/code-upstream-updates?base_branch=$base_branch";

        $this->environment->expects($this->once())
            ->method('getBranchName')
            ->with()
            ->willReturn($this->environment->id);

        $this->model = new UpstreamStatus(null, ['environment' => $this->environment,]);
        $this->model->setRequest($this->request);
    }

    /**
     * Tests UpstreamStatus::getStatus() when the status is current
     */
    public function testGetStatusCurrent()
    {
        $this->request->expects($this->once())
            ->method('request')
            ->with($this->equalTo($this->request_url))
            ->willReturn(['data' => (object)['behind' => 0,],]);

        $this->assertEquals('current', $this->model->getStatus());
    }

    /**
     * Tests UpstreamStatus::getStatus() when the status is outdated
     */
    public function testGetStatusOutdated()
    {
        $this->request->expects($this->once())
            ->method('request')
            ->with($this->equalTo($this->request_url))
            ->willReturn(['data' => (object)['behind' => 1,],]);

        $this->assertEquals('outdated', $this->model->getStatus());
    }

    /**
     * Tests UpstreamStatus::getUpdates()
     */
    public function testGetUpdates()
    {
        $expected = 'return me';

        $this->request->expects($this->once())
            ->method('request')
            ->with($this->equalTo($this->request_url))
            ->willReturn(['data' => $expected,]);

        $out = $this->model->getUpdates();
        $this->assertEquals($expected, $out);
    }

    /**
     * Tests UpstreamStatus::hasUpdates() when there are no updates
     */
    public function testHasNoUpdates()
    {
        $this->request->expects($this->once())
            ->method('request')
            ->with($this->equalTo($this->request_url))
            ->willReturn(['data' => (object)['behind' => 0,],]);

        $this->assertFalse($this->model->hasUpdates());
    }

    /**
     * Tests UpstreamStatus::hasUpdates() when there are updates
     */
    public function testHasUpdates()
    {
        $this->request->expects($this->once())
            ->method('request')
            ->with($this->equalTo($this->request_url))
            ->willReturn(['data' => (object)['behind' => 1,],]);

        $this->assertTrue($this->model->hasUpdates());
    }
}
