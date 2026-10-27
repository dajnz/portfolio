#!/usr/bin/env bash
# assert-schema-compat.sh <release_dir> <env_file> <allow_downgrade>
#
# Fails when the release ships fewer migrations than the database has already
# applied, i.e. when deploying it would run old code against a newer schema
# (migrations are forward-only and `migrate --force` never reverts). The
# applied set comes from the `migrations` table, the release set from
# <release_dir>/database/migrations, so the check needs no git history and
# allows older refs that still contain every applied migration.
# <allow_downgrade> = 1 warns and continues instead of failing.
set -euo pipefail

if [ "$#" -ne 3 ]; then
    echo "usage: assert-schema-compat.sh <release_dir> <env_file> <allow_downgrade>" >&2
    exit 2
fi

release_dir="$1"
env_file="$2"
allow_downgrade="$3"

case "$allow_downgrade" in
    0|1) ;;
    *)
        echo "allow_downgrade must be 0 or 1 (got '$allow_downgrade')" >&2
        exit 2
        ;;
esac

set -a
. "$env_file"
set +a

psql_db() {
    docker exec "$1" psql -U "$DB_USERNAME" -d "$DB_DATABASE" -tAc "$2"
}

db_container="$(docker ps -q -f name=portfolio_db | head -n 1)"

if [ -z "$db_container" ]; then
    echo "database container is not running — schema compatibility check skipped"
    exit 0
fi

if [ "$(psql_db "$db_container" "select to_regclass('public.migrations') is not null")" != "t" ]; then
    echo "no migrations table yet — schema compatibility check skipped"
    exit 0
fi

applied="$(psql_db "$db_container" 'select migration from migrations' | sed '/^$/d' | sort)"

if [ -z "$applied" ]; then
    echo "no migrations applied yet — schema compatibility check passed"
    exit 0
fi

shipped=""

if [ -d "$release_dir/database/migrations" ]; then
    shipped="$(
        find "$release_dir/database/migrations" -maxdepth 1 -name '*.php' -printf '%f\n' \
            | sed 's/\.php$//' \
            | sort
    )"
fi
missing="$(comm -23 <(printf '%s\n' "$applied") <(printf '%s\n' "$shipped"))"

if [ -z "$missing" ]; then
    echo "schema compatibility check passed"
    exit 0
fi

echo "the release predates migrations the database already applied:" >&2
printf '%s\n' "$missing" | sed 's/^/  - /' >&2

if [ "$allow_downgrade" = "1" ]; then
    echo "WARNING: continuing because ALLOW_SCHEMA_DOWNGRADE=1 — the old code may fail against the current schema" >&2
    exit 0
fi

cat >&2 <<EOF
ERROR: deploying this ref would run old code against a newer schema.
Deploy a ref that contains the migrations above, or take a database backup first:
  docker exec $db_container pg_dumpall -U $DB_USERNAME \\
    > "$(dirname "$env_file")/pre-downgrade-$(date +%Y%m%d%H%M%S).sql"
then re-run the deploy with ALLOW_SCHEMA_DOWNGRADE=1.
EOF

exit 1
