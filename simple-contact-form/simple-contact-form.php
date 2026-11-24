<?php
/**
 * Plugin Name:       Simple Contact Form
 * Description:       A lightweight, secure contact form. Add it anywhere with the [simple_contact_form] shortcode. Submissions are emailed and saved in the admin.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Devgroop
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-contact-form
 * Domain Path:       /languages
 *
 * @package SimpleContactForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCF_VERSION', '1.0.0' );
define( 'SCF_FILE', __FILE__ );
define( 'SCF_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCF_URL', plugin_dir_url( __FILE__ ) );

require_once SCF_DIR . 'includes/class-scf-settings.php';
require_once SCF_DIR . 'includes/class-scf-submissions.php';
require_once SCF_DIR . 'includes/class-scf-form.php';
require_once SCF_DIR . 'includes/class-scf-admin.php';

/**
 * Boot the plugin.
 */
function scf_init() {
	load_plugin_textdomain( 'simple-contact-form', false, dirname( plugin_basename( SCF_FILE ) ) . '/languages' );

	SCF_Submissions::init();
	SCF_Form::init();

	if ( is_admin() ) {
		SCF_Admin::init();
	}
}
add_action( 'plugins_loaded', 'scf_init' );

/**
 * Activation: seed default options and register the post type so rewrite rules are sane.
 */
function scf_activate() {
	if ( false === get_option( SCF_Settings::OPTION ) ) {
		add_option( SCF_Settings::OPTION, SCF_Settings::defaults() );
	}
	SCF_Submissions::register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'scf_activate' );

/**
 * Deactivation.
 */
function scf_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'scf_deactivate' );
