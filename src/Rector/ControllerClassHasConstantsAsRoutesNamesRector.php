<?php

namespace Wexample\SymfonyDev\Rector;

use App\Wex\BaseBundle\Controller\AbstractController;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use Rector\Core\Enum\ObjectReference;
use Rector\Core\NodeManipulator\ClassInsertManipulator;
use Rector\Core\PhpParser\AstResolver;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\AttributeRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\ConstantRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ControllerClassHasConstantsAsRoutesNamesRector extends AbstractRector
{
    use ControllerRectorTrait;
    use ConstantRectorTrait;
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
            'Ensure controllers class routes consants',
            [
                new CodeSample(
                    // code before
                    'No constant for routes names',
                    // code after
                    'public const ROUTE_NAME = "route_name";'
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
        if ($this->isFinalControllerClass($node)) {
            $methods = $node->getMethods();

            foreach ($methods as $method) {
                if ($routeName = $this->getFirstAttributeArgumentStringIfLiteral(
                    $method,
                    Route::class,
                    VariableHelper::NAME
                )) {
                    if (in_array($routeName, AbstractController::PATH_TYPES)) {
                        $constantClass = AbstractController::class;
                        $constantName = 'PATH_TYPE_'.strtoupper($routeName);
                    } else {
                        $constantClass = ObjectReference::SELF();
                        $constantName = 'ROUTE_'.strtoupper($routeName);

                        if (! $this->hasClassConstant($node, $constantName)) {
                            $const = new Const_(
                                $constantName,
                                new String_($routeName)
                            );
                            $classConst = new ClassConst([$const]);
                            $classConst->flags = Class_::MODIFIER_PUBLIC | Class_::MODIFIER_FINAL;

                            $this->classInsertManipulator->addConstantToClass(
                                $node,
                                $constantName,
                                $classConst
                            );
                        }
                    }

                    $this->setNodeAttributeArgConstant(
                        $method,
                        Route::class,
                        VariableHelper::NAME,
                        $constantClass,
                        $constantName
                    );

                    return $node;
                }
            }
        }
    }

    protected function getPhpAttributeGroupFactory(): PhpAttributeGroupFactory
    {
        return $this->phpAttributeGroupFactory;
    }
}
