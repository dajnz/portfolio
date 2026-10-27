# ADR-0042: Ansible-native deployment target configuration and AGE prompt

- **Status:** Accepted
- **Date:** 2026-10-26
- **Source:** Deployment target configuration review
- **Supersedes:** —

## Context

Deployment target settings were supplied through `PROD_HOST`, `PROD_USER`,
`PROD_SSH_KEY`, and `GIT_REPO` environment variables, and the first deploy read
the AGE private key from `AGE_PRIVATE_KEY`. That required exporting four
non-secret values on every run and leaked Ansible connection terminology into
the operator's workflow. The repository is public, so the target host and user
should not be committed, and the SSH key file must stay outside git.

## Decision

- `devops/deploy/hosts.yml` defines two groups: `production` (host
  `portfolio_prod`) and `vm_test` (host `portfolio_vm`). `make deploy` selects
  `production`; `make deploy-test` selects `vm_test`; `make rollback` selects
  `production` and `make rollback-test` selects `vm_test`.
- Target values live in gitignored `devops/deploy/host_vars/<host>.yml` files
  with user-facing names: `deploy_server_ip`, `deploy_server_user`,
  `deploy_server_ssh_key_path`, and `portfolio_git_repo`. Committed
  `*.example.yml` files show the shape; `make deploy-init` (production) and
  `make deploy-test-init` (vm-test) copy the matching example without
  overwriting, create/validate/encrypt the profile env, and print the next
  steps.
- A shared `devops/deploy/tasks/assert_target_config.yml` is imported by both
  `deploy.yml` and `rollback.yml`. It runs on the control machine before any
  task connects to the target: it asserts the three `deploy_server_*` values
  and checks that the configured SSH key file exists, failing with a pointer to
  the matching init target.
- `devops/deploy/group_vars/all.yml` is committed and maps those names to the
  Ansible connection settings (`ansible_host`, `ansible_user`,
  `ansible_ssh_private_key_file`), keeping the operator-edited files free of
  Ansible-specific terminology.
- `portfolio_git_repo` defaults to the public HTTPS clone URL
  (`https://github.com/dajnz/portfolio.git`), which needs no credentials on the
  target. A private repository uses an SSH deploy key on the target and the SSH
  URL; tokens are never embedded in the URL. `GIT_REPO` remains an environment
  fallback for automation.
- `deploy_profile` is derived from `deploy_target` (`vm_test` → `vm-test`,
  otherwise `production`); the Makefile no longer passes it.
- The first deploy checks the profile-specific local `.env.prod[.test].age`
  exists and prompts for the AGE private key with `ansible.builtin.pause`
  (`echo: false`, `no_log`) only when the profile env file does not exist yet
  and `AGE_PRIVATE_KEY` is unset. A failed `age -d` aborts the deploy; the
  existing transient-key/decrypt/shred flow is unchanged. `AGE_PRIVATE_KEY`
  remains the non-interactive fallback. No plaintext key is stored in the
  repository.

## Consequences

- Operators run `make deploy-init` (production) or `make deploy-test-init`
  (vm-test) once per target, edit the printed host_vars file and the env keys
  reported by the gate, and re-run the same command; no `PROD_*`/`GIT_REPO`
  exports are needed. SSH key files stay outside the repository.
- The init commands require the dev container (`make up-localhost`) because
  `secrets/init.sh` uses its PHP and age; they fail with a clear message when it
  is not running.
- Missing or incomplete target vars or a missing SSH key file fail in the
  control-side preflight before any connection; a missing profile age file or
  invalid age key fails the first deploy in 0d.
- The committed inventory and group_vars define the structure; the actual
  target values stay local. A missing or incomplete host_vars file fails before
  the clone.
- The first deploy asks for the age key interactively; automated or repeated
  runs use `AGE_PRIVATE_KEY` or need no key at all once the profile env exists.
- Rollback selects the target group through its own target (`make rollback` /
  `make rollback-test`) instead of a separate set of environment variables.
