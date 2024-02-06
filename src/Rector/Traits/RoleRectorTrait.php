<?php

namespace Wexample\SymfonyDev\Rector\Traits;

use Symfony\Component\Yaml\Yaml;

trait RoleRectorTrait
{
    protected function getParentRole(string $role): ?string
    {
        $inheritance = $this->loadRolesInheritance();

        return $inheritance[$role] ?? null;
    }

    public function loadRolesInheritance(): array
    {
        $yaml = Yaml::parseFile(
            getcwd().'/config/packages/security.yaml'
        );
        $rolesInheritance = [];

        foreach ($yaml['security']['role_hierarchy'] as $role => $parentRoles) {
            if (is_array($parentRoles)) {
                $parentRole = current($parentRoles);
            } else {
                $parentRole = $parentRoles;
            }

            $rolesInheritance[$role] = $parentRole;
        }

        return $rolesInheritance;
    }
}
