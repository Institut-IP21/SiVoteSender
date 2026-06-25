# Security Policy

SiVote runs elections; we welcome responsible disclosure.

## Reporting a vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Email **security@ip21.si**  with a description, repro
steps, the affected version/commit, and any suggested fix. You'll get an acknowledgement within
**48 hours** and a status update within **7 days**. Good-faith researchers get safe harbor and credit.

## Supported versions

| Version | Status |
| --- | --- |
| `master` | Actively maintained |
| Tagged releases | Best-effort, 12 months |
| Pre-release / integration branches | Not supported |

## Sender-specific notes

- The **SNS webhook** (`POST /sns/webhook`) is authenticated by AWS signature verification
  (`VerifySnsMessage`), not the API token. In production, set `AWS_SNS_TOPIC_ARNS` to allowlist your
  topic(s) — without it the middleware accepts any validly-signed SNS message.
- The API uses a shared `API_TOKEN_LIST` + an `Owner` header for tenant scoping; per-tenant tokens are
  a planned hardening (see the platform security model in the Engine repo).
- Voter PII (emails, names) lives here. Protect the database and `APP_KEY`; serve over HTTPS only.

See the platform-wide
[Security & Secrecy Model](https://github.com/Institut-IP21/SiVoteEngine/blob/master/docs/SECURITY_MODEL.md)
for the full threat model and guarantees.
