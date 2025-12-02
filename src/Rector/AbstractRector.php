<?php

namespace Wexample\SymfonyDev\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\Core\Enum\ObjectReference;
use Rector\Core\PhpParser\AstResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use ReflectionAttribute;
use Reflector;
use Wexample\Helpers\Helper\ClassHelper;

abstract class AbstractRector extends \Rector\Core\Rector\AbstractRector
{
    public function __construct(
        private readonly AstResolver $astResolver,
    ) {
    }

    public function isSubclassOf(
        Node $node,
        string $classPath
    ): bool {
        return $this->getReflexion($node)?->isSubclassOf($classPath);
    }

    public function getReflexion(Node $node): ?ClassReflection
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        if (! $scope instanceof Scope) {
            return null;
        }
        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        return $classReflection;
    }

    public function isTraitUsed(
        Node $node,
        string $traitClassPath
    ): bool {
        $classReflection = $this->getReflexion($node);
        $traits = $this->astResolver->parseClassReflectionTraits($classReflection);
        foreach ($traits as $trait) {
            if ($this->getName($trait) === $traitClassPath) {
                return true;
            }
        }

        return false;
    }

    public function renderTemplate(
        string $path,
        $parameters = []
    ): string {
        $content = file_get_contents(
            $path
        );

        foreach ($parameters as $key => $value) {
            $content = str_replace(
                '{{ '.$key.' }}',
                $value,
                $content
            );
        }

        return $content;
    }

    protected function getFirstAttribute(
        string $attributeClass,
        Reflector $reflection
    ): ?ReflectionAttribute {
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return $attribute;
            }
        }

        return null;
    }

    protected function buildConstantArgument(
        string|ObjectReference $reference,
        string $constantName
    ): Arg {
        if (is_string($reference)) {
            $withLeadingSlash = ClassHelper::NAMESPACE_SEPARATOR.$reference;
            if (class_exists($withLeadingSlash)) {
                $reference = $withLeadingSlash;
            }
        }

        if ($reference instanceof ObjectReference) {
            $reference = $reference->getValue();
        }

        return new Arg(
            new ClassConstFetch(
                new Node\Name(
                    $reference
                ),
                $constantName
            )
        );
    }

    protected function nodeIsSubclassOf(
        Node $node,
        string $class
    ): bool {
        return is_subclass_of(
            $this->getReflexion($node)->getNativeReflection()->getName(),
            $class
        );
    }
}
