# NetRoom

Network documentation for IT departments — sites, server rooms, racks, switches, patch panels, ports, VLANs, cabling and IP addresses in one place, instead of a spreadsheet nobody trusts.

Built with Laravel 12, Inertia and Vue 3. Ships in Russian and English.

## Why

Most teams document their network in Excel: one sheet per switch, VLAN membership encoded as cell colours, and no way to answer "which port serves this desk?" without asking the person who built it. NetRoom keeps the same mental model — a grid of VLANs against ports — but turns it into something you can search, trace and share.

## Features

- **Sites and rooms** — several locations, each with its own server rooms, racks and workplaces.
- **Racks** — visual elevations, devices mounted by unit, front and rear faces.
- **Switches** — model catalogue with port templates, so creating a device lays out its ports automatically.
- **VLAN matrix** — the familiar VLAN × port grid, with tagged/untagged membership and PVID validation.
- **Patch panels** — front/rear port pairs, so a cable trace runs *through* the panel to the socket at the far end.
- **Cabling** — copper and fibre (one or two strands), labels, lengths, and end-to-end tracing from a switch port to a person's desk.
- **VPN tunnels** — how sites connect to each other, whichever firewall terminates them.
- **IPAM** — subnets, address usage, conflicts, and management addresses linked to their devices.
- **Network map** — an interactive diagram at two levels: all sites and the tunnels between them, then the devices inside one site.
- **Excel import** — bring in the switch spreadsheet you already keep by hand, VLAN-colour matrix and all, with a preview that shows exactly what will be created and what the sheet gets wrong before anything is written.
- **Export** — the documented estate back out to a workbook, scoped to what you can see.
- **Global search** — one box (`Ctrl`/`⌘ K`) across devices, workplaces, VLANs, subnets, sites and rooms.
- **Dashboard** — how much is documented, what needs attention (address conflicts, switches with no VLANs), and what changed lately.
- **QR labels** — a printable sheet of stickers for hardware and desks; scan one to open its page in the panel.
- **Roles and scopes** — permissions decide *what* a user may do, site access decides *where*.
- **Activity log** — every change, with before/after values.

## Requirements

- PHP 8.4+
- Node 22+
- PostgreSQL 16+ (SQLite works for a quick local try)

## Quick start

```bash
git clone https://github.com/xxxproms/netroom.git
cd netroom
composer setup
composer dev
```

`composer setup` installs dependencies, creates `.env`, generates an application key, runs migrations and builds the front end. `composer dev` starts the application, queue worker, log viewer and Vite together.

Accounts are created by an administrator — self-service registration is off by default. Set `NETROOM_ALLOW_REGISTRATION=true` in `.env` to open it up.

### Demo data

To look around a populated panel rather than an empty one:

```bash
php artisan db:seed --class=DemoSeeder
```

This builds a fictional estate — two neighbouring complexes sharing a VLAN plan, a town office, a factory and a cottage — with racks, switches, patch panels and documented ports. It also creates one account per role (`admin@`, `engineer@`, `tech@`, `viewer@example.com`, password `password`), so the access levels can be compared side by side. Meant for a scratch database, never a real one.

### Docker

```bash
cp .env.example .env
docker compose run --rm --no-deps --entrypoint "" app php artisan key:generate --show
# put the printed base64:… value into .env as APP_KEY
docker compose up -d
```

This starts the application on <http://localhost:8080> with PostgreSQL alongside it. Full steps, including creating the first administrator, are in [docs/installation.md](docs/installation.md).

## Language

The interface ships in Russian and English. `APP_LOCALE` sets the default; each user can pick their own under **Settings → Appearance**.

## Documentation

- [Installation](docs/installation.md) — Docker and bare-metal, and creating the first administrator.
- [Configuration](docs/configuration.md) — environment settings, roles and site access.

## Development

```bash
composer ci:check   # lint, format, types and tests — everything CI runs
composer test       # tests only
npm run lint        # fix front-end lint issues
```

## Contributing

Issues and pull requests are welcome. Please run `composer ci:check` before opening a pull request.

## License

MIT — see [LICENSE](LICENSE).
