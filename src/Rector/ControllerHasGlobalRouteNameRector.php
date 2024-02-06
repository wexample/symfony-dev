<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\PhpParser\AstResolver;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\AttributeRectorTrait;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;
use Wexample\SymfonyHelpers\Helper\RouteHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ControllerHasGlobalRouteNameRector extends AbstractRector
{
    use AttributeRectorTrait;
    use ControllerRectorTrait;

    public function __construct(
        AstResolver $astResolver,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory
    ) {
        parent::__construct(
            $astResolver
        );
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure controllers has a #Route attribute with his_name_',
            [
                new CodeSample(
                    // code before
                    'No #Route or no name: attribute',
                    // code after
                    '[#Route ... name:controller_name_]'
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
        $reflexion = $this->getReflexion($node)->getNativeReflection();

        if ($this->isFinalControllerClass($node)) {
            return $this->addAttributeWithArgIfMissing(
                $node,
                Route::class,
                VariableHelper::NAME,
                RouteHelper::buildRoutePrefixFromControllerClass($reflexion->getName())
            );
        }

        return null;
    }

    protected function getPhpAttributeGroupFactory(): PhpAttributeGroupFactory
    {
        return $this->phpAttributeGroupFactory;
    }
}
