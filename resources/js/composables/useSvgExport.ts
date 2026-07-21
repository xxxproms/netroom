/**
 * Saves an SVG element as a PNG the user can drop into a report. Everything is
 * inlined and drawn on a canvas, so no server round-trip and no external help.
 */
export function exportSvgToPng(
    svg: SVGSVGElement,
    filename: string,
    scale = 2,
): void {
    const box = svg.viewBox.baseVal;
    const width = box.width || svg.clientWidth;
    const height = box.height || svg.clientHeight;

    const clone = svg.cloneNode(true) as SVGSVGElement;
    clone.setAttribute('width', String(width));
    clone.setAttribute('height', String(height));

    // The page background is a CSS variable the exported file cannot resolve, so
    // paint a solid one behind the diagram.
    const styles = getComputedStyle(document.body);
    const background =
        styles.getPropertyValue('--background').trim() || '#ffffff';

    const markup = new XMLSerializer().serializeToString(clone);
    const source = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(markup)}`;

    const image = new Image();
    image.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;

        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        context.fillStyle = background.startsWith('oklch')
            ? '#ffffff'
            : background;
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const link = document.createElement('a');
        link.download = `${filename}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    };
    image.src = source;
}
