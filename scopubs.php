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

use Scopubs\PostTypes\ObservedAuthorPostRegistration;
use Scopubs\PostTypes\PublicationPostRegistration;

// == REGISTERING CUSTOM POST TYPES
$observed_author_registration = new ObservedAuthorPostRegistration( 'observed-author' );
$observed_author_registration->register();

$publication_registration = new PublicationPostRegistration( 'publication' );
$publication_registration->register();