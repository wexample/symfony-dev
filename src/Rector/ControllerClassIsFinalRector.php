<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\PhpParser\AstResolver;
use Rector\Privatization\NodeManipulator\VisibilityManipulator;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Wexample\SymfonyDev\Rector\Traits\ControllerRectorTrait;

class ControllerClassIsFinalRector extends AbstractRector
{
    use ControllerRectorTrait;

    public function __construct(
        AstResolver $astResolver,
        protected VisibilityManipulator $visibilityManipulator
    ) {
        parent::__construct($astResolver);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure controllers class is abstract on final',
            [
                new CodeSample(
                    // code before
                    'class MyController {',
                    // code after
                    'final class MyController {'
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
        if ($this->isInstanceOfAbstractControllerClass($node)) {
            if (! $this->getReflexion($node)->getNativeReflection()->isAbstract()) {
                $this->visibilityManipulator->makeFinal($node);
            }
        }
    }
}
