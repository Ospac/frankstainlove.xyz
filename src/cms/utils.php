<?php
require_once "constants.php";
require_once "vendor/php-markdown-lib-9.1/Michelf/MarkdownExtra.inc.php";

use Michelf\MarkdownExtra;

// -----------------------------------------------------------------------------
// Markdown and page rendering
// -----------------------------------------------------------------------------
/**
 * Parse the optional YAML-like frontmatter at the beginning of a Markdown file.
 * The project only needs scalar page settings, so a small parser is sufficient.
 */
function parse_frontmatter($source)
{
    $metadata = [];
    $lineBreak = '(?:\r\n|\r|\n)';
    $pattern = '/\A(?:\xEF\xBB\xBF)?---[ \t]*' . $lineBreak
        . '(.*?)' . $lineBreak . '---[ \t]*(?:' . $lineBreak . '|\z)/s';

    if (!preg_match($pattern, $source, $matches)) {
        return [$metadata, $source];
    }

    foreach (preg_split('/\r\n|\r|\n/', $matches[1]) as $line) {
        // Restrict whitespace matching to ASCII spaces and tabs. Using \s here
        // can consume bytes from UTF-8 characters such as Korean text.
        if (preg_match('/^[ \t]*([A-Za-z0-9_-]+)[ \t]*:[ \t]*(.*?)[ \t]*$/', $line, $fields)) {
            $value = trim($fields[2]);
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            $metadata[$fields[1]] = $value;
        }
    }

    return [$metadata, substr($source, strlen($matches[0]))];
}


/**
 *   render_func takes in
 *   - path: a string path to the markdown file to be rendered
 *   - ext: a string of the file extension (.md)
 *   - _src: an open file handle for the file
 *
 *   and it returns a string of the HTML file rendered from that markdown file,
 *   with appropriate headers and title
 *
 */
function render_func($path, $ext, $_src = "")
{

    $metadata = [];

    // Get rendered html and parse page metadata.
    if ($ext === 'md') {
        $source = stream_get_contents($_src);
        [$metadata, $source] = parse_frontmatter($source);
        $body = markdown_to_html($source);
    } elseif ($ext === 'html' || $ext === 'htm') {
        // Source is already HTML.
        $body = "";
        while (!feof($_src)) {
            $body .= fgets($_src);
        }
    }
    fclose($_src);

    // Handle header and footer
    $header = null;
    $footer = null;
    $headerTitle = null;

    $editingHeader = strpos($path, 'header.md') !== false;
    $editingFooter = strpos($path, 'footer.md') !== false;
    $editingHtml = $ext === 'html' || $ext === 'htm';

    // if rendering header or footer, they should be rendered in their correct layout-position,
    if ($editingHeader) {
        $header = $body;
        $body = "";
        $footer = "";
    } elseif ($editingFooter) {
        $footer = $body;
        $header = "";
        $body = "";
        // if html is being editied, header or footer should not be displayed
    } elseif ($editingHtml) {
        $header = "";
        $footer = "";
    } else {
        $header = render_l11n_layout("header.md", $path);
        $footer = render_l11n_layout("footer.md", $path);
    }

    $requestedLayout = $metadata['layout'] ?? null;
    $defaultLayoutFileName = 'default';
    if (is_string($requestedLayout) && preg_match('/^[A-Za-z0-9_-]+$/', $requestedLayout)) {
        $requestedLayoutPath = LAYOUT_DIR . '/' . $requestedLayout . '.php';
        if (is_file($requestedLayoutPath)) {
            $defaultLayoutFileName = $requestedLayout;
        }
    }

    // Expose frontmatter values directly to layouts. Values are escaped here
    // so layout files can use arbitrary frontmatter variables without
    // repeating HTML escaping.
    $metadata['title'] = $metadata['title'] ?? 'lichen-markdown';
    $metadata['layout'] = $defaultLayoutFileName;

    foreach ($metadata as $name => $value) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            unset($metadata[$name]);
            continue;
        }

        $metadata[$name] = htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    extract($metadata, EXTR_SKIP);

    set_error_handler(function () {
        return true;
    }, E_WARNING | E_NOTICE);

    ob_start();
    include LAYOUT_DIR . '/' . $defaultLayoutFileName . '.php';
    $output = ob_get_clean();
    restore_error_handler();
    $output = rewrite_asset_paths($output, $path);
    return rewrite_internal_links($output, $path);
}

// -----------------------------------------------------------------------------
// Build output
// -----------------------------------------------------------------------------

/*
 * Render Markdown files and copy other files to the corresponding dist path.
 */
function save_dist($relative_src_path)
{
    $ext = pathinfo($relative_src_path, PATHINFO_EXTENSION);
    $sourcePath = source_path($relative_src_path);
    $publicPath = content_relative_path($relative_src_path);
    if ($ext === 'md') {
        $output_dest_path = DIST_DIR . preg_replace('"\.md$"', '.html', $publicPath);

        // Re-render until the displayed value includes the final HTML size.
        $pageSize = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $_md_src = fopen($sourcePath, "r") or die("File not found: " . $relative_src_path);
            $output = render_func($publicPath, $ext, $_md_src);
            $output = rewrite_asset_paths($output, $publicPath);
            $output = rewrite_internal_links($output, $publicPath);
            if ($pageSize !== null) {
                $output = inject_page_size($output, $pageSize);
            }

            $nextPageSize = format_file_size(calculate_page_size($output, $output_dest_path));
            if ($nextPageSize === $pageSize) {
                break;
            }
            $pageSize = $nextPageSize;
        }
    } else {
        // Files under page/assets are public assets, not part of the page URL.
        // Use the content-relative path so /page/assets/foo becomes /assets/foo.
        $output_dest_path = DIST_DIR . $publicPath;
    }
    $directoryPath = dirname($output_dest_path);
    // check if the directory exists that will contain the rendered html file
    if (!is_dir($directoryPath)) {
        mkdir($directoryPath, 0755, true);
    }
    // if its a markdown source file, then copy the actual html over there
    if ($ext === 'md') {
        file_put_contents($output_dest_path, $output);
    }
    // otherwise just create a symlink back to the source file (in order to save space)
    else {
        $targetPath = $output_dest_path;
        $absoluteSrcPath = $sourcePath;
        @symlink($absoluteSrcPath, $targetPath);
    }
}

function save_all_to_dist($dir)
{
    // RecursiveDirectoryIterator to iterate through the directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            function ($current, $key, $iterator) {
                // Skip application and generated directories.
                $skipDirs = ['src', 'cms', 'layout', 'dist', 'update', '.git', '.obsidian'];
                if ($current->getPath() === PROJECT_DIR && !in_array($current->getBasename(), ['page', 'assets'])) {
                    return false;
                }
                if ($current->isDir() && in_array($current->getBasename(), $skipDirs)) {
                    return false;  // Skip this directory
                }
                return true;  // Otherwise, include this file/directory
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY // Only return files, not directories
    );
    foreach ($iterator as $file) {

        // process only regular files (not directories)
        if ($file->isFile()) {
            $absolutePath = $file->getRealPath();
            $relativePath = "/" . str_replace(PROJECT_DIR . DIRECTORY_SEPARATOR, '', $absolutePath);
            // Only Markdown files under /page are site content. Project docs
            // such as README.md must not become public pages.
            if (str_ends_with(strtolower($relativePath), '.md') && !str_starts_with($relativePath, '/page/')) {
                continue;
            }
            save_dist($relativePath); // save_dist on the file
        }
    }
}

// -----------------------------------------------------------------------------
// Shared file and Markdown helpers
// -----------------------------------------------------------------------------

/* Takes in a markdown file and renders it to HTML using MarkdownExtra. */
function markdown_to_html($file)
{
    if (is_resource($file)) {
        $source = "";
        while (!feof($file)) {
            $source .= fgets($file);
        }
    } else {
        $source = (string) $file;
    }

    $html = MarkdownExtra::defaultTransform($source);

    return $html;
}

function delTree($dir)
{
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";

        // If it's a symlink, just unlink it (do not follow into it)
        if (is_link($path)) {
            unlink($path);

            // If it's a directory (and not a symlink), recurse
        } elseif (is_dir($path)) {
            delTree($path);

            // Otherwise, regular file — delete it
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Return rendered Markdown when the file exists, or an empty string otherwise.
function render_file_if_exist_or_empty_string($path)
{
    if (file_exists($path)) {
        $src = fopen($path, 'r');
        $rendered = markdown_to_html($src);
        fclose($src);
        return $rendered;
    }

    return "";
}

// Render a localized header or footer when the target page is under /l11n/<LANG>/.
function render_l11n_layout($fileName, $targetPath)
{
    $pathArray = explode("/", $targetPath);
    $file_path = PAGE_DIR . "/" . $fileName;
    if (($pathArray[1] ?? '') === 'l11n') {
        $lang = $pathArray[2];
        $file_path = PAGE_DIR . "/l11n" . "/" . $lang . "/" . $fileName;
    }

    $rendered = render_file_if_exist_or_empty_string($file_path);
    if ($rendered === "") {
        return $rendered;
    }

    // Layout links are authored relative to the layout file, but are rendered
    // inside the current page. Preserve that distinction when rewriting them.
    $layoutPath = content_relative_path(
        '/' . ltrim(substr($file_path, strlen(PAGE_DIR)), '/')
    );
    return rewrite_internal_links($rendered, $targetPath, $layoutPath);
}

// Render a Markdown component file, or return an empty string when absent.
function render_component($name)
{
    $path = PAGE_DIR . "/" . $name . ".md";
    if (!file_exists($path)) {
        return "";
    }

    $src = fopen($path, "r");
    $html = markdown_to_html($src);
    fclose($src);
    return $html;
}

// -----------------------------------------------------------------------------
// Page size calculation
// -----------------------------------------------------------------------------

function format_file_size($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    $precision = $unit === 0 ? 0 : ($size < 10 ? 2 : 1);
    return number_format($size, $precision, '.', '') . ' ' . $units[$unit];
}

function inject_page_size($html, $size) {
    $escapedSize = htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
    return preg_replace_callback(
        '/(<([a-z][a-z0-9:-]*)\b[^>]*\bid=["\']page-size["\'][^>]*>).*?(<\/\2>)/is',
        function ($matches) use ($escapedSize) {
            return $matches[1] . $escapedSize . $matches[3];
        },
        $html,
        1
    );
}

function local_resource_path($url, $basePath) {
    $url = trim($url);
    if ($url === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $url)) {
        return null;
    }

    $url = preg_split('/[?#]/', $url, 2)[0];
    if ($url === '') return null;

    if ($url[0] === '/') {
        $path = PROJECT_DIR . '/' . ltrim($url, '/');
    } else {
        $path = dirname($basePath) . '/' . $url;
    }

    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') array_pop($parts);
        else $parts[] = $part;
    }
    $resolved = '/' . implode('/', $parts);
    if (is_file($resolved)) return $resolved;

    // Assets may not have been symlinked into dist yet while a page is built.
    if (str_starts_with($resolved, DIST_DIR . '/')) {
        $source = PROJECT_DIR . '/' . ltrim(substr($resolved, strlen(DIST_DIR)), '/');
        if (is_file($source)) return $source;
    }
    return null;
}

function collect_css_resources($path, &$files, &$visited) {
    $realPath = realpath($path);
    if ($realPath === false || isset($visited[$realPath])) return;
    $visited[$realPath] = true;
    $files[$realPath] = true;

    $css = file_get_contents($realPath);
    preg_match_all('/@import\s+(?:url\(\s*)?["\']?([^"\')\s]+)["\']?\s*\)?/i', $css, $imports);
    preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/i', $css, $urls);

    foreach (array_merge($imports[1], $urls[1]) as $url) {
        $resource = local_resource_path($url, $realPath);
        if ($resource === null) continue;
        if (strtolower(pathinfo($resource, PATHINFO_EXTENSION)) === 'css') {
            collect_css_resources($resource, $files, $visited);
        } else {
            $files[realpath($resource) ?: $resource] = true;
        }
    }
}

function calculate_page_size($html, $outputPath) {
    $files = [];
    $visitedCss = [];
    $htmlSize = strlen($html);

    preg_match_all('/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $links);
    preg_match_all('/<(?:img|script|iframe|audio|video|source|track)\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $srcs);
    preg_match_all('/<img\b[^>]*\bsrcset\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $srcsets);

    foreach ($links[1] as $url) {
        $resource = local_resource_path($url, $outputPath);
        if ($resource === null) continue;
        if (strtolower(pathinfo($resource, PATHINFO_EXTENSION)) === 'css') {
            collect_css_resources($resource, $files, $visitedCss);
        } else {
            $files[realpath($resource) ?: $resource] = true;
        }
    }
    foreach ($srcs[1] as $url) {
        $resource = local_resource_path($url, $outputPath);
        if ($resource !== null) $files[realpath($resource) ?: $resource] = true;
    }
    foreach ($srcsets[1] as $srcset) {
        foreach (explode(',', $srcset) as $candidate) {
            $url = preg_split('/\s+/', trim($candidate))[0] ?? '';
            $resource = local_resource_path($url, $outputPath);
            if ($resource !== null) $files[realpath($resource) ?: $resource] = true;
        }
    }

    $total = $htmlSize;
    foreach (array_keys($files) as $file) {
        if (is_file($file)) $total += filesize($file);
    }
    return $total;
}

// -----------------------------------------------------------------------------
// URL rewriting
// -----------------------------------------------------------------------------

// Return an asset URL relative to the rendered page's directory.
function asset_url($pagePath, $assetPath) {
    $pageDirectory = trim(str_replace('\\', '/', dirname($pagePath)), '/');
    $depth = $pageDirectory === '' ? 0 : count(explode('/', $pageDirectory));

    return str_repeat('../', $depth) . ltrim($assetPath, '/');
}

// Static pages cannot resolve root-relative asset URLs when served below a domain root.
function rewrite_asset_paths($html, $pagePath) {
    return preg_replace_callback(
        '/(["\'])\/assets\/([^"\']*)/',
        function ($matches) use ($pagePath) {
            return $matches[1] . asset_url($pagePath, 'assets/' . $matches[2]);
        },
        $html
    );
}

// Static builds expose rendered Markdown files as .html files, not .md routes.
function rewrite_internal_links($html, $pagePath, $sourcePath = null) {
    return preg_replace_callback(
        '/(<a\b[^>]*\bhref\s*=\s*)(["\'])([^"\']*\.md(?:[?#][^"\']*)?)\2/i',
        function ($matches) use ($pagePath, $sourcePath) {
            $target = $matches[3];
            if (preg_match('/^\s*(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $target)) {
                return $matches[0];
            }

            preg_match('/^([^?#]*)(.*)$/', $target, $parts);
            $targetPath = preg_replace('/\.md$/i', '.html', $parts[1]);
            $outputPagePath = preg_replace('/\.md$/i', '.html', $pagePath);
            $pageDirectory = trim(dirname($outputPagePath), '/');
            $sourceDirectory = trim(dirname($sourcePath ?? $pagePath), '/');
            $targetSegments = explode('/', trim($targetPath, '/'));

            if ($parts[1][0] !== '/') {
                $targetSegments = array_merge(
                    $sourceDirectory === '' ? [] : explode('/', $sourceDirectory),
                    $targetSegments
                );
            }

            $resolved = [];
            foreach ($targetSegments as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                if ($segment === '..') {
                    array_pop($resolved);
                } else {
                    $resolved[] = $segment;
                }
            }

            $baseSegments = $pageDirectory === '' ? [] : explode('/', $pageDirectory);
            while (count($baseSegments) > 0 && count($resolved) > 0 && $baseSegments[0] === $resolved[0]) {
                array_shift($baseSegments);
                array_shift($resolved);
            }

            $relativePath = str_repeat('../', count($baseSegments)) . implode('/', $resolved);
            return $matches[1] . $matches[2] . $relativePath . $parts[2] . $matches[2];
        },
        $html
    );
}
