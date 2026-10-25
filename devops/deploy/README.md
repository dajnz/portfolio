# Deployment runbook

Both profiles deploy the same Swarm stack (`portfolio`) from a shallow clone of
`GIT_REPO` and build the images on the server. One profile per VM at a time —
the stack name, ports, and `/opt/portfolio` are shared.

| Profile | Entry point | Env file | TLS |
|---|---|---|---|
| `production` | `make deploy GIT_REF=…` | `/opt/portfolio/.env.prod` | certbot service + `certbot_certs` volume; initial certificate issued manually once |
| `vm-test` | `make deploy-test GIT_REF=…` | `/opt/portfolio/.env.prod.test` | operator-provided files under `/opt/portfolio/tls/live/<DOMAIN>/`; certbot scaled to zero |

## Shared prerequisites

- `ansible-playbook` on the control machine; `PROD_HOST`, `PROD_USER`,
  `PROD_SSH_KEY` exported for `devops/deploy/hosts.yml`.
- `GIT_REPO` must be reachable **from the VM** (the clone runs there), e.g. the
  pushed branch/tag on GitHub or a bare repository the VM can reach over SSH.
- Ubuntu VM with **Docker Engine 23+ installed and running**. The playbook
  installs only git, gettext-base, age, curl, and openssl; it does **not**
  install Docker and stops with a clear error if Docker is missing or older.
- A sudo-capable SSH user, with the SSH host key trusted on the control
  machine. `make deploy`, `make deploy-test`, and `make rollback` prompt once
  for the sudo password (`ASK_BECOME=0` for a passwordless-sudo user). If
  `PROD_SSH_KEY` is passphrase-protected, ssh prompts for it unless the key is
  loaded in `ssh-agent`.
- The playbook ensures the Docker daemon trusts the local HTTP registry: when
  `127.0.0.1:5000` is absent it merges it into `/etc/docker/daemon.json`
  (preserving other keys) and live-reloads the daemon — no container restart.
  If a full restart is ever required, the deploy stops with a maintenance-window
  message instead of restarting a live host.
- Ports 80/443 reachable from the client (bridged networking, or forwarded
  ports when the VM is behind NAT). A stable VM IP avoids Swarm advertise
  surprises — pass `--advertise-addr <vm-ip>` if `docker swarm init` picks the
  wrong interface. The registry binds `0.0.0.0:5000` in host mode; restrict it
  with the host firewall (e.g. `ufw deny 5000/tcp`). The playbook does not
  manage the firewall.

## Production

```bash
make secrets-init          # fill DOMAIN, LETSENCRYPT_EMAIL, GITHUB_*, MAIL_* in .env.prod.local
make secrets-init          # validate + encrypt to .env.prod.age
export AGE_PRIVATE_KEY=…   # first deploy only: decrypts the env on the server
make deploy GIT_REF=<tag-or-commit>
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

### 2. Environment

```bash
make secrets-init-test
# edit .env.prod.test.local: DOMAIN=portfolio.test
make secrets-init-test   # derives APP_URL/GITHUB_REDIRECT_URI and encrypts
```

The test profile pre-fills dummy GitHub/mail values and logs mail locally;
`DOMAIN` stays gated. The first run creates the file and stops at the gate —
set `DOMAIN`, then re-run to derive the URLs and encrypt.

### 3. Deploy and verify

```bash
export PROD_HOST=192.0.2.10 PROD_USER=deploy PROD_SSH_KEY=~/.ssh/vm_test
export GIT_REPO=<url reachable from the VM>
export AGE_PRIVATE_KEY=…   # first deploy only
make deploy-test GIT_REF=<branch-or-tag>

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
  passwordless-sudo targets.
- Removing `/opt/portfolio/tls/live/<DOMAIN>/` makes `make deploy-test` fail at
  preflight 0e, before any clone or build.
- The manifest tags are the rebuild trigger: re-deploying the same ref changes
  nothing. Bump `devops/deploy/manifest.yml` (or change the env file) between
  iterations of a test branch.
- `make rollback` (same `PROD_*` variables) rolls the stack back; it does not
  need the profile.
- The vm-test env path, hash file, and age artifact are distinct from
  production, so a VM can switch profiles without reusing the wrong env.
