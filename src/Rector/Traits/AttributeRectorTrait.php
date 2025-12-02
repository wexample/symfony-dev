<?php

namespace Wexample\SymfonyDev\Rector\Traits;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Rector\PhpAttribute\Printer\PhpAttributeGroupFactory;

trait AttributeRectorTrait
{
    abstract protected function getPhpAttributeGroupFactory(): PhpAttributeGroupFactory;

    protected function addAttributeWithArgIfMissing(
        Node $node,
        string $attributeClass,
        string $name,
        string $value
    ): ?Node {
        // Do not modify if exists.
        if ($this->hasNodeAttributeArg(
            $node,
            $attributeClass,
            $name
        )) {
            return null;
        }

        if (! $attribute = $this->getFirstAttributeNode(
            $node,
            $attributeClass
        )) {
            $attribute = $this->addNodeAttribute(
                $node,
                $attributeClass
            );
        }

        $this->addNodeAttributeArg(
            $attribute,
            $name,
            $value
        );

        return $node;
    }

    protected function hasNodeAttributeArg(
        Node $node,
        string $attributeClass,
        string $name
    ): bool {
        if ($attr = $this->getFirstAttributeNode(
            $node,
            $attributeClass
        )) {
            foreach ($attr->args as $arg) {
                if ($arg->name->name === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getFirstAttributeNode(
        Node $node,
        string $attributeClass
    ): ?Attribute {
        /** @var AttributeGroup $attrGroup */
        foreach ($node->attrGroups as $attrGroup) {
            /** @var Attribute $attr */
            foreach ($attrGroup->attrs as $attr) {
                if ($this->getName($attr) === $attributeClass) {
                    return $attr;
                }
            }
        }

        return null;
    }

    protected function addNodeAttribute(
        Node $node,
        string $attributeClass
    ): AttributeGroup {
        $attributeGroup = $this
            ->phpAttributeGroupFactory
            ->createFromClass(
                $attributeClass
            );
        $node->attrGroups[] = $attributeGroup;

        return $attributeGroup;
    }

    protected function addNodeAttributeArg(
        $attributeGroup,
        string $name,
        string $value
    ): Arg {
        $arg = new Arg(
            new String_($value)
        );

        $arg->name = new Identifier(
            $name
        );

        $attributeGroup->attrs[0]->args[] = $arg;

        return $arg;
    }

    protected function setNodeAttributeArgValue(
        Node $node,
        string $attributeClass,
        string $name,
        mixed $value
    ) {
        if ($attr = $this->getFirstAttributeNode(
            $node,
            $attributeClass
        )) {
            foreach ($attr->args as $arg) {
                if ($arg->name->name === $name) {
                    if (is_string($value)) {
                        $arg->value->value = $value;
                    } else {
                        $arg->value = $value;
                    }
                }
            }
        }
    }

    protected function isFirstAttributeArgumentIsConstant(
        Node $parentNode,
        string $attributeClass,
        string $argumentName
    ): bool {
        return $this->isFirstAttributeArgumentIsOfType(
            $parentNode,
            $attributeClass,
            $argumentName,
            ClassConstFetch::class
        );
    }

    protected function isFirstAttributeArgumentIsOfType(
        Node $parentNode,
        string $attributeClass,
        string $argumentName,
        string $type
    ): bool {
        $value = $this->getFirstAttributeArgumentNode(
            $parentNode,
            $attributeClass,
            $argumentName
        )
            ?->value;

        return $value
            && $value::class === $type;
    }

    protected function getFirstAttributeArgumentNode(
        Node $parentNode,
        string $attributeClass,
        string $argumentName
    ): ?Arg {
        $attributeNode = $this->getFirstAttributeNode(
            $parentNode,
            $attributeClass
        );

        foreach ($attributeNode->args as $arg) {
            if ($arg->name->name === $argumentName) {
                return $arg;
            }
        }

        return null;
    }

    protected function getFirstAttributeArgumentStringIfLiteral(
        Node $node,
        string $attributeClass,
        string $argumentName
    ): ?string {
        $routeNameArg = $this->getFirstAttributeArgumentNode(
            $node,
            $attributeClass,
            $argumentName
        );

        return $routeNameArg->value->value;
    }

    protected function setNodeAttributeArgConstant(
        Node $node,
        string $attributeClass,
        string $name,
        string $constantClass,
        string $constantName,
    ) {
        $this->setNodeAttributeArgValue(
            $node,
            $attributeClass,
            $name,
            $this->buildConstantArgument(
                $constantClass,
                $constantName
            )
        );
    }
}
