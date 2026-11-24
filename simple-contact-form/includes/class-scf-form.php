<?php
/**
 * Front-end form: shortcode, validation, spam checks, email delivery.
 *
 * @package SimpleContactForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact form handler.
 */
class SCF_Form {

	const ACTION      = 'scf_submit';
	const NONCE       = 'scf_submit_nonce';
	const MIN_SECONDS = 3; // Humans take longer than this to fill a form.

	/**
	 * Result of a non-JS submission handled during this request.
	 *
	 * @var array|null
	 */
	private static $result = null;

	/**
	 * Shortcode instance counter (for unique IDs).
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_shortcode( 'simple_contact_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		// AJAX (logged in and out).
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle_ajax' ) );

		// Plain POST fallback when JavaScript is unavailable.
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * Register (not enqueue) assets; they load only when the shortcode renders.
	 */
	public static function register_assets() {
		wp_register_style( 'scf-form', SCF_URL . 'assets/css/form.css', array(), SCF_VERSION );
		wp_register_script( 'scf-form', SCF_URL . 'assets/js/form.js', array(), SCF_VERSION, true );
		wp_localize_script(
			'scf-form',
			'scfForm',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'sending' => __( 'Sending…', 'simple-contact-form' ),
				'error'   => __( 'Something went wrong. Please try again.', 'simple-contact-form' ),
				'invalid' => __( 'Please fix the highlighted fields.', 'simple-contact-form' ),
			)
		);
	}

	/**
	 * Render the form.
	 *
	 * Attributes:
	 *   title        Optional heading above the form.
	 *   button       Submit button label.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'  => '',
				'button' => __( 'Send message', 'simple-contact-form' ),
			),
			$atts,
			'simple_contact_form'
		);

		wp_enqueue_style( 'scf-form' );
		wp_enqueue_script( 'scf-form' );

		self::$instance++;
		$id       = 'scf-form-' . self::$instance;
		$settings = SCF_Settings::all();

		// Values / errors from a non-JS submission in this same request.
		$result = self::$result;
		$errors = $result && ! $result['success'] ? $result['errors'] : array();
		$values = $result && ! $result['success'] ? $result['values'] : array();
		$sent   = isset( $_GET['scf_sent'] ) && '1' === $_GET['scf_sent']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$val = static function ( $key ) use ( $values ) {
			return isset( $values[ $key ] ) ? $values[ $key ] : '';
		};
		$err = static function ( $key ) use ( $errors, $id ) {
			if ( empty( $errors[ $key ] ) ) {
				return '';
			}
			return '<span class="scf-error" id="' . esc_attr( $id . '-' . $key . '-error' ) . '">' . esc_html( $errors[ $key ] ) . '</span>';
		};

		ob_start();
		?>
		<div class="scf-wrap" id="<?php echo esc_attr( $id ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="scf-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<div class="scf-notice <?php echo $sent ? 'scf-notice--success' : ( $errors ? 'scf-notice--error' : '' ); ?>" role="status" aria-live="polite" tabindex="-1"<?php echo ( $sent || $errors ) ? '' : ' hidden'; ?>>
				<?php
				if ( $sent ) {
					echo esc_html( $settings['success_message'] );
				} elseif ( ! empty( $errors['_form'] ) ) {
					echo esc_html( $errors['_form'] );
				} elseif ( $errors ) {
					esc_html_e( 'Please fix the highlighted fields.', 'simple-contact-form' );
				}
				?>
			</div>

			<form class="scf-form" method="post" action="<?php echo esc_url( remove_query_arg( 'scf_sent' ) . '#' . $id ); ?>" novalidate>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="scf_ts" value="<?php echo esc_attr( time() ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

				<?php // Honeypot: hidden from humans, bots tend to fill it in. ?>
				<div class="scf-hp" aria-hidden="true">
					<label for="<?php echo esc_attr( $id ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'simple-contact-form' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $id ); ?>-website" name="scf_website" tabindex="-1" autocomplete="off">
				</div>

				<div class="scf-row scf-row--half">
					<p class="scf-field">
						<label for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Name', 'simple-contact-form' ); ?> <span class="scf-req" aria-hidden="true">*</span></label>
						<input type="text" id="<?php echo esc_attr( $id ); ?>-name" name="scf_name" value="<?php echo esc_attr( $val( 'name' ) ); ?>" required maxlength="100" autocomplete="name"<?php echo self::aria_invalid( $errors, 'name', $id ); ?>>
						<?php echo $err( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</p>
					<p class="scf-field">
						<label for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email', 'simple-contact-form' ); ?> <span class="scf-req" aria-hidden="true">*</span></label>
						<input type="email" id="<?php echo esc_attr( $id ); ?>-email" name="scf_email" value="<?php echo esc_attr( $val( 'email' ) ); ?>" required maxlength="254" autocomplete="email"<?php echo self::aria_invalid( $errors, 'email', $id ); ?>>
						<?php echo $err( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</p>
				</div>

				<?php if ( $settings['show_phone'] || $settings['show_subject'] ) : ?>
				<div class="scf-row scf-row--half">
					<?php if ( $settings['show_phone'] ) : ?>
					<p class="scf-field">
						<label for="<?php echo esc_attr( $id ); ?>-phone"><?php esc_html_e( 'Phone', 'simple-contact-form' ); ?></label>
						<input type="tel" id="<?php echo esc_attr( $id ); ?>-phone" name="scf_phone" value="<?php echo esc_attr( $val( 'phone' ) ); ?>" maxlength="30" autocomplete="tel"<?php echo self::aria_invalid( $errors, 'phone', $id ); ?>>
						<?php echo $err( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</p>
					<?php endif; ?>
					<?php if ( $settings['show_subject'] ) : ?>
					<p class="scf-field">
						<label for="<?php echo esc_attr( $id ); ?>-subject"><?php esc_html_e( 'Subject', 'simple-contact-form' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $id ); ?>-subject" name="scf_subject" value="<?php echo esc_attr( $val( 'subject' ) ); ?>" maxlength="150">
					</p>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<p class="scf-field">
					<label for="<?php echo esc_attr( $id ); ?>-message"><?php esc_html_e( 'Message', 'simple-contact-form' ); ?> <span class="scf-req" aria-hidden="true">*</span></label>
					<textarea id="<?php echo esc_attr( $id ); ?>-message" name="scf_message" rows="6" required maxlength="5000"<?php echo self::aria_invalid( $errors, 'message', $id ); ?>><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
					<?php echo $err( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>

				<?php if ( '' !== trim( $settings['consent_text'] ) ) : ?>
				<p class="scf-field scf-field--consent">
					<label>
						<input type="checkbox" name="scf_consent" value="1" required<?php checked( $val( 'consent' ), '1' ); ?><?php echo self::aria_invalid( $errors, 'consent', $id ); ?>>
						<?php echo wp_kses_post( $settings['consent_text'] ); ?>
					</label>
					<?php echo $err( 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
				<?php endif; ?>

				<p class="scf-actions">
					<button type="submit" class="scf-submit"><?php echo esc_html( $atts['button'] ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * aria-invalid / aria-describedby attributes for a field.
	 *
	 * @param array  $errors Errors.
	 * @param string $key    Field key.
	 * @param string $id     Form ID.
	 * @return string
	 */
	private static function aria_invalid( $errors, $key, $id ) {
		if ( empty( $errors[ $key ] ) ) {
			return '';
		}
		return ' aria-invalid="true" aria-describedby="' . esc_attr( $id . '-' . $key . '-error' ) . '"';
	}

	/**
	 * AJAX submission endpoint.
	 */
	public static function handle_ajax() {
		$result = self::process();
		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}
		wp_send_json_error( array( 'errors' => $result['errors'] ), 400 );
	}

	/**
	 * Non-JS submission: process, then redirect on success (PRG) or let the
	 * shortcode re-render with errors.
	 */
	public static function handle_post() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! isset( $_POST['action'] ) || self::ACTION !== $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
			return;
		}

		self::$result = self::process();

		if ( self::$result['success'] ) {
			$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'scf_sent', '1', $redirect ) );
			exit;
		}
	}

	/**
	 * Validate, spam-check, store and email a submission.
	 *
	 * @return array{success:bool,message?:string,errors?:array,values?:array}
	 */
	public static function process() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
		$raw = wp_unslash( $_POST );
		// phpcs:enable

		$values = array(
			'name'    => isset( $raw['scf_name'] ) ? sanitize_text_field( $raw['scf_name'] ) : '',
			'email'   => isset( $raw['scf_email'] ) ? sanitize_email( $raw['scf_email'] ) : '',
			'phone'   => isset( $raw['scf_phone'] ) ? sanitize_text_field( $raw['scf_phone'] ) : '',
			'subject' => isset( $raw['scf_subject'] ) ? sanitize_text_field( $raw['scf_subject'] ) : '',
			'message' => isset( $raw['scf_message'] ) ? sanitize_textarea_field( $raw['scf_message'] ) : '',
			'consent' => ! empty( $raw['scf_consent'] ) ? '1' : '',
		);

		$fail = static function ( $errors ) use ( $values ) {
			return array(
				'success' => false,
				'errors'  => $errors,
				'values'  => $values,
			);
		};

		// --- Security / spam checks -------------------------------------
		if ( ! isset( $raw[ self::NONCE ] ) || ! wp_verify_nonce( $raw[ self::NONCE ], self::ACTION ) ) {
			return $fail( array( '_form' => __( 'Your session expired. Please reload the page and try again.', 'simple-contact-form' ) ) );
		}

		$is_spam = ! empty( $raw['scf_website'] )
			|| empty( $raw['scf_ts'] )
			|| ( time() - (int) $raw['scf_ts'] ) < self::MIN_SECONDS;

		/**
		 * Filter whether a submission is spam (hook in Akismet, reCAPTCHA, etc.).
		 *
		 * @param bool  $is_spam Current verdict.
		 * @param array $values  Sanitized values.
		 */
		if ( apply_filters( 'scf_is_spam', $is_spam, $values ) ) {
			// Pretend success so bots don't learn anything.
			return array(
				'success' => true,
				'message' => SCF_Settings::get( 'success_message' ),
			);
		}

		$ip         = self::client_ip();
		$rate_key   = 'scf_rl_' . md5( $ip );
		$rate_limit = (int) SCF_Settings::get( 'rate_limit' );
		$count      = (int) get_transient( $rate_key );
		if ( $rate_limit > 0 && $count >= $rate_limit ) {
			return $fail( array( '_form' => __( 'Too many messages sent. Please try again later.', 'simple-contact-form' ) ) );
		}

		// --- Field validation -------------------------------------------
		$errors = array();
		if ( '' === $values['name'] ) {
			$errors['name'] = __( 'Please enter your name.', 'simple-contact-form' );
		} elseif ( mb_strlen( $values['name'] ) > 100 ) {
			$errors['name'] = __( 'Name is too long.', 'simple-contact-form' );
		}
		if ( '' === $values['email'] || ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'simple-contact-form' );
		}
		if ( '' !== $values['phone'] && ! preg_match( '/^[0-9+().\-\s]{5,30}$/', $values['phone'] ) ) {
			$errors['phone'] = __( 'Please enter a valid phone number.', 'simple-contact-form' );
		}
		if ( '' === trim( $values['message'] ) ) {
			$errors['message'] = __( 'Please enter a message.', 'simple-contact-form' );
		} elseif ( mb_strlen( $values['message'] ) > 5000 ) {
			$errors['message'] = __( 'Message is too long (5000 characters max).', 'simple-contact-form' );
		}
		if ( '' !== trim( SCF_Settings::get( 'consent_text' ) ) && '1' !== $values['consent'] ) {
			$errors['consent'] = __( 'Please confirm to continue.', 'simple-contact-form' );
		}

		/**
		 * Filter validation errors (add custom rules).
		 *
		 * @param array $errors Field => message.
		 * @param array $values Sanitized values.
		 */
		$errors = apply_filters( 'scf_validate', $errors, $values );

		if ( $errors ) {
			return $fail( $errors );
		}

		// --- Persist & send ---------------------------------------------
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		$data = $values + array(
			'page_url'   => esc_url_raw( (string) wp_get_referer() ),
			'ip'         => $ip,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		if ( SCF_Settings::get( 'store_submissions' ) ) {
			SCF_Submissions::store( $data );
		}

		$sent = self::send_email( $data );

		/**
		 * Fires after a valid submission is processed.
		 *
		 * @param array $data Submission data.
		 * @param bool  $sent Whether the email was accepted by wp_mail().
		 */
		do_action( 'scf_submission', $data, $sent );

		if ( ! $sent && ! SCF_Settings::get( 'store_submissions' ) ) {
			return $fail( array( '_form' => __( 'Sorry, your message could not be sent. Please try again later.', 'simple-contact-form' ) ) );
		}

		return array(
			'success' => true,
			'message' => SCF_Settings::get( 'success_message' ),
		);
	}

	/**
	 * Email the site owner.
	 *
	 * @param array $data Submission data.
	 * @return bool
	 */
	private static function send_email( array $data ) {
		$settings = SCF_Settings::all();
		$to       = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $settings['recipient'] ) ) ) );
		if ( ! $to ) {
			$to = array( get_option( 'admin_email' ) );
		}

		$subject = trim( $settings['subject_prefix'] . ' ' . ( $data['subject'] ? $data['subject'] : sprintf(
			/* translators: %s: sender name */
			__( 'New message from %s', 'simple-contact-form' ),
			$data['name']
		) ) );

		$lines = array(
			__( 'Name', 'simple-contact-form' )    . ': ' . $data['name'],
			__( 'Email', 'simple-contact-form' )   . ': ' . $data['email'],
		);
		if ( $data['phone'] ) {
			$lines[] = __( 'Phone', 'simple-contact-form' ) . ': ' . $data['phone'];
		}
		if ( $data['subject'] ) {
			$lines[] = __( 'Subject', 'simple-contact-form' ) . ': ' . $data['subject'];
		}
		$lines[] = '';
		$lines[] = $data['message'];
		$lines[] = '';
		$lines[] = '--';
		$lines[] = __( 'Sent from', 'simple-contact-form' ) . ': ' . $data['page_url'];
		$lines[] = __( 'IP', 'simple-contact-form' ) . ': ' . $data['ip'];

		// Strip anything that could break out of a header line.
		$reply_name = str_replace( array( "\r", "\n", '"', '<', '>' ), '', $data['name'] );
		$headers    = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: "%s" <%s>', $reply_name, $data['email'] ),
		);

		/**
		 * Filter the email before sending.
		 *
		 * @param array $mail { to, subject, body, headers }.
		 * @param array $data Submission data.
		 */
		$mail = apply_filters(
			'scf_email',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => implode( "\n", $lines ),
				'headers' => $headers,
			),
			$data
		);

		return wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );
	}

	/**
	 * Best-effort client IP. Uses REMOTE_ADDR only (proxy headers are spoofable);
	 * filter `scf_client_ip` if you sit behind a trusted proxy/CDN.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = apply_filters( 'scf_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
