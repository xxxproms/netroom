<?php

namespace App\Support\Import;

/**
 * The spreadsheet encodes VLAN membership as a cell's fill colour rather than a
 * value: a saturated blue means the port carries the VLAN tagged (a trunk), a
 * green means untagged (an access port), and a pale fill means the VLAN is not
 * on that switch. PhpSpreadsheet resolves the workbook's theme colours to plain
 * ARGB, so the state is read from the dominant colour channel.
 */
final class FillClassifier
{
    public const TAGGED = 'tagged';

    public const UNTAGGED = 'untagged';

    public const NONE = 'none';

    /**
     * @param  string|null  $argb  an 8-digit ARGB like "FF0F9ED5", or null
     */
    public function classify(?string $argb): string
    {
        if ($argb === null || strlen($argb) < 6) {
            return self::NONE;
        }

        // Drop the alpha byte if it is there.
        $hex = strlen($argb) === 8 ? substr($argb, 2) : $argb;

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        // A pale or near-white fill is the "not on this switch" state: either
        // everything is bright, or the colour is barely saturated.
        if ($min >= 190 || ($max - $min) < 40) {
            return self::NONE;
        }

        if ($b === $max && $b > $g) {
            return self::TAGGED;
        }

        if ($g === $max && $g > $r) {
            return self::UNTAGGED;
        }

        return self::NONE;
    }
}
