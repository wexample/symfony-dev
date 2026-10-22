## Architecture

The bundle is built from three independent layers: a **DependencyInjection** layer that reads configuration and wires services, a **Command** layer that automates the development environment, and a **demonstration data** layer that empties the database and fills it again. Each layer is self-contained and does not call into the others.

### Entry point

src/WexampleSymfonyDevBundle.php is an empty subclass of Symfony's `Bundle`. Symfony discovers it, which triggers src/DependencyInjection/WexampleSymfonyDevExtension.php.

### DependencyInjection

src/DependencyInjection/WexampleSymfonyDevExtension.php calls `loadConfig()` (from the parent `AbstractWexampleSymfonyExtension`) to import src/Resources/config/services.yaml, then processes the user-supplied configuration and stores the result as container parameters:

- `wexample_symfony_dev.vendor_dev_paths` — glob patterns pointing to local PHP package directories.
- `wexample_symfony_dev.js_dev_packages` — glob patterns pointing to local JS package directories.
- `wexample_symfony_dev.setup_hooks` — extra Symfony commands to run during `dev:setup`.

src/DependencyInjection/Configuration.php defines the tree. All three keys are optional arrays of strings (or, for `setup_hooks`, arrays of `{command, args}` maps).

src/Resources/config/services.yaml autowires every class under `Command/`, `Controller/` and `Service/` so no manual service definition is required.

The extension also registers `SeederInterface` for autoconfiguration, so an application's seeder is found by its interface alone, with nothing to declare.

### Command layer

The setup and password commands extend src/Command/AbstractDevCommand.php, which:

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

#### dev:seed

src/Command/SeedCommand.php extends `AbstractBundleCommand` directly — it needs neither the kernel nor the dev vendor paths. It refuses to run when the application declares no seeder, then refuses again without `--force`, since the first thing it does is empty the database. `--seed` (default `2026`) is the number the dataset is drawn from. What was written is listed back, by name.

### Demonstration data

The content belongs to the application, the mechanism to this bundle. An application declares one class implementing src/Interface/SeederInterface.php — `load()`, returning what it wrote as `['patients' => 53]` — and nothing else: the interface is registered for autoconfiguration, so the service is found by its type.

src/Service/SeedService.php is the mechanism. `seed(int $seed)` seeds `mt_rand()`, empties the database, runs every tagged seeder, flushes once, and returns the merged report. Seeding the random source before the seeders run is what makes the dataset reproducible: the same seed gives the same rows, so an acceptance scenario can name the rows it walks through. `hasSeeders()` answers whether the application declared anything, which the command and the menu entry both ask before offering to reload nothing.

src/Service/DatabaseResetService.php empties every table, `doctrine_migration_versions` left alone — truncating it would have the application believe no migration ever ran. It takes the DBAL `Connection` and nothing more, so a test can hand it one directly. PostgreSQL gets a single `TRUNCATE … CASCADE`; MySQL and MariaDB get one `TRUNCATE TABLE` per table with `FOREIGN_KEY_CHECKS` lifted around them, since the order the tables come in is nobody's choice; anything else, SQLite among them, gets a `DELETE FROM` per table.

src/Controller/SeedController.php is the route the development menu posts to: `POST /_dev/seed`, named `dev_seed`, declared `env: ['dev', 'test']`. It is reached by a program and read by nobody, hence the `/_dev` path. The sign-in is asked for with `isGranted()` and not with `#[IsGranted]`: without the security bundle the attribute is read by nobody, where `isGranted()` throws — a route emptying a database fails closed. It answers `404` when the application declares no seeder, so the database is never emptied for nothing. The CSRF token is checked next, then the visitor is sent to `/`, signed out with the accounts that were replaced. An application loading its routes with `routing.controllers` gets it with every controller service; another imports src/Resources/config/routes.yaml.

The entry of the development menu posting to that route is not here: it is front-end, and lives in `symfony-dev-ds`, which depends on `symfony-design-system`. This package depends on no front-end package — `symfony-loader` depends on it, so the other way round would be a cycle.

### Service

src/Service/JsDevPackagesResolver.php is consumed by `SetupNodeCommand`. It reads `wexample_symfony_dev.js_dev_packages`, expands any glob patterns, opens the `package.json` of each matching directory, and returns a map of `name → absolute path`. It is the single place that translates glob configuration into concrete filesystem paths for the Node tooling.

### Helper

src/Helper/DevHelper.php holds the single constant `VENDOR_DIR_NAME = 'vendor'`.
