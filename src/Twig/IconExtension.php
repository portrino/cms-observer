<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * Inlines a Lucide icon (assets/icons/<name>.svg, ISC-licensed) as raw SVG
 * markup - no icon font, no separate HTTP request, and it inherits color via
 * the icons' own stroke="currentColor" rather than needing per-icon CSS.
 */
final class IconExtension
{
    private const string ICONS_DIR = __DIR__.'/../../assets/icons';

    #[AsTwigFunction(name: 'icon', isSafe: ['html'])]
    public function icon(string $name, string $class = 'icon'): string
    {
        $path = self::ICONS_DIR."/{$name}.svg";
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Unknown icon "%s".', $name));
        }

        $svg = (string) file_get_contents($path);

        return preg_replace(
            ['/width="24"/', '/height="24"/', '/<svg/'],
            ['width="1em"', 'height="1em"', sprintf('<svg class="%s"', htmlspecialchars($class, ENT_QUOTES))],
            $svg,
            1,
        ) ?? $svg;
    }
}
