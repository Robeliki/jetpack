<?php

class Publicize extends Publicize_Base {

	function __construct() {
		parent::__construct();

		add_action( 'load-settings_page_sharing', array( $this, 'admin_page_load' ) );

		add_action( 'wp_ajax_publicize_tumblr_options_page', array( $this, 'options_page_tumblr' ) );
		add_action( 'wp_ajax_publicize_facebook_options_page', array( $this, 'options_page_facebook' ) );
		add_action( 'wp_ajax_publicize_twitter_options_page', array( $this, 'options_page_twitter' ) );
		add_action( 'wp_ajax_publicize_linkedin_options_page', array( $this, 'options_page_linkedin' ) );
		add_action( 'wp_ajax_publicize_yahoo_options_page', array( $this, 'options_page_yahoo' ) );

		add_action( 'wp_ajax_publicize_tumblr_options_save', array( $this, 'options_save_tumblr' ) );
		add_action( 'wp_ajax_publicize_facebook_options_save', array( $this, 'options_save_facebook' ) );
		add_action( 'wp_ajax_publicize_twitter_options_save', array( $this, 'options_save_twitter' ) );
		add_action( 'wp_ajax_publicize_linkedin_options_save', array( $this, 'options_save_linkedin' ) );
		add_action( 'wp_ajax_publicize_yahoo_options_save', array( $this, 'options_save_yahoo' ) );

		add_action( 'load-settings_page_sharing', array( $this, 'force_user_connection' ) );
	}

	function force_user_connection() {
		global $current_user;
		$user_token = Jetpack_Data::get_access_token( $current_user->ID );
		$is_user_connected = $user_token && !is_wp_error( $user_token );

		// If the user is already connected via Jetpack, then we're good
		if ( $is_user_connected )
			return;

		// If they're not connected, then remove the Publicize UI and tell them they need to connect first
		global $publicize_ui;
		remove_action( 'pre_admin_screen_sharing', array( $publicize_ui, 'admin_page' ) );

		Jetpack::init()->admin_styles();
		add_action( 'pre_admin_screen_sharing', array( $this, 'admin_page_warning' ), 1 );
	}

	function admin_page_warning() {
		$jetpack = Jetpack::init();
		?>
				<div id="message" class="updated jetpack-message jp-connect">
					<div class="jetpack-wrap-container">
						<div class="jetpack-text-container">
							<h4>
								<p><?php _e( "To use Publicize you&#8217;ll need to link your account here to your WordPress.com account using the button to the right. If you don't have one yet you can sign up for free, in just a few seconds.", 'jetpack' ) ?></p>
							</h4>
						</div>
						<div class="jetpack-install-container">
							<p class="submit"><a href="<?php echo $jetpack->build_connect_url(); ?>" class="button-connector" id="wpcom-connect"><?php _e( 'Link account with WordPress.com', 'jetpack' ); ?></a></p>
						</div>
					</div>
				</div>
		<?php
	}

	// @todo only load current users conncetions and _user_id = 0
	function get_connections( $service_name, $_blog_id, $_user_id ) {
		$connections = Jetpack::get_option( 'publicize_connections' );
		$connections_to_return = array();
		if ( !empty( $connections ) && is_array( $connections ) ) {
			if ( !empty( $connections[$service_name] ) ) {
				foreach( $connections[$service_name] as $id => $connection ) {
					if ( 0 == $connection['connection_data']['user_id'] || $this->user_id() == $connection['connection_data']['user_id'] ) {
						$connections_to_return[$id] = $connection;
					}
				}
			}
			return $connections_to_return;
		}
		return false;
	}

	function get_connection_id( $connection ) {
		return $connection['connection_data']['id'];
	}

	function get_connection_meta( $connection ) {
		$connection['user_id'] = $connection['connection_data']['user_id']; // Allows for shared connections
		return $connection;
	}

	function admin_page_load() {
		if ( isset( $_GET['action'] ) ) {
			if ( isset( $_GET['service'] ) )
				$service_name = $_GET['service'];

			switch ( $_GET['action'] ) {
				case 'request':
					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );

					$verification = Jetpack::create_nonce( 'publicize' );

					$user = wp_get_current_user();
					$redirect = $this->api_url( $service_name, urlencode_deep( array(
						'action'       => 'request',
						'redirect_uri' => add_query_arg( array( 'action' => 'done' ), menu_page_url( 'sharing', false ) ),
						'for'          => 'publicize', // required flag that says this connection is intended for publicize
						'siteurl'      => site_url(),
						'state'        => $user->ID,
						'blog_id'      => Jetpack::get_option('id'),
						'secret_1'	   => $verification['secret_1'],
						'secret_2'     => $verification['secret_2'],
						'eol'		   => $verification['eol'],
					) ) );
					wp_redirect( $redirect );
					exit;
				break;

				case 'completed':
					// Jetpack blog requests Publicize Connections via new XML-RPC method
					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.fetchPublicizeConnections' );

					if ( !$xml->isError() ) {
						$response = $xml->getResponse();
						Jetpack::update_option( 'publicize_connections', $response );
					}
				break;

				case 'delete':
					$id = $_GET['id'];

					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );

					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.deletePublicizeConnection', $id );

					if ( !$xml->isError() ) {
						$response = $xml->getResponse();
						Jetpack::update_option( 'publicize_connections', $response );
					}
				break;
			}
		}
	}

	function globalization() {
		if ( 'on' == $_REQUEST['global'] ) {
			$id = $_REQUEST['connection'];

			if ( !current_user_can( Publicize::GLOBAL_CAP ) )
				return;

			Jetpack::load_xml_rpc_client();
			$xml = new Jetpack_IXR_Client();
			$xml->query( 'jetpack.globalizePublicizeConnection', $id, 'globalize' );

			if ( !$xml->isError() ) {
				$response = $xml->getResponse();
				Jetpack::update_option( 'publicize_connections', $response );
			}
		}
	}

	/**
	* Gets a URL to the public-api actions. Works like WP's admin_url
	*
	* @param string $service Shortname of a specific service.
	* @return URL to specific public-api process
	*/
	// on WordPress.com this is/calls Keyring::admin_url
	function api_url( $service = false, $params = array() ) {
		$url = apply_filters( 'publicize_api_url', 'https://public-api.wordpress.com/connect/?jetpack=publicize' );

		if ( $service )
			$url = add_query_arg( array( 'service' => $service ), $url );

		if ( count ( $params ) )
			$url = add_query_arg( $params, $url );

		return $url;
	}

	function connect_url( $service_name ) {
		return add_query_arg( array(
			'action'   => 'request',
			'service'  =>  $service_name,
			'kr_nonce' => wp_create_nonce( 'keyring-request' ),
			'nonce'    => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );
	}

	function disconnect_url( $service_name, $id ) {
		return add_query_arg( array (
			'action'   => 'delete',
			'service'  => $service_name,
			'id'       => $id,
			'kr_nonce' => wp_create_nonce( 'keyring-request' ),
			'nonce'    => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );
	}

	function get_services( $filter ) {
		if ( !in_array( $filter, array( 'all', 'connected' ) ) )
			$filter = 'all';
			
		$services = array(
				'facebook' => array(),
				'twitter'  => array(),
				'linkedin' => array(),
				'tumblr'   => array(),
				'yahoo'    => array(),
		);

		$connected_services = array();

		if ( 'all' == $filter ) {
			return $services;
		} else {
			foreach ( $services as $service => $empty ) {
				$connections = $this->get_connections( $service, false, false );
				if ( $connections )
					$connected_services[$service] = $connections;
			}
			return $connected_services;
		}
	}

	function get_connection( $service, $id, $_blog_id, $_user_id ) {
		// @todo implement
	}

	function flag_post_for_publicize( $new_status, $old_status, $post ) {
		// Stub only. Doesn't need to do anything on Jetpack Client
	}

	/**
	* Options Code
	*/

	function options_page_facebook() {
		$connected_services = Jetpack::get_option( 'publicize_connections' );
		$connection = $connected_services['facebook'][$_REQUEST['connection']];
		$options_to_show = $connection['connection_data']['meta']['options_responses'];

		// Nonce check
		check_admin_referer( 'options_page_facebook_' . $_REQUEST['connection'] );

		$me = $options_to_show[0];
		$pages = $options_to_show[1]['data'];

		$profile_checked = true;
		$page_selected = false;

		if ( !empty( $connection['connection_data']['meta']['facebook_page'] ) ) {
			$found = false;
			if ( is_array( $pages->data ) ) {
				foreach ( $pages->data as $page ) {
					if ( $page->id == $connection['connection_data']['meta']['facebook_page'] ) {
						$found = true;
						break;
					}
				}
			}

			if ( $found ) {
				$profile_checked = false;
				$page_selected = $connection['connection_data']['meta']['facebook_page'];
			}
		}

		?>

		<div id="thickbox-content">

			<?php
			ob_start();
			Publicize_UI::connected_notice( 'Facebook' );
			$update_notice = ob_get_clean();

			if ( ! empty( $update_notice ) )
				echo $update_notice;
			?>

			<p><?php _e( 'Publicize to my <strong>Facebook Wall</strong>:' ) ?></p>
			<table id="option-profile">
				<tbody>
					<tr>
						<td class="radio"><input type="radio" name="option" data-type="profile" id="<?php echo esc_attr( $me['id'] ) ?>" value="" <?php checked( $profile_checked, true ); ?> /></td>
						<td class="thumbnail"><label for="<?php echo esc_attr( $me['id'] ) ?>"><img src="<?php echo esc_url( $me['picture']['data']['url'] ) ?>" width="50" height="50" /></label></td>
						<td class="details"><label for="<?php echo esc_attr( $me['id'] ) ?>"><?php echo esc_html( $me['name'] ) ?></label></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $pages ) : ?>

				<p><?php _e( 'Publicize to my <strong>Facebook Page</strong>:' ) ?></p>
				<table id="option-fb-fanpage">
					<tbody>

						<?php foreach ( $pages as $i => $page ) : ?>
							<?php if ( ! isset( $page['perms'] ) ) { continue; } ?>
							<?php if ( ! ( $i % 2 ) ) : ?>
								<tr>
							<?php endif; ?>
									<td class="radio"><input type="radio" name="option" data-type="page" id="<?php echo intval( $page['id'] ) ?>" value="<?php echo intval( $page['id'] ) ?>" <?php checked( $page_selected && $page_selected == $page['id'], true ); ?> /></td>
									<td class="thumbnail"><label for="<?php echo esc_attr( $page['id'] ) ?>"><img src="<?php echo esc_url( str_replace( '_s', '_q', $page['picture']['data']['url'] ) ) ?>" width="50" height="50" /></label></td>
									<td class="details">
										<label for="<?php echo esc_attr( $page['id'] ) ?>">
											<span class="name"><?php echo esc_html( $page['name'] ) ?></span><br/>
											<span class="category"><?php echo esc_html( $page['category'] ) ?></span>
										</label>
									</td>
							<?php if ( ( $i % 2 ) || ( $i == count( $pages ) - 1 ) ): ?>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>

					</tbody>
				</table>

			<?php endif; ?>

			<?php Publicize_UI::global_checkbox( 'facebook', $_REQUEST['connection'] ); ?>

			<p style="text-align: center;">
				<input type="submit" value="<?php esc_attr_e( 'OK', 'jetpack' ) ?>" class="button fb-options save-options" name="save" data-connection="<?php echo esc_attr( $_REQUEST['connection'] ); ?>" rel="<?php echo wp_create_nonce('save_fb_token_' . $_REQUEST['connection'] ) ?>" />
			</p><br/>
		</div>

		<?php
	}

	function options_save_facebook() {
		// Nonce check
		check_admin_referer( 'save_fb_token_' . $_REQUEST['connection'] );

		$id = $_POST['connection'];

		// Check for a numeric page ID
		$page_id = (int) $_POST['selected_id'];
		if ( !ctype_digit( $page_id ) )
			die( 'Security check' );

		if ( isset( $_POST['selected_id'] ) && 'profile' == $_POST['type'] ) {
			// Publish to User Wall/Profile
			$options = array(
				'facebook_page'       => null,
				'facebook_profile'    => true
			);

		} else {
			if ( 'page' != $_POST['type'] || !isset( $_POST['selected_id'] ) ) {
				return;
			}

			// Publish to Page
			$options = array(
				'facebook_page'       => $page_id,
				'facebook_profile'    => null
			);
		}

		Jetpack::load_xml_rpc_client();
		$xml = new Jetpack_IXR_Client();
		$xml->query( 'jetpack.setPublicizeOptions', $id, $options );

		if ( !$xml->isError() ) {
			$response = $xml->getResponse();
			Jetpack::update_option( 'publicize_connections', $response );
		}

		$this->globalization();
	}

	function options_page_tumblr() {
		// Nonce check
		check_admin_referer( 'options_page_tumblr_' . $_REQUEST['connection'] );

		$connected_services = Jetpack::get_option( 'publicize_connections' );
		$connection = $connected_services['tumblr'][$_POST['connection']];
		$options_to_show = $connection['connection_data']['meta']['options_responses'];
		$request = $options_to_show[0];

		$blogs = $request['response']['user']['blogs'];

		$blog_selected = false;

		if ( !empty( $connection['connection_data']['meta']['tumblr_base_hostname'] ) ) {
			foreach ( $blogs as $blog ) {
				if ( $connection['connection_data']['meta']['tumblr_base_hostname'] == $this->get_basehostname( $blog['url'] ) ) {
					$blog_selected = $connection['connection_data']['meta']['tumblr_base_hostname'];
					break;
				}
			}

		}

		// Use their Primary blog if they haven't selected one yet
		if ( !$blog_selected ) {
			foreach ( $blogs as $blog ) {
				if ( $blog['primary'] )
					$blog_selected = $this->get_basehostname( $blog['url'] );
			}
		} ?>

		<div id="thickbox-content">

			<?php
			ob_start();
			Publicize_UI::connected_notice( 'Tumblr' );
			$update_notice = ob_get_clean();

			if ( ! empty( $update_notice ) )
				echo $update_notice;
			?>

			<p><?php _e( 'Publicize to my <strong>Tumblr blog</strong>:' ) ?></p>

			<ul id="option-tumblr-blog">

			<?php
			foreach ( $blogs as $blog ) {
				$url = $this->get_basehostname( $blog['url'] ); ?>
				<li>
					<input type="radio" name="option" data-type="blog" id="<?php echo esc_attr( $url ) ?>" value="<?php echo esc_attr( $url ) ?>" <?php checked( $blog_selected == $url, true ); ?> />
					<label for="<?php echo esc_attr( $url ) ?>"><span class="name"><?php echo esc_html( $blog['title'] ) ?></span></label>
				</li>
			<?php } ?>

			</ul>

			<?php Publicize_UI::global_checkbox( 'tumblr', $_REQUEST['connection'] ); ?>

			<p style="text-align: center;">
				<input type="submit" value="<?php esc_attr_e( 'OK', 'jetpack' ) ?>" class="button tumblr-options save-options" name="save" data-connection="<?php echo esc_attr( $_REQUEST['connection'] ); ?>" rel="<?php echo wp_create_nonce( 'save_tumblr_blog_' . $_REQUEST['connection'] ) ?>" />
			</p> <br />
		</div>

		<?php
	}

	function get_basehostname( $url ) {
		return parse_url( $url, PHP_URL_HOST );
	}

	function options_save_tumblr() {
		// Nonce check
		check_admin_referer( 'save_tumblr_blog_' . $_REQUEST['connection'] );

		$id = $_POST['connection'];

		$options = array( 'tumblr_base_hostname' => $_POST['selected_id'] );

		Jetpack::load_xml_rpc_client();
		$xml = new Jetpack_IXR_Client();
		$xml->query( 'jetpack.setPublicizeOptions', $id, $options );

		if ( !$xml->isError() ) {
			$response = $xml->getResponse();
			Jetpack::update_option( 'publicize_connections', $response );
		}

		$this->globalization();
	}

	function options_page_twitter() { Publicize_UI::options_page_other( 'twitter' ); }
	function options_page_linkedin() { Publicize_UI::options_page_other( 'linkedin' ); }
	function options_page_yahoo() { Publicize_UI::options_page_other( 'yahoo' ); }

	function options_save_twitter() { $this->options_save_other( 'twitter' ); }
	function options_save_linkedin() { $this->options_save_other( 'linkedin' ); }
	function options_save_yahoo() { $this->options_save_other( 'yahoo' ); }

	function options_save_other( $service_name ) {
		// Nonce check
		check_admin_referer( 'save_' . $service_name . '_token_' . $_REQUEST['connection'] );
		$this->globalization();
	}

	// stub
	function refresh_tokens_message() {

	}
}
