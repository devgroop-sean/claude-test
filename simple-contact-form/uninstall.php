<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package SimpleContactForm
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'scf_settings' );

$scf_ids = get_posts(
	array(
		'post_type'      => 'scf_submission',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $scf_ids as $scf_id ) {
	wp_delete_post( $scf_id, true );
}
