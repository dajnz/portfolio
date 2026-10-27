# ADR-0044: First-deploy admin bootstrap

- **Status:** Accepted
- **Date:** 2026-10-27
- **Source:** Deployment onboarding review
- **Supersedes:** —

## Context

A fresh deployment has no admin account: ADR-0029 keeps credentials out of
seeders and makes `admin:create` the only creation path. Operators previously
had to SSH into the target after the first deploy and run the command manually,
which added a separate step and hid the generated password from the deploy
output.

## Decision

- `admin:create` gains a `--generate` option. In non-interactive mode it allows
  `--login` and `--email` without `--password`; the command generates the
  password internally and prints the credentials box. The password never
  appears in `docker exec`/process arguments.
- The deploy playbook's final step runs
  `php artisan admin:create --login=<login> --email=<email> --generate
  --no-interaction` inside a running `portfolio_app` container.
- Defaults are login `admin` and email `admin@<DOMAIN>`. `ADMIN_LOGIN` and
  `ADMIN_EMAIL` in the environment override them.
- If an admin already exists, the command exits non-zero with
  `An admin account already exists`; the playbook treats that as a skip. It
  never uses `--force`.
- The step runs on every deploy and relies on the command's guard, so a failed
  first deploy can create the admin on a later successful attempt.
- Credentials are printed once to the deploy output; nothing is written to
  disk. Lost credentials are reset with `admin:reset-password`.
- Login, email, and password remain editable in the admin profile.

## Consequences

- The first successful deploy ends with usable admin credentials and no manual
  SSH step.
- The no-seeder invariant is preserved; `admin:create` remains the sole
  creation path.
- The generated password appears in the deploy terminal output. Operators must
  not pipe the deploy log to shared storage; the README states this.
- Password generation calls the HaveIBeenPwned API. If the target cannot reach
  it, the final step fails while the stack remains deployed; the operator can
  rerun the deploy or create the admin manually.
