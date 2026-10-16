<?php
// Project paths. Markdown content lives outside the application skeleton.
define('PROJECT_DIR', dirname(dirname(__DIR__)));
define('PAGE_DIR', PROJECT_DIR . '/page');
define('ASSET_DIR', PAGE_DIR . '/assets');
define('DIST_DIR', PROJECT_DIR . '/dist');
define('CMS_DIR', PROJECT_DIR . '/src/cms');
define('LAYOUT_DIR', PROJECT_DIR . '/src/layout');

function content_relative_path($path)
{
    $path = '/' . ltrim($path, '/');
    return preg_replace('#^/page(?=/|$)#', '', $path) ?: '/';
}

function source_path($path)
{
    $path = '/' . ltrim($path, '/');
    if (preg_match('#^/assets(?:/|$)#', $path)) {
        return PAGE_DIR . $path;
    }
    if (preg_match('#^/page(?:/|$)#', $path)) {
        return PROJECT_DIR . $path;
    }
    if (str_ends_with(strtolower($path), '.md')) {
        return PAGE_DIR . content_relative_path($path);
    }
    return PROJECT_DIR . $path;
}
