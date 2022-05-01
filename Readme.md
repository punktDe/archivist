# PunktDe.Archivist

[![Travis Build Status](https://travis-ci.org/punktDe/archivist.svg?branch=master)](https://travis-ci.org/punktDe/archivist) [![Latest Stable Version](https://poser.pugx.org/punktde/archivist/v/stable)](https://packagist.org/packages/punktde/archivist) [![Total Downloads](https://poser.pugx.org/punktde/archivist/downloads)](https://packagist.org/packages/punktde/archivist)

**Purpose of this package:** Automatically sorts nodes into a predefined structure which is created on the fly.

Neos has some drawbacks, if you store lots of Node - like news for example - on the same hierarchical level.
Especially the backend trees are getting slow and confusing. This package automatically sorts this nodes in a configured and
automatically created hierarchy.

## Configuration

You can configure the behavior differently for every triggering node type. The configuration options
are best explained by example. These examples are taken from ``Configuration/Testing/Settings.yaml``
and are thus automatically tested.

### Simple Example

Configuration for the nodeType 'PunktDe.Archivist.TriggerNode'. The sorting is triggered if a
node of this type is created or if a property on this node is changed. This node is then
available as 'node' in the other parts of the configuration

```yaml
PunktDe:
  Archivist:
    sortingInstructions:

      'PunktDe.Archivist.TriggerNode':

        # The query selecting the root node of the automatically created hierarchy
        hierarchyRoot: "${q(site).find('[instanceof Neos.ContentRepository.Testing:Page]').get(0)}"

        # Optional: The sorting of the nodes inside the target hierarchy. Can be the name of a property
        # or an eel expression like seen below
        sorting: title

        # Optional: Trigger sorting only, when condition is met. Can be used to make sure that required properties are set as expected.
        condition: "${node.properties.date != null}"

        # In the context is evaluated first. You can define variables here which you can use in
        # the remaining configuration
        context:
          publishDate: "${node.properties.date}"

        # Automatically publish the created document hierarchy
        publishHierarchy: true

        # Definition of the auto-generated hierarchy
        hierarchy:
          -
            # The type of the hierarchy-node
            type: 'PunktDe.Archivist.HierarchyNode'

            # Properties of the new created node.
            properties:
              name: "${Date.year(publishDate)}"
              title: "${Date.year(publishDate)}"
              hiddenInIndex: "${true}"

            # The property which is identical throughout all nodes of this level
            identity: title

            # An eel query that describes the sorting condition
            sorting: "${q(a).property('title') < q(b).property('title')}"
          -
            type: 'PunktDe.Archivist.HierarchyNode'
            properties:
              name: "${Date.month(publishDate)}"
              title: "${Date.month(publishDate)}"
            identity: title

            # Simple sorting on a property
            sorting: title
```

### Example with a triggering content node

A content node triggers the move of its parent document node. For example, if you have a
title node which should be considered to move the page.

```yaml
PunktDe:
  Archivist:
    sortingInstructions:
      'PunktDe.Archivist.TriggerContentNode':

        # The query selecting the root node of the automatically created hierarchy
        hierarchyRoot: "${q(site).find('[instanceof Neos.ContentRepository.Testing:Page]').get(0)}"

        # Optional: The node to be moved, described by an Eel query.
        # This defaults to the triggering node if not set. The triggering node is available as "node".
        # If the affected node is not found by the operation is skipped.
        # This can for example be used if a change in a content node should move its parent document node
        #
        affectedNode: "${q(node).parent('[instanceof Neos.ContentRepository.Testing:Document]').get(0)}"

        # Definition of the auto-generated hierarchy
        hierarchy:
          -
            # The type of the hierarchy-node
            type: 'PunktDe.Archivist.HierarchyNode'

            # Properties of the new created node.
            properties:
              name: "${Archivist.buildSortingCharacter(title)}"
              title: "${Archivist.buildSortingCharacter(title)}"
```

### Define multiple configurations for the same NodeType

In addition to

```yaml
PunktDe:
  Archivist:
    sortingInstructions:
      MyNode.Type:
        hierarchyRoot: # ...
```

you can now do this:

```yaml
PunktDe:
  Archivist:
    sortingInstructions:
      MyNode.Type:
        foo1:
          hierarchyRoot:  # ...
        foo2:
          hierarchyRoot:  # ...
```
Make sure, to define a `conditon` to not run several configurations on the same Node action.

## Archivist Eel Helper

`Archivist.buildSortingCharacter(string, position = 0, length = 1)` Generates upper case sorting characters from the given string. Starting position and length can be defined.
