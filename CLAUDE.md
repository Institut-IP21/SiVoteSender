# CLAUDE.md — web_sender (SiVote Sender)

Manages **lists of voters, voter verification, and sending ballot-invitation emails** (SNS/SES). Part of the [E-Voting superproject](../CLAUDE.md); pairs with `web_engine`.

## Stack

- **Laravel 13**, PHP `^8.3` (dev image: 8.4)
- **Database queues** — Horizon/Redis/`predis` removed; `.env.example` ships `QUEUE_CONNECTION=database`, cache `file`. Process jobs with `php artisan queue:work`.
- SNS/SES intake validated with `aws/aws-php-sns-message-validator` (part of our SNS-webhook-verification overlay; see Notes).
- CORS is the framework's native `config/cors.php` (the old `fruitcake/laravel-cors` is gone).
- Dev: PHPUnit **13**, Paratest **7**, Collision **8**, `spatie/laravel-ignition` **2**, `larastan/larastan` **3** (phpstan 2); `rector/rector` **2** + `barryvdh/laravel-ide-helper` **3**
- `minimum-stability: dev`

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
- Latent fatal in `VerificationApiController::list()` (an undefined `use ($owner)`) was fixed during integration.

**Security overlay (treat as core, pitch before changing):** SNS webhook verification (`VerifySnsMessage` + `routes/sns.php`, throttled and signature-verified *outside* `ApiAuth`, with an SSRF allowlist in `AmazonController`), authz/IDOR policies (`VoterPolicy` / `SentMessagePolicy` + `can:` guards), and scoped `removeVoters`.

## State / cutover

**As of the 2026-06-13 cutover this is `master`** — the integrated Laravel 13 work (DB queue, our security overlay). The old `upgrade/laravel-12` branch + PR **SiVoteSender#4**, the partial **9→10 stash**, and any `feature/laravel-upgrade-12` stub are merged/superseded — ignore them. Pre-cutover state is on tag `master-pre-integration-2026-06-13`. See the superproject `CLAUDE.md` for the full story.
