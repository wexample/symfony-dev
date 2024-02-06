<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use ReflectionClass;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\MethodRectorTrait;
use Wexample\SymfonyTesting\Helper\TestControllerHelper;

class TestControllerHasNoOrphanMethodsRector extends AbstractRector
{
    use MethodRectorTrait;
    use ControllerRectorTrait;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure test controllers contains only expected test methods',
            [
                new CodeSample(
                    // code before
                    'Controller test testNonExistingMethod',
                    // code after
                    'Controller test without testNonExistingMethod'
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node)
    {
        if ($this->isControllerTestClass($node)) {
            $hasChange = false;
            $name = $this->getName($node);

            $controllerReflexion = new ReflectionClass(
                TestControllerHelper::buildControllerClassPath(
                    $name
                )
            );
            $testControllerReflexion = new ReflectionClass($name);
            $methods = $testControllerReflexion->getMethods();

            foreach ($methods as $method) {
                $remove = false;

                if ($this->isTestControllerRouteMethod($method)) {
                    $originalMethodName = $this->buildOriginalTestMethodName($method->getName());

                    if ($controllerReflexion->hasMethod($originalMethodName)) {
                        $originalMethod = $controllerReflexion->getMethod($originalMethodName);

                        if (!$this->isControllerRouteMethod($originalMethod)) {
                            $remove = true;
                        }
                    } else {
                        $remove = true;
                    }
                }

                if ($remove) {
                    $methodName = $method->getName();
                    $filtered = [];

                    foreach ($node->stmts as $statement) {
                        if ($this->getName($statement) !== $methodName) {
                            $filtered[] = $statement;
                        }
                    }

                    $node->stmts = $filtered;
                    $hasChange = true;
                }
            }

            if ($hasChange) {
                return $node;
            }
        }

        return null;
    }
}
