<?php

namespace Wexample\SymfonyDev\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JsDevPackagesResolver
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * Resolve glob patterns and return an array of package aliases.
     *
     * @return array<string, string> Alias => absolute path
     */
    public function resolvePackages(): array
    {
        $patterns = $this->parameterBag->has('wexample_symfony_dev.js_dev_packages')
            ? $this->parameterBag->get('wexample_symfony_dev.js_dev_packages')
            : [];

        $packages = [];

        foreach ($patterns as $pattern) {
            $resolved = $this->resolvePattern($pattern);
            foreach ($resolved as $alias => $path) {
                $packages[$alias] = $path;
            }
        }

        return $packages;
    }

    /**
     * Resolve a single pattern (with or without glob).
     *
     * @return array<string, string>
     */
    private function resolvePattern(string $pattern): array
    {
        $packages = [];

        // Check if pattern contains glob characters
        if (str_contains($pattern, '*')) {
            $matches = glob($pattern, GLOB_ONLYDIR);
            if ($matches === false) {
                return [];
            }

            foreach ($matches as $dir) {
                $packageJson = $dir . '/package.json';
                if (file_exists($packageJson)) {
                    $alias = $this->extractAliasFromPackageJson($packageJson);
                    if ($alias !== null) {
                        $packages[$alias] = rtrim($dir, '/');
                    }
                }
            }
        } else {
            // Direct path without glob
            $dir = rtrim($pattern, '/');
            $packageJson = $dir . '/package.json';
            if (is_dir($dir) && file_exists($packageJson)) {
                $alias = $this->extractAliasFromPackageJson($packageJson);
                if ($alias !== null) {
                    $packages[$alias] = $dir;
                }
            }
        }

        return $packages;
    }

    /**
     * Extract the package name from package.json to use as alias.
     */
    private function extractAliasFromPackageJson(string $packageJsonPath): ?string
    {
        $content = file_get_contents($packageJsonPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (! is_array($data) || ! isset($data['name'])) {
            return null;
        }

        return $data['name'];
    }
}
