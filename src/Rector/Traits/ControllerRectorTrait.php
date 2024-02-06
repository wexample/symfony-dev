<?php

namespace Wexample\SymfonyDev\Rector\Traits;

use App\Helper\RoleHelper;
use App\Wex\BaseBundle\Api\Controller\AbstractApiController;
use App\Wex\BaseBundle\Api\Controller\AbstractApiEntityController;
use App\Wex\BaseBundle\Controller\AbstractController;
use JetBrains\PhpStorm\Pure;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;
use ReflectionMethod;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Service\Syntax\ControllerSyntaxService;
use Wexample\SymfonyTesting\Helper\TestControllerHelper;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;
use Wexample\SymfonyTesting\Tests\AbstractRoleTestCase;

trait ControllerRectorTrait
{
    use MethodRectorTrait;

    protected function isTestControllerRouteMethod(ReflectionMethod $method): bool
    {
        return $this->isPublicAndNotMagic($method)
            && !$method->isStatic()
            && str_starts_with($method->getName(), 'test');
    }

    #[Pure]
    protected function buildOriginalTestMethodName(string $methodName): string
    {
        return lcfirst(TextHelper::trimStringPrefix(
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

        if (!is_null($parent) && !$this->getReflexion($parent)) {
            // Method is from a trait.
            if (trait_exists($this->getReflexion($node)->getName())) {
                return null;
            }
        }

        if (!$parent instanceof Class_) {
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
            && !$node->isAbstract()
            && $node->isFinal();
    }

    protected function isTestControllerClass(Node $node): bool
    {
        return is_subclass_of(
            $this->getReflexion($node)->getNativeReflection()->getName(),
            AbstractRoleTestCase::class
        );
    }

    protected function getControllerTestRole(Node $node): ?string
    {
        $basePath = AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH;
        $classPath = $this->getName($node);

        if (str_starts_with(ClassHelper::NAMESPACE_SEPARATOR.$classPath, $basePath)) {
            return
                'ROLE_'
                .strtoupper(
                    explode(
                        ClassHelper::NAMESPACE_SEPARATOR,
                        TextHelper::trimStringPrefix($classPath, $basePath)
                    )[4]
                );
        }

        return null;
    }

    protected function buildControllerTestRoleBaseClassPath(string $role): string
    {
        $parentRoleClass = RoleHelper::getRoleNamePartAsClass($role);

        return
            AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH
            .$parentRoleClass
            .ClassHelper::NAMESPACE_SEPARATOR;
    }

    protected function forEachTestableOriginalControllerMethod(
        Node $testControllerNode,
        callable $callback
    ): ?Node {
        if ($this->isControllerTestClass($testControllerNode)) {
            $name = $this->getName($testControllerNode);

            $controllerClassPath = TestControllerHelper::buildControllerClassPath(
                $name
            );

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
            && !$method->isStatic()
            && !in_array($method->getName(), [
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
