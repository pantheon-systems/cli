<?php

namespace Pantheon\Terminus\Tests\Unit\Collections;

use Composer\Autoload\ClassLoader;
use Pantheon\Terminus\Collections\WorkflowLogsCollection;
use Pantheon\Terminus\Terminus;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class WorkflowLogsCollectionTest extends CollectionTestBase
{
    protected \ReflectionClass $reflector;


    /**
     * @param array $data
     * @return mixed
     * @test
     * @throws \ReflectionException
     * @dataProvider dataProvider
     */
    public function testCollection(array $data)
    {

        $collection = $this->reflector->newInstanceWithoutConstructor();
        $collection->setData($data);
        $this->assertCount($collection->count(), $data, 'The collection should have the same number of items as the data array.');
        $this->assertInstanceOf($this->getClass(), $collection, 'The collection should be an instance of the class being tested.');
        $this->assertEquals($data[0]->workflow->id, $collection->latest()->id, 'The latest item in the collection should be the same as the first item in the data array.');
    }

    /**
     * @param array $data
     * @test
     * @dataProvider dataProvider
     * @throws \ReflectionException
     */
    public function testFindLatestByProperty(array $data)
    {
        $collection = $this->reflector->newInstanceWithoutConstructor();
        $collection->setData($data);
        $findMe = $collection->findLatestByProperty('id', $data[5]->workflow->id);
        $this->assertNotNull($findMe, 'Searching for a property by ID should yield a result.');
        $this->assertEquals($data[5]->workflow->id, $findMe->id, 'Searching for a property by ID should yield the correct workflow.');
    }

    /**
     * @param array $data
     * @test
     * @dataProvider dataProvider
     * @throws \ReflectionException
     */
    public function testFindLatestFromOptionsArray(array $data)
    {
        $collection = $this->reflector->newInstanceWithoutConstructor();
        $collection->setData($data);
        $findMe = $collection->findLatestFromOptionsArray(['id' => $data[5]->workflow->id]);
        $this->assertNotNull($findMe, 'Searching for a property by ID should yield a result.');
        $this->assertEquals($data[5]->workflow->id, $findMe->id, 'Searching for a property by ID should yield the correct workflow.');

        $collection->rewind();
        $findMe = $collection->findLatestFromOptionsArray(['commit_hash' => 'f69f04c1c50d415801e30a808bd3857650565204']);
        $this->assertNotNull($findMe, 'Searching for a property by commit hash should yield a result.');
        $this->assertEquals('429fa362-22c3-11ef-a213-1af7603d5813', $findMe->id, 'Searching for a property by commit hash should yield the correct workflow.');
    }


    /**
     * @return mixed
     */
    public function dataProvider()
    {
        // TODO: add some more json responses based on the different types of workflows
        return [
            [ json_decode(
                file_get_contents(
                    dirname(TERMINUS_BIN_FILE) . '/tests/fixtures/WorkflowLogsCollectionTest.json'
                )
            ) ]
        ];
    }

    /**
     * @return string
     */
    protected function getClass(): string
    {
        return WorkflowLogsCollection::class;
    }
}
