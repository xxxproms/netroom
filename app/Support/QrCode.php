<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Turns a URL into an inline SVG QR code. A label stuck on a switch or a desk
 * carries the link to its page in the panel, so a phone camera opens the
 * documentation for whatever the technician is standing in front of.
 */
class QrCode
{
    /**
     * The SVG markup for a code, sized in pixels, without the XML prolog so it
     * can be dropped straight into a page.
     */
    public function svg(string $data, int $size = 128): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($data);

        // Drop the leading XML prolog; inline SVG does not want it.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}
