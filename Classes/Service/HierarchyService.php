<?php
namespace PunktDe\Archivist\Service;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\ContentRepository\Utility as NodeUtility;
use PunktDe\Archivist\Exception\ArchivistConfigurationException;

/**
 * @Flow\Scope("singleton")
 */
class HierarchyService
{
    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var EelEvaluationService
     */
    protected $eelEvaluationService;

    /**
     * @Flow\Inject
     * @var SortingService
     */
    protected $sortingService;

    /**
     * @flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @param array $hierarchyConfiguration
     * @param array $context
     * @return NodeInterface
     */
    public function buildHierarchy(array $hierarchyConfiguration, array $context): NodeInterface
    {
        $targetNode = null;
        $parent = $context['hierarchyRoot'];

        foreach ($hierarchyConfiguration as $hierarchyLevelConfiguration) {
            $parent = $this->buildHierarchyLevel($parent, $hierarchyLevelConfiguration, $context);
        }

        return $parent;
    }

    /**
     * @param NodeInterface $parentNode
     * @param array $hierarchyLevelConfiguration
     * @param array $context
     * @return NodeInterface The created or found hierarchy node
     * @throws ArchivistConfigurationException
     */
    protected function buildHierarchyLevel(NodeInterface $parentNode, array $hierarchyLevelConfiguration, array $context): NodeInterface
    {
        $hierarchyLevelNodeName = '';
        $this->evaluateHierarchyLevelConfiguration($hierarchyLevelConfiguration);

        $hierarchyLevelNodeType = $this->nodeTypeManager->getNodeType($hierarchyLevelConfiguration['type']);
        if (!($hierarchyLevelNodeType instanceof NodeType)) {
            throw new ArchivistConfigurationException(sprintf('NodeType "%s" was not defined', $hierarchyLevelConfiguration['type']), 1516371948);
        }

        $existingNode = $this->findExistingHierarchyNode($parentNode, $hierarchyLevelConfiguration, $context);
        if ($existingNode instanceof NodeInterface) {
            return $existingNode;
        }

        if (isset($hierarchyLevelConfiguration['properties']['name'])) {
            $hierarchyLevelNodeName = (string)$this->eelEvaluationService->evaluateIfValidEelExpression($hierarchyLevelConfiguration['properties']['name'], $context);
        }

        if ($hierarchyLevelNodeName === '') {
            return $parentNode;
        }

        $hierarchyLevelNodeTemplate = new NodeTemplate();
        $hierarchyLevelNodeTemplate->setNodeType($hierarchyLevelNodeType);

        if (isset($hierarchyLevelConfiguration['properties'])) {
            $this->applyProperties($hierarchyLevelNodeTemplate, $hierarchyLevelConfiguration['properties'], $context);
        }

        if ($hierarchyLevelNodeType->isOfType('Neos.Neos:Document') && !isset($this->properties['uriPathSegment'])) {
            $hierarchyLevelNodeTemplate->setProperty('uriPathSegment', NodeUtility::renderValidNodeName($hierarchyLevelNodeTemplate->getName()));
        }

        $hierarchyLevelNode = $parentNode->createNodeFromTemplate($hierarchyLevelNodeTemplate, $hierarchyLevelNodeName);

        $this->logger->log(sprintf('Built hierarchy level on path %s with node type %s ', $hierarchyLevelNode->getPath(), $hierarchyLevelConfiguration['type']), LOG_DEBUG);

        if (isset($hierarchyLevelConfiguration['sorting'])) {
            $this->sortingService->sortChildren($parentNode, $hierarchyLevelConfiguration['sorting'], $hierarchyLevelNodeType->getName());
        }

        return $hierarchyLevelNode;
    }

    /**
     * @param NodeTemplate $node
     * @param array $properties
     * @param array $context
     */
    protected function applyProperties(NodeTemplate $node, array $properties, array $context)
    {
        foreach ($properties as $propertyName => $propertyValue) {
            $propertyValue = $this->eelEvaluationService->evaluateIfValidEelExpression($propertyValue, $context);
            $node->setProperty($propertyName, $propertyValue);
        }
    }

    /**
     * @param array $hierarchyLevelConfiguration
     * @throws ArchivistConfigurationException
     */
    protected function evaluateHierarchyLevelConfiguration(array $hierarchyLevelConfiguration)
    {
        if (!isset($hierarchyLevelConfiguration['type'])) {
            throw new ArchivistConfigurationException('Missing "type" setting for archivist hierarchy', 1516371948);
        }

        if (!isset($hierarchyLevelConfiguration['properties']) || !is_array($hierarchyLevelConfiguration['properties']) || count($hierarchyLevelConfiguration['properties']) === 0) {
            throw new ArchivistConfigurationException('Please define some properties to set up the hierarchy node', 1516382105);
        }
    }

    /**
     * @param NodeInterface $parentNode
     * @param array $hierarchyLevelConfiguration
     * @param array $context
     * @return null
     * @throws ArchivistConfigurationException
     */
    protected function findExistingHierarchyNode(NodeInterface $parentNode, array $hierarchyLevelConfiguration, array $context)
    {
        if (!isset($hierarchyLevelConfiguration['identity'])) {
            return null;
        }
        $identifyingPropertyName = $hierarchyLevelConfiguration['identity'];

        if (!isset($hierarchyLevelConfiguration['properties'][$identifyingPropertyName])) {
            throw new ArchivistConfigurationException(sprintf('The defined identity "%s" was not found in your defined properties', $identifyingPropertyName), 1516371948);
        }
        $identifyingProperty = $hierarchyLevelConfiguration['properties'][$identifyingPropertyName];

        $identifyingValue = $this->eelEvaluationService->evaluateIfValidEelExpression($identifyingProperty, $context);

        return (new FlowQuery([$parentNode]))->children(sprintf('[instanceof %s][%s = "%s"]', $hierarchyLevelConfiguration['type'], $identifyingPropertyName, $identifyingValue))->get(0);
    }

}
