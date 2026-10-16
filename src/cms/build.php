<?php
/**
 * This file is designed in such a way that it can be run as a standalone script via the command:
 * php src/cms/build.php
 *
 * or it can be accessed via a web request to the URL /cms/build.php
 *
 * either of these ways of calling this will cause a rebuild of the entire dist directory
 *
 * this rebuild function is idempotent (aka gives same result no matter how many times its run,
 * or what the initial state is), so this build endpoint can safely be repeatedly called multiple times
 */
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

try {
    require_once "utils.php";

    // Change to the CMS directory
    chdir(CMS_DIR);

    // delete the contents of dist before re-building
    if (is_dir(DIST_DIR)) {
        echo "++ clearing dist: " . DIST_DIR . "\n";
        delTree(DIST_DIR);
    }


    // Save all files to the distribution directory
    save_all_to_dist(PROJECT_DIR);

    // Output success message
    echo "++ saved build output to " . DIST_DIR . "\n";

    // if this is a web request, send a 200 response
    if (php_sapi_name() !== 'cli') {
        http_response_code(200);
    }
} catch (Exception $e) {
    // Handle errors and respond with a 500 status code
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo "An error occurred: " . $e->getMessage();
    } else {
        // Log the error to the console when running via CLI
        fwrite(STDERR, "An error occurred: " . $e->getMessage() . "\n");
    }

    // Exit the script with an error code
    exit(1);
}
