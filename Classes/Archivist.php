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
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
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
     * @var array
     */
    protected $sortedNodeInstructions = [];

    /**
     * @var array
     */
    protected $nodesInProcessing = [];

    /**
     * @param NodeInterface $triggeringNode
     * @param array $sortingInstructions
     * @throws ArchivistConfigurationException
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     * @throws \Neos\Eel\Exception
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
                $this->logger->info(sprintf('A node of type %s (%s) triggered node organization but the affectedNode was not found.', $triggeringNode->getNodeType()->getName(), $triggeringNode->getIdentifier()), LogEnvironment::fromMethodName(__METHOD__));
                return;
            }
        } else {
            $affectedNode = $triggeringNode;
        }

        $this->lockNodeForProcessing($affectedNode);
        $this->nodeDataRepository->persistEntities();

        $this->logger->debug(sprintf('Organizing node of type %s with path %s', $affectedNode->getNodeType()->getName(), $affectedNode->getPath()), LogEnvironment::fromMethodName(__METHOD__));
        $context = $this->buildBaseContext($triggeringNode, $sortingInstructions);

        if (isset($sortingInstructions['context']) && is_array($sortingInstructions['context'])) {
            $context = $this->buildCustomContext($context, $sortingInstructions['context']);
        }

        if (isset($sortingInstructions['hierarchy']) && is_array($sortingInstructions['hierarchy'])) {
            $hierarchyNode = $this->hierarchyService->buildHierarchy($sortingInstructions['hierarchy'], $context, $sortingInstructions['publishHierarchy'] ?? false);

            if ($affectedNode->getParent() !== $hierarchyNode) {
                $affectedNode->moveInto($hierarchyNode);

                $this->organizedNodeParents[$affectedNode->getIdentifier()] = $affectedNode->getParent();

                $this->logger->debug(sprintf('Moved affected node %s to path %s', $affectedNode->getNodeType()->getName(), $affectedNode->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            }
        }

        if (isset($sortingInstructions['sorting'])) {
            $this->sortingService->sortChildren($affectedNode, $sortingInstructions['sorting'], null);
            $this->sortedNodeInstructions[$affectedNode->getIdentifier()] = $sortingInstructions['sorting'];
        }

        $this->releaseNodeProcessingLock($affectedNode);
    }

    /**
     * On actions like createAfter, the following happens
     *
     * 1. save the parent
     * 2. createInto parent
     * --- Archivist creates the hierarchy and moves / sorts the node
     * 3. move node after the parent
     *
     * The second move is done to the affected node. When we use a triggered node and an affected node we cannot catch that signal.
     * So we need to move the node again to the originally calculated position.
     *
     * @param NodeInterface $node
     * @return bool
     * @throws \Neos\Eel\Exception
     */
    public function restorePathIfOrganizedDuringThisRequest(NodeInterface $node): bool
    {
        if (isset($this->organizedNodeParents[$node->getIdentifier()])) {
            if ($node->getParent() === $this->organizedNodeParents[$node->getIdentifier()]) {
                return true;
            }

            $node->moveInto($this->organizedNodeParents[$node->getIdentifier()]);
            $this->logger->debug(sprintf('Path of affected node %s was restored', $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            return true;
        }

        if (isset($this->sortedNodeInstructions[$node->getIdentifier()])) {
            $this->sortingService->sortChildren($node, $this->sortedNodeInstructions[$node->getIdentifier()], null);
            return true;
        }

        return false;
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    public function isNodeInProcess(NodeInterface $node)
    {
        return isset($this->nodesInProcessing[$node->getIdentifier()]);
    }

    /**
     * @param NodeInterface $node
     */
    protected function lockNodeForProcessing(NodeInterface $node)
    {
        $this->nodesInProcessing[$node->getIdentifier()] = true;
    }

    /**
     * @param NodeInterface $node
     */
    protected function releaseNodeProcessingLock(NodeInterface $node)
    {
        unset($this->nodesInProcessing[$node->getIdentifier()]);
    }

    /**
     * @param NodeInterface $node
     * @param array $sortingInstructions
     * @return array
     * @throws ArchivistConfigurationException
     * @throws \Neos\Eel\Exception
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
     * @throws \Neos\Eel\Exception
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


