<?php

declare(strict_types=1);

if (!function_exists('asset_url')) {
    /**
     * URL for a file under public/assets, versioned by its modification time.
     */
    function asset_url(string $path, ?string $publicRoot = null): string
    {
        $path = ltrim($path, '/');
        $file = rtrim($publicRoot ?? FCPATH, '/\\') . '/assets/' . $path;
        $url = base_url('assets/' . $path);

        return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
    }
}
