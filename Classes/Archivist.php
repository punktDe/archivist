<?php

namespace PunktDe\Archivist;


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
     * @param NodeInterface $node
     * @param array $sortingInstructions
     */
    public function sortNode(NodeInterface $node, array $sortingInstructions)
    {
        $this->nodeDataRepository->persistEntities();
        $this->logger->log(sprintf('Sorting node of type %s with identifier %s', $node->getNodeType()->getName(), $node->getIdentifier()), LOG_DEBUG);
        $context = $this->buildBaseContext($node, $sortingInstructions);

        if (isset($sortingInstructions['context']) && is_array($sortingInstructions['context'])) {
            $context = $this->buildCustomContext($context, $sortingInstructions['context']);
        }

        if (isset($sortingInstructions['hierarchy']) && is_array($sortingInstructions['hierarchy'])) {
            $targetNode = $this->hierarchyService->buildHierarchy($sortingInstructions['hierarchy'], $context);
            $node->moveInto($targetNode);
        }

        if (isset($sortingInstructions['sorting'])) {
            $this->sortingService->sort($targetNode, $sortingInstructions['sorting'], null);
        }
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


