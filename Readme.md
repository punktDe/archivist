# PunktDe.Archivist 

**Purpose of this package:** Automatically sorts nodes into a predefined structure which is created on the fly.

Neos has some drawbacks, if you store lots of Node - like news for example - on the same hierarchical level. 
Especially the backend trees are getting slow and confusing. This package automatically sorts this nodes in a configured and 
automatically created hierarchy.

This package is inspired by the package [Sitegeist Critical Mass](https://github.com/sitegeist/Sitegeist.CriticalMass), 
but solves only the purpose described above. 

## Configuration

You can configure the behavior differently for every triggering node type.

## Example Configuration

    PunktDe:
      Archivist:**
        sortingInstructions:
          # Configuration for the node 'PunktDe.Archivist.TriggerNode'
          'PunktDe.Archivist.TriggerNode':
            # The query selecting the root node of the automatically created hierarchy
            root: "${q(site).find('[instanceof Neos.ContentRepository.Testing:Page]').get(0)}"
    
            # Optional: The sorting of the nodes inside the target hierarchy. Can be the name of a property or an eel expression like seen below
            sorting: title
     
            context:
              publishDate: "${node.properties.date}"
     
            # Definition of the auto-generated hierarchy
            hierarchy:
              -
                # The type of the hierarchy-node
                type: 'PunktDe.Archivist.HierarchyNode'
                # Properties of the new created node.
                properties:
                  name: "${Date.year(publishDate)}"
                  title: "${Date.year(publishDate)}"
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
                sorting: title
