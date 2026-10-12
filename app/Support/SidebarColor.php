<?php

namespace App\Support;

class SidebarColor
{
    public const DEFAULT = '#8b0000';

    /** Dark text/icon color used when the sidebar background is light. */
    public const DARK_TEXT = '#1f2937';

    /**
     * Whether a background color is too light for the default white
     * sidebar text (relative luminance above the point where white text
     * drops below ~3:1 contrast). The chosen color is always applied
     * as-is; this only decides whether the menu text switches to dark.
     */
    public static function isLight(?string $hex): bool
    {
        if (!is_string($hex) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return false;
        }

        [$r, $g, $b] = sscanf(strtolower($hex), '#%02x%02x%02x');

        $channel = function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return $luminance > 0.3;
    }
}
