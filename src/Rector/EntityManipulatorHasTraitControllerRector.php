<?php

namespace Wexample\SymfonyDev\Rector;

use App\Wex\BaseBundle\Api\Controller\VirtualEntityController;
use App\Wex\BaseBundle\Controller\AbstractEntityController;
use PhpParser\Node\Stmt\Class_;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;

class EntityManipulatorHasTraitControllerRector extends AbstractEntityManipulatorRector
{
    use ControllerRectorTrait;

    public function getAbstractManipulatorClass(): string
    {
        return AbstractEntityController::class;
    }

    protected function ignore(Class_ $node): bool
    {
        return ! $this->isFinalControllerClass($node)
            || $this->getReflexion($node)->getName() === VirtualEntityController::class;
    }
}
