<?php

namespace Wexample\SymfonyDev\Traits;

use Wexample\SymfonyDev\WexampleSymfonyDevBundle;
use Wexample\SymfonyHelpers\Traits\BundleClassTrait;

trait SymfonyDevBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyDevBundle::class;
    }
}
