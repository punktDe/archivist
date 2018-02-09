<?php

namespace PunktDe\Archivist;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Log\SystemLoggerInterface;
use PunktDe\Archivist\Exception\ArchivistConfigurationException;
use PunktDe\Archivist\Service\EelEvaluationService;
use PunktDe\Archivist\Service\HierarchyService;
use PunktDe\Archivist\Service\SortingService;

class Archivist
{
    /**
     * @Flow\Inject
     * @var HierarchyService
     */
    protected $hierarchyService;

    /**
     * @Flow\Inject
     * @var EelEvaluationService
     */
    protected $eelEvaluationService;

    /**
     * @flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var SortingService
     */
    protected $sortingService;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $context;

    /**
     * @var array
     */
    protected $organizedNodeParents = [];

    /**
     * @param NodeInterface $triggeringNode
     * @param array $sortingInstructions
     */
    public function organizeNode(NodeInterface $triggeringNode, array $sortingInstructions)
    {
        if (isset($sortingInstructions['condition'])) {
            $condition = $this->eelEvaluationService->evaluate($sortingInstructions['condition'], ['node' => $triggeringNode]);
            if ($condition !== true) {
                return;
            }
        }
        if (isset($sortingInstructions['affectedNode'])) {
            $affectedNode = $this->eelEvaluationService->evaluate($sortingInstructions['affectedNode'], ['node' => $triggeringNode]);

            if (!($affectedNode instanceof NodeInterface)) {
                $this->logger->log(sprintf('A node of type %s (%s) triggered node organization but the affectedNode was not found.', $triggeringNode->getNodeType()->getName(), $triggeringNode->getIdentifier()));
                return;
            }
        } else {
            $affectedNode = $triggeringNode;
        }

        $this->nodeDataRepository->persistEntities();

        $this->logger->log(sprintf('Organizing node of type %s with path %s', $affectedNode->getNodeType()->getName(), $affectedNode->getPath()), LOG_DEBUG);
        $context = $this->buildBaseContext($triggeringNode, $sortingInstructions);

        if (isset($sortingInstructions['context']) && is_array($sortingInstructions['context'])) {
            $context = $this->buildCustomContext($context, $sortingInstructions['context']);
        }

        if (isset($sortingInstructions['hierarchy']) && is_array($sortingInstructions['hierarchy'])) {
            $hierarchyNode = $this->hierarchyService->buildHierarchy($sortingInstructions['hierarchy'], $context);

            if ($affectedNode->getParent() !== $hierarchyNode) {
                $affectedNode->moveInto($hierarchyNode);

                $this->organizedNodeParents[$affectedNode->getIdentifier()] = $affectedNode->getParent();

                $this->logger->log(sprintf('Moved affected node %s to path %s', $affectedNode->getNodeType()->getName(), $affectedNode->getPath()), LOG_DEBUG);
            }

            if (isset($sortingInstructions['sorting'])) {
                $this->sortingService->sortChildren($hierarchyNode, $sortingInstructions['sorting'], null);
            }
        }

    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    public function restorePathIfOrganizedDuringThisRequest(NodeInterface $node): bool
    {
        if (isset($this->organizedNodeParents[$node->getIdentifier()])) {
            if ($node->getParent() === $this->organizedNodeParents[$node->getIdentifier()]) {
                return true;
            }

            $node->moveInto($this->organizedNodeParents[$node->getIdentifier()]);
            $this->logger->log(sprintf('Path of affected node %s was restored', $node->getPath()), LOG_DEBUG);
            return true;
        }
        return false;
    }

    /**
     * @param NodeInterface $node
     * @param array $sortingInstructions
     * @return array
     * @throws ArchivistConfigurationException
     */
    protected function buildBaseContext(NodeInterface $node, array $sortingInstructions): array
    {
        $context = [
            'documentNode' => (new FlowQuery([$node]))->closest('[instanceof Neos.Neos:Document]')->get(0),
            'site' => (new FlowQuery([$node]))->parents('[instanceof Neos.Neos:Document]')->last()->get(0),
            'node' => $node
        ];

        if (!isset($sortingInstructions['hierarchyRoot'])) {
            throw new ArchivistConfigurationException('You need to set an eel expression to determine the "hierarchyRoot" node to sort the node into.', 1516348967);
        }

        $hierarchyRoot = $this->eelEvaluationService->evaluateIfValidEelExpression($sortingInstructions['hierarchyRoot'], $context);
        if (!($hierarchyRoot instanceof NodeInterface)) {
            throw new ArchivistConfigurationException('The hierarchyRoot node defined was not found.', 1516348968);
        }

        $context['hierarchyRoot'] = $hierarchyRoot;

        return $context;
    }

    /**
     * @param array $baseContext
     * @param array $contextConfiguration
     * @return array
     */
    protected function buildCustomContext(array $baseContext, array $contextConfiguration): array
    {
        $customContext = $baseContext;
        foreach ($contextConfiguration as $variableName => $contextConfigurationExpression) {
            $customContext[$variableName] = $this->eelEvaluationService->evaluate($contextConfigurationExpression, $baseContext);
        }
        return $customContext;
    }
}


