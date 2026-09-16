import { toPng, toSvg } from 'html-to-image';

/**
 * Getting a diagram off the screen and into the department's files: a raster
 * copy to paste into a ticket, a vector one that survives being blown up, and a
 * printed sheet the cupboard door can hold.
 *
 * The minimap, controls, editing chrome and any floating panels are filtered
 * out so only the diagram lands in the picture. The legend is not — a filed
 * drawing should read on its own.
 */

const SKIP = [
    'vue-flow__minimap',
    'vue-flow__controls',
    'vue-flow__panel',
    'vue-flow__resize-control',
    'map-toolbar',
    'map-selection',
];

const clean = (node: Node): boolean =>
    !SKIP.some((cls) => (node as HTMLElement).classList?.contains(cls));

const save = (dataUrl: string, filename: string): void => {
    const link = document.createElement('a');
    link.download = filename;
    link.href = dataUrl;
    link.click();
};

export async function exportMapPng(
    pane: HTMLElement,
    filename: string,
    dark: boolean,
): Promise<void> {
    save(
        await toPng(pane, {
            backgroundColor: dark ? '#0b0f19' : '#ffffff',
            pixelRatio: 2,
            filter: clean,
        }),
        `${filename}.png`,
    );
}

/**
 * The vector copy. Vue Flow draws its nodes as HTML and only the links as SVG,
 * so the file carries the nodes inside a foreignObject: it scales without going
 * soft and opens in any browser, but a drawing editor will not take it apart.
 */
export async function exportMapSvg(
    pane: HTMLElement,
    filename: string,
    dark: boolean,
): Promise<void> {
    save(
        await toSvg(pane, {
            backgroundColor: dark ? '#0b0f19' : '#ffffff',
            filter: clean,
        }),
        `${filename}.svg`,
    );
}

export type PaperSize = 'a4' | 'a3';
export type PaperOrientation = 'landscape' | 'portrait';

/**
 * A picture of the diagram for the print sheet.
 *
 * Two things differ from the file exports. The sheet carries its own legend in
 * the title block, so the one on the canvas is left out rather than printed
 * twice. And the picture is taken in the light theme: html-to-image reads the
 * colours computed on the live page, so a dark canvas would print as a black
 * rectangle and empty a cartridge doing it. The page therefore goes light for
 * the few seconds the capture takes — which is also a fair signal that
 * something is happening.
 */
export async function printableImage(pane: HTMLElement): Promise<string> {
    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');

    root.classList.remove('dark');
    await new Promise((resolve) => requestAnimationFrame(resolve));

    try {
        return await toPng(pane, {
            backgroundColor: '#ffffff',
            pixelRatio: 2,
            filter: (node) =>
                clean(node) &&
                !(node as HTMLElement).classList?.contains('map-legend'),
        });
    } finally {
        if (wasDark) {
            root.classList.add('dark');
        }
    }
}

const PAGE_STYLE_ID = 'map-print-page';

/**
 * Hands the sheet to the browser's own print dialog, which is also where "save
 * as PDF" lives — so the department gets a PDF without this page shipping a PDF
 * writer. The paper size has to go into an @page rule, which no style attribute
 * can carry, so a tag goes in and comes straight back out.
 */
export function printSheet(
    size: PaperSize,
    orientation: PaperOrientation,
): Promise<void> {
    const style = document.createElement('style');
    style.id = PAGE_STYLE_ID;
    style.textContent = `@page { size: ${size} ${orientation}; margin: 10mm; }`;
    document.head.append(style);
    document.body.classList.add('map-printing');

    return new Promise((resolve) => {
        const done = (): void => {
            window.removeEventListener('afterprint', done);
            document.body.classList.remove('map-printing');
            document.getElementById(PAGE_STYLE_ID)?.remove();
            resolve();
        };

        window.addEventListener('afterprint', done);

        // Let the sheet paint before the dialog freezes the page.
        requestAnimationFrame(() => window.print());
    });
}
