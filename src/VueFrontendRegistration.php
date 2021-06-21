<?php

namespace Scopubs;

use Scopubs\Author\ObservedAuthorPost;
use Scopubs\Publication\PublicationPost;
use Scopubs\Log\LogPost;
use Scopubs\Command\CommandManager;

/**
 * Class VueFrontendRegistration
 *
 * THE MOTIVATION
 *
 * The scopubs plugin uses Vue.js as the frontend library for handling all user interaction on the settings page,
 * within widgets etc. The JS and CSS code which makes this frontend application work has to be registered with
 * wordpress, so the source files get properly loaded.
 *
 * THE IDEA
 *
 * This registration of the necessary JS and CSS assets for the Vue frontend application is the responsibility of this
 * class. It wraps all the necessary hooks etc. behind a single call to the "register" function, which has to be made
 * in the top level plugin file:
 *
 *    $frontend_registration = new VueFrontendRegistration();
 *    $frontend_registration->register();
 *
 * HOT RELOADING
 *
 * One convenient feature of this class is, that it supports a special development mode. When using the development
 * docker environment which comes with this repository, a special env variable will be set, which signals this class
 * to use a different JS source file which uses the local Vue development server with hot reloading instead of the
 * precompiled library version.
 * For this feature, the development server obviously needs to be running! (npm run serve)
 *
 * @package Scopubs
 */
class VueFrontendRegistration {

    # -- Default Attributes
    public $style_handle = 'scopubs-frontend-style';
    public $style_file = 'scopubs-frontend.css';

    public $script_handle = 'scopubs-frontend-script';
    public $script_file_development = 'scopubs-frontend.js';
    public $script_file_production = 'scopubs-frontend.common.js';

    public $version = '0.1.0';

    public $development_env = 'SCOPUBS_DEV';

    # -- Instance Attributes
    public $dist_url;

    public function __construct() {
        $this->dist_url = SCOPUBS_URL_BASE . 'js/dist/';
    }

    public function register() {
        add_action( 'init', [$this, 'register_styles'] );
        add_action( 'init', [$this, 'register_scripts'] );

        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_styles'] );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );
    }

    // -- Registering scripts and stylesheets

    public function register_styles() {
        // The stylesheet is called the same for the production and the development version. Thus we do not need to
        // check which is the case here.
        $style_src = $this->dist_url . $this->style_file;
        wp_register_style($this->style_handle, $style_src, [], $this->version, 'all');
    }

    public function register_scripts() {
        // For the javascript script file, the situation is different. There is an actual difference between files for
        // the development mode and the production mode both in name and in function. The development mode is
        // explicitly supported here because it features the very convenient hot reload functionality.
        if ($this->in_development_mode()) {
            $script_src = $this->dist_url . $this->script_file_development;
        } else {
            $script_src = $this->dist_url . $this->script_file_production;
        }
        wp_register_script($this->script_handle, $script_src, [], $this->version, false);

        // https://pippinsplugins.com/use-wp_localize_script-it-is-awesome/
        // I didnt get this at first, but the script handle which is passed to this function needs to be the same as
        // an already registered script for which you want to make the object available. Generally the object does NOT
        // just become available in all JS!
        // We need this to pass important information to the frontend application, most importantly the base URL for
        // all rest urls, we wouldnt want to hard code this...

        $frontend_data = apply_filters(PLUGIN_PREFIX . '_frontend_data', [
            'plugin_name'           => PLUGIN_NAME,
            'plugin_prefix'         => PLUGIN_PREFIX,
            'rest_url'              => esc_url_raw( get_rest_url() ),
            'admin_url'             => esc_url_raw( get_admin_url() ),
            'nonce'                 => wp_create_nonce( 'wp_rest' ),
            'author_post_type'      => ObservedAuthorPost::$post_type,
            'publication_post_type' => PublicationPost::$post_type,
            'log_post_type'         => LogPost::$post_type,
            'command_base'          => CommandManager::$rest_base,
        ]);
        wp_localize_script($this->script_handle, 'WP', $frontend_data);
    }

    // -- Actually enqueueing scripts and stylesheets

    public function enqueue_styles() {
        wp_enqueue_style($this->style_handle);
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->script_handle);
    }

    // -- Utility methods

    public function in_development_mode() {
        // We simply need to check if the environment variable exists. This fact alone will tell us if we are supposed
        // to be in dev mode or not. Because certainly a variable with this specific name is only set by the means
        // of the local.yml docker-compose config file for the development setup of this plugin.
        $value = getenv( $this->development_env );

        return $value == "1";
    }

}