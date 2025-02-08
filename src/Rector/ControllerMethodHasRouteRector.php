<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use ReflectionMethod;
use Symfony\Component\Routing\Annotation\Route;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ControllerMethodHasRouteRector extends AbstractControllerMethodNameRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure controllers uses constants for common operations',
            [
                new CodeSample(
                    // code before
                    'Route name like "edit"',
                    // code after
                    'Route name using EDIT constant'
                ),
            ]
        );
    }

    public function refactorMethod(
        Node $node,
        ReflectionMethod $method
    ): Node|array|null {
        if ($this->isControllerRouteMethod($method)
                // TODO Waiting for API platform rewrite.
            && !$this->isInstanceOfApiController($node)
        ) {
            return $this->addAttributeWithArgIfMissing(
                $node,
                Route::class,
                VariableHelper::NAME,
                TextHelper::toSnake($this->getName($node))
            );
        }

        return null;
    }
}
