<?php

namespace Wexample\SymfonyDev\Rector;

use App\Tests\Integration\Role\AbstractRoleTestCase;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\RoleRectorTrait;
use Wexample\SymfonyHelpers\Helper\AbstractRoleHelper;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;

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
        if ($this->isTestControllerClass($node)
            && $role = $this->getControllerTestRole($node)) {
            $parentRole = $this->getParentRole($role);

            if (!$parentRole && AbstractRoleHelper::ROLE_ANONYMOUS !== $role) {
                $parentRole = AbstractRoleHelper::ROLE_ANONYMOUS;
            }

            if (!$parentRole) {
                $parentClass = AbstractRoleTestCase::class;
            } else {
                $parentClass = $this->buildControllerTestRoleBaseClassPath($parentRole)
                    .$this->trimControllerTestToleBaseClassPath(
                        $this->getName($node)
                    );
            }

            if (!is_subclass_of($this->getName($node), $parentClass)) {
                $node->extends = new FullyQualified(
                    TextHelper::trimStringPrefix(
                        $parentClass,
                        ClassHelper::NAMESPACE_SEPARATOR
                    )
                );
            }

            return null;
        }
    }

    protected function trimControllerTestToleBaseClassPath(string $classPath): string
    {
        $trimmed = TextHelper::trimStringPrefix(
            ClassHelper::NAMESPACE_SEPARATOR.$classPath,
            AbstractRoleTestCase::getRoleTestClassBasePath()
        );

        $parts = explode(ClassHelper::NAMESPACE_SEPARATOR, $trimmed);

        array_shift($parts);

        return implode(
            ClassHelper::NAMESPACE_SEPARATOR,
            $parts
        );
    }
}
