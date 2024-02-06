<?php

namespace Wexample\SymfonyDev\Rector;

use App\Service\Syntax\ControllerSyntaxService;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\MethodRectorTrait;

class TestControllerHasMethodsRector extends AbstractRector
{
    use MethodRectorTrait;
    use ControllerRectorTrait;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure test controllers contains expected test methods',
            [
                new CodeSample(
                    // code before
                    'Controller test without myControllerMethodTest',
                    // code after
                    'Controller test with myControllerMethodTest'
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
        $testReflexion = $this->getReflexion($node);

        $this->forEachTestableOriginalControllerMethod($node, function (ReflectionMethod $method) use (
            $node,
            $testReflexion
        ) {
            $methodName = $method->getName();

            $testMethodName = ControllerSyntaxService::METHOD_PREFIX_TEST
                .ucfirst($methodName);

            if (!$testReflexion->hasMethod($testMethodName)) {
                $classMethod = new ClassMethod(
                    $testMethodName,
                    [
                        'flags' => Class_::MODIFIER_PUBLIC,
                        'params' => [],
                    ]
                );

                $classMethod->stmts = [
                    new Node\Stmt\Expression(
                        new MethodCall(
                            new Variable('this'),
                            'assertTrue',
                            [
                                new Arg(
                                    new ConstFetch(
                                        new Name(
                                            'false'
                                        )
                                    )
                                ),
                            ]
                        )
                    ),
                ];

                $node->stmts[static::class.$testMethodName] = $classMethod;

                return true;
            }

            return false;
        });

        return null;
    }
}
