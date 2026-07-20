# NetRoom

Network documentation for IT departments — sites, server rooms, racks, switches, patch panels, ports, VLANs, cabling and IP addresses in one place, instead of a spreadsheet nobody trusts.

> **Status:** early development. The foundation (auth, roles, localisation) is in place; the domain features listed below are landing phase by phase.

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
- **Roles and scopes** — permissions decide *what* a user may do, site access decides *where*.
- **Activity log** — every change, with before/after values.

## Requirements

- PHP 8.3+
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

### Docker

```bash
cp .env.example .env
docker compose up -d
```

This starts the application on <http://localhost:8080> with PostgreSQL alongside it.

## Language

The interface ships in Russian and English. `APP_LOCALE` sets the default; each user can pick their own under **Settings → Appearance**.

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
