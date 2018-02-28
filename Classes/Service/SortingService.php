<?php
namespace PunktDe\Archivist\Service;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Log\SystemLoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class SortingService
{
    /**
     * @Flow\Inject
     * @var EelEvaluationService
     */
    protected $eelEvaluationService;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @param NodeInterface $parentNode
     * @param string $eelOrProperty
     * @param string $nodeTypeFilter
     */
    public function sortChildren(NodeInterface $parentNode, string $eelOrProperty, $nodeTypeFilter)
    {
        if ($this->eelEvaluationService->isValidExpression($eelOrProperty)) {
            $eelExpression = $eelOrProperty;
        } else {
            $eelExpression = sprintf('${String.toLowerCase(q(a).property("%s")) < String.toLowerCase(q(b).property("%s"))}', $eelOrProperty, $eelOrProperty);
        }
        $this->sortChildNodesByEelExpression($parentNode, $eelExpression, $nodeTypeFilter);
    }

    /**
     * @param NodeInterface $parenNode
     * @param string $eelExpression
     * @param string $nodeTypeFilter
     * @return void
     */
    protected function sortChildNodesByEelExpression(NodeInterface $parenNode, string $eelExpression, $nodeTypeFilter)
    {
        $nodes = $parenNode->getChildNodes($nodeTypeFilter);
        $object = null;

        foreach ($nodes as $nodeA) {
            /** @var NodeInterface $nodeA */
            $object = null;
            /** @var NodeInterface $nodeB */
            foreach ($nodes as $nodeB) {
                if ($this->eelEvaluationService->evaluate($eelExpression, ['a' => $nodeA, 'b' => $nodeB])) {
                    $object = $nodeB;
                    break;
                }
            }
        }

        if ($object !== null && $nodeA !== $object) {
            $this->logger->log(sprintf('Moving node %s before %s', $nodeA->getPath(), $object->getPath()), LOG_DEBUG);
            $nodeA->moveBefore($object);
        }
    }
}
