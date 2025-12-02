<?php

namespace Wexample\SymfonyDev\Rector\Traits;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;
use ReflectionMethod;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyHelpers\Service\Syntax\ControllerSyntaxService;
use Wexample\SymfonyTesting\Helper\TestControllerHelper;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;

trait ControllerRectorTrait
{
    use MethodRectorTrait;

    protected function isTestControllerRouteMethod(ReflectionMethod $method): bool
    {
        return $this->isPublicAndNotMagic($method)
            && ! $method->isStatic()
            && str_starts_with($method->getName(), 'test');
    }

    protected function buildOriginalTestMethodName(string $methodName): string
    {
        return lcfirst(TextHelper::removePrefix(
            $methodName,
            'test'
        ));
    }

    protected function isAbstractControllerClassMethod(ClassMethod $node): ?ReflectionMethod
    {
        $parentClassNode = $this->getParentClassNode($node);

        if ($parentClassNode && $this->isInstanceOfAbstractControllerClass(
            $parentClassNode
        )) {
            return $this->getNodeMethod($node);
        }

        return null;
    }

    protected function getParentClassNode(Node $node): ?Node\Stmt\Class_
    {
        $parent = $node->getAttributes()['parent'];

        if (! is_null($parent) && ! $this->getReflexion($parent)) {
            // Method is from a trait.
            if (trait_exists($this->getReflexion($node)->getName())) {
                return null;
            }
        }

        if (! $parent instanceof Class_) {
            return null;
        }

        return $parent;
    }

    protected function isInstanceOfAbstractControllerClass(Node $node): bool
    {
        // Method is owned by a controller.
        return $this->nodeIsSubclassOf(
            $node,
            AbstractController::class
        );
    }

    protected function isFinalControllerClass(Node $node): bool
    {
        return $this->isInstanceOfAbstractControllerClass($node)
            && ! $node->isAbstract()
            && $node->isFinal();
    }

    protected function getControllerTestRole(Node $node): ?string
    {
        if (! $this->isControllerTestClass($node)) {
            return null;
        }

        return TestControllerHelper::buildControllerRoleName(
            $this->getName($node)
        );
    }

    protected function buildControllerTestRoleBaseClassPath(string $role): string
    {
        $parentRoleClass = RoleHelper::getRoleNamePartAsClass($role);

        return
            AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH
            .$parentRoleClass
            .ClassHelper::NAMESPACE_SEPARATOR;
    }

    /**
     * @throws Exception
     */
    protected function forEachTestableOriginalControllerMethod(
        Node $testControllerNode,
        callable $callback
    ): ?Node {
        if ($this->isControllerTestClass($testControllerNode)) {
            $name = $this->getName($testControllerNode);

            if (! $controllerClassPath = TestControllerHelper::buildControllerClassPath(
                $name
            )) {
                return null;
            }

            $hasChange = false;

            if (class_exists($controllerClassPath)) {
                $controllerReflexion = (new ReflectionClass($controllerClassPath));
                $methods = $controllerReflexion->getMethods();

                foreach ($methods as $method) {
                    if ($this->isControllerRouteMethod($method)) {
                        if ($callback($method)) {
                            $hasChange = true;
                        }
                    }
                }
            }

            if ($hasChange) {
                return $testControllerNode;
            }
        }

        return null;
    }

    protected function isControllerTestClass(Node $node): bool
    {
        // Based on string as class may have wrong inheritance.
        return str_ends_with(
            $this->getName($node),
            ClassHelper::CLASS_PATH_PART_CONTROLLER.ControllerSyntaxService::SUFFIX_TEST
        );
    }

    protected function isInstanceOfApiController(Node $node): bool
    {
        return $this->nodeIsSubclassOf(
            $node,
            AbstractApiController::class
        )
            || $this->nodeIsSubclassOf(
                $node,
                AbstractApiEntityController::class
            );
    }

    protected function isControllerRouteMethod(ReflectionMethod $method): bool
    {
        return $this->isPublicAndNotMagic($method)
            && ! $method->isStatic()
            && ! in_array($method->getName(), [
                'adaptiveRender',
                'adaptiveRedirectToRoute',
                'getEnvironment',
                'getEntityCrudService',
                'getEntityRepository',
                'setContainer',
                'defaultEntityActionList',
            ]);
    }
}
