<?php

namespace Wexample\SymfonyDev\Rector;

use App\Wex\BaseBundle\Entity\AbstractEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\NodeManipulator\ClassInsertManipulator;
use Rector\Core\PhpParser\AstResolver;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\AttributeRectorTrait;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class EntityHasConstantsAsPropertiesTypes extends AbstractRector
{
    use AttributeRectorTrait;

    public function __construct(
        AstResolver $astResolver,
        private readonly ClassInsertManipulator $classInsertManipulator,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory
    ) {
        parent::__construct($astResolver);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure entity properties attributes types uses constants',
            [
                new CodeSample(
                    // code before
                    'type: "string"',
                    // code after
                    'type: Types::STRING'
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node|Class_ $node)
    {
        if ($this->nodeIsSubclassOf($node, AbstractEntity::class)) {
            $properties = $node->getProperties();
            $hasChange = false;

            foreach ($properties as $property) {
                if ($columnType = $this->getFirstAttributeArgumentStringIfLiteral(
                    $property,
                    Column::class,
                    VariableHelper::TYPE
                )) {
                    $constantName = strtoupper($columnType);

                    if (defined(Types::class.'::'.$constantName)) {
                        $hasChange = true;

                        $this->setNodeAttributeArgConstant(
                            $property,
                            Column::class,
                            VariableHelper::TYPE,
                            Types::class,
                            $constantName
                        );
                    }
                }
            }

            if ($hasChange) {
                return $node;
            }
        }
    }

    protected function getPhpAttributeGroupFactory(): PhpAttributeGroupFactory
    {
        return $this->phpAttributeGroupFactory;
    }
}
