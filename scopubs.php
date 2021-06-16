<?php
/**
 * Plugin Name:         ScoPubs
 * Plugin URI:          https://github.com/the16thpythonist/ScoPubs.git
 * Requires at least:   5.5
 * Requires PHP:        7.2
 * Description:         Scientific Publication Posts for Wordpress
 * Author:              Jonas Teufel
 * Author URI:          https://github.com/the16thpythonist
 * Version:             0.1.0
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once 'vendor/autoload.php';

use Scopubs\Author\ObservedAuthorPostRegistration;
use Scopubs\Publication\PublicationPostRegistration;
use Scopubs\Log\LogPostRegistration;

use Scopubs\VueFrontendRegistration;

// == DEFINING CONSTANTS
define('SCOPUBS_URL_BASE', plugin_dir_url(__FILE__));

// == REGISTERING CUSTOM POST TYPES
$observed_author_registration = new ObservedAuthorPostRegistration();
$observed_author_registration->register();

$publication_registration = new PublicationPostRegistration();
$publication_registration->register();

$log_registration = new LogPostRegistration();
$log_registration->register();

$frontend_registration = new VueFrontendRegistration();
$frontend_registration->register();

function options_page() {
    ?>
    <div id="scopubs-options-component">
        Seems like Vue component could not be loaded...
    </div>
    <?php
}

function register_options_page() {
    add_options_page('Scopubs Plugin Settings', 'Scopubs Settings', 'manage_options', 'scopubs_plugin', 'options_page');
}

add_action('admin_menu', 'register_options_page');