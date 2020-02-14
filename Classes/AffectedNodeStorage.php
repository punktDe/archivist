<?php
declare(strict_types=1);

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
class AffectedNodeStorage
{

    /**
     * @var array
     */
    protected $affectedNodePaths = [];

    public function addNode(NodeInterface $node): void
    {
        $this->affectedNodePaths[] = (string)$node->getContextPath();
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    public function hasNode(NodeInterface $node): bool
    {
        return in_array($node->getContextPath(), $this->affectedNodePaths);
    }
}
