<?php

namespace Wexample\SymfonyDev\Rector;

use App\Helper\RoleHelper;
use App\Tests\Integration\Role\AbstractRoleTestCase;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector;
use Rector\Core\PhpParser\AstResolver;
use Rector\FileSystemRector\ValueObject\AddedFileWithContent;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Attribute\RectorIgnoreControllerRoleTest;
use Wexample\SymfonyDev\Rector\Traits\AttributeRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\RoleRectorTrait;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;

class TestControllerHasRolesTestsRector extends AbstractRector
{
    use AttributeRectorTrait;
    use ControllerRectorTrait;
    use RoleRectorTrait;

    public function __construct(
        AstResolver $astResolver,
        private readonly RemovedAndAddedFilesCollector $removedAndAddedFilesCollector,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory
    ) {
        parent::__construct(
            $astResolver
        );
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure controller has all tests files',
            [
                new CodeSample(
                    // code before
                    'Missing ControllerTest files',
                    // code after
                    'All ControllerTest files exists'
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
        if ($this->isFinalControllerClass($node)
            && !$this->getFirstAttributeNode(
                $node,
                RectorIgnoreControllerRoleTest::class,
            )) {
            foreach (RoleHelper::ROLES as $role) {
                $parentRole = $this->getParentRole($role);
                $roleClass = RoleHelper::getRoleNamePartAsClass($role);
                $controllerClass = $this->getReflexion($node)->getName();

                if ($parentRole) {
                    $parentTestClass = AbstractRoleTestCase::buildTestControllerClassPath(
                        $controllerClass,
                        $parentRole
                    );
                } else {
                    $parentTestClass = AbstractRoleTestCase::getRoleTestClassBasePath()
                        .ClassHelper::getShortName(AbstractRoleTestCase::class);
                }

                $classPath = AbstractRoleTestCase::buildTestControllerClassPath(
                    $controllerClass,
                    $role
                );

                $filePathTest = getcwd().FileHelper::FOLDER_SEPARATOR
                    .ClassHelper::buildClassFilePath(
                        $classPath,
                        ClassHelper::DIR_TESTS
                    );

                if (!file_exists($filePathTest)) {
                    $content = ClassHelper::PHP_OPENER.
                        $this->renderTemplate(getcwd()
                            .'/front/php/Controller/Test.html.twig', [
                            'classNameTest' => ClassHelper::getShortName($classPath),
                            'namespace' => TextHelper::trimFirstChunk(
                                ClassHelper::trimLastClassChunk($classPath),
                                ClassHelper::NAMESPACE_SEPARATOR
                            ),
                            'parentTestClass' => $parentTestClass,
                            'roleClass' => $roleClass,
                        ]);

                    $this->removedAndAddedFilesCollector->addAddedFile(
                        new AddedFileWithContent(
                            $filePathTest,
                            $content
                        )
                    );
                }
            }

            return null;
        }
    }

    protected function getPhpAttributeGroupFactory(): PhpAttributeGroupFactory
    {
        return $this->phpAttributeGroupFactory;
    }
}
