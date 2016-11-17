<?php

namespace Pantheon\Terminus\UnitTests\Commands\Site\Org;

use Pantheon\Terminus\Commands\Site\Org\AddCommand;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\UnitTests\Commands\Org\Site\OrgSiteCommandTest;
use Terminus\Collections\SiteOrganizationMemberships;

class AddCommandTest extends OrgSiteCommandTest
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

        $this->organization->expects($this->any())
            ->method('getName')
            ->willReturn('org_id');

        $this->site->expects($this->any())
            ->method('getName')
            ->willReturn('my-site');

        $this->command = new AddCommand($this->getConfig());
        $this->command->setSites($this->sites);
        $this->command->setLogger($this->logger);
        $this->command->setSession($this->session);
    }

    public function testAddOrg()
    {
        $workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
        // workflow succeeded
        $workflow->expects($this->once())->method('checkProgress')->willReturn(true);
        $workflow->expects($this->once())->method('getMessage')->willReturn('successful workflow');

        $this->site->org_memberships->expects($this->once())
            ->method('create')
            ->with('org_id', 'team_member')
            ->willReturn($workflow);

        $this->logger->expects($this->at(0))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('Adding {org} as a supporting organization to {site}.'),
                $this->equalTo(['site' => 'my-site', 'org' => 'org_id'])
            );
        $this->logger->expects($this->at(1))
            ->method('log')->with(
                $this->equalTo('notice'),
                $this->equalTo('successful workflow')
            );
        $this->command->addOrgToSite('my-site', 'org_id');
    }
}
