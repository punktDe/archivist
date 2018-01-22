<?php

namespace PunktDe\Archivist\Tests\Functional;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Tests\Functional\AbstractNodeTest;
use Neos\Eel\FlowQuery\FlowQuery;

class ArchivistTest extends AbstractNodeTest
{

    /**
     * @test
     */
    public function nodeStructureIsAvailable() {
        $this->assertEquals('Neos.ContentRepository.Testing:Page', $this->node->getNodeType()->getName());
    }

    /**
     * @test
     */
    public function createNode()
    {
        $newNode = $this->triggerNodeCreation();

        // The hierarchy is created
        $lvl1 = $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode')[0];
        $this->assertInstanceOf(NodeInterface::class, $lvl1);
        $this->assertEquals('2017', $lvl1->getProperty('title'));

        $lvl2 = $lvl1->getChildNodes('PunktDe.Archivist.HierarchyNode')[0];
        $this->assertInstanceOf(NodeInterface::class, $lvl2);
        $this->assertEquals('01', $lvl2->getProperty('title'));
        $this->assertEquals($this->nodeContextPath . '/2017/1', $lvl2->getPath());

        // The node is sorted in the hierarchy
        $this->assertEquals($this->nodeContextPath . '/2017/1/trigger-node', $newNode->getPath());
    }

    /**
     * @test
     */
    public function hierarchyIsNotCreatedTwice()
    {
        $this->triggerNodeCreation('trigger-node1');
        $this->triggerNodeCreation('trigger-node2');

        $this->assertCount(1, $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode'));
    }

    /**
     * @test
     */
    public function hierarchyNodesAreSortedCorrectlyWithSimpleProperty() {
        $this->triggerNodeCreation('trigger-node1', ['date' => new \DateTime('2017-01-20')]);
        $this->triggerNodeCreation('trigger-node2', ['date' => new \DateTime('2016-01-19')]);

        $yearNodes = $this->node->getChildNodes('PunktDe.Archivist.HierarchyNode');
        $this->assertEquals('2016', $yearNodes[0]->getProperty('title'));
        $this->assertEquals('2017', $yearNodes[1]->getProperty('title'));
    }

    /**
     * @test
     */
    public function hierarchyNodesAreSortedCorrectlyWithEelExpression() {
        $this->triggerNodeCreation('trigger-node1', ['date' => new \DateTime('2017-02-20')]);
        $this->triggerNodeCreation('trigger-node2', ['date' => new \DateTime('2016-01-19')]);

        $monthNodes = (new FlowQuery([$this->node]))->children('[instanceof PunktDe.Archivist.HierarchyNode]')->children('[instanceof PunktDe.Archivist.HierarchyNode]')->get();
        $this->assertEquals('1', $monthNodes[0]->getProperty('title'));
        $this->assertEquals('2', $monthNodes[1]->getProperty('title'));
    }

    /**
     * @test
     */
    public function createdNodesAreSortedCorrectly() {
        $this->triggerNodeCreation('trigger-node2', ['title' => 'Node 2']);
        $this->triggerNodeCreation('trigger-node1', ['title' => 'Node 1']);

        $triggerNodes = (new FlowQuery([$this->node]))->find('[instanceof PunktDe.Archivist.TriggerNode]')->get();

        $this->assertEquals('Node 1', $triggerNodes[0]->getProperty('title'));
        $this->assertEquals('Node 2', $triggerNodes[1]->getProperty('title'));
    }

    /**
     * @param string $nodeName
     * @param array $properties
     * @return \Neos\ContentRepository\Domain\Model\NodeInterface
     */
    protected function triggerNodeCreation($nodeName = 'trigger-node', array $properties = [])
    {
        $defaultProperties = [
            'title' => 'New Article',
            'date' => new \DateTime('2017-01-19')
        ];

        $properties = array_merge($defaultProperties, $properties);

        $nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $triggerNodeType = $nodeTypeManager->getNodeType('PunktDe.Archivist.TriggerNode');

        $triggerNodeTemplate = new NodeTemplate();
        $triggerNodeTemplate->setNodeType($triggerNodeType);

        foreach ($properties as $key => $property) {
            $triggerNodeTemplate->setProperty($key, $property);
        }

        return $this->node->createNodeFromTemplate($triggerNodeTemplate, $nodeName);
    }

}
