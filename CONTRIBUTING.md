# Contributing to SiVote Sender

Thanks for helping improve SiVote. This service handles voter data and election mail, so correctness,
privacy, and security come first.

## Developer Certificate of Origin (DCO)

Contributions are accepted under the [Developer Certificate of Origin](https://developercertificate.org/)
— a lightweight, sign-off-based affirmation, **no CLA or paperwork**. By signing off you certify you
wrote the change (or have the right to submit it) under the repository's [LICENSE](LICENSE). Sign every
commit:

```bash
git commit -s -m "Fix bounce-status mapping"
```

## Pull-request flow

1. Fork and branch from `master`.
2. Add a test for the change (new behaviour ⇒ test; bug ⇒ regression test).
3. Gates must pass:
   ```bash
   php artisan test
   php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress   # level 8, must stay clean
   ```
4. Sign off (`git commit -s`, DCO) and open a PR explaining the change.

## Ground rules

- **Don't weaken secrecy or tenant scoping.** The Sender must never expose how anyone voted, and voter
  lists are owner-scoped — keep the `Owner`/policy checks intact.
- **The SNS webhook is security-critical.** Don't bypass signature verification or the topic allowlist.
- Keep voter PII out of logs and error payloads.
- Vulnerabilities go to [SECURITY.md](SECURITY.md), not public issues.

By contributing you agree your work is licensed under the project's license (see [LICENSE](LICENSE)).
