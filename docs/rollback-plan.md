# Rollback Plan

## Rollback Trigger Conditions

- sustained 5xx increase after deploy
- failed smoke checks
- queue worker crash loop after release
- broken authentication or tenant isolation
- migration performance degradation that makes the system unsafe to keep live

## Code Rollback

Use the previous release symlink:

```bash
APP_ROOT=/var/www/fms-app bash infra/production/scripts/rollback-release.sh
```

Or target a specific release:

```bash
APP_ROOT=/var/www/fms-app TARGET_RELEASE=<release-id> bash infra/production/scripts/rollback-release.sh
```

## Database Rollback Rules

- do not assume `migrate:rollback` is safe in production
- treat destructive schema changes as forward-only unless they were explicitly designed and tested to reverse cleanly
- restore from backup when data correctness is at risk

## Rollback Procedure

1. Stop the release and declare rollback in the incident or change channel.
2. Repoint `current` to the previous release.
3. Restart queue workers.
4. Re-run `/up`, `/readyz`, SPA load, and login smoke checks.
5. If data corruption or destructive migration impact is present, start the database restore procedure.

## Retention

- keep multiple recent release directories on disk
- keep the previous known-good release until the next release is stable
