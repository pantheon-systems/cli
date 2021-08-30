<?php

namespace Pantheon\Terminus\Tests\Functional;

use Pantheon\Terminus\Tests\Traits\LoginHelperTrait;
use Pantheon\Terminus\Tests\Traits\TerminusTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class TagCommandsTest
 *
 * @package Pantheon\Terminus\Tests\Functional
 */
class TagCommandsTest extends TestCase
{
    use TerminusTestTrait;
    use LoginHelperTrait;

    /**
     * @test
     * @covers \Pantheon\Terminus\Commands\Tag\AddCommand
     * @covers \Pantheon\Terminus\Commands\Tag\ListCommand
     * @covers \Pantheon\Terminus\Commands\Tag\RemoveCommand
     *
     * @group tag
     * @group short
     */
    public function testTagAddListRemove()
    {
        $siteName = $this->getSiteName();
        $orgId = $this->getOrg();
        $newTag = uniqid("tag-");

        // ADD
        $this->terminus("tag:add {$siteName} {$orgId} {$newTag}", null);

        // LIST
        $tagList1 = $this->terminusJsonResponse("tag:list {$siteName} {$orgId}");
        $this->assertIsArray($tagList1, "Returned values from tag list should be array");
        $this->assertContains($newTag, $tagList1, "Tag list should contain new tag");


        // REMOVE
        $this->terminus("tag:remove {$siteName} {$orgId} {$newTag}", null);

        $tagList2 = $this->terminusJsonResponse("tag:list {$siteName} {$orgId}");
        $this->assertNotContains($newTag, $tagList2, "Tag list should no longer contain new tag");
    }
}
