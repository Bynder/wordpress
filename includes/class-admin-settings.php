<?php

namespace Webdam;

/**
 * WebDAM Admin Settings Page (Settings > WebDAM)
 */
class Admin {

	/**
	 * @var Used to store an internal reference for the class
	 */
	private static $_instance;

	private $admin_settings_page_url;

	private $admin_set_cookie_page_url;

	/**
	 * Fetch THE instance of the admin object
	 *
	 * @param null
	 *
	 * @return Admin object instance
	 */
	static function get_instance( ) {

		if ( empty( static::$_instance ) ){

			self::$_instance = new self();
		}

		// Return the single/cached instance of the class
		return self::$_instance;
	}

	/**
	 * Set WordPress hooks
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function __construct() {

		// Fetch our cached page urls so they're available in this class
		// before the admin_menu hook fires
		$admin_settings_page_url = get_transient( 'WebDAM\Admin\settings_page_url' );
		$admin_set_cookie_page_url = get_transient( 'WebDAM\Admin\set_cookie_page_url' );

		if ( ! empty( $admin_settings_page_url ) ) {
			$this->admin_settings_page_url = $admin_settings_page_url;
		}

		if ( ! empty( $admin_set_cookie_page_url ) ) {
			$this->admin_set_cookie_page_url = $admin_set_cookie_page_url;
		}


		// Display a notice when WebDAM settings are needed
		if ( ! \webdam_get_settings() ) {
			add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
		}

		// Create the Settings > Webdam page
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'create_settings_page_elements' ) );
		add_action( 'update_option_webdam_settings', array( $this, 'update_option_webdam_settings' ), 10, 3 );

		// Enqueue styles and scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
	}

	/**
	 * Show a notice to admin users to update plugin options
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function show_admin_notice() {
		/*
		 * We want to show notice only to those users who can update options,
		 * for everyone else the notice won't mean much if anything.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		} ?>

		<div class="error">
			<p>
				<strong><?php

					printf(
						wp_kses( __( 'Please update the <a href="%s">%s</a> with your information.', 'webdam' ) ),
						esc_url( $this->settings_admin_page_url ),
						esc_html_e( 'WebDAM Settings', 'webdam' )
					); ?>

				</strong>
			</p>
		</div><?php
	}

	/**
	 * Create the settings page(s)
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function action_admin_menu() {

		// Create the 'WebDAM' Settings page
		add_options_page(
			'WebDAM Settings',
			'WebDAM',
			'manage_options',
			'webdam-settings',
			array( $this, 'create_settings_page' )
		);

		$this->admin_settings_page_url = add_query_arg(
			'page',
			'webdam-settings',
			admin_url( 'options-general.php' )
		);

		// Cache the settings page url so it's available before these hooks execute
		set_transient( 'WebDAM\Admin\settings_page_url', $this->admin_settings_page_url );

		// Create the soon-to-be hidden admin set cookie page
		// This page is used to set the chosen asset cookie
		// it needs to be accessible, but hidden from the admin menu
		add_options_page(
			'WebDAM Set Cookie',
			'WebDAM Set Cookie',
			'manage_options',
			'webdam-set-cookie',
			array( $this, 'create_set_cookie_page' )
		);

		$this->admin_set_cookie_page_url = add_query_arg(
			'page',
			'webdam-set-cookie',
			admin_url( 'options-general.php' )
		);

		// Cache the settings page cookie url so it's available before these hooks execute
		set_transient( 'WebDAM\Admin\set_cookie_page_url', $this->admin_set_cookie_page_url );

		// Hide the admin set cookie page
		remove_submenu_page( 'options-general.php', 'webdam-set-cookie' );
	}

	/**
	 * Getter function to obtain the admin settings page url
	 *
	 * @return string Unescaped url
	 */
	public function get_admin_settings_page_url() {
		return $this->admin_settings_page_url;
	}

	/**
	 * Getter function to obtain the admin settings page url
	 *
	 * @return string Unescaped url
	 */
	public function get_admin_set_cookie_page_url() {
		return $this->admin_set_cookie_page_url;
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function wp_enqueue_scripts() {

		// Only enqueue these items on our settings pages
		if ( ! empty( $_GET['page'] ) ) {

			if ( 'webdam-settings' === $_GET['page'] ) {

				// Enqueue the WebDAM admin settings CSS
				wp_enqueue_style(
					'webdam-admin-settings',
					WEBDAM_PLUGIN_URL . 'assets/webdam-admin-settings.css',
					array(),
					false,
					'screen'
				);
			}

			if ( 'webdam-set-cookie' == $_GET['page'] ) {

				// Enqueue the WebDAM cookie setting JavaScript
				wp_enqueue_script(
					'webdam-set-cookie',
					WEBDAM_PLUGIN_URL . 'assets/webdam-set-cookie.js',
					array(),
					false,
					true
				);

			}
		}
	}

	/**
	 * Render the hidden admin set cookie page
	 *
	 * This page is used to set the chosen asset cookie
	 * it needs to be accessible, but hidden from the admin menu
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function create_set_cookie_page() { ?>

		<p>
			<?php esc_html_e( 'This page is used to set the WebDAM chosen asset cookie.', 'webdam' ); ?>
			<br />
			<?php esc_html_e( 'It needs to be accessible, but is purposefully hidden from the admin menu.', 'webdam' ); ?>
		</p>

		<?php
	}

	/**
	 * Register our setting
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function create_settings_page_elements() {

		/*
		 * WordPress on WPCOM renders JavaScript on the admin
		 * pages which prevents admin pages from being loaded within
		 * an iframe. Because we're loading the webdam-set-cookie page
		 * within a hidden iframe to set the webdam cookie, we need
		 * to disable that enforcement. For reference, the JavaScript
		 * looks like this:
		 *
		 * <script type="text/javascript">
		 *	if (window.top !== window.self) {
		 *		window.top.location.href = window.self.location.href; }
		 *	</script>
		 *
		 * There are two possible ways to prevent this script from being outputted.
		 *
		 * Either define IFRAME_REQUEST on the iframed admin page before
		 * the admin_print_scripts action. (Which is what we're doing here on admin_init)
		 *
		 * Or, use a URL with frame-nonce GET param and a value obtained from
		 * wpcom_get_frame_nonce() function's call. Eg.:
		 * add_query_arg( array( 'frame-nonce' => wpcom_get_frame_nonce() ), $url );
		 *
		 * Because this restriction has to do with using a specific admin page,
		 * it makes sense to use the named const approach rather than the nonce method.
		 */
		if ( 'webdam-set-cookie' === $_GET['page'] ) {
			define( 'IFRAME_REQUEST', true );
		}

		/**
		 * Register the webdam_settings setting
		 *
		 * sanitize_option_webdam_settings
		 */
		register_setting(
			'webdam_settings',
			'webdam_settings',
			array( $this, 'webdam_settings_input_sanitization' )
		);
	}

	/**
	 * Create the settings page contents/form fields
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function create_settings_page() {

		// Set some default items
		$api_status_text = __( 'API NOT Authenticated', 'webdam' );
		$api_status_class = 'not-authenticated';

		// Fetch our existing settings
		$settings = get_option( 'webdam_settings' );

		// Determine if we're authenticated or not
		if ( \webdam_is_authenticated() ) {
			$api_status_text = __( 'API Authenticated', 'webdam' );
			$api_status_class = 'authenticated';
		} ?>
		
		<div class="webdam-settings wrap <?php echo esc_attr( $api_status_class ); ?>">
			<h2><?php echo esc_html_e( 'WebDAM Settings', 'webdam' ); ?></h2>
			<form method="post" action="options.php"><?php

				// This prints out all hidden setting fields
				settings_fields( 'webdam_settings' ); ?>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="webdam_account_domain"><?php esc_html_e( 'Domain', 'webdam' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="webdam_account_domain"
									name="webdam_settings[webdam_account_domain]"
									value="<?php echo ! empty( $settings['webdam_account_domain'] ) ? esc_attr( $settings['webdam_account_domain'] ) : ''; ?>"
									placeholder="yourdomain.webdamdb.com">
							</td>
						</tr><tr id="api-status-row">
							<th scope="row">
								<label for="webdam_enable_api"><?php esc_html_e( 'API Status', 'webdam' ); ?></label>
							</th><td>
								<p class="api-authentication-status">
									<span class="<?php echo esc_attr( $api_status_class ); ?>">
										<?php echo esc_html( $api_status_text ); ?>
									</span>
								</p><?php

								// Display link to authenticate if needed
								if ( \webdam_is_authenticated() ) {

									// API is authentication—good to go

								} else {
									// Once we have client_id/secret show the api auth_code link
									if ( empty( $settings['api_client_secret'] ) || empty( $settings['api_client_id'] ) ) {

										printf(
											'<p>%s<p><p><a target="_blank" href="%s" title="%s">%s</a></p>',
											esc_html__( 'Enter your WebDAM Client ID and Secret Keys below.', 'webdam' ),
											esc_url( 'http://webdam.com/DAM-software/API/' ),
											esc_attr__( 'Obtain your API keys', 'webdam' ),
											esc_html__( 'Click here to obtain your keys.', 'webdam' )
										);
									} else {
										// Display the authorization link
										// this link takes user to webdam to login and authorize our API
										printf(
											'<p><a href="%s" title="%s" class="%s">%s</a></p>',
											esc_url( \webdam_get_authorization_url() ),
											esc_attr__( 'Authorize WebDAM', 'webdam' ),
											esc_attr( 'authorization-url' ),
											esc_html__( 'Click here to authorize API access to your WebDAM account.', 'webdam' )
										);
										// Display a notice for the user to enter their api keys
									}
								} ?>
							</td>
						</tr><tr id="api-client-id-row">
							<th scope="row"><?php esc_html_e( 'API Client ID', 'webdam' ); ?></th>
							<td>
								<input
									type="text"
									id="api_client_id"
									name="webdam_settings[api_client_id]"
									value="<?php echo ! empty( $settings['api_client_id'] ) ? esc_attr( $settings['api_client_id'] ) : ''; ?>">
							</td>
						</tr><tr id="api-client-secret-row">
							<th scope="row"><?php esc_html_e( 'API Client Secret', 'webdam' ); ?></th>
							<td>
								<input
									type="text"
									id="api_client_secret"
									name="webdam_settings[api_client_secret]"
									value="<?php echo ! empty( $settings['api_client_secret'] ) ? esc_attr( $settings['api_client_secret'] ) : ''; ?>">
							</td>
						</tr><tr id="enable-sideloading-row">
							<th scope="row"><?php esc_html_e( 'Save chosen assets in the Media Library', 'webdam' ); ?></th>
							<td>
								<input
									type="checkbox"
									id="enable-sideloading"
									name="webdam_settings[enable_sideloading]"
									value="1"
									<?php isset( $settings['enable_sideloading'] ) ? checked( $settings['enable_sideloading'], 1 ) : ''; ?>>
							</td>
						</tr>
					</tbody>
				</table><?php

				submit_button(); ?>

			</form>
		</div><?php

	}

	/**
	 * Sanitize each setting field as it's saved
	 *
	 * @param array $input Contains all settings fields as array keys
	 *
	 * @return array
	 */
	public function webdam_settings_input_sanitization( $input ) {

		$old_settings = get_option( 'webdam_settings' );
		$new_settings = array();

		$response_type = 'updated';
		$response_message = '';

		// Save the domain
		if( isset( $input['webdam_account_domain'] ) ) {
			$new_settings['webdam_account_domain'] = sanitize_text_field( $input['webdam_account_domain'] );
		}

		// Save the client id
		if( isset( $input['api_client_id'] ) ) {
			$new_settings['api_client_id'] = sanitize_text_field( $input['api_client_id'] );
		}

		// Save the client secret
		if( isset( $input['api_client_secret'] ) ) {
			$new_settings['api_client_secret'] = sanitize_text_field( $input['api_client_secret'] );
		}

		// Save the sideloading preference
		if( isset( $input['enable_sideloading'] ) ) {
			$new_settings['enable_sideloading'] = intval( $input['enable_sideloading'] );
		}

		// Determine what status message to display to the user
		if ( $new_settings === $old_settings ) {
			$response_message = __( 'No changes made.', 'webdam' );
		} else {
			$response_message = __( 'Settings saved.', 'webdam' );

			if ( ! empty( $old_settings ) ) {
				$response_type = 'error';
				$response_message = __( 'Looks like you\'ve changed your WebDAM settings. Please authorize the API again.', 'webdam' );
			}
		}

		// Display a message to the user
		if ( ! empty( $response_message ) ) {

			global $wp_settings_errors;

			$show_settings_error = true;

			// Set a flag if our settings error is already scheduled to display
			// This can occur if this sanitization callback gets called twice.
			foreach ( $wp_settings_errors as $error ) {
				if ( 'webdam-settings-respsonse' === $error['setting'] ) {
					if ( $response_message === $error['message'] ) {
						$show_settings_error = false;
					}
				}
			}

			// Only show the new settings error if the same message
			// has not already been scheduled to display
			if ( $show_settings_error ) {
				add_settings_error(
					'webdam-settings-respsonse',
					esc_attr( 'webdam-settings-' . $response_type ),
					$response_message,
					$response_type
				);
			}
		}

		// Update the values stored in our database
		return $new_settings;
	}

	/**
	 * Fires after the webdam_settings option is saved
	 * using the core "update_option_{$option}" action
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $new_value The new option value.
	 * @param string $option    Option name.
	 */
	public function update_option_webdam_settings( $old_value, $new_value, $option ) {

		// If new settings are being saved broadcast that changes are being saved
		if ( $new_value !== $old_value ) {
			do_action( 'webdam-saved-new-settings' );
		}
	}
}

Admin::get_instance();

// EOF