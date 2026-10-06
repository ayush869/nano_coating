<?php

namespace App\Support;

/**
 * URLs for stored files. Catalog images and the logo are public; customer
 * site photos need a signed-in staff member or customer. Files are served by
 * MediaController, so no storage:link is needed.
 */
class Media
{
    public const PUBLIC_DIRS = ['products', 'services', 'logo'];

    public static function url(?string $path): ?string
    {
        if (! $path || ! str_contains($path, '/')) {
            return null;
        }
        [$dir, $file] = explode('/', $path, 2);

        return in_array($dir, self::PUBLIC_DIRS, true)
            ? route('media.public', ['dir' => $dir, 'file' => $file])
            : route('media.photo', ['file' => $file]);
    }
}
