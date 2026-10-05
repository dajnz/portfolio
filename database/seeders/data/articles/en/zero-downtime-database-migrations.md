Every schema change that runs during a deploy competes with live traffic for the same table. The expand–migrate–contract pattern removes that race.

## Why a single migration is dangerous

`ALTER TABLE ... ADD COLUMN ... NOT NULL DEFAULT` rewrites the table and takes a lock. On a large table that is an outage, not a migration.

| Change | Safe directly? |
| --- | --- |
| Add nullable column | yes |
| Add index concurrently | yes |
| Rename column | no |
| Change type | no |

> [!WARNING]
> A migration that takes an exclusive lock on a hot table stops the whole application, not just the deploy.

## Expand

Add the new column as nullable and start writing to both old and new fields.

```sql
ALTER TABLE projects ADD COLUMN slug_new varchar(255);
```

## Migrate

Backfill in batches so the transaction log does not explode.

```sql
UPDATE projects SET slug_new = slug
WHERE id IN (SELECT id FROM projects WHERE slug_new IS NULL LIMIT 1000);
```

## Contract

Once every reader uses the new column, drop the old one in a later release.

> [!TIP]
> Ship expand and contract as separate deploys. The gap between them is your rollback window.

## Rollbacks

Because each step is backward-compatible, rolling back the application is enough — no schema rollback needed mid-incident.
