<?php
namespace PunktDe\Archivist\Service;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Exception;
use Neos\Eel\Package;
use Neos\Eel\Utility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class EelEvaluationService
{
    /**
     * @var array
     * @Flow\InjectConfiguration(path="defaultContext", package="Neos.Fusion")
     */
    protected $defaultContextConfiguration;

    /**
     * @var EelEvaluatorInterface
     * @Flow\Inject(lazy=false)
     */
    protected $eelEvaluator;

    /**
     * @param string $expression
     * @param array $context
     * @return mixed|string
     * @throws Exception
     */
    public function evaluateIfValidEelExpression(string $expression, array $context)
    {
        if (!$this->isValidExpression($expression)) {
            return $expression;
        }

        return $this->evaluate($expression, $context);
    }

    /**
     * @param string $expression
     * @return bool
     */
    public function isValidExpression(string $expression): bool
    {
        return (int)preg_match(Package::EelExpressionRecognizer, $expression) > 0;
    }

    /**
     * @param string $expression
     * @param array $context
     * @return mixed
     * @throws Exception
     */
    public function evaluate(string $expression, array $context)
    {
        return Utility::evaluateEelExpression($expression, $this->eelEvaluator, $context, $this->defaultContextConfiguration);
    }
}
