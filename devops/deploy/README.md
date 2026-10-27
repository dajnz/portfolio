# Deployment runbook

Both profiles deploy the same Swarm stack (`portfolio`) from a shallow clone of
`portfolio_git_repo` and build the images on the server. One profile per VM at a
time — the stack name, ports, and `/opt/portfolio` are shared.

| Profile | Entry point | Env file | TLS |
|---|---|---|---|
| `production` | `make deploy GIT_REF=…` | `/opt/portfolio/.env.prod` | certbot service + `certbot_certs` volume; initial certificate issued manually once |
| `vm-test` | `make deploy-test GIT_REF=…` | `/opt/portfolio/.env.prod.test` | operator-provided files under `/opt/portfolio/tls/live/<DOMAIN>/`; certbot scaled to zero |

## Shared prerequisites

- `ansible-playbook` on the control machine.
- A running dev container (`make up-localhost`): the init commands generate the
  secrets with PHP and age from it.
- `portfolio_git_repo` must be reachable **from the target** (the clone runs
  there). Use the HTTPS URL for the public repository — no credentials are
  needed on the target. For a private repository, install an SSH deploy key on
  the target and use the SSH URL instead; never embed a token in the URL.
- Ubuntu VM with **Docker Engine 23+ installed and running**. The playbook
  installs only git, gettext-base, age, curl, and openssl; it does **not**
  install Docker and stops with a clear error if Docker is missing or older.
- A sudo-capable SSH user, with the SSH host key trusted on the control
  machine. `make deploy`, `make deploy-test`, `make rollback`, and `make
  rollback-test` load the target key from `deploy_server_ssh_key_path` into
  ssh-agent first, prompting for its passphrase when needed, then prompt once
  for the sudo password (`ASK_BECOME=0` for a passwordless-sudo user). Start
  an ssh-agent/keychain before deploying; the loader skips `ssh-add` when the
  key is already loaded.
- The first deploy checks the profile-specific local `.env.prod[.test].age`
  exists and prompts for/validates the age private key at the start of the
  playbook (before the clone and build), aborting on a failed decrypt; the
  server env must end up root-owned `0600`. Set `AGE_PRIVATE_KEY` in the
  environment to skip the prompt for non-interactive runs.
- The playbook ensures the Docker daemon trusts the local HTTP registry: when
  `127.0.0.1:5000` is absent it merges it into `/etc/docker/daemon.json`
  (preserving other keys) and live-reloads the daemon — no container restart.
  If a full restart is ever required, the deploy stops with a maintenance-window
  message instead of restarting a live host.
- The playbook creates `/opt/registry/data` and runs the local registry as a
  container bound to `127.0.0.1:5000` when missing. It waits for `/v2/` to
  answer; the registry is not reachable from other hosts.
- After convergence the deploy provisions the RustFS `portfolio`/`backups`
  buckets, the public portfolio policy, and the scoped backups user with the
  official `rustfs/rc` client. In dev, `make rustfs-buckets` does the same
  against the running dev stack.
- The first successful deploy creates the admin account and prints the
  generated password once. Defaults are `admin` / `admin@<DOMAIN>`; set
  `ADMIN_LOGIN` / `ADMIN_EMAIL` in the environment (for example
  `ADMIN_EMAIL=you@example.com make deploy-test …`) and change the credentials
  later in the admin profile.
- Ports 80/443 reachable from the client (bridged networking, or forwarded
  ports when the VM is behind NAT). A stable VM IP avoids Swarm advertise
  surprises — pass `--advertise-addr <vm-ip>` if `docker swarm init` picks the
  wrong interface. The registry binds loopback only (`127.0.0.1:5000`), so it
  needs no firewall rule; the playbook does not manage the firewall.

### Initialization

Prepare a target with one command per profile:

```bash
make deploy-init        # production
make deploy-test-init   # vm-test
```

Each command copies the matching `devops/deploy/host_vars/portfolio_{prod,vm}.yml`
(gitignored, never overwritten) and creates, validates, and encrypts the profile
env: `.env.prod[.test].local` → `.env.prod[.test].age`. On the first run the env
is created with `CHANGE_ME` placeholders and the command stops at the gate
listing the keys to fill; edit the printed host_vars file and those env keys,
then re-run the same command. Later runs only fill missing keys and re-encrypt;
existing values are never overwritten.

`hosts.yml` defines the `production` (`portfolio_prod`) and `vm_test`
(`portfolio_vm`) groups; `group_vars/all.yml` maps `deploy_server_*` to
Ansible's connection settings. `make deploy` / `make deploy-test` select the
group, and `make rollback` / `make rollback-test` do the same for rollbacks.
The deploy preflight
checks the `deploy_server_*` values and that the SSH key file exists before
connecting; missing values fail with a pointer to the matching init target.

## Production

```bash
make deploy-init   # creates host_vars/portfolio_prod.yml + .env.prod.local
# edit deploy_server_ip, deploy_server_user, deploy_server_ssh_key_path,
# portfolio_git_repo, DOMAIN, LETSENCRYPT_EMAIL, GITHUB_*, MAIL_*
make deploy-init   # validates + encrypts to .env.prod.age
make deploy GIT_REF=<tag-or-commit>
# first deploy prompts for the age private key; set AGE_PRIVATE_KEY=… to skip
# the first successful deploy prints the generated admin password once
```

After the first deploy, obtain the certificate once as described in
`docs/detailed-spec/devops.md` (production setup flow), then restart nginx with
`docker service update --force portfolio_nginx`.

## vm-test

### 1. Certificates

Generate a throwaway CA and a leaf with a SAN for the test hostname (`.test`
avoids mDNS collisions; do not reuse a production hostname — HSTS is applied):

```bash
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout ca.key -out ca.crt -subj "/CN=portfolio VM test CA"

openssl req -newkey rsa:2048 -sha256 -nodes \
  -keyout portfolio.test.key -out portfolio.test.csr \
  -subj "/CN=portfolio.test"

printf '%s\n' \
  'subjectAltName=DNS:portfolio.test' \
  'basicConstraints=CA:FALSE' \
  'keyUsage=digitalSignature,keyEncipherment' \
  'extendedKeyUsage=serverAuth' > portfolio.test.ext

openssl x509 -req -sha256 -days 825 \
  -in portfolio.test.csr -CA ca.crt -CAkey ca.key -CAcreateserial \
  -extfile portfolio.test.ext -out portfolio.test.crt

cp portfolio.test.crt fullchain.pem
cp portfolio.test.key privkey.pem
```

Place them on the VM at the strict path asserted by the playbook:

```bash
sudo install -d -m 755 /opt/portfolio/tls/live/portfolio.test
sudo install -m 644 fullchain.pem /opt/portfolio/tls/live/portfolio.test/fullchain.pem
sudo install -m 600 privkey.pem   /opt/portfolio/tls/live/portfolio.test/privkey.pem
```

Trust `ca.crt` on the client (browser/OS) and point the hostname at the VM:

```
192.0.2.10 portfolio.test
```

### 2. Target config and environment

```bash
make deploy-test-init
# edit devops/deploy/host_vars/portfolio_vm.yml (deploy_server_*,
# portfolio_git_repo) and .env.prod.test.local: DOMAIN=portfolio.test
make deploy-test-init   # derives APP_URL/GITHUB_REDIRECT_URI and encrypts
```

The test profile pre-fills dummy GitHub/mail values and logs mail locally;
`DOMAIN` stays gated. The first run creates both files and stops at the gate —
set `DOMAIN`, then re-run to derive the URLs and encrypt.

### 3. Deploy and verify

```bash
make deploy-test GIT_REF=<branch-or-tag>
# first deploy prompts for the age private key; set AGE_PRIVATE_KEY=… to skip
# the first successful deploy prints the generated admin password once

curl --cacert ca.crt https://portfolio.test/up
curl --cacert ca.crt https://portfolio.test/
curl -sI --cacert ca.crt https://portfolio.test/images/logo-full-light.svg
# on the VM:
docker service ls --filter name=portfolio_
docker exec "$(docker ps -q -f name=portfolio_app | head -n 1)" php artisan config:show database
```

Nginx enables the HTTPS block when the two files exist at container start. To
replace a certificate later, overwrite the files and run
`docker service update --force portfolio_nginx`.

### 4. Failure and iteration notes

- Missing/older Docker stops the preflight before the clone or any build —
  install Docker Engine 23+ and re-run. The playbook never installs it.
- The deploy prompts once for the sudo password (plus the SSH key passphrase
  if the key is not in `ssh-agent`); `ASK_BECOME=0` skips the sudo prompt on
  passwordless-sudo targets. If the target key passphrase prompt fails with
  `no ssh-agent is running`, start an agent/keychain and re-run.
- Removing `/opt/portfolio/tls/live/<DOMAIN>/` makes `make deploy-test` fail at
  preflight 0e, before any clone or build.
- RustFS provisioning runs after convergence; a failure stops the deploy before
  the env hash is recorded. It recreates the backups user on every run (RustFS
  does not support policy detach), so avoid running a backup job during
  setup/deploy.
- The manifest tags are the rebuild trigger: re-deploying the same ref changes
  nothing. Bump `devops/deploy/manifest.yml` (or change the env file) between
  iterations of a test branch.
- Missing or incomplete `devops/deploy/host_vars/<host>.yml` (for example no
  `deploy_server_*` values) or a missing SSH key file fails in the control-side
  preflight before any connection — run `make deploy-init` (or
  `make deploy-test-init`) and edit the created file.
- A missing profile-specific local `.env.prod[.test].age` or an invalid/empty
  age key fails the first deploy in task 0d before the stack is deployed.
- The admin bootstrap invokes `admin:create --generate`; the running app image
  must include that flag (any image built from this revision). If the manifest
  tag is unchanged, the old image is still running and the admin task fails —
  bump the manifest and redeploy. If the credentials are lost, run
  `admin:reset-password` in an app container.
- Upgrading a host that still runs the old `registry` Swarm service: remove it
  first (`docker service rm registry`); the images remain in
  `/opt/registry/data`. A clean host needs no action.
- `make rollback` rolls back the production group, `make rollback-test` the
  VM. Both use the matching host_vars.
- The vm-test env path, hash file, and age artifact are distinct from
  production, so a VM can switch profiles without reusing the wrong env.
