# CLAUDE.md — web_sender (SiVote Sender)

Manages **lists of voters, voter verification, and sending ballot-invitation emails** (SNS/SES). Part of the [E-Voting superproject](../CLAUDE.md); pairs with `web_engine`.

## Stack

- **Laravel 12**, PHP `^8.2` (dev image: 8.4)
- Laravel **Horizon** 5.4 (Redis queues, via `predis`)
- CORS is the framework's native `config/cors.php` (the old `fruitcake/laravel-cors` is gone).
- Dev: PHPUnit **11**, Paratest **7**, Collision **8**, `spatie/laravel-ignition` **2**, `larastan/larastan` **3** (phpstan 2)
- `minimum-stability: stable`

## Testing & static analysis

```bash
php artisan test                                          # Unit + Feature suites
./vendor/bin/paratest                                     # parallel
php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress
```

PHPStan is at **level 8**, clean (no baseline). `phpstan.neon` uses `checkModelProperties`; framework scaffolding is excluded. After big edits, `vendor/bin/phpstan clear-result-cache`.

## Domain CLI (custom artisan commands)

`evote:cache`, and `TestEmailCommand` (sender email smoke test) — see `app/Console/Commands`. Mail is caught locally by **Mailhog** (UI http://localhost:8025).

## Notes / gotchas

Bugs fixed during the upgrade (surfaced by phpstan / review) — be aware these were real defects, not cosmetic:

- `VerificationController` had a `switch` fall-through that let cases bleed into one another.
- `SentMessage::scopeEmailOnly()` was filtering on `TYPE_SMS` instead of `TYPE_EMAIL` — i.e. the "email only" scope returned SMS.
- `config/auth.php` provider model corrected to `ApiUser`.

The old partial **9→10 stash** and any `feature/laravel-upgrade-12` stub branch are **superseded** by the completed work — ignore them. Upgrade work lives on `upgrade/laravel-12` (draft PR **SiVoteSender#4** → `master`).
