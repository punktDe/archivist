<?php
namespace PunktDe\Archivist;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;

class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Node::class, 'nodeAdded', NodeSignalInterceptor::class, 'nodeAdded');
        $dispatcher->connect(Node::class, 'nodePropertyChanged', NodeSignalInterceptor::class, 'nodePropertyChanged');
    }
}
