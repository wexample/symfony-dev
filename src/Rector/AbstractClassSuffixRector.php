<?php

namespace Wexample\SymfonyDev\Rector;

use const DIRECTORY_SEPARATOR;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector;
use Rector\Core\Configuration\RenamedClassesDataCollector;
use Rector\Core\PhpParser\AstResolver;
use Rector\Renaming\NodeManipulator\ClassRenamer;
use Symplify\RuleDocGenerator\Exception\PoorDocumentationException;
use Symplify\RuleDocGenerator\Exception\ShouldNotHappenException;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

abstract class AbstractClassSuffixRector extends AbstractRector
{
    public function __construct(
        AstResolver $astResolver,
        private readonly ClassRenamer $classRenamer,
        private readonly RenamedClassesDataCollector $renamedClassesDataCollector,
        private readonly RemovedAndAddedFilesCollector $removedAndAddedFilesCollector
    ) {
        parent::__construct($astResolver);
    }

    public function getNodeTypes(): array
    {
        // what node types are we looking for?
        // pick any node from https://github.com/rectorphp/php-parser-nodes-docs/
        return [Class_::class];
    }

    public function refactor(Node|Class_ $node): ?Node
    {
        return $this->refactorClassSuffix(
            $node,
            $this->getClassBasePath(),
            $this->getClassSuffix()
        );
    }

    public function refactorClassSuffix(
        Class_ $node,
        string $classBasePath,
        string $classSuffix
    ): ?Class_ {
        $classPath = $this->nodeNameResolver->getName($node);

        if (str_starts_with(
            $classPath,
            $classBasePath
        )) {
            if (! str_ends_with(
                $classPath,
                $classSuffix
            )) {
                $node = $this->renameClasses(
                    $node,
                    $classPath.$classSuffix
                );

                $this->renameFileAccordingClassName(
                    $node,
                    $classSuffix
                );

                return $node;
            }
        }

        return null;
    }

    public function renameClasses(
        Class_ $node,
        string $newClassPath
    ): Class_ {
        $classPath = $this->nodeNameResolver->getName($node);

        $renamed = [
            $classPath => $newClassPath,
        ];

        $this->classRenamer->renameNode($node, $renamed);
        $this->renamedClassesDataCollector->addOldToNewClasses($renamed);

        return $node;
    }

    public function renameFileAccordingClassName(
        Node|Class_ $node,
        string $suffix
    ) {
        $classShortName = $this->nodeNameResolver->getShortName($this->getName($node));
        $smartFileInfo = $this->file->getSmartFileInfo();

        // no match → rename file
        $newFileLocation = $smartFileInfo->getPath()
            .DIRECTORY_SEPARATOR
            .$classShortName
            .$suffix.'.php';

        $this->removedAndAddedFilesCollector
            ->addMovedFile(
                $this->file,
                $newFileLocation
            );
    }

    abstract public function getClassBasePath(): string;

    abstract public function getClassSuffix(): string;

    /**
     * This method helps other to understand the rule and to generate documentation.
     *
     * @throws PoorDocumentationException|ShouldNotHappenException
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change class suffix if missing in '.$this->getClassBasePath(),
            [
                new CodeSample(
                    // code before
                    'MyFormProcessorName',
                    // code after
                    'MyFormProcessorName'.$this->getClassSuffix()
                ),
            ]
        );
    }
}
