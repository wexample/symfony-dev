# symfony_dev

Version: 4.0.3

`wexample/symfony-dev` is a Symfony bundle that ships a collection of [Rector](https://getrector.org/) rules for enforcing Wexample coding conventions automatically: controllers must be `final` and carry a global `#[Route]` name prefix, route names on methods must match their PHP method names, form and entity classes must carry the right class suffixes, entity `#[Column]` types must reference `Types::*` constants, and role-based test files must exist for every controller. It is aimed at Symfony developers inside the Wexample suite who want those conventions applied by a code-mod tool rather than enforced manually in code review.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The bundle is built from three independent layers: a **DependencyInjection** layer that reads configuration and wires services, a **Command** layer that automates the development environment, and a **Rector** layer that enforces coding conventions by transforming PHP source files. Each layer is self-contained and does not call into the others.

### Entry point

src/WexampleSymfonyDevBundle.php is an empty subclass of Symfony's `Bundle`. Symfony discovers it, which triggers src/DependencyInjection/WexampleSymfonyDevExtension.php.

### DependencyInjection

src/DependencyInjection/WexampleSymfonyDevExtension.php calls `loadConfig()` (from the parent `AbstractWexampleSymfonyExtension`) to import src/Resources/config/services.yaml, then processes the user-supplied configuration and stores the result as container parameters:

- `wexample_symfony_dev.vendor_dev_paths` — glob patterns pointing to local PHP package directories.
- `wexample_symfony_dev.js_dev_packages` — glob patterns pointing to local JS package directories.
- `wexample_symfony_dev.setup_hooks` — extra Symfony commands to run during `dev:setup`.

src/DependencyInjection/Configuration.php defines the tree. All three keys are optional arrays of strings (or, for `setup_hooks`, arrays of `{command, args}` maps).

src/Resources/config/services.yaml autowires every class under `Command/` and `Service/` so no manual service definition is required.

### Command layer

All commands extend src/Command/AbstractDevCommand.php, which:

- reads `wexample_symfony_dev.vendor_dev_paths` from the parameter bag into `$this->devVendors`;
- provides `forEachDevPackage(callable)` to iterate every `composer.json`-bearing directory found under those paths;
- provides `reloadAutoloader(OutputInterface)`, which runs `composer dump-autoload --classmap-authoritative`;
- provides helpers to read and write the application's `composer.json`.

#### dev:setup

src/Command/SetupCommand.php is the top-level orchestrator. It runs, in order:

1. `dev:setup-node --force`
2. `dev:setup-composer`
3. Any hooks from `wexample_symfony_dev.setup_hooks`
4. `reloadAutoloader()`
5. `php bin/console cache:clear`

#### dev:setup-composer

src/Command/SetupComposerCommand.php installs local PHP packages as `vendor/` symlinks without dirtying `composer.lock`. The sequence is:

1. Back up `composer.json` (in memory) and `composer.lock` (to `composer.lock.backup`).
2. Inject path repositories derived from `wexample_symfony_dev.vendor_dev_paths` into a temporary `composer.json`, with `canonical: false` and `symlink: true`.
3. Relax any exact version constraints on those packages to `^x.y.z`.
4. Remove the existing installed copies from `vendor/`.
5. Remove `composer.lock` and run `composer install`.
6. Restore the original `composer.json` and `composer.lock`.
7. Force-symlink every local source directory over whatever Composer resolved, so the working copy always wins regardless of Packagist version resolution.

#### dev:setup-node

src/Command/SetupNodeCommand.php handles the JS side. It:

1. Creates a symlink `<package>/node_modules → <project>/node_modules` for every local package that ships an `assets/package.json`.
2. Runs `yarn install` inside each directory listed by `JsDevPackagesResolver`, skipping packages whose `node_modules` already exists unless `--force` is passed.

#### dev:change-user-password and dev:change-all-users-password

src/Command/ChangeUserPasswordCommand.php finds a single user by an identifier field (default: `email`) and replaces their password hash. src/Command/ChangeAllUsersPasswordCommand.php does the same for every record in the repository. Both default to `App\Entity\User` and accept `--class` to point at a different entity.

### Service

src/Service/JsDevPackagesResolver.php is consumed by `SetupNodeCommand`. It reads `wexample_symfony_dev.js_dev_packages`, expands any glob patterns, opens the `package.json` of each matching directory, and returns a map of `name → absolute path`. It is the single place that translates glob configuration into concrete filesystem paths for the Node tooling.

### Helper

src/Helper/DevHelper.php holds the single constant `VENDOR_DIR_NAME = 'vendor'`.

### Rector layer

The Rector layer is a collection of rules that Rector applies to PHP source files. Rules are not loaded by Symfony and carry no service registrations; they are referenced directly in the consuming project's `rector.php`.

#### Abstract base classes

src/Rector/AbstractRector.php extends Rector's own `AbstractRector` and adds shared utilities used by every rule in this bundle:

- `getReflexion(Node)` — retrieves the `ClassReflection` for any node from PHPStan's scope.
- `isSubclassOf(Node, string)` and `nodeIsSubclassOf(Node, string)` — inheritance checks via reflection.
- `isTraitUsed(Node, string)` — resolves parsed traits through `AstResolver`.
- `renderTemplate(string, array)` — simple `` template rendering used to generate test file stubs.
- `buildConstantArgument(string, string)` — constructs a `ClassConstFetch` AST node for a class constant reference.

src/Rector/AbstractControllerMethodNameRector.php targets `ClassMethod` nodes. `refactor()` checks whether the method belongs to a class that extends `AbstractController`; if so, it hands the method to the abstract `refactorMethod(ClassMethod, ReflectionMethod)` that each subclass implements. It also provides `buildNodeControllerRoutePrefix(Node)`, which derives the expected route prefix from the controller's class name.

src/Rector/AbstractClassSuffixRector.php targets `Class_` nodes. `refactor()` checks whether the class lives under `getClassBasePath()` and lacks the suffix returned by `getClassSuffix()`; if so, it renames the class with `ClassRenamer`, updates the rename map in `RenamedClassesDataCollector`, and schedules the file to be moved via `RemovedAndAddedFilesCollector`.

src/Rector/AbstractEntityManipulatorRector.php targets `Class_` nodes that are concrete subclasses of a manipulator class (a crud service or controller). For each such class, it calls `getEntityClassName()` on the class itself, derives the expected cousin trait path through `EntitySyntaxService`, and inserts the trait as the first trait if it is not already present.

#### Concrete rules

| File | What it changes |
|---|---|
| src/Rector/ControllerClassIsFinalRector.php | Adds `final` to any non-abstract controller class. |
| src/Rector/ControllerHasGlobalRouteNameRector.php | Adds `#[Route(name: 'controller_prefix_')]` to a final controller class if the attribute or its `name` argument is missing. |
| src/Rector/ControllerMethodHasRouteRector.php | Adds `#[Route(name: 'snake_method_name')]` to any public, non-static, non-magic controller method that lacks one. |
| src/Rector/ControllerRouteNameHasNoControllerPrefixRector.php | Strips the controller prefix from a method-level `#[Route(name:)]` that duplicates the class-level prefix. |
| src/Rector/ControllerMethodNameMatchRouteNameRector.php | Renames the PHP method to the camelCase form of its `#[Route(name:)]` value. |
| src/Rector/ControllerClassHasConstantsAsRoutesNamesRector.php | Replaces string route name literals in `#[Route(name:)]` with `self::ROUTE_*` constants, creating the constant if absent. |
| src/Rector/FormSuffixRector.php | Ensures Form classes under `ClassHelper::CLASS_FORM_BASE_PATH` end with the Form suffix. |
| src/Rector/FormProcessorSuffixRector.php | Ensures FormProcessor classes end with the FormProcessor suffix. |
| src/Rector/EntityHasConstantsAsPropertiesTypes.php | Replaces `#[Column(type: 'string')]` string literals with `Types::STRING`-style Doctrine constants. |
| src/Rector/EntityManipulatorHasTraitCrudServiceRector.php | Ensures concrete crud services use their entity's manipulator trait. |
| src/Rector/EntityManipulatorHasTraitControllerRector.php | Ensures concrete entity controllers use their entity's manipulator trait, skipping abstract and virtual controllers. |
| src/Rector/TestControllerHasRolesTestsRector.php | Creates missing role-based test class files (e.g. `AnonymousMyControllerTest.php`) for each final controller, rendering them from a project-side Twig stub. Skipped when `#[RectorIgnoreControllerRoleTest]` is present on the controller. |
| src/Rector/TestControllerHasMethodsRector.php | Adds a failing `test*` stub method to a controller test class for each route method in the corresponding controller that has no test yet. |
| src/Rector/TestControllerHasNoOrphanMethodsRector.php | Removes `test*` methods from a controller test class whose corresponding controller route method no longer exists. |
| src/Rector/TestControllerInheritsFromParentRoleRector.php | Updates a role-based test class to extend the correct parent test class derived from the role hierarchy. |

#### Rector traits

Traits are mixed into the rules above to share logic without repeating it.

src/Rector/Traits/ControllerRectorTrait.php identifies whether a class node is a controller (`isInstanceOfAbstractControllerClass`), a final controller (`isFinalControllerClass`), or a controller test class (`isControllerTestClass`). It also provides `forEachTestableOriginalControllerMethod`, which reflects the real controller referenced by a test class and invokes a callback for each route method.

src/Rector/Traits/MethodRectorTrait.php provides three helpers: `isPublicAndNotMagic(ReflectionMethod)`, `getNodeMethod(Node)` (which wraps the reflection lookup in a try/catch for mid-refactor renames), and `changeMethodName(ClassMethod, string)`.

src/Rector/Traits/AttributeRectorTrait.php is a CRUD layer for PHP 8 attribute nodes: find an attribute by class name, check whether a named argument already exists, add an attribute group, add or mutate an argument value (string literal or class constant fetch).

src/Rector/Traits/ConstantRectorTrait.php provides `hasClassConstant(Class_, string)`, which walks the class's constant list to check for a given name.

src/Rector/Traits/RoleRectorTrait.php parses `config/packages/security.yaml` at analysis time and exposes `getParentRole(string)` for navigating the role hierarchy.

#### Rector attribute

src/Rector/Attribute/RectorIgnoreControllerRoleTest.php is a plain PHP attribute (`#[Attribute]`). Attaching it to a controller class tells `TestControllerHasRolesTestsRector` to skip that class.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- wexample/symfony-helpers: >=6.0.0
- wexample/symfony-testing: >=1.0.86

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
