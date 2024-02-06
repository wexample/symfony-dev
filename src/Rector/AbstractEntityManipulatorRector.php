<?php

namespace Wexample\SymfonyDev\Rector;

use App\Service\EntityCrud\AbstractEntityCrudService;
use App\Service\Syntax\EntitySyntaxService;
use App\Wex\BaseBundle\Controller\Traits\VariableEntityTypeControllerTrait;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Runner\Exception;
use Rector\Core\NodeManipulator\ClassInsertManipulator;
use Rector\Core\PhpParser\AstResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

abstract class AbstractEntityManipulatorRector extends AbstractRector
{
    public function __construct(
        private readonly AstResolver $astResolver,
        private readonly ClassInsertManipulator $classInsertManipulator
    ) {
        parent::__construct($this->astResolver);
    }

    public function getNodeTypes(): array
    {
        // what node types are we looking for?
        // pick any node from https://github.com/rectorphp/php-parser-nodes-docs/
        return [Class_::class];
    }

    public function refactor(Node|Class_ $node): ?Node
    {
        if (
            $this->isSubclassOf(
                $node,
                $this->getAbstractManipulatorClass()
            )
            && !$this->ignore($node)
            && !$node->isAbstract()
            && !$this->isTraitUsed(
                $node,
                VariableEntityTypeControllerTrait::class
            )
        ) {
            /** @var AbstractEntityCrudService $classService */
            $classService = $this->getName($node);

            if (method_exists($classService, 'getEntityClassName')) {
                $entityClassName = $classService::getEntityClassName();
            } else {
                throw new Exception('Unable to determine handled entity by manipulator in '.$classService);
            }

            $manipulatorTrait = EntitySyntaxService::getCousinPathByName(
                $entityClassName,
                EntitySyntaxService::COUSIN_TRAIT_MANIPULATOR
            );

            if (!$this->isTraitUsed(
                $node,
                $manipulatorTrait
            )) {
                $this->getReflexion($node);

                $this->classInsertManipulator->addAsFirstTrait(
                    $node,
                    $manipulatorTrait
                );
            }
        }

        return null;
    }

    abstract public function getAbstractManipulatorClass(): string;

    protected function ignore(Class_ $node): bool
    {
        return false;
    }

    /**
     * This method helps other to understand the rule and to generate documentation.
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change class suffix if missing.',
            [
                new CodeSample(
                // code before
                    'No class cousin for MySuperEntity',
                    // code after
                    'Generated cousins : MysSuperEntityManipulatorTrait, MySuperEntityCrudService, etc.'
                ),
            ]
        );
    }
}
