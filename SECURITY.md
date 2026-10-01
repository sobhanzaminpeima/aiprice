# Security policy

## Reporting a vulnerability

Do not open a public GitHub issue for a suspected vulnerability or exposed credential. Report it privately to the project owner with reproduction steps, affected routes, expected impact, and any safe mitigation already applied.

## Supported version

Security fixes target the latest production release and the active development branch. Older snapshots and generated release archives are not supported.

## Production safeguards

- Keep `APP_DEBUG=false` and force HTTPS with secure session cookies.
- Store `.env`, SMTP, database, OpenAI, Stripe, and SSH credentials only in the deployment environment or a secrets manager.
- Replace all seeded passwords before production use. Seeded users are development fixtures, not production credentials.
- Grant staff the minimum required role and permission; reserve `super_admin` for recovery and privileged administration.
- Keep login, registration, password reset, contact, AI extraction, and webhook rate limits enabled.
- Require email verification for publishing and billing-sensitive actions.
- Rotate tokens after password resets or suspected compromise and review active personal access tokens.
- Verify Stripe webhook signatures and never accept client-reported payment success as authoritative.
- Run queue workers and scheduled cleanup so expired listings, subscriptions, reset tokens, and failed jobs are processed.
- Back up both the database and uploaded files before every release and test restoration periodically.
- Keep PHP, Composer, Node, npm, Laravel, Vue, and transitive dependencies patched.

## Repository hygiene

The `.gitignore` excludes environment files, release bundles, local databases, generated builds, dependencies, logs, and one-off deployment helpers. Before every push, inspect staged files with:

```bash
git diff --cached --stat
git diff --cached
```

If a secret is committed, removing it in a later commit is not enough: rotate it immediately and remove it from Git history where appropriate.
