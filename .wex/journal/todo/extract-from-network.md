# Finish the Rector/syntax extraction from network (+ workflow generator)

Opened: 2026-09-24
Updated: 2026-09-24
Author: agent:archeology

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

The 13 custom Rector rules and their bases were copied from network into `src/Rector/`, but 4 of them still import `App\…` classes and fatal outside the old app; no reusable rule set is shipped; the syntax console commands were not ported. Finish the job, then add the few rules/generators specified in issues but never written.

Knowledge: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/already-extracted-check.md.j2` (section "API, entity routing, Rector, syntax, search") and `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/base-bundle-sweep.md.j2`.
Issues: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/197.md` (suffixes), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/246.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/248.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/268.md` (workflow generator), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/296.md` (controllers must declare HTTP methods), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/293.md` (every form has an anonymous role test, keep with rector), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/258.md` (every command has a test).

## Steps

1. Fix the `App\` imports in `src/Rector/AbstractEntityManipulatorRector.php`, `EntityManipulatorHasTraitControllerRector.php`, `EntityManipulatorHasTraitCrudServiceRector.php`, `ControllerClassHasConstantsAsRoutesNamesRector.php` (`grep -rn "use App" src`). Replacements: symfony-helpers `AbstractController` (constants are `DEFAULT_ROUTE_NAME_*` now, not `PATH_TYPES`), symfony-helpers `EntitySyntaxService`; the EntityCrud concept is gone (repositories carry `save/createNew*`), so either drop `EntityManipulatorHasTraitCrudServiceRector` or retarget it to `Service/Entity/*EntityService`. Add one fixture test per rule (Rector's `AbstractRectorTestCase`).
2. Check the Rector version (`require-dev rector/rector 0.12.28`, `Rector\Core\…`): upgrade to the current major and adapt class names.
3. Ship a set: `config/sets/wexample.php` built from `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/rector.php` (8 core rules: AddLiteralSeparatorToNumber 1_000_000, ClosureToArrowFunction, FinalizePublicClassConstant, NullCoalescingOperator, SimplifyUselessVariable, StrStartsWith, StrEndsWith, IfToSpaceship; then the custom rules **in the order of that file**; the 4 TestController* rules as an opt-in second set). Document in README.
4. Port syntax commands as `AbstractDevCommand` subclasses: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Command/Syntax/AbstractSyntaxCommand.php`, `EntitySyntaxCommand.php` (creates missing `<E>EntityManipulatorTrait` for each entity, template `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/front/php/Entity/EntityManipulatorTrait.html.twig`), `RoleSyntaxCommand.php`. Use the `develop` tree (`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop/src/Command/Syntax/`) if it is already on package namespaces. Skip `ConfigSyntaxCommand` (calls a non-existent method).
5. New rule `TraitSuffixRector` (#197: traits end with `Trait`) as a subclass of the existing suffix base.
6. New rule for #296: a controller action route without `methods:` gets `methods: [GET]` (POST when the action handles a form). Fixture tests.
7. Workflow generator (#268): command `dev:workflow:generate <Entity>` reading an entity's status constants/enum (`HasStatusTrait` + `STATUS_*` or a backed enum) and writing/merging a `config/packages/workflow/<entity>.yaml` (places only, transitions left for the human; never overwrite existing keys). Tests on a fixture entity.

## Do not

- Do not port `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Service/Syntax/ConfigSyntaxService.php` (empty stub).
- Do not keep references to `VirtualEntityController`/`VariableEntityTypeControllerTrait` (old API).

## Acceptance

- `composer test` green with fixture tests for each rule; a consuming app can `->sets([WexampleSetList::DEFAULT])`.
