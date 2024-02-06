<?php

namespace Wexample\SymfonyDev\Rector;

use Wexample\SymfonyHelpers\Helper\ClassHelper;

class FormSuffixRector extends AbstractClassSuffixRector
{
    public function getClassBasePath(): string
    {
        return ClassHelper::CLASS_FORM_BASE_PATH;
    }

    public function getClassSuffix(): string
    {
        return ClassHelper::CLASS_PATH_PART_FORM;
    }
}
