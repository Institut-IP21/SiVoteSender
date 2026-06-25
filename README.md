<div align="center">

# SiVote Sender

**Voter management & ballot delivery for the [SiVote](https://github.com/Institut-IP21) secret-ballot platform.**

Manages voter lists, sends ballot invitations and verification emails, and tracks delivery
(bounces/complaints) via AWS SNS. Pairs with
[SiVote Engine](https://github.com/Institut-IP21/SiVoteEngine), which holds the ballots and votes.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)

</div>

---

## Why a separate service

Ballot secrecy depends on the voter↔code mapping living **apart** from the votes. The Sender knows who
was invited and which single-use code each voter received; the Engine only ever sees encrypted votes
keyed by code. Neither side alone can link a person to how they voted. (Full model:
[Engine docs/SECURITY_MODEL.md](https://github.com/Institut-IP21/SiVoteEngine/blob/master/docs/SECURITY_MODEL.md).)

## What the sender does

- **Voter lists** — create, import (CSV/batch), update, scoped removal.
- **Invitations** — send ballot invites with each voter's single-use code (links to the Engine ballot).
  _(For Level 2/3, the electoral commission instead distributes invites from its own machine using
  [SiVoteHomeSender](https://github.com/Institut-IP21/SiVoteHomeSender), so the platform never learns
  the code↔voter mapping.)_
- **Verification** — optional pre-vote identity/verification email flow.
- **Delivery tracking** — AWS SNS webhook records bounces/complaints and maintains a global block list,
  so undeliverable addresses surface and aren't repeatedly mailed.
- **Results email** — sends per-ballot results emails using the Engine's templates and org branding.
- **Previews** — render the real invite / verification / results mailables to HTML for review.

## Requirements

- PHP **8.3+** (dev image 8.4), Composer
- MySQL **8+**
- **Database queue** (no Redis) — run `php artisan queue:work`
- AWS **SES** (sending) + **SNS** (delivery tracking) for production email

## Quick start (local)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan evote:cache
php artisan queue:work          # processes mail jobs (database queue)
```

Locally, [Mailhog](http://localhost:8025) catches outbound mail. For real delivery you need AWS (below).

## AWS SES & SNS

1. **SES** — verify your sending domain/address; note your send-rate quota.
2. **SNS** — create a topic for bounce/complaint notifications and subscribe the webhook
   `POST /sns/webhook` to it. The webhook is signature-verified (`VerifySnsMessage`) and rate-limited,
   **outside** the API-token auth — AWS authenticates by signature.
3. Set `AWS_SNS_TOPIC_ARNS` to your topic ARN(s) — an allowlist that prevents accepting notifications
   from untrusted topics. **Set this in production.**

For local SNS testing, expose the sender with ngrok and point an SNS subscription at it:

```bash
ngrok http sender.evote.local:80
```

## Configuration

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public URL of the sender |
| `API_TOKEN_LIST` | Comma-separated API tokens (must match the Engine's for a shared caller) |
| `DB_*` | MySQL connection |
| `MAIL_*` / `MAIL_FROM_ADDRESS` | SMTP / from-address |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | AWS credentials (SES/SNS) |
| `SES_MAX_SEND_RATE` | Outbound rate cap (msgs/sec) — match your SES quota |
| `AWS_SNS_TOPIC_ARNS` | Allowlisted SNS topic ARN(s) for the delivery webhook |
| `QUEUE_CONNECTION=database` | Mail runs on the DB queue |
| `MAIL_ALERTS_*`, `LOG_SLACK_WEBHOOK_URL` | Optional bounce/complaint alerting |

## API at a glance

All API routes require `Authorization: <token>` + `Owner: <team-uuid>` headers. The SNS webhook is the
exception (signature-verified instead).

```
GET/POST   /api/voterlist          POST /api/voterlist/{id}/voters
POST       /api/voterlist/{id}/send-invites
POST       /api/voterlist/{id}/send-results
GET/POST   /api/verification       GET /api/verification/{id}/start
POST       /api/ballot/invite-preview   POST /api/ballot/result-preview
POST       /sns/webhook            (AWS SNS; signature-verified)
```

The authoritative route list is in [`routes/api.php`](routes/api.php) and [`routes/sns.php`](routes/sns.php).
For the full self-hosted lifecycle (engine + sender via the `evote:*` artisan CLI) see the Engine's
[docs/SELF_HOSTING.md](https://github.com/Institut-IP21/SiVoteEngine/blob/master/docs/SELF_HOSTING.md).

## Testing & static analysis

```bash
php artisan test
./vendor/bin/paratest
php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress   # level 8, clean
```

## Contributing & security

[CONTRIBUTING.md](CONTRIBUTING.md) (DCO) · [SECURITY.md](SECURITY.md) (don't open public issues for
vulns) · [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## License

**MIT** — see [LICENSE](LICENSE). _eGlasovanje_ is a trademark of Institut-IP21; the hosted GUI
(`web_app`) is proprietary. Feedback & support: info@ip21.si
