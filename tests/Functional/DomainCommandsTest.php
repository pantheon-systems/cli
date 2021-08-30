<?php

namespace Pantheon\Terminus\Tests\Functional;

use Pantheon\Terminus\Tests\Traits\LoginHelperTrait;
use Pantheon\Terminus\Tests\Traits\TerminusTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class DomainCommandsTest
 *
 * @package Pantheon\Terminus\Tests\Functional
 */
class DomainCommandsTest extends TestCase
{
    use TerminusTestTrait;
    use LoginHelperTrait;

    /**
     * @test
     * @covers \Pantheon\Terminus\Commands\Domain\AddCommand
     * @covers \Pantheon\Terminus\Commands\Domain\DNSCommand
     * @covers \Pantheon\Terminus\Commands\Domain\ListCommand
     * @covers \Pantheon\Terminus\Commands\Domain\LookupCommand
     * @covers \Pantheon\Terminus\Commands\Domain\RemoveCommand
     * @covers \Pantheon\Terminus\Commands\Domain\Primary\AddCommand
     * @covers \Pantheon\Terminus\Commands\Domain\Primary\RemoveCommand
     *
     * @group domain
     * @group long
     */
    public function testAddListLookupRemove()
    {
        $sitename = $this->getSiteName();
        $newDomain = uniqid("test-") . ".test";

        // LIST
        $results = $this->terminusJsonResponse("domain:list {$sitename}.live");
        $this->assertIsArray($results, "Returned values from domain list should be array");
        $this->assertGreaterThan(
            0,
            count($results),
            "Count of domains should be greater than 0"
        );


        // ADD
        $this->terminus("domain:add {$sitename}.live {$newDomain}", null);
        sleep(10);
        $results2 = $this->terminusJsonResponse("domain:list {$sitename}.live");
        $domains = array_column($results2, 'id');
        $this->assertContains($newDomain, $domains, "Domain list should contain added domain");


        // LOOKUP - TODO: actually test this result (BWeaver 2021-07-23)
        $lookedUp = $this->terminusJsonResponse("domain:lookup {$newDomain}", null);


        // ADD PRIMARY
        $this->terminus("domain:primary:add {$sitename}.live {$newDomain}");
        $results2 = $this->terminusJsonResponse("domain:list {$sitename}.live");
        // $primaryDomains has domain names as keys, 'primary' value as values
        $primaryDomains = array_combine(array_column($results2, 'id'), array_column($results2, 'primary'));
        $this->assertArrayHasKey($newDomain, $primaryDomains, "Domain list should contain new domain");
        $this->assertEquals('1', $primaryDomains[$newDomain], "New domain should be primary");


        // REMOVE PRIMARY
        $this->terminus("domain:primary:remove {$sitename}.live");
        $results2 = $this->terminusJsonResponse("domain:list {$sitename}.live");
        // $primaryDomains has domain names as keys, 'primary' value as values
        $primaryDomains = array_combine(array_column($results2, 'id'), array_column($results2, 'primary'));
        $this->assertArrayHasKey($newDomain, $primaryDomains, "Domain list should contain new domain");
        $this->assertNotEquals("1", $primaryDomains[$newDomain], "New domain should not be primary anymore");


        // REMOVE
        $this->terminus("domain:remove {$sitename}.live {$newDomain}");
        $results2 = $this->terminusJsonResponse("domain:list {$sitename}.live");
        $domains = array_column($results2, 'id');
        $this->assertFalse(array_search($newDomain, $domains), "Domain list should no longer contain domain");
    }
}
