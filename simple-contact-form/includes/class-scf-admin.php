<?php
/**
 * Admin settings page.
 *
 * @package SimpleContactForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen under the "Contact Form" menu.
 */
class SCF_Admin {

	const PAGE = 'scf-settings';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SCF_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add Settings submenu under the submissions menu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . SCF_Submissions::POST_TYPE,
			__( 'Contact Form Settings', 'simple-contact-form' ),
			__( 'Settings', 'simple-contact-form' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Link to settings from the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'edit.php?post_type=' . SCF_Submissions::POST_TYPE . '&page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'simple-contact-form' ) . '</a>' );
		return $links;
	}

	/**
	 * Register the option and fields.
	 */
	public static function register_settings() {
		register_setting(
			self::PAGE,
			SCF_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => SCF_Settings::defaults(),
			)
		);

		add_settings_section( 'scf_email', __( 'Email', 'simple-contact-form' ), '__return_false', self::PAGE );
		add_settings_section( 'scf_form', __( 'Form', 'simple-contact-form' ), '__return_false', self::PAGE );
		add_settings_section( 'scf_spam', __( 'Storage & spam', 'simple-contact-form' ), '__return_false', self::PAGE );

		$fields = array(
			array( 'recipient', __( 'Send to', 'simple-contact-form' ), 'text', 'scf_email', __( 'One or more addresses, comma-separated.', 'simple-contact-form' ) ),
			array( 'subject_prefix', __( 'Subject prefix', 'simple-contact-form' ), 'text', 'scf_email', '' ),
			array( 'success_message', __( 'Success message', 'simple-contact-form' ), 'text', 'scf_form', '' ),
			array( 'show_phone', __( 'Phone field', 'simple-contact-form' ), 'checkbox', 'scf_form', __( 'Show an optional phone field', 'simple-contact-form' ) ),
			array( 'show_subject', __( 'Subject field', 'simple-contact-form' ), 'checkbox', 'scf_form', __( 'Show an optional subject field', 'simple-contact-form' ) ),
			array( 'consent_text', __( 'Consent checkbox', 'simple-contact-form' ), 'textarea', 'scf_form', __( 'If filled in, a required checkbox with this text is added (e.g. a privacy policy link). Basic HTML allowed.', 'simple-contact-form' ) ),
			array( 'store_submissions', __( 'Save submissions', 'simple-contact-form' ), 'checkbox', 'scf_spam', __( 'Keep a copy of each message in the admin', 'simple-contact-form' ) ),
			array( 'rate_limit', __( 'Rate limit', 'simple-contact-form' ), 'number', 'scf_spam', __( 'Max messages per IP address per hour. 0 disables.', 'simple-contact-form' ) ),
		);

		foreach ( $fields as $f ) {
			add_settings_field(
				$f[0],
				$f[1],
				array( __CLASS__, 'field' ),
				self::PAGE,
				$f[3],
				array(
					'key'       => $f[0],
					'type'      => $f[2],
					'desc'      => $f[4],
					'label_for' => 'checkbox' === $f[2] ? null : 'scf-' . $f[0],
				)
			);
		}
	}

	/**
	 * Render one field.
	 *
	 * @param array $args Field args.
	 */
	public static function field( $args ) {
		$key   = $args['key'];
		$value = SCF_Settings::get( $key );
		$name  = SCF_Settings::OPTION . '[' . $key . ']';
		$id    = 'scf-' . $key;

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, 1, false ),
					esc_html( $args['desc'] )
				);
				return;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'number':
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" min="0" step="1" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}

		if ( $args['desc'] ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', isset( $input['recipient'] ) ? $input['recipient'] : '' ) ) ), 'is_email' );
		if ( ! $emails ) {
			add_settings_error( SCF_Settings::OPTION, 'scf_recipient', __( 'No valid recipient email given; using the site admin email.', 'simple-contact-form' ) );
			$emails = array( get_option( 'admin_email' ) );
		}
		$out['recipient'] = implode( ', ', $emails );

		$out['subject_prefix']    = isset( $input['subject_prefix'] ) ? sanitize_text_field( $input['subject_prefix'] ) : '';
		$out['success_message']   = isset( $input['success_message'] ) && '' !== trim( $input['success_message'] )
			? sanitize_text_field( $input['success_message'] )
			: SCF_Settings::defaults()['success_message'];
		$out['consent_text']      = isset( $input['consent_text'] ) ? wp_kses_post( $input['consent_text'] ) : '';
		$out['show_phone']        = empty( $input['show_phone'] ) ? 0 : 1;
		$out['show_subject']      = empty( $input['show_subject'] ) ? 0 : 1;
		$out['store_submissions'] = empty( $input['store_submissions'] ) ? 0 : 1;
		$out['rate_limit']        = isset( $input['rate_limit'] ) ? max( 0, absint( $input['rate_limit'] ) ) : 5;

		return $out;
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php esc_html_e( 'Add the form to any page or post with this shortcode:', 'simple-contact-form' ); ?>
				<code>[simple_contact_form]</code>
				&nbsp;<?php esc_html_e( 'Optional attributes:', 'simple-contact-form' ); ?>
				<code>title="Get in touch"</code> <code>button="Send"</code>
			</p>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
