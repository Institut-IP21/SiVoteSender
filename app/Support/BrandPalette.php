<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The org brand palette for the sender's emails. Slimmer than the engine's copy —
 * mailables only need the accent itself and a readable foreground for the action
 * button (no dark/soft surfaces). fromHex() returns null for an absent/malformed
 * value. Mirrored from web_engine's BrandPalette core, like the wire DTOs.
 */
final class BrandPalette
{
    private const DARK_INK = '#11161a';
    private const WHITE = '#ffffff';

    private function __construct(public readonly string $color) {}

    public static function fromHex(?string $hex): ?self
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) {
            return null;
        }

        return new self($hex);
    }

    /** Readable text colour on the accent: near-black on light accents, white on dark. */
    public function foreground(): string
    {
        return $this->contrast(self::DARK_INK) > $this->contrast(self::WHITE)
            ? self::DARK_INK
            : self::WHITE;
    }

    /** WCAG contrast ratio between the accent and another #rrggbb colour. */
    public function contrast(string $other): float
    {
        $a = self::luminance($this->color);
        $b = self::luminance($other);
        [$hi, $lo] = $a > $b ? [$a, $b] : [$b, $a];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** WCAG relative luminance of a #rrggbb colour. */
    private static function luminance(string $hex): float
    {
        $channels = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
        $linear = array_map(
            static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
