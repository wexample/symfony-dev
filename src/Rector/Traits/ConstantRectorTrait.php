<?php

namespace Wexample\SymfonyDev\Rector\Traits;

use PhpParser\Node\Stmt\Class_;

trait ConstantRectorTrait
{
    private function hasClassConstant(
        Class_ $class,
        string $constantName
    ): bool {
        foreach ($class->getConstants() as $classConst) {
            if ($this->nodeNameResolver->isName($classConst, $constantName)) {
                return true;
            }
        }

        return false;
    }
}
