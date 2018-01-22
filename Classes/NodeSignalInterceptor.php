<?php

namespace PunktDe\Archivist;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;

/**
* @Flow\Scope("singleton")
*/
class NodeSignalInterceptor
{
    /**
     * @Flow\InjectConfiguration(path="sortingInstructions")
     * @var array
     */
    protected $sortingInstructions = [];

    /**
     * @param NodeInterface $node
     */
    public function nodeAdded(NodeInterface $node) {
        if(!array_key_exists($node->getNodeType()->getName(), $this->sortingInstructions)) {
            return;
        }

        (new Archivist())->sortNode($node, $this->sortingInstructions[$node->getNodeType()->getName()]);
    }
}
