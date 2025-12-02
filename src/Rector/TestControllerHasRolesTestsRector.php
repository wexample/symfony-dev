<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector;
use Rector\Core\PhpParser\AstResolver;
use Rector\FileSystemRector\ValueObject\AddedFileWithContent;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyDev\Rector\Attribute\RectorIgnoreControllerRoleTest;
use Wexample\SymfonyDev\Rector\Traits\AttributeRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\RoleRectorTrait;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyTesting\Helper\TestControllerHelper;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;

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
            && ! $this->getFirstAttributeNode(
                $node,
                RectorIgnoreControllerRoleTest::class,
            )
        ) {
            // We should find a way to add more tested roles here.
            foreach ([RoleHelper::ROLE_ANONYMOUS] as $role) {
                $parentRole = $this->getParentRole($role);
                $roleClass = RoleHelper::getRoleNamePartAsClass($role);
                $controllerClass = $this->getReflexion($node)->getName();

                if ($parentRole) {
                    $parentTestClass = TestControllerHelper::buildTestControllerClassPath(
                        $controllerClass,
                        $parentRole,
                        checkExists: false
                    ) ?? AbstractRoleControllerTestCase::class;
                } else {
                    $parentTestClass = AbstractRoleControllerTestCase::class;
                }

                $classPath = TestControllerHelper::buildTestControllerClassPath(
                    $controllerClass,
                    $role,
                    checkExists: false
                );

                if (! class_exists($classPath)) {
                    # Guess a path
                    $filePathTest = getcwd().FileHelper::FOLDER_SEPARATOR
                        .ClassHelper::buildClassFilePath(
                            $classPath,
                            ClassHelper::DIR_TESTS
                        );

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
