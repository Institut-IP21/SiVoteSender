# CLAUDE.md — web_sender (SiVote Sender)

Manages **lists of voters, voter verification, and sending ballot-invitation emails** (SNS/SES). Part of the [E-Voting superproject](../CLAUDE.md); pairs with `web_engine`.

## Stack

- **Laravel 9** (target: **12** — see superproject CLAUDE.md), PHP `^8.0` (dev image: 8.4)
- Laravel **Horizon** 5.4 (Redis queues, via `predis`)
- Dev: PHPUnit 9, Paratest 6, Collision 6, `spatie/laravel-ignition` 1
- `fruitcake/laravel-cors` 2 (removable — CORS is built into the framework)
- `minimum-stability: dev` (tighten to `stable` during upgrade)

## Testing

```bash
php artisan test          # Unit + Feature suites
./vendor/bin/phpunit
./vendor/bin/paratest     # parallel
```

## Domain CLI (custom artisan commands)

`evote:cache`, and `TestEmailCommand` (sender email smoke test) — see `app/Console/Commands`.

## ⚠️ Stashed upgrade WIP

A **partial Laravel 9 → 10 upgrade** is parked in `stash@{0}` (composer.json/lock bumped to L10, plus edits to `VerificationApiController`, `Http/Kernel`, `Verification`/`Voter` models, the `Verification` service, feature tests, and a `phpunit.xml` migration). Inspect with `git stash show -u stash@{0}`; apply with `git stash apply stash@{0}` to use it as the 9→10 stepping stone. `stash@{1}` is unrelated 2021 cruft.

## Upgrade watch-list (9 → 12)

- Apply/reconcile the stashed 9→10 work first, then continue 10 → 11 → 12.
- **Horizon** major bumps track Laravel majors (5 → 5.x/6) — verify Redis/queue config each step.
- Remove `fruitcake/laravel-cors`; use `config/cors.php`.
- Collision 6 → 7/8, PHPUnit 9 → 10/11, Paratest 6 → 7, Ignition 1 → 2.
- `minimum-stability` → `stable`.
