<?php
declare(strict_types=1);

namespace PunktDe\Archivist\Aspect;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use PunktDe\Archivist\AffectedNodeStorage;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class FeedbackCreationAspect
{
    /**
     * @Flow\Inject
     * @var AffectedNodeStorage
     */
    protected $affectedNodeStorage;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Around("method(Neos\Neos\Ui\Domain\Model\AbstractChange->addNodeCreatedFeedback())")
     * @param JoinPointInterface $joinPoint The current join point
     * @return void
     */
    public function addNodeCreatedFeedback(JoinPointInterface $joinPoint): void
    {
        /** @var NodeInterface $subject */
        $subject = $joinPoint->getMethodArgument('subject');
        if($subject instanceof  NodeInterface && $this->affectedNodeStorage->hasNode($subject)) {
            $this->logger->debug('Prevented the original node created feedback, as it would return the wrong node path.', LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        $joinPoint->getAdviceChain()->proceed($joinPoint);
    }
}
