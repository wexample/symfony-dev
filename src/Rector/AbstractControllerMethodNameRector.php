<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Core\PhpParser\AstResolver;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;
use ReflectionMethod;
use Wexample\SymfonyDev\Rector\Traits\AttributeRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\MethodRectorTrait;
use Wexample\SymfonyHelpers\Helper\RouteHelper;

abstract class AbstractControllerMethodNameRector extends AbstractRector
{
    use ControllerRectorTrait;
    use MethodRectorTrait;
    use AttributeRectorTrait;

    public function __construct(
        AstResolver $astResolver,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory
    ) {
        parent::__construct(
            $astResolver
        );
    }

    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    public function refactor(Node $node): Node|array|null
    {
        if ($method = $this->isAbstractControllerClassMethod($node)) {
            return $this->refactorMethod($node, $method);
        }

        return null;
    }

    /**
     * @return Node|Node[]|null
     */
    abstract public function refactorMethod(
        ClassMethod $node,
        ReflectionMethod $method
    ): Node|array|null;

    protected function getPhpAttributeGroupFactory(): PhpAttributeGroupFactory
    {
        return $this->phpAttributeGroupFactory;
    }

    protected function buildNodeControllerRoutePrefix(Node $node): string
    {
        return RouteHelper::buildRoutePrefixFromControllerClass(
            $this->getReflexion($node)->getNativeReflection()->getName()
        );
    }
}
