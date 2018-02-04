<?php

namespace PunktDe\Archivist;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

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

        (new Archivist())->organizeNode($node, $this->sortingInstructions[$node->getNodeType()->getName()]);
    }

    /**
     * @param NodeInterface $node
     * @param string $propertyName
     * @param $oldValue
     * @param $value
     */
    public function nodePropertyChanged(NodeInterface $node, string $propertyName, $oldValue, $value) {
        if(!array_key_exists($node->getNodeType()->getName(), $this->sortingInstructions)) {
            return;
        }

        (new Archivist())->organizeNode($node, $this->sortingInstructions[$node->getNodeType()->getName()]);
    }
}
