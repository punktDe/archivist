<?php
namespace PunktDe\Archivist\Service;

use Neos\Eel\EelEvaluatorInterface;
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
     */
    public function evaluate(string $expression, array $context)
    {
        return Utility::evaluateEelExpression($expression, $this->eelEvaluator, $context, $this->defaultContextConfiguration);
    }
}
