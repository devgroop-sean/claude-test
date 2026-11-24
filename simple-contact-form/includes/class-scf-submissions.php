<?php
/**
 * Stores submissions as a private custom post type.
 *
 * @package SimpleContactForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submission storage and admin list display.
 */
class SCF_Submissions {

	const POST_TYPE = 'scf_submission';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );

		if ( is_admin() ) {
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
			add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
			add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		}
	}

	/**
	 * Register the submission post type (admin only, not public).
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Contact Submissions', 'simple-contact-form' ),
					'singular_name' => __( 'Contact Submission', 'simple-contact-form' ),
					'menu_name'     => __( 'Contact Form', 'simple-contact-form' ),
					'all_items'     => __( 'Submissions', 'simple-contact-form' ),
					'edit_item'     => __( 'View Submission', 'simple-contact-form' ),
					'not_found'     => __( 'No submissions yet.', 'simple-contact-form' ),
					'search_items'  => __( 'Search Submissions', 'simple-contact-form' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/**
	 * Save a submission.
	 *
	 * @param array $data Sanitized submission fields.
	 * @return int|WP_Error Post ID.
	 */
	public static function store( array $data ) {
		$title = $data['subject'] ? $data['subject'] : sprintf(
			/* translators: %s: sender name */
			__( 'Message from %s', 'simple-contact-form' ),
			$data['name']
		);

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => $data['message'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		foreach ( array( 'name', 'email', 'phone', 'subject', 'page_url', 'ip', 'user_agent' ) as $key ) {
			if ( isset( $data[ $key ] ) && '' !== $data[ $key ] ) {
				update_post_meta( $post_id, '_scf_' . $key, $data[ $key ] );
			}
		}

		return $post_id;
	}

	/**
	 * List table columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'        => $columns['cb'],
			'title'     => __( 'Subject', 'simple-contact-form' ),
			'scf_name'  => __( 'Name', 'simple-contact-form' ),
			'scf_email' => __( 'Email', 'simple-contact-form' ),
			'date'      => $columns['date'],
		);
	}

	/**
	 * List table column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'scf_name' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_scf_name', true ) );
		} elseif ( 'scf_email' === $column ) {
			$email = get_post_meta( $post_id, '_scf_email', true );
			printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) );
		}
	}

	/**
	 * Remove quick edit, keep view/trash.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( self::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Read-only details box on the edit screen.
	 */
	public static function meta_boxes() {
		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );
		remove_meta_box( 'slugdiv', self::POST_TYPE, 'normal' );
		add_meta_box( 'scf_details', __( 'Submission', 'simple-contact-form' ), array( __CLASS__, 'render_details' ), self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Render submission details.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_details( $post ) {
		$fields = array(
			'name'       => __( 'Name', 'simple-contact-form' ),
			'email'      => __( 'Email', 'simple-contact-form' ),
			'phone'      => __( 'Phone', 'simple-contact-form' ),
			'subject'    => __( 'Subject', 'simple-contact-form' ),
			'page_url'   => __( 'Sent from', 'simple-contact-form' ),
			'ip'         => __( 'IP address', 'simple-contact-form' ),
			'user_agent' => __( 'User agent', 'simple-contact-form' ),
		);

		echo '<table class="form-table"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, '_scf_' . $key, true );
			if ( '' === $value ) {
				continue;
			}
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			if ( 'email' === $key ) {
				printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $value ), esc_html( $value ) );
			} elseif ( 'page_url' === $key ) {
				printf( '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url( $value ) );
			} else {
				echo esc_html( $value );
			}
			echo '</td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__( 'Received', 'simple-contact-form' ) . '</th><td>' . esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Message', 'simple-contact-form' ) . '</th><td>' . nl2br( esc_html( $post->post_content ) ) . '</td></tr>';
		echo '</tbody></table>';

		echo '<style>#post-body-content, #titlediv { display:none; }</style>';
	}
}
