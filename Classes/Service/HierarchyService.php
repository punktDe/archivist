<?php
declare(strict_types=1);

namespace PunktDe\Archivist\Service;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\PublishingServiceInterface;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
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
     * @var PublishingServiceInterface
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @param array $hierarchyConfiguration
     * @param array $context
     * @param bool $publishHierarchy Automatically publish the built hierarchy node to live workspace.
     * @return NodeInterface
     * @throws ArchivistConfigurationException
     * @throws NodeTypeNotFoundException
     * @throws \Neos\Eel\Exception
     */
    public function buildHierarchy(array $hierarchyConfiguration, array $context, bool $publishHierarchy = false): NodeInterface
    {
        $targetNode = null;
        $parent = $context['hierarchyRoot'];

        foreach ($hierarchyConfiguration as $hierarchyLevelConfiguration) {
            $parent = $this->buildHierarchyLevel($parent, $hierarchyLevelConfiguration, $context, $publishHierarchy);
        }

        return $parent;
    }

    /**
     * @param NodeInterface $parentNode
     * @param array $hierarchyLevelConfiguration
     * @param array $context
     * @param bool $publishHierarchy
     * @return NodeInterface The created or found hierarchy node
     * @throws ArchivistConfigurationException
     * @throws NodeTypeNotFoundException
     * @throws \Neos\ContentRepository\Exception\NodeExistsException
     * @throws \Neos\Eel\Exception
     */
    protected function buildHierarchyLevel(NodeInterface $parentNode, array $hierarchyLevelConfiguration, array $context, bool $publishHierarchy): NodeInterface
    {
        $hierarchyLevelNodeName = null;
        $this->evaluateHierarchyLevelConfiguration($hierarchyLevelConfiguration);

        $hierarchyLevelNodeType = $this->nodeTypeManager->getNodeType($hierarchyLevelConfiguration['type']);
        if (!($hierarchyLevelNodeType instanceof NodeType)) {
            throw new ArchivistConfigurationException(sprintf('NodeType "%s" was not defined', $hierarchyLevelConfiguration['type']), 1516371948);
        }

        $existingNode = $this->findExistingHierarchyNode($parentNode, $hierarchyLevelConfiguration, $context);
        if ($existingNode instanceof NodeInterface) {
            return $existingNode;
        }

        $hierarchyLevelNodeTemplate = new NodeTemplate();
        $hierarchyLevelNodeTemplate->setNodeType($hierarchyLevelNodeType);


        if (isset($hierarchyLevelConfiguration['properties']['name'])) {
            $hierarchyLevelNodeName = (string)$this->eelEvaluationService->evaluateIfValidEelExpression($hierarchyLevelConfiguration['properties']['name'], $context);

            if($hierarchyLevelNodeName !== '') {
                $hierarchyLevelNodeTemplate->setName(NodeUtility::renderValidNodeName($hierarchyLevelNodeName));
            } else {
                $hierarchyLevelNodeName = null;
            }

            unset($hierarchyLevelConfiguration['properties']['name']);
        }

        $hierarchyLevelNode = $parentNode->createNode($hierarchyLevelNodeTemplate->getName(), $hierarchyLevelNodeTemplate->getNodeType());

        if (isset($hierarchyLevelConfiguration['properties'])) {
            $this->applyProperties($hierarchyLevelNode, $hierarchyLevelConfiguration['properties'], $context);
        }

        if ($hierarchyLevelNodeType->isOfType('Neos.Neos:Document') && !isset($hierarchyLevelConfiguration['properties']['uriPathSegment'])) {
            $hierarchyLevelNode->setProperty('uriPathSegment', $hierarchyLevelNode->getName());
        }

        $this->logger->log(sprintf('Built hierarchy level on path %s with node type %s ', $hierarchyLevelNode->getPath(), $hierarchyLevelConfiguration['type']), LOG_DEBUG);

        if (isset($hierarchyLevelConfiguration['sorting'])) {
            $this->sortingService->sortChildren($hierarchyLevelNode, $hierarchyLevelConfiguration['sorting'], $hierarchyLevelNodeType->getName());
        }

        if ($publishHierarchy === true) {
            if ($hierarchyLevelNode->getWorkspace()->isPublicWorkspace() === false) {
                $this->publishNodeAndChildContent($hierarchyLevelNode);
            }
        }

        $this->nodeDataRepository->persistEntities();

        return $hierarchyLevelNode;
    }

    /**
     * @param NodeInterface $node
     * @param array $properties
     * @param array $context
     * @throws \Neos\Eel\Exception
     */
    protected function applyProperties(NodeInterface $node, array $properties, array $context)
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
     * @return NodeInterface|null
     * @throws ArchivistConfigurationException
     * @throws \Neos\Eel\Exception
     */
    protected function findExistingHierarchyNode(NodeInterface $parentNode, array $hierarchyLevelConfiguration, array $context): ?NodeInterface
    {
        if (!isset($hierarchyLevelConfiguration['identity'])) {
            return null;
        }
        $identifyingPropertyName = $hierarchyLevelConfiguration['identity'];

        if (!isset($hierarchyLevelConfiguration['properties'][$identifyingPropertyName])) {
            throw new ArchivistConfigurationException(sprintf('The defined identity "%s" was not found in your defined properties', $identifyingPropertyName), 1516371948);
        }
        $identifyingProperty = $hierarchyLevelConfiguration['properties'][$identifyingPropertyName];

        $this->nodeDataRepository->persistEntities();

        $identifyingValue = $this->eelEvaluationService->evaluateIfValidEelExpression($identifyingProperty, $context);

        if ($identifyingPropertyName === 'name') {
            return $parentNode->getNode($identifyingValue);
        }

        return (new FlowQuery([$parentNode]))->children(sprintf('[instanceof %s][%s = "%s"]', $hierarchyLevelConfiguration['type'], $identifyingPropertyName, $identifyingValue))->get(0);
    }

    /**
     * @param NodeInterface $node
     */
    protected function publishNodeAndChildContent(NodeInterface $node): void
    {
        $contentNodes = $node->getChildNodes('Neos.Neos:Content');

        /** @var NodeInterface $contentNode */
        foreach ($contentNodes as $contentNode) {
            if ($contentNode->getWorkspace()->isPublicWorkspace() === false) {
                $this->publishNodeAndChildContent($contentNode);
            }
        }

        $this->logger->log('Publishing node ' . $node->__toString(), LOG_DEBUG);
        $this->publishingService->publishNode($node);
    }
}
