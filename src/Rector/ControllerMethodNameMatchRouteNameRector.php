<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;
use Symfony\Component\Routing\Annotation\Route;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyHelpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ControllerMethodNameMatchRouteNameRector extends AbstractControllerMethodNameRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure controllers contains proper methods names',
            [
                new CodeSample(
                    // code before
                    'my_route_name with mySuperMethod',
                    // code after
                    'my_route_name with myRouteName'
                ),
            ]
        );
    }

    public function refactorMethod(
        ClassMethod $node,
        ReflectionMethod $method
    ): Node|array|null {
        if ($attribute = $this->getFirstAttribute(
            Route::class,
            $method
        )) {
            if ($this->changeMethodName($node, TextHelper::toCamel(
                $attribute->getArguments()[VariableHelper::NAME]
            ))) {
                return $node;
            }
        }

        return null;
    }
}
