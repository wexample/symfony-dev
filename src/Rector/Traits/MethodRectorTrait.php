<?php

namespace Wexample\SymfonyDev\Rector\Traits;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;

trait MethodRectorTrait
{
    protected function isPublicAndNotMagic(ReflectionMethod $method): bool
    {
        return '_' !== $method->getName()[0] && $method->isPublic();
    }

    protected function getNodeMethod(Node $node): ?ReflectionMethod
    {
        // During massive renaming, names may change.
        try {
            return $this
                ->getReflexion($node)
                ->getNativeReflection()
                ->getMethod(
                    $this->getName($node)
                );
        } catch (Exception) {
        }

        return null;
    }

    protected function changeMethodName(
        ClassMethod $node,
        $routeName
    ): bool {
        if ($node->name->name !== $routeName) {
            $node->name = new Identifier(
                $routeName
            );

            return true;
        }

        return false;
    }
}
