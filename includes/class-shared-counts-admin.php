<?php
/**
 * Admin class.
 *
 * Contains functionality for the admin dashboard (is_admin()).
 *
 * @package    SharedCounts
 * @author     Bill Erickson & Jared Atchison
 * @since      1.0.0
 * @license    GPL-2.0+
 * @copyright  Copyright (c) 2017
 */
class Shared_Counts_Admin {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Plugin settings.
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_menu', array( $this, 'settings_add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'settings_assets' ) );
		add_filter( 'plugin_action_links_' . SHARED_COUNTS_BASE, array( $this, 'settings_link' ) );
		add_filter( 'plugin_row_meta',  array( $this, 'author_links' ), 10, 2 );

		// Post metabox.
		add_action( 'admin_init', array( $this, 'metabox_add' ) );
		add_action( 'wp_ajax_shared_counts_refresh', array( $this, 'metabox_ajax' ) );
		add_action( 'wp_ajax_shared_counts_delete', array( $this, 'metabox_ajax_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'metabox_assets' ) );
		add_action( 'save_post', array( $this, 'metabox_save' ), 10, 2 );
	}

	// ********************************************************************** //
	//
	// Settings - these methods wrangle our settings and related functionality.
	//
	// ********************************************************************** //

	/**
	 * Return the settings options values.
	 *
	 * Used globally. Options are filterable.
	 *
	 * @since 1.0.0
	 */
	public function options() {

		$options = get_option( 'shared_counts_options', $this->settings_default() );

		return apply_filters( 'shared_counts_options', $options );
	}

	/**
	 * Initialize the Settings page options.
	 *
	 * @since 1.0.0
	 */
	public function settings_init() {

		register_setting( 'shared_counts_options', 'shared_counts_options', array( $this, 'settings_sanitize' ) );
	}

	/**
	 * Add the Settings page.
	 *
	 * @since 1.0.0
	 */
	public function settings_add() {

		add_options_page( __( 'Shared Counts Settings', 'shared-counts' ), __( 'Shared Counts', 'shared-counts' ), 'manage_options', 'shared_counts_options', array( $this, 'settings_page' ) );
	}

	/**
	 * Build the Settings page.
	 *
	 * @since 1.0.0
	 */
	public function settings_page() {

		?>
		<div class="wrap">

			<h1><?php esc_html_e( 'Shared Count Settings', 'shared-counts' ); ?></h1>

			<p><?php esc_html_e( 'Welcome to Shared Counts. Our goal is to display share count badges on your site, with just the right amount of options, in a manner that keeps your site fast.', 'shared-counts' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="shared-counts-settings-form">

				<?php
				settings_fields( 'shared_counts_options' );
				$options = get_option( 'shared_counts_options', $this->settings_default() );
				?>

				<!-- Count Settings, as in the numbers -->

				<h2 class="title"><?php esc_html_e( 'Share Counts', 'shared-counts' ); ?></h2>

				<table class="form-table">

					<!-- Count Source -->
					<tr valign="top" id="shared-counts-setting-row-count_source">
						<th scope="row"><label for="shared-counts-setting-count_source"><?php esc_html_e( 'Count Source', 'shared-counts' ); ?></label></th>
						<td>
							<select name="shared_counts_options[count_source]" id="shared-counts-setting-count_source">
								<?php
								$opts = array(
									'none'        => __( 'None', 'shared-counts' ),
									'sharedcount' => __( 'SharedCount.com', 'shared-counts' ),
									'native'      => __( 'Native', 'shared-counts' ),
								);
								foreach ( $opts as $key => $label ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $key ),
										selected( $key, $this->settings_value( 'count_source' ), false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description" style="margin-bottom: 10px;">
								<?php esc_html_e( 'This determines the source of the share counts.', 'shared-counts' ); ?>
							</p>
							<p class="description" style="margin-bottom: 10px;">
								<?php _e( '<strong>None</strong>: no counts are displayed and your website will not connect to an outside API, useful if you want simple badges without the counts or associated overhead.', 'shared-counts' ); ?>
							</p>
							<p class="description" style="margin-bottom: 10px;">
								<?php _e( '<strong>SharedCount.com</strong>: counts are retrieved from the SharedCount.com API. This is our recommended option for those wanting share counts. This method allows fetching all counts for with only 2 API calls, so it is best for performance.', 'shared-counts' ); ?>
							</p>
							<p class="description">
								<?php _e( '<strong>Native</strong>: counts are retrieved from their native service. Eg Facebook API for Facebook counts, Pinterest API for Pin counts, etc. This method is more "expensive" since depending on the counts desired uses more API calls (6 API calls if all services are enabled).', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- ShareCount API Key (ShareCount only) -->
					<tr valign="top" id="shared-counts-setting-row-sharedcount_key">
						<th scope="row"><label for="shared-counts-setting-sharedcount_key"><?php esc_html_e( 'SharedCount API Key', 'shared-counts' ); ?></label></th>
						<td>
							<input type="text" name="shared_counts_options[sharedcount_key]" value="<?php echo esc_attr( $this->settings_value( 'sharedcount_key' ) ); ?>" class="regular-text" />
							<p class="description">
								<?php _e( 'Sign up on SharedCount.com for your (free) API key. SharedCount provides 1,000 API requests daily, or 10,000 request daily if you connect to Facebook. With our caching, this works with sites that receive millions of page views a month and is adaquate for most sites.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- Twitter Counts (SharedCount only) -->
					<tr valign="top" id="shared-counts-setting-row-twitter_counts">
						<th scope="row"><label for="shared-counts-setting-twitter_counts"><?php esc_html_e( 'Include Twitter Counts', 'shared-counts' ); ?></label></th>
						<td>
							<input type="checkbox" name="shared_counts_options[twitter_counts]" value="1" id="shared-counts-setting-twitter_counts" <?php checked( $this->settings_value( 'twitter_counts' ), 1 ); ?>>
							<p class="description">
								<?php esc_html_e( 'SharedCount.com does not provide Twitter counts. Checking this option will seperately pull Twitter counts from NewShareCounts.com, which is the service that tracks Twitter counts.', 'shared-counts' ); ?><br><a href="http://newsharecounts.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sign up for NewShareCounts.com (free).', 'shared-counts' ); ?></a>
							</p>
						</td>
					</tr>

					<!-- Retrieve Share Counts From (Native only) -->
					<tr valign="top" id="shared-counts-setting-row-service">
						<th scope="row"><?php esc_html_e( 'Retrieve Share Counts From', 'shared-counts' ); ?></th>
						<td>
							<fieldset>
							<?php
							$services = $this->query_services();
							foreach ( $services as $service ) {
								echo '<label for="shared-counts-setting-service-' . sanitize_html_class( $service['key'] ) . '">';
									printf(
										'<input type="checkbox" name="shared_counts_options[query_services][]" value="%s" id="shared-counts-setting-service-%s" %s>',
										esc_attr( $service['key'] ),
										sanitize_html_class( $service['key'] ),
										checked( in_array( $service['key'], $this->settings_value( 'query_services' ), true ), true, false )
									);
									echo esc_html( $service['label'] );
								echo '</label><br />';
							}
							?>
							</fieldset>
							<p class="description">
								<?php esc_html_e( 'Each service requires a separate API request, so using many services could cause performance issues. Alternately, consider using SharedCounts for the count source.', 'shared-counts' ); ?>
								<br><br><?php esc_html_e( 'Twitter does provide counts; Twitter share counts will pull from NewShareCounts.com, which is the service that tracks Twitter counts.', 'shared-counts' ); ?><br><a href="http://newsharecounts.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sign up for NewShareCounts.com (free).', 'shared-counts' ); ?></a>
							</p>
						</td>
					</tr>

					<!-- Facebook Access Token (Native only) -->
					<tr valign="top" id="shared-counts-setting-row-fb_access_token">
						<th scope="row"><label for="shared-counts-setting-fb_access_token"><?php esc_html_e( 'Facebook Access Token', 'shared-counts' ); ?></label></th>
						<td>
							<input type="text" name="shared_counts_options[fb_access_token]" value="<?php echo esc_attr( $this->settings_value( 'fb_access_token' ) ); ?>" id="shared-counts-setting-fb_access_token" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'If you have trouble receiving Facebook counts, you may need to setup an access token.', 'shared-counts' ); ?><br><a href="https://smashballoon.com/custom-facebook-feed/access-token/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Follow these instructions.', 'shared-counts' ); ?></a>
							</p>
						</td>
					</tr>

					<!-- Count Total Only (SharedCount / Native only) -->
					<tr valign="top" id="shared-counts-setting-row-total_only">
						<th scope="row"><label for="shared-counts-setting-total_only"><?php esc_html_e( 'Count Total Only', 'shared-counts' ); ?></label></th>
						<td>
							<input type="checkbox" name="shared_counts_options[total_only]" value="1" id="shared-counts-setting-total_only" <?php checked( $this->settings_value( 'total_only' ), 1 ); ?>>
							<p class="description">
								<?php esc_html_e( 'Check this if you would like to only display the share count total. This is useful if you would like to display the total counts (via Total Counts button) but not the individual counts for each service.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- Empty Counts (SharedCount / Native only) -->
					<tr valign="top" id="shared-counts-setting-row-hide_empty">
						<th scope="row"><label for="shared-counts-setting-hide_empty"><?php esc_html_e( 'Hide Empty Counts', 'shared-counts' ); ?></label></th>
						<td>
							<input type="checkbox" name="shared_counts_options[hide_empty]" value="1" id="shared-counts-setting-hide_empty" <?php checked( $this->settings_value( 'hide_empty' ), 1 ); ?>>
							<p class="description">
								<?php esc_html_e( 'Optionally, empty counts (0) can be hidden.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- Preserve non-HTTPS counts -->
					<?php if ( apply_filters( 'shared_counts_admin_https', is_ssl() ) ) : ?>
					<tr valign="top" id="shared-counts-setting-row-preserve_http">
						<th scope="row"><label for="shared-counts-setting-preserve_http"><?php esc_html_e( 'Preserve HTTP Counts', 'shared-counts' ); ?></label></th>
						<td>
							<input type="checkbox" name="shared_counts_options[preserve_http]" value="1" id="shared-counts-setting-preserve_http" <?php checked( $this->settings_value( 'preserve_http' ), 1 ); ?>>
							<p class="description">
								<?php esc_html_e( 'Check this if you would also like to include non-SSL (http://) share counts. This is useful if the site was originally used http:// but has since moved to https://. Enabling this option will double the API calls. ', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>
					<?php endif; ?>

				</table>

				<hr />

				<!-- Display settings -->

				<h2 class="title"><?php esc_html_e( 'Display', 'shared-counts' ); ?></h2>

				<table class="form-table">

					<!-- Buttons Display -->
					<tr valign="top" id="shared-counts-setting-row-included_services">
						<th scope="row"><?php esc_html_e( 'Share Buttons to Display', 'shared-counts' ); ?></th>
						<td>
							<select name="shared_counts_options[included_services][]" id="shared-counts-setting-included_services" class="shared-counts-services" multiple="multiple" style="min-width:350px;">
								<?php
								$services = array(
									'facebook'        => 'Facebook',
									'facebook_likes'  => 'Facebook Like',
									'facebook_shares' => 'Facebook Share',
									'twitter'         => 'Twitter',
									'pinterest'       => 'Pinterest',
									'linkedin'        => 'LinkedIn',
									'google'          => 'Google+',
									'stumbleupon'     => 'Stumble Upon',
									'included_total'  => 'Total Counts',
									'print'           => 'Print',
									'email'           => 'Email',
								);
								$services = apply_filters( 'shared_counts_admin_services', $services );
								$selected = $this->settings_value( 'included_services' );

								// Output selected elements first to preserve order.
								foreach ( $selected as $opt ) {
									if ( isset( $services[ $opt ] ) ) {
										printf(
											'<option value="%s" selected>%s</option>',
											esc_attr( $opt ),
											esc_html( $services[ $opt ] )
										);
										unset( $services[ $opt ] );
									}
								}
								// Now output other items.
								foreach ( $services as $key => $label ) {
									printf(
										'<option value="%s">%s</option>',
										esc_attr( $key ),
										esc_html( $label )
									);
								}
								?>
							</select>
						</td>
					</tr>

					<!-- Enable Email reCAPTCHA (if email button is configured) -->
					<tr valign="top" id="shared-counts-setting-row-recaptcha">
						<th scope="row"><label for="shared-counts-setting-recaptcha"><?php esc_html_e( 'Enable Email reCAPTCHA', 'shared-counts' ); ?></label></th>
						<td>
							<input type="checkbox" name="shared_counts_options[recaptcha]" value="1" id="shared-counts-setting-recaptcha" <?php checked( $this->settings_value( 'recaptcha' ), 1 ); ?>>
							<p class="description">
								<?php esc_html_e( 'Highly recommended, Google\'s v2 reCAPTCHA will protect the email sharing feature from abuse.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- Google reCAPTCHA Site key (if recaptcha is enabled) -->
					<tr valign="top" id="shared-counts-setting-row-recaptcha_site_key">
						<th scope="row"><label for="shared-counts-setting-recaptcha_site_key"><?php esc_html_e( 'reCAPTCHA Site Key', 'shared-counts' ); ?></label></th>
						<td>
							<input type="text" name="shared_counts_options[recaptcha_site_key]" value="<?php echo esc_attr( $this->settings_value( 'recaptcha_site_key' ) ); ?>" id="shared-counts-setting-recaptcha_site_key" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'After signing up for Google\'s v2 reCAPTCHA (free), provide your site key here.', 'shared-counts' ); ?><br><a href="https://www.google.com/recaptcha/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn more.', 'shared-counts' ); ?></a>
							</p>
						</td>
					</tr>

					<!-- Google reCAPTCHA Secret key (if recaptcha is enabled) -->
					<tr valign="top" id="shared-counts-setting-row-recaptcha_secret_key">
						<th scope="row"><label for="shared-counts-setting-recaptcha_secret_key"><?php esc_html_e( 'reCAPTCHA Secret Key', 'shared-counts' ); ?></label></th>
						<td>
							<input type="text" name="shared_counts_options[recaptcha_secret_key]" value="<?php echo esc_attr( $this->settings_value( 'recaptcha_secret_key' ) ); ?>" id="shared-counts-setting-recaptcha_secret_key" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'After signing up for Google\'s v2 reCAPTCHA (free), provide your secret key here.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- Button style -->
					<tr valign="top" id="shared-counts-setting-row-style">
						<th scope="row"><label for="shared-counts-setting-style"><?php esc_html_e( 'Share Button Style', 'shared-counts' ); ?></label></th>
						<td>
							<select name="shared_counts_options[style]" id="shared-counts-setting-style">
								<?php
								$opts = apply_filters( 'shared_counts_styles', array(
									'fancy' => esc_html__( 'Fancy', 'shared-counts' ),
									'gss'   => esc_html__( 'Slim', 'shared-counts' ),
								) );
								foreach ( $opts as $key => $label ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $key ),
										selected( $key, $this->settings_value( 'style' ), false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php printf( __( 'Three different share button counts are available; see <a href="%s" target="_blank" rel="noopener noreferrer">the plugin page</a> for screenshots.', 'shared-counts' ), 'https://wordpress.org/plugins/shared-counts/' ); ?>
							</p>
						</td>
					</tr>

					<!-- Theme location -->
					<tr valign="top" id="shared-counts-setting-row-theme_location">
						<th scope="row"><label for="shared-counts-setting-theme_location"><?php esc_html_e( 'Theme Location', 'shared-counts' ); ?></label></th>
						<td>
							<select name="shared_counts_options[theme_location]" id="shared-counts-setting-theme_location">
								<?php
								$opts = array(
									''                     => esc_html__( 'None', 'shared-counts' ),
									'before_content'       => esc_html__( 'Before Content', 'shared-counts' ),
									'after_content'        => esc_html__( 'After Content',  'shared-counts' ),
									'before_after_content' => esc_html__( 'Before and After Content', 'shared-counts' ),
								);
								foreach ( $opts as $key => $label ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $key ),
										selected( $key, $this->settings_value( 'theme_location' ), false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Automagically add the share buttons before and/or after your post content.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

					<!-- Supported Post Types (Hide if theme location is None) -->
					<tr valign="top" id="shared-counts-setting-row-post_type">
						<th scope="row"><?php esc_html_e( 'Supported Post Types', 'shared-counts' ); ?></th>
						<td>
							<fieldset>
							<?php
							$opts = get_post_types(
								array(
									'public' => true,
								),
								'names'
							);
							if ( isset( $opts['attachment'] ) ) {
								unset( $opts['attachment'] );
							}
							foreach ( $opts as $post_type ) {
								echo '<label for="shared-counts-setting-post_type-' . sanitize_html_class( $post_type ) . '">';
									printf(
										'<input type="checkbox" name="shared_counts_options[post_type][]" value="%s" id="shared-counts-setting-post_type-%s" %s>',
										esc_attr( $post_type ),
										sanitize_html_class( $post_type ),
										checked( in_array( $post_type, $this->settings_value( 'post_type' ), true ), true, false )
									);
									echo esc_html( $post_type );
								echo '</label><br/>';
							}
							?>
							</fieldset>
							<p class="description">
								<?php esc_html_e( 'Which content type(s) you would like to display the share buttons on.', 'shared-counts' ); ?>
							</p>
						</td>
					</tr>

				</table>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes', 'shared-counts' ); ?>" />
				</p>

			</form>

		</div>
		<?php
	}

	/**
	 * Load settings page assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook
	 */
	public function settings_assets( $hook ) {

		if ( 'settings_page_shared_counts_options' === $hook ) {

			// Choices CSS.
			wp_enqueue_style(
				'choices',
				SHARED_COUNTS_URL . 'assets/css/choices.css',
				array(),
				'3.0.2'
			);

			// Select2 JS library.
			wp_enqueue_script(
				'choices',
				SHARED_COUNTS_URL . 'assets/js/choices.min.js',
				array( 'jquery' ),
				'3.0.2',
				false
			);

			// jQuery Conditions JS library.
			wp_enqueue_script(
				'jquery-conditionals',
				SHARED_COUNTS_URL . 'assets/js/jquery.conditions.min.js',
				array( 'jquery' ),
				'1.0.0',
				false
			);

			// Our settings JS.
			wp_enqueue_script(
				'share-count-settings',
				SHARED_COUNTS_URL . 'assets/js/admin-settings.js',
				array( 'jquery' ),
				SHARED_COUNTS_VERSION,
				false
			);
		}
	}

	/**
	 * Default settings values.
	 *
	 * @since 1.0.0
	 */
	public function settings_default() {

		return array(
			'count_source'         => 'none',
			'fb_access_token'      => '',
			'sharedcount_key'      => '',
			'twitter_counts'       => '',
			'style'                => '',
			'total_only'           => '',
			'hide_empty'           => '',
			'preserve_http'        => '',
			'post_type'            => array( 'post' ),
			'theme_location'       => '',
			'included_services'    => array( 'facebook', 'twitter', 'pinterest' ),
			'query_services'       => array(),
			'recaptcha'            => '',
			'recpatcha_site_key'   => '',
			'recaptcha_secret_key' => '',
		);
	}

	/**
	 * Return settings value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key
	 *
	 * @return bool|string
	 */
	public function settings_value( $key = false ) {

		$defaults = $this->settings_default();
		$options  = get_option( 'shared_counts_options', $defaults );

		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		} elseif ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		} else {
			return false;
		}
	}

	/**
	 * Query Services.
	 *
	 * @since 1.0.0
	 *
	 * @return array $services
	 */
	public function query_services() {

		$services = array(
			array(
				'key'   => 'facebook',
				'label' => 'Facebook',
			),
			array(
				'key'   => 'twitter',
				'label' => 'Twitter',
			),
			array(
				'key'   => 'pinterest',
				'label' => 'Pinterest',
			),
			array(
				'key'   => 'linkedin',
				'label' => 'LinkedIn',
			),
			array(
				'key'   => 'stumbleupon',
				'label' => 'StumbleUpon',
			),
		);

		$services = apply_filters( 'shared_counts_query_services', $services );

		return $services;
	}

	/**
	 * Sanitize saved settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	public function settings_sanitize( $input ) {

		// Reorder services based on the order they were provided.
		$input['count_source']         = sanitize_text_field( $input['count_source'] );
		$input['total_only']           = isset( $input['total_only'] ) ? '1' : '';
		$input['hide_empty']           = isset( $input['hide_empty'] ) ? '1' : '';
		$input['preserve_http']        = isset( $input['preserve_http'] ) ? '1' : '';
		$input['query_services']       = isset( $input['query_services'] ) ? array_map( 'sanitize_text_field', $input['query_services'] ) : array();
		$input['fb_access_token']      = sanitize_text_field( $input['fb_access_token'] );
		$input['sharedcount_key']      = sanitize_text_field( $input['sharedcount_key'] );
		$input['twitter_counts']       = isset( $input['twitter_counts'] ) ? '1' : '';
		$input['style']                = sanitize_text_field( $input['style'] );
		$input['post_type']            = isset( $input['post_type'] ) ? array_map( 'sanitize_text_field', $input['post_type'] ) : array();
		$input['theme_location']       = sanitize_text_field( $input['theme_location'] );
		$input['included_services']    = isset( $input['included_services'] ) ? array_map( 'sanitize_text_field', $input['included_services'] ) : array();
		$input['recaptcha']            = isset( $input['recaptcha'] ) ? '1' : '';
		$input['recaptcha_site_key']   = sanitize_text_field( $input['recaptcha_site_key'] );
		$input['recaptcha_secret_key'] = sanitize_text_field( $input['recaptcha_secret_key'] );

		return $input;
	}

	/**
	 * Add settings link to the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links
	 *
	 * @return array $links
	 */
	public function settings_link( $links ) {

		$setting_link = sprintf( '<a href="%s">%s</a>', add_query_arg( array( 'page' => 'shared_counts_options' ), admin_url( 'options-general.php' ) ), __( 'Settings', 'shared-counts' ) );
		array_unshift( $links, $setting_link );
		return $links;
	}

	/**
	 * Plugin author name links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links
	 * @param string $file
	 *
	 * @return string
	 */
	public function author_links( $links, $file ) {

		if ( strpos( $file, 'shared-counts.php' ) !== false ) {
			$links[1] = 'By <a href="http://www.billerickson.net">Bill Erickson</a> & <a href="http://www.jaredatchison.com">Jared Atchison</a>';
		}
		return $links;
	}

	// ********************************************************************** //
	//
	// Metabox - these methods register and handle the post edit metabox.
	//
	// ********************************************************************** //

	/**
	 * Initialize the metabox for supported post types.
	 *
	 * @since 1.0.0
	 */
	public function metabox_add() {

		$options = $this->options();

		// If we are not collecting share counts, disable the metabox.
		if ( ! empty( $options['count_source'] ) && 'none' === $options['count_source'] ) {
			return;
		}

		if ( ! empty( $options['post_type'] ) ) {
			$post_types = (array) $options['post_type'];
			foreach ( $post_types as $post_type ) {
				add_meta_box( 'shared-counts-metabox', __( 'Share Counts', 'shared-counts' ), array( $this, 'metabox' ), $post_type, 'side', 'low' );
			}
		}
	}

	/**
	 * Output the metabox.
	 *
	 * @since 1.0.0
	 */
	public function metabox() {

		global $post;

		// Alert user that post must be published to track share counts.
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Post must be published to view share counts.', 'shared-counts' ) . '</p>';
			return;
		}

		$counts  = get_post_meta( $post->ID, 'shared_counts', true );
		$groups  = get_post_meta( $post->ID, 'shared_counts_groups', true );

		if ( ! empty( $counts ) ) {

			// Decode the primary counts. This is the total of all possible
			// share count URLs.
			$counts = json_decode( $counts, true );

			// Output the primary counts numbers.
			echo $this->metabox_counts_group( 'total', $counts, $post->ID );

			// Show https and http groups at the top if we have them.
			if ( ! empty( $groups['http'] ) && ! empty( $groups['https'] ) ) {
				echo $this->metabox_counts_group( 'https', array(), $post->ID );
				echo $this->metabox_counts_group( 'http', array(), $post->ID );
			}

			// Output other counts.
			if ( ! empty( $groups ) ) {
				foreach ( $groups as $slug => $group ) {
					// Skip https and https groups since we output them manually
					// above already.
					if ( ! in_array( $slug, array( 'http', 'https' ) ) ) {
						echo $this->metabox_counts_group( $slug, array(), $post->ID );
					}
				}
			}

			// Display the date and time the share counts were last updated.
			$date = get_post_meta( $post->ID, 'shared_counts_datetime', true );
			$date = $date + ( get_option( 'gmt_offset' ) * 3600 );
			echo '<p class="counts-updated">' . esc_html__( 'Last updated', 'shared-counts' ) . ' <span>' . date( 'M j, Y g:ia', $date ) . '</span></p>';

		} else {

			// Current post has not fetched share counts yet.
			echo '<p class="counts-empty">' . esc_html__( 'No share counts downloaded for this post.', 'shared-counts' ) . '</p>';
		}

		// Action buttons.
		echo '<div class="button-wrap">';

			// Toggle option to add a new URL to track.
			if ( apply_filters( 'shared_counts_url_groups', true ) {
				echo '<button class="button shared-counts-refresh add" data-nonce="' . wp_create_nonce( 'shared-counts-refresh-' . $post->ID ) . '" data-postid="' . $post->ID . '">';
					esc_html_e( 'Add URL', 'shared-counts' );
				echo '</button>';
			}

			// Refresh share counts.
			echo '<button class="button shared-counts-refresh" data-nonce="' . wp_create_nonce( 'shared-counts-refresh-' . $post->ID ) . '" data-postid="' . $post->ID . '">';
				esc_html_e( 'Refresh Counts', 'shared-counts' );
			echo '</button>';

		echo '</div>';

		// Option to exclude share buttons for this post.
		$exclude   = absint( get_post_meta( $post->ID, 'shared_counts_exclude', true ) );
		$post_type = get_post_type_object( get_post_type( $post->ID ) )  ;
		echo '<p><input type="checkbox" name="shared_counts_exclude" id="shared_counts_exclude" value="1" ' . checked( 1, $exclude, false ) . ' /> <label for="shared_counts_exclude">' . esc_html__( 'Don\'t display buttons on this', 'shared-counts' ) . ' ' . strtolower( $post_type->labels->singular_name ) . '</label></p>';

		// Nonce for saving exclude setting on save.
		wp_nonce_field( 'shared_counts', 'shared_counts_nonce' );
	}

	/**
	 * Build the metabox list item counts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group
	 * @param array $counts
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function metabox_counts_group( $group = 'total', $counts = array(), $post_id ) {

		$icon    = 'total' === $group ? 'down-alt2' : 'right-alt2';
		$class   = 'total' === $group ? 'count-group-open' : 'count-group-closed';
		$options = $this->options();
		$url     = false;
		$disable = false;

		if ( 'total' === $group ) {
			$name  = esc_html__( 'Total', 'shared-counts' );
			$total = get_post_meta( $post_id, 'shared_counts_total', true );
		} else {
			$groups = get_post_meta( $post_id, 'shared_counts_groups', true );
			if ( ! empty( $groups[ $group ]['name'] ) ) {
				$name    = esc_html( $groups[ $group ]['name'] );
				$counts  = json_decode( $groups[ $group ]['counts'], true );
				$total   = $groups[ $group ]['total'];
				$url     = ! empty( $groups[ $group ]['url'] ) ? $groups[ $group ]['url'] : false;
				$disable = ! empty( $groups[ $group ]['disable'] ) ? true : false;
			}
		}

		if ( empty( $counts ) || ! is_array( $counts ) ) {
			return;
		}

		ob_start();

		// Count group wrap.
		echo '<div class="count-group ' . $class . ' ' . $group . '">';

			// Group title, delete, and display toggle.
			echo '<h3>';
				echo esc_html( $name );
				echo '<span class="total">(' . number_format( absint( $total ) ) . ')</span>';
				if ( ! in_array( $group, array( 'total', 'http', 'https' ), true ) ) {
					echo '<a href="#" class="shared-counts-refresh delete" data-group="' . esc_attr( $group ) . '" data-nonce="' . wp_create_nonce( 'shared-counts-refresh-' . $post_id ) . '" data-postid="' . $post_id . '" title="' . esc_attr__( 'Delete count group', 'shared-counts' ) . '"><span class="dashicons dashicons-dismiss"></span></a>';
				}
				echo '<a href="#" class="count-group-toggle" title="' . esc_attr__( 'Toggle count group', 'shared-counts' ) . '"><span class="dashicons dashicons-arrow-' . $icon . '"></span></a>';
			echo '</h3>';

			echo '<div class="count-details">';

				if ( $url ) {
					echo '<input type="text" value="' . esc_attr( $url ) . '" class="count-url" readonly />';
				}

				echo '<ul>';
					echo '<li>' . esc_html__( 'Facebook Total:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['Facebook']['total_count'] ) ? number_format( absint( $counts['Facebook']['total_count'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'Facebook Likes:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['Facebook']['like_count'] ) ? number_format( absint( $counts['Facebook']['like_count'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'Facebook Shares:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['Facebook']['share_count'] ) ? number_format( absint( $counts['Facebook']['share_count'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'Facebook Comments:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['Facebook']['comment_count'] ) ? number_format( absint( $counts['Facebook']['comment_count'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'Twitter:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['Twitter'] ) ? number_format( absint( $counts['Twitter'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'Pinterest:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['Pinterest'] ) ? number_format( absint( $counts['Pinterest'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'LinkedIn:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['LinkedIn'] ) ? number_format( absint( $counts['LinkedIn'] ) ) : '0' ) . '</strong></li>';
					echo '<li>' . esc_html__( 'StumbleUpon:', 'shared-counts' ) . ' <strong>' . ( ! empty( $counts['StumbleUpon'] ) ? number_format( absint( $counts['StumbleUpon'] ) ) : '0' ) . '</strong></li>';
					// Show Email shares if enabled.
					if ( in_array( 'email', $options['included_services'], true ) ) {
						echo '<li>' . esc_html__( 'Email:', 'shared-counts' ) . ' <strong>' . absint( get_post_meta( $post_id, 'shared_counts_email', true ) ) . '</strong></li>';
					}
				echo '</ul>';

				if ( ! in_array( $group, array( 'total', 'http', 'https' ), true ) ) {
					echo '<p><input type="checkbox" name="shared_counts_disable[' . $group . ']" id="shared_counts_disable_' . $group . '" value="1" ' . checked( true, $disable, false ) . ' /> <label for="shared_counts_disable_' . $group . '">' . esc_html__( 'Disable API updates.', 'shared-counts' ) . '</label></p>';
				}

			echo '</div>';

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Metabox AJAX functionality.
	 *
	 * @since 1.0.0
	 */
	public function metabox_ajax() {

		// Run a security check.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'shared-counts-refresh-' . $_POST['post_id'] ) ) {
			wp_send_json_error(
				array(
					'msg'     => esc_html__( 'Failed security.', 'shared-counts' ),
					'msgtype' => 'error',
				)
			);
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'msg'     => esc_html__( 'You do not have permission.', 'shared-counts' ),
					'msgtype' => 'error',
				)
			);
		}

		$id     = absint( $_POST['post_id'] );
		$groups = get_post_meta( $id, 'shared_counts_groups', true );
		$msg    = esc_html__( 'Share counts updated.', 'shared-counts' );

		// Empty post meta returns an empty string but we want an empty array.
		if ( ! is_array( $groups ) ) {
			$groups = array();
		}


		if ( ! empty( $_POST['group_url'] ) && ! empty( $_POST['group_name'] ) ) {
			// Check if we are are adding a new URL group.

			$msg                 = esc_html__( 'New URL added; Share counts updated.', 'shared-counts' );
			$group_id            = uniqid();
			$groups[ $group_id ] = array(
				'name'   => sanitize_text_field( $_POST['group_name'] ),
				'url'    => esc_url_raw( $_POST['group_url'] ),
				'counts' => '',
				'total'  => 0,
			);

			update_post_meta( $id, 'shared_counts_groups', $groups );

		} elseif ( ! empty( $_POST['group_delete'] ) && isset( $groups[ $_POST['group_delete'] ] ) ) {
			// Check if we are deleting a URL group.

			$msg = esc_html__( 'URL deleted; Share counts updated.', 'shared-counts' );

			unset( $groups[ $_POST['group_delete'] ] );

			update_post_meta( $id, 'shared_counts_groups', $groups );
		}

		// Force the counts to update.
		$total = shared_counts()->core->counts( $id, true, true );

		// Include the primary counts numbers.
		$counts = $this->metabox_counts_group( 'total', $total, $id );

		// Include https and http groups at the top if we have them.
		if ( ! empty( $groups['http'] ) && ! empty( $groups['https'] ) ) {
			$counts .= $this->metabox_counts_group( 'https', array(), $id );
			$counts .= $this->metabox_counts_group( 'http', array(), $id );
		}

		// Include other count groups.
		if ( ! empty( $groups ) ) {
			foreach ( $groups as $slug => $group ) {
				// Skip https and https groups since we output them manually
				// above already.
				if ( ! in_array( $slug, array( 'http', 'https' ) ) ) {
					$counts .= $this->metabox_counts_group( $slug, array(), $id );
				}
			}
		}

		wp_send_json_success( array(
			'msg'     => $msg,
			'msgtype' => 'success',
			'date'    => date( 'M j, Y g:ia', time() + ( get_option( 'gmt_offset' ) * 3600 ) ),
			'counts'  => $counts,
		) );
	}

	/**
	 * Load metabox assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook
	 */
	public function metabox_assets( $hook ) {

		global $post;

		$options = $this->options();

		if ( empty( $options['post_type'] ) ) {
			return;
		}

		if ( 'post.php' === $hook && in_array( $post->post_type, $options['post_type'], true ) ) {
			wp_enqueue_script(
				'shared-counts',
				SHARED_COUNTS_URL . 'assets/js/admin-metabox.js',
				array( 'jquery' ),
				SHARED_COUNTS_VERSION,
				false
			);
			wp_enqueue_style(
				'shared-counts',
				SHARED_COUNTS_URL . 'assets/css/admin-metabox.css',
				array(),
				SHARED_COUNTS_VERSION
			);
			// Localize JS strings.
			$args = array(
				'loading'        => esc_html__( 'Updating...', 'shared-counts' ),
				'refresh'        => esc_html__( 'Refresh Counts', 'shared-counts' ),
				'add_url'        => esc_html__( 'Add URL', 'shared-counts' ),
				'adding'         => esc_html__( 'Adding...', 'shared-counts' ),
				'url_prompt'     => esc_html__( 'Enter the full URL you would like to track.', 'shared-counts' ),
				'url_prompt_eg'  => esc_html__( 'E.g. http://your-domain.com/some-old-post-url', 'shared-counts' ),
				'name_prompt'    => esc_html__( 'Enter the nickname for the URL.', 'shared-counts' ),
				'name_prompt_eg' => esc_html__( 'E.g. "Post title typo"', 'shared-counts' ),
				'confirm_delete' => esc_html__( 'Are you sure you want to remove this URL group and the associated share counts?', 'shared-counts' ),
			);
			wp_localize_script( 'shared-counts', 'shared_counts', $args );
		}
	}

	/**
	 * Save the Metabox.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id
	 * @param object $post
	 */
	public function metabox_save( $post_id, $post ) {

		// Security check.
		if ( ! isset( $_POST['shared_counts_nonce'] ) || ! wp_verify_nonce( $_POST['shared_counts_nonce'], 'shared_counts' ) ) {
			return;
		}

		// Bail out if running an autosave, ajax, cron.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		// Bail out if the user doesn't have the correct permissions to update the slider.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Display exclude setting.
		if ( isset( $_POST['shared_counts_exclude'] ) ) {
			update_post_meta( $post_id, 'shared_counts_exclude', 1 );
		} else {
			delete_post_meta( $post_id, 'shared_counts_exclude' );
		}

		// Disable group update settings.
		$groups = get_post_meta( $post_id, 'shared_counts_groups', true );

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $slug => $group ) {
				if ( in_array( $slug, array( 'http', 'https' ) ) ) {
					continue;
				}
				if ( isset( $groups[ $slug ]['disable'] ) ) {
					unset( $groups[ $slug ]['disable'] );
				}
				if ( isset( $_POST['shared_counts_disable'][ $slug ] ) ) {
					$groups[ $slug ]['disable'] = true;
				}
			}
			update_post_meta( $post_id, 'shared_counts_groups', $groups );
		}
	}
}
