<?php

namespace Pantheon\Terminus\UnitTests\Commands\Site;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\Site\ListCommand;
use Pantheon\Terminus\Session\Session;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;

/**
 * Test suite class for Pantheon\Terminus\Commands\Site\ListCommand
 */
class ListCommandTest extends CommandTestCase
{
    /**
     * @inheritdoc
     */
    protected function setup()
    {
        parent::setUp();

        $this->command = new ListCommand($this->getConfig());
        $this->command->setLogger($this->logger);
        $this->command->setSession($this->session);
    }

    /**
     * Exercises site:list with no filters and all membership types
     */
    public function testListAllSites()
    {
        $dummy_info = [
            'name' => 'my-site',
            'id' => 'site_id',
            'service_level' => 'pro',
            'framework' => 'cms',
            'owner' => 'user_id',
            'created' => '1984-07-28 16:40',
            'memberships' => 'org_id: org_url',
        ];

        $this->site->memberships = ['org_id: org_url'];
        $this->sites->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo(['org_id' => null, 'team_only' => false,]))
            ->willReturn($this->sites);
        $this->sites->expects($this->never())
            ->method('filterByName');
        $this->sites->expects($this->never())
            ->method('filterByOwner');
        $this->site->expects($this->any())
            ->method('serialize')
            ->with()
            ->willReturn($dummy_info);
        $this->sites->expects($this->once())
            ->method('all')
            ->with()
            ->willReturn([$this->site, $this->site,]);
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->index();
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals([$dummy_info, $dummy_info,], $out->getArrayCopy());
    }

    /**
     * Exercises site:list with no filters and team membership type
     */
    public function testListTeamSitesOnly()
    {
        $dummy_info = [
            'name' => 'my-site',
            'id' => 'site_id',
            'service_level' => 'pro',
            'framework' => 'cms',
            'owner' => 'user_id',
            'created' => '1984-07-28 16:40',
            'memberships' => 'user_id: Team',
        ];

        $this->site->memberships = ['user_id: Team'];
        $this->sites->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo(['org_id' => null, 'team_only' => true,]))
            ->willReturn($this->sites);
        $this->sites->expects($this->never())
            ->method('filterByName');
        $this->sites->expects($this->never())
            ->method('filterByOwner');
        $this->site->expects($this->any())
            ->method('serialize')
            ->with()
            ->willReturn($dummy_info);
        $this->sites->expects($this->once())
            ->method('all')
            ->with()
            ->willReturn([$this->site, $this->site,]);
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->index(['team' => true, 'owner' => null, 'org' => null, 'name' => null,]);
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals([$dummy_info, $dummy_info,], $out->getArrayCopy());
    }

    /**
     * Exercises site:list with no filters and belonging to an org
     */
    public function testListOrgSitesOnly()
    {
        $dummy_info = [
            'name' => 'my-site',
            'id' => 'site_id',
            'service_level' => 'pro',
            'framework' => 'cms',
            'owner' => 'user_id',
            'created' => '1984-07-28 16:40',
            'memberships' => 'org_id: org_url',
        ];

        $this->site->memberships = ['org_id: org_url'];
        $this->sites->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo(['org_id' => 'org_id', 'team_only' => false,]))
            ->willReturn($this->sites);
        $this->sites->expects($this->never())
            ->method('filterByName');
        $this->sites->expects($this->never())
            ->method('filterByOwner');
        $this->site->expects($this->any())
            ->method('serialize')
            ->with()
            ->willReturn($dummy_info);
        $this->sites->expects($this->once())
            ->method('all')
            ->with()
            ->willReturn([$this->site, $this->site,]);
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->index(['team' => false, 'owner' => null, 'org' => 'org_id', 'name' => null,]);
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals([$dummy_info, $dummy_info,], $out->getArrayCopy());
    }

    /**
     * Exercises site:list with a name filter of either membership type
     */
    public function testListByNameRegex()
    {
        $dummy_info = [
            'name' => 'my-site',
            'id' => 'site_id',
            'service_level' => 'pro',
            'framework' => 'cms',
            'owner' => 'user_id',
            'created' => '1984-07-28 16:40',
            'memberships' => 'org_id: org_url',
        ];
        $regex = '(.*)';

        $this->site->memberships = ['org_id: org_url'];
        $this->sites->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo(['org_id' => null, 'team_only' => false,]))
            ->willReturn($this->sites);
        $this->sites->expects($this->once())
            ->method('filterByName')
            ->with($this->equalTo($regex))
            ->willReturn($this->sites);
        $this->sites->expects($this->never())
            ->method('filterByOwner');
        $this->site->expects($this->any())
            ->method('serialize')
            ->with()
            ->willReturn($dummy_info);
        $this->sites->expects($this->once())
            ->method('all')
            ->with()
            ->willReturn([$this->site, $this->site,]);
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->index(['team' => false, 'owner' => null, 'org' => null, 'name' => $regex,]);
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals([$dummy_info, $dummy_info,], $out->getArrayCopy());
    }

    /**
     * Exercises site:list of either membership type owned by a user of a given ID
     */
    public function testListByOwner()
    {
        $user_id = 'user_id';
        $dummy_info = [
          'name' => 'my-site',
          'id' => 'site_id',
          'service_level' => 'pro',
          'framework' => 'cms',
          'owner' => $user_id,
          'created' => '1984-07-28 16:40',
          'memberships' => 'org_id: org_url',
        ];

        $this->site->memberships = ['org_id: org_url'];
        $this->sites->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo(['org_id' => null, 'team_only' => false,]))
            ->willReturn($this->sites);
        $this->session->expects($this->never())
            ->method('filterByName');
        $this->sites->expects($this->once())
            ->method('filterByOwner')
            ->with($this->equalTo($user_id))
            ->willReturn($this->sites);
        $this->site->expects($this->any())
            ->method('serialize')
            ->with()
            ->willReturn($dummy_info);
        $this->sites->expects($this->once())
            ->method('all')
            ->with()
            ->willReturn([$this->site, $this->site,]);
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->index(['team' => false, 'owner' => $user_id, 'org' => null, 'name' => null,]);
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals([$dummy_info, $dummy_info,], $out->getArrayCopy());
    }

    /**
     * Exercises site:list of either membership type owned by a user is the logged-in user
     */
    public function testListMyOwn()
    {
        $user_id = 'user_id';
        $dummy_info = [
            'name' => 'my-site',
            'id' => 'site_id',
            'service_level' => 'pro',
            'framework' => 'cms',
            'owner' => $user_id,
            'created' => '1984-07-28 16:40',
            'memberships' => 'org_id: org_url',
        ];

        $this->user->id = $user_id;

        $this->site->memberships = ['org_id: org_url'];
        $this->sites->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo(['org_id' => null, 'team_only' => false,]))
            ->willReturn($this->sites);
        $this->session->expects($this->never())
            ->method('filterByName');
        $this->sites->expects($this->once())
            ->method('filterByOwner')
            ->with($this->equalTo($user_id))
            ->willReturn($this->sites);
        $this->site->expects($this->any())
            ->method('serialize')
            ->with()
            ->willReturn($dummy_info);
        $this->sites->expects($this->once())
            ->method('all')
            ->with()
            ->willReturn([$this->site, $this->site,]);
        $this->logger->expects($this->never())
            ->method('log');

        $out = $this->command->index(['team' => false, 'owner' => 'me', 'org' => null, 'name' => null,]);
        $this->assertInstanceOf(RowsOfFields::class, $out);
        $this->assertEquals([$dummy_info, $dummy_info,], $out->getArrayCopy());
    }
}
