<?php
/**
 * Plugin Name: WDS Headless (Core)
 * Plugin URI: https://github.com/WebDevStudios/nextjs-wordpress-starter
 * Description: The core WordPress plugin for the Next.js WordPress Starter.
 * Author: WebDevStudios <contact@webdevstudios.com>
 * Author URI: https://webdevstudios.com
 * Version: 2.0.1
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL-2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WDS_Headless_Core
 */

namespace WDS_Headless_Core;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Define constants.
define( 'WDS_HEADLESS_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WDS_HEADLESS_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WDS_HEADLESS_CORE_VERSION', '2.0.1' );
define( 'WDS_HEADLESS_CORE_OPTION_NAME', 'headless_config' );

// Register de/activation hooks.
register_activation_hook( __FILE__, __NAMESPACE__ . '\activation_callback' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivation_callback' );

require_once 'inc/editor.php';
require_once 'inc/links.php';
require_once 'inc/media.php';
require_once 'inc/menus.php';
require_once 'inc/settings.php';
require_once 'inc/wp-graphql.php';
