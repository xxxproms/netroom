import type { RackPatch } from '@/types';

/**
 * Draws the rack's commutation as one self-contained SVG — device bands, their
 * ports coloured by the cable each carries, the cords between them, and the
 * uplink chips along the top. Purpose-built rather than a screenshot of the
 * live DOM, so the picture is crisp, deterministic, and needs no page state.
 * Hand the result to exportSvgToPng for a PNG the department can file.
 */

const MEDIA_COLOR: Record<string, string> = {
    utp: '#94a3b8',
    fibre: '#0891b2',
};

const PAD = 20;
const LABEL_W = 180;
const CELL = 30; // width per port
const SQUARE = 20;
const BAND_H = 48; // one device row
const RAIL_H = 56; // uplink band
const NS = 'http://www.w3.org/2000/svg';

type Point = { x: number; y: number };

function esc(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function cordPath(a: Point, b: Point): string {
    const dy = Math.max(18, Math.min(90, Math.abs(b.y - a.y) * 0.4));

    return `M ${a.x} ${a.y} C ${a.x} ${a.y + dy}, ${b.x} ${b.y - dy}, ${b.x} ${b.y}`;
}

function cableColor(color: string | null, media: string): string {
    return color ?? MEDIA_COLOR[media] ?? '#94a3b8';
}

/** Build the diagram as an off-DOM <svg> element ready to rasterise. */
export function buildRackSvg(data: RackPatch, title: string): SVGSVGElement {
    const maxPorts = Math.max(
        1,
        ...data.devices.map((device) => device.ports.length),
    );
    const width = PAD * 2 + LABEL_W + maxPorts * CELL;

    const railH = data.externals.length ? RAIL_H : 0;
    const height = PAD * 2 + railH + data.devices.length * BAND_H + 28;

    const bg =
        `<rect width="${width}" height="${height}" fill="#ffffff"/>` +
        `<text x="${PAD}" y="${PAD + 4}" font-family="sans-serif" font-size="14" font-weight="700" fill="#0f172a">${esc(title)}</text>`;

    const parts: string[] = [];
    const centers: Record<number, Point> = {};
    const cords: string[] = [];
    const top = PAD + 28;

    // Device bands, laying out ports and recording their centres as we go.
    data.devices.forEach((device, row) => {
        const bandY = top + railH + row * BAND_H;
        const squareY = bandY + 10;

        parts.push(
            `<text x="${PAD}" y="${bandY + 18}" font-family="sans-serif" font-size="12" font-weight="600" fill="#0f172a">${esc(device.name)}</text>`,
            `<text x="${PAD}" y="${bandY + 32}" font-family="sans-serif" font-size="10" fill="#64748b">${esc(device.kind)}</text>`,
        );

        device.ports.forEach((port, i) => {
            const x = PAD + LABEL_W + i * CELL;
            const cx = x + SQUARE / 2;
            const cy = squareY + SQUARE / 2;
            centers[port.id] = { x: cx, y: cy };

            const link = port.link;
            const fill = link
                ? cableColor(link.cable.color, link.cable.media)
                : '#ffffff';
            const stroke = link ? '#334155' : '#cbd5e1';

            parts.push(
                `<rect x="${x}" y="${squareY}" width="${SQUARE}" height="${SQUARE}" rx="4" fill="${fill}" stroke="${stroke}" stroke-width="1"/>`,
                `<text x="${cx}" y="${squareY + SQUARE + 10}" font-family="sans-serif" font-size="8" fill="#475569" text-anchor="middle">${esc(port.name)}</text>`,
            );
        });
    });

    // Internal cords: one per cable, both ends inside this rack.
    const seen = new Set<number>();

    for (const device of data.devices) {
        for (const port of device.ports) {
            const link = port.link;

            if (!link || seen.has(link.cable.id)) {
                continue;
            }

            const far = link.far;

            if (far?.kind === 'port' && centers[far.id] && centers[port.id]) {
                cords.push(
                    `<path d="${cordPath(centers[port.id], centers[far.id])}" fill="none" stroke="${cableColor(link.cable.color, link.cable.media)}" stroke-width="2.25" stroke-linecap="round" ${link.cable.media === 'fibre' ? 'stroke-dasharray="7 4"' : ''}/>`,
                );
                seen.add(link.cable.id);
            }
        }
    }

    // Uplink chips along the rail, each joined to its near port.
    if (data.externals.length) {
        const railY = top;

        data.externals.forEach((ext, i) => {
            const chipX = PAD + LABEL_W + i * 150;
            const chipW = 140;
            const color = cableColor(ext.color, ext.media);

            parts.push(
                `<rect x="${chipX}" y="${railY}" width="${chipW}" height="34" rx="6" fill="#f8fafc" stroke="${color}" stroke-width="1.5"/>`,
                `<circle cx="${chipX + 12}" cy="${railY + 17}" r="5" fill="${color}"/>`,
                `<text x="${chipX + 24}" y="${railY + 20}" font-family="sans-serif" font-size="10" fill="#0f172a">${esc(ext.far.label.slice(0, 22))}</text>`,
            );

            const near = centers[ext.near_port_id];

            if (near) {
                cords.push(
                    `<path d="${cordPath({ x: chipX + chipW / 2, y: railY + 34 }, near)}" fill="none" stroke="${color}" stroke-width="2.25" stroke-linecap="round" ${ext.media === 'fibre' ? 'stroke-dasharray="7 4"' : ''}/>`,
                );
            }
        });
    }

    // Cords sit beneath the port squares so the squares stay legible on top.
    const markup =
        `<svg xmlns="${NS}" viewBox="0 0 ${width} ${height}">` +
        bg +
        cords.join('') +
        parts.join('') +
        `</svg>`;

    const doc = new DOMParser().parseFromString(markup, 'image/svg+xml');

    return doc.documentElement as unknown as SVGSVGElement;
}
