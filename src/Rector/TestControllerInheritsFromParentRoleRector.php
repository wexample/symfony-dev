<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\RoleRectorTrait;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;

class TestControllerInheritsFromParentRoleRector extends AbstractRector
{
    use ControllerRectorTrait;
    use RoleRectorTrait;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure controller has a proper parent controller',
            [
                new CodeSample(
                    'class SomeControllerTest extends AbstractControllerTest',
                    'class SomeControllerTest extends \App\...\Role\User\SomeControllerTest'
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
        if ($role = $this->getControllerTestRole($node)) {
            $parentRole = $this->getParentRole($role);

            if (!$parentRole && RoleHelper::ROLE_ANONYMOUS !== $role) {
                $parentRole = RoleHelper::ROLE_ANONYMOUS;
            }

            if (!$parentRole) {
                $parentClass = AbstractRoleControllerTestCase::class;
            } else {
                $parentClass = $this->buildControllerTestRoleBaseClassPath($parentRole)
                    .$this->trimControllerTestToBaseClassPath(
                        $this->getName($node)
                    );
            }

            if (!is_subclass_of($this->getName($node), $parentClass)) {
                $node->extends = new FullyQualified(
                    TextHelper::removePrefix(
                        $parentClass,
                        ClassHelper::NAMESPACE_SEPARATOR
                    )
                );
            }

            return null;
        }
    }

    protected function trimControllerTestToBaseClassPath(string $classPath): string
    {
        $trimmed = TextHelper::removePrefix(
            ClassHelper::NAMESPACE_SEPARATOR.$classPath,
            AbstractRoleControllerTestCase::getRoleTestClassBasePath()
        );

        $parts = explode(ClassHelper::NAMESPACE_SEPARATOR, $trimmed);

        array_shift($parts);

        return implode(
            ClassHelper::NAMESPACE_SEPARATOR,
            $parts
        );
    }
}
