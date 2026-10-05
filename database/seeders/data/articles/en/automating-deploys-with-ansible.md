A small team does not need a platform engineering department to get repeatable deploys. Ansible turns the release into a scripted, reviewable sequence.

## Idempotent by design

Every task describes the desired state. Running the playbook twice must be as safe as running it once — that is what makes deployments boring.

```yaml
- name: Upload the release archive
  ansible.builtin.copy:
    src: "./artifacts/app.tar.gz"
    dest: "{{ release_root }}/releases/{{ git_ref }}/"
```

> [!IMPORTANT]
> If a task only works on a clean host, it is not automation — it is a one-time script.

## Build once, promote many

Build the image once, tag it with the commit, and promote the same artifact through environments. Rebuilding per environment invites drift.

| Stage | Action |
| --- | --- |
| Build | compile assets, build image |
| Deploy | pull, migrate, restart |
| Verify | health check, smoke test |

## Health checks gate the rollout

A deploy that cannot fail loudly is not safe. Wait for health, and roll back automatically when it never turns green.

> [!TIP]
> Run migrations as a separate, explicit step so a failed deploy does not leave half-applied schema changes.

## Keep secrets out of the playbook

Reference encrypted files or a secret manager. Plain-text secrets in a repository survive long after the person who added them.

## The payoff

A new contributor can ship on their first day, because the process is written down in a form the machine enforces.
