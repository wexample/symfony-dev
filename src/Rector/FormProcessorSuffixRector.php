<?php

namespace Wexample\SymfonyDev\Rector;

use Wexample\Helpers\Helper\ClassHelper;

class FormProcessorSuffixRector extends AbstractClassSuffixRector
{
    public function getClassBasePath(): string
    {
        return ClassHelper::CLASS_FORM_PROCESSOR_BASE_PATH;
    }

    public function getClassSuffix(): string
    {
        return ClassHelper::CLASS_PATH_PART_FORM_PROCESSOR;
    }
}
