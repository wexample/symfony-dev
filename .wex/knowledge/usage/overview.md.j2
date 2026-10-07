`wexample/symfony-dev` is a Symfony bundle of development tools, registered in the `dev` and `test` environments only. It ships three things.

**The demonstration data.** An application declares one class implementing `SeederInterface` — what its rows are — and gets the rest: `dev:seed` empties the database and fills it again, reproducibly (the same `--seed` gives the same rows, so acceptance scenarios can name what they walk through), `POST /_dev/seed` does the same from the browser, and `wexample/symfony-dev-ds` adds « reload the demonstration data » to the development menu of `symfony-design-system`.

**Different data for the tests.** A demonstration and a test suite rarely want the same rows. Give each
its own seeder and restrict it with Symfony's `#[When]`: a class marked `#[When(env: 'dev')]` loads the
demonstration, one marked `#[When(env: 'test')]` loads what the tests rely on, and `SeedService` only ever
sees the seeders of the environment it runs in. A test fills its database with the same call the
command makes — `static::getContainer()->get(SeedService::class)->seed()` — which empties the database
first, so it is a whole reset rather than an addition.

**The development environment.** `dev:setup` installs the suite's local PHP and JS packages as `vendor/` symlinks without dirtying `composer.lock`, and `dev:change-user-password` / `dev:change-all-users-password` rewrite password hashes.

**The coding conventions.** A collection of [Rector](https://getrector.org/) rules enforcing them automatically: controllers must be `final` and carry a global `#[Route]` name prefix, route names on methods must match their PHP method names, form and entity classes must carry the right class suffixes, entity `#[Column]` types must reference `Types::*` constants, and role-based test files must exist for every controller. They are referenced from the consuming project's `rector.php`, so the conventions are applied by a code-mod tool rather than enforced by hand in code review.
