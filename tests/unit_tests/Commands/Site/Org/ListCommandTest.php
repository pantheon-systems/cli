<?php

namespace Pantheon\Terminus\UnitTests\Commands\Site\Org;

use Pantheon\Terminus\Commands\Site\Org\ListCommand;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;
use Terminus\Collections\SiteOrganizationMemberships;
use Terminus\Models\SiteOrganizationMembership;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class ListCommandTest extends CommandTestCase
{
    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->site->org_memberships = $this->getMockBuilder(SiteOrganizationMemberships::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->command = new ListCommand($this->getConfig());
        $this->command->setSites($this->sites);
        $this->command->setLogger($this->logger);
    }

    public function testListOrgs()
    {
        $data = [
            ['org_name' => 'abc', 'org_id' => '000'],
            ['org_name' => 'def', 'org_id' => '111'],
        ];
        $memberships = [];
        foreach ($data as $item) {
            $mock = $this->getMockBuilder(SiteOrganizationMembership::class)
                ->disableOriginalConstructor()
                ->getMock();
            $mock->expects($this->once())
                ->method('serialize')
                ->willReturn($item);
            $memberships[] = $mock;
        }

        $this->site->org_memberships->expects($this->once())
            ->method('all')
            ->willReturn($memberships);


        $out = $this->command->listOrgs('my-site');
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals($data, $out->getArrayCopy());
    }

    public function testListOrgsNone()
    {
        $this->site->org_memberships->expects($this->once())
            ->method('all')
            ->willReturn([]);

        $this->logger->expects($this->at(0))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('This site has no supporting organizations.')
            );

        $this->command->listOrgs('my-site');
    }
}
