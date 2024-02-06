<?php

namespace Wexample\SymfonyDev\Rector;

use App\Service\EntityCrud\AbstractEntityCrudService;

class EntityManipulatorHasTraitCrudServiceRector extends AbstractEntityManipulatorRector
{
    public function getAbstractManipulatorClass(): string
    {
        return AbstractEntityCrudService::class;
    }
}
