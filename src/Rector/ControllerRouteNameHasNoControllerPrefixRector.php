<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use ReflectionMethod;
use Symfony\Component\Routing\Annotation\Route;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ControllerRouteNameHasNoControllerPrefixRector extends AbstractControllerMethodNameRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure routes name does not contain route prefix (which is present globally)',
            [
                new CodeSample(
                    // code before
                    'my_controller_route_name',
                    // code after
                    'route_name'
                ),
            ]
        );
    }

    public function refactorMethod(
        Node $node,
        ReflectionMethod $method
    ): Node|array|null {
        // Ignore missing attributes.
        if ($attribute = $this->getFirstAttribute(Route::class, $method)) {
            $controllerRoutePrefix = $this->buildNodeControllerRoutePrefix($node);
            $routeName = $attribute->getArguments()[VariableHelper::NAME];

            if (str_starts_with($routeName, $controllerRoutePrefix)) {
                $newRoute = TextHelper::removePrefix($routeName, $controllerRoutePrefix);

                $this->setNodeAttributeArgValue(
                    $node,
                    Route::class,
                    VariableHelper::NAME,
                    $newRoute
                );

                return $node;
            }
        }

        return null;
    }
}
