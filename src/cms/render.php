<?php
require_once "utils.php";

// get the body content
$_src = null;
$path = null;
$ext = null;
$_content = null;

// Decode the URL once while preserving spaces in actual filenames.
$_SERVER['PATH_INFO'] = urldecode($_SERVER['PATH_INFO']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_src = fopen("php://input", "r");
    $path = content_relative_path($_SERVER['PATH_INFO']);
    $ext = pathinfo($path, PATHINFO_EXTENSION);
} else if (isset($_SERVER['REDIRECT_URL'])) {
    $path = urldecode($_SERVER['REDIRECT_URL']);
    $sourcePath = source_path($path);
    $_src = fopen($sourcePath, "r") or die("File not found: " . $path);
    $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
    $path = content_relative_path($path);
    header("Last-Modified: " . date("r", filemtime($sourcePath)));
} else {
    $path = $_SERVER['PATH_INFO'];
    $sourcePath = source_path($path);
    $_src = fopen($sourcePath, "r") or die("File not found: " . $path);
    $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
    $path = content_relative_path($path);
    header("Last-Modified: " . date("r", filemtime($sourcePath)));
}

$output = render_func($path, $ext, $_src);

echo $output;

?>
