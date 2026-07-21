# Configuration

NetRoom reads its settings from the environment, like any Laravel application.
The values below are the ones specific to NetRoom; everything else is standard
Laravel and Fortify.

## Environment

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_LOCALE` | `en` | Default interface language. Each user can override it in their profile. |
| `NETROOM_ALLOW_REGISTRATION` | `false` | Self-service sign-up. Off by default — accounts are created by an administrator, since the panel documents internal infrastructure. |
| `DB_CONNECTION` | `sqlite` | `pgsql` in production; `sqlite` is fine for a quick local try. |

The interface ships in Russian and English (`config/netroom.php`).

## Roles and access

Authorisation has two independent axes:

- **Role** decides *what* a user may do. Four roles ship by default:
  - `admin` — full access, including users and settings.
  - `engineer` — changes the network: infrastructure, VLANs, cabling, catalogue, IPAM, import/export.
  - `technician` — patches cables.
  - `viewer` — read-only.
- **Site access** decides *where*. A user either has `has_all_sites` set, or is
  attached to specific sites; every list, search and map is read through that
  scope.

Roles and permissions are seeded by `PermissionSeeder` and defined in
`app/Support/Permissions.php`. Adjust the permission-to-role mapping there and
re-run the seeder to change what each role may do.

## First administrator

After the migrations run, create an administrator (see
[installation.md](installation.md#docker)). There is deliberately no default
account — an empty install has no way in until you make one.

## Backups

The export on the **Labels / Import** area writes the documented estate to an
Excel workbook. For a full backup, dump the database as usual
(`pg_dump` / your host's snapshot); the workbook is a convenience copy, not a
substitute.
