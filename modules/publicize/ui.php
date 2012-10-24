<?php

/**
* Only user facing pieces of Publicize are found here.
*/
class Publicize_UI {

	/**
	* Contains an instance of class 'publicize' which loads Keyring, sets up services, etc.
	*/
	var $publicize;

	/**
	* Hooks into WordPress to display the various pieces of UI and load our assets
	*/
	function __construct() {
		global $publicize;

		$this->publicize = $publicize = new Publicize;

		// assets (css, js)
		add_action( 'load-settings_page_sharing', array( &$this, 'load_assets' ) );
		add_action( 'admin_head-post.php', array( &$this, 'post_page_metabox_assets' ) );
		add_action( 'admin_head-post-new.php', array( &$this, 'post_page_metabox_assets' ) );

		// management of publicize (sharing screen, ajax/lightbox popup, and metabox on post screen)
		add_action( 'pre_admin_screen_sharing', array( &$this, 'admin_page' ) );
		add_action( 'post_submitbox_misc_actions', array( &$this, 'post_page_metabox' ) );
	}

	/**
	* If the ShareDaddy plugin is not active we need to add the sharing settings page to the menu still
	*/
	function sharing_menu() {
		add_submenu_page( 'options-general.php', __( 'Sharing Settings', 'jetpack' ), __( 'Sharing', 'jetpack' ), 'publish_posts', 'sharing', array( &$this, 'management_page' ) );
	}


	/**
	* Management page to load if Sharedaddy is not active so the 'pre_admin_screen_sharing' action exists.
	*/
	function management_page() { ?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br /></div>
			<h2><?php _e( 'Sharing Settings', 'jetpack' ); ?></h2>

				<?php do_action( 'pre_admin_screen_sharing' ) ?>

		</div> <?php
	}

	/**
	* styling for the sharing screen and popups
	* JS for the options and switching
	*/
	function load_assets() {
		wp_enqueue_script(
			'publicize',
			plugins_url( 'assets/publicize.js', __FILE__ ),
			array( 'jquery', 'thickbox' ),
			'20121019'
		);

		wp_enqueue_style(
			'publicize',
			plugins_url( 'assets/publicize.css', __FILE__ ),
			array(),
			'20120925'
		);

		add_thickbox();
	}

	function connected_notice( $service_name ) { ?>
		<div class='updated'>
			<p><?php printf( __( 'You have successfully connected your blog with your %s account.', 'jetpack' ), Publicize::get_service_label( $service_name ) ); ?></p>
		</div><?php
	}

	/**
	* Lists the current user's publicized accounts for the blog
	* looks exactly like Publicize v1 for now, UI and functionality updates will come after the move to keyring
	*/
	function admin_page() {
		$_blog_id = get_current_blog_id();
		?>

  		<form action="" id="publicize-form">
	  		<h3 id="publicize"><?php _e( 'Publicize', 'jetpack' ) ?></h3>
	  		<p>
	  			<?php esc_html_e( 'Connect your blog to popular social networking sites and automatically share new posts with your friends.', 'jetpack' ) ?>
	  			<?php esc_html_e( 'You can make a connection for just yourself or for all users on your blog. Shared connections are marked with the (Shared) text.', 'jetpack' ); ?>
	  		</p>

	  		<div id="publicize-services-block">
		  		<?php
		  		foreach ( $this->publicize->get_services( 'all' ) as $name => $service ) :
		  			$connect_url = $this->publicize->connect_url( $name );
		  			?>
		  			<div class="publicize-service-entry">
			  			<div id="<?php echo esc_attr( $name ); ?>" class="publicize-service-left">
			  				<a href="<?php echo esc_url( $connect_url ); ?>"><span class="pub-logos" id="<?php echo esc_attr( $name ); ?>">&nbsp;</span></a>
			  			</div>

			  			<div class="publicize-service-right">
			  				<?php if ( $this->publicize->is_enabled( $name ) && $connections = $this->publicize->get_connections( $name ) ) : ?>
			  					<ul>
				  					<?php
									foreach( $connections as $c ) :
										$id = $this->publicize->get_connection_id( $c );
										$disconnect_url = $this->publicize->disconnect_url( $name, $id );

										$cmeta = $this->publicize->get_connection_meta( $c );
										$profile_link = $this->publicize->get_profile_link( $name, $c );
										$connection_display = $this->publicize->get_display_name( $name, $c );

										$options_nonce = wp_create_nonce( 'options_page_' . $name . '_' . $id ); ?>

										<?php if ( $this->publicize->show_options_popup( $name, $c ) ): ?>
										<script type="text/javascript">
										jQuery(document).ready( function($) {
											showOptionsPage.call(
											this,
											'<?php echo esc_js( $name ); ?>',
											'<?php echo esc_js( $options_nonce ); ?>',
											'<?php echo esc_js( $id ); ?>'
											);
										} );
										</script>
										<?php endif; ?>

										<li>
											<?php
											if ( !empty( $profile_link ) ) : ?>
												<a class="publicize-profile-link" href="<?php echo esc_attr( $profile_link ); ?>">
													<?php echo esc_html( $connection_display ); ?>
												</a><?php
											else :
												echo esc_html( $connection_display );
											endif;
											?>

											<?php if ( 0 == $cmeta['connection_data']['user_id'] ) : ?>
												<small>(<?php esc_html_e( 'Shared', 'jetpack' ); ?>)</small>

												<?php if ( current_user_can( Publicize::GLOBAL_CAP ) ) : ?>
													<a class="pub-disconnect-button" title="<?php esc_html_e( 'Disconnect', 'jetpack' ); ?>" href="<?php echo esc_url( $disconnect_url ); ?>">×</a>
												<?php endif; ?>

											<?php else : ?>
												<a class="pub-disconnect-button" title="<?php esc_html_e( 'Disconnect', 'jetpack' ); ?>" href="<?php echo esc_url( $disconnect_url ); ?>">×</a>
											<?php endif; ?>
										</li>

										<?php
									endforeach;
				  					?>
				  				</ul>
				  			<?php endif; ?>
					  		<a id="<?php echo esc_attr( $name ); ?>" class="publicize-add-connection" href="<?php echo esc_url( $connect_url); ?>"><?php esc_html_e( sprintf( __( 'Add new %s connection.', 'jetpack' ), $this->publicize->get_service_label( $name ) ) ); ?></a>
			  			</div>
			  		</div>
				<?php endforeach; ?>
	  		</div>

			<?php wp_nonce_field( "wpas_posts_{$_blog_id}", "_wpas_posts_{$_blog_id}_nonce" ); ?>
			<input type="hidden" id="wpas_ajax_blog_id" name="wpas_ajax_blog_id" value="<?php echo $_blog_id; ?>" />
	  	</form><?php

	}

	function global_checkbox( $service_name, $id ) {
		if ( current_user_can( Publicize::GLOBAL_CAP ) ) : ?>
			<p>
				<input id="globalize_<?php echo $service_name; ?>" type="checkbox" name="global" value="<?php echo wp_create_nonce( 'publicize-globalize-' . $id ) ?>" />
				<label for="globalize_<?php echo $service_name; ?>"><?php _e( 'Make this connection available to all users of this blog?', 'jetpack' ); ?></label>
			</p>
		<?php endif;
	}

	function broken_connection( $service_name, $id ) { ?>
		<div id="thickbox-content">
			<div class='error'>
				<p><?php printf( __( 'There was a problem connecting to %s. Please disconnect and try again.', 'jetpack' ), Publicize::get_service_label( $service_name ) ); ?></p>
			</div>
		</div><?php
	}

	function options_page_other( $service_name ) {
		// Nonce check
		check_admin_referer( "options_page_{$service_name}_" . $_REQUEST['connection'] );
		?>
		<div id="thickbox-content">
			<?php
			ob_start();
			Publicize_UI::connected_notice( $service_name );
			$update_notice = ob_get_clean();
			if ( ! empty( $update_notice ) )
				echo $update_notice;
			?>

			<?php Publicize_UI::global_checkbox( $service_name, $_REQUEST['connection'] ); ?>

			<p style="text-align: center;">
				<input type="submit" value="<?php esc_attr_e( 'OK', 'jetpack' ) ?>" class="button <?php echo $service_name; ?>-options save-options" name="save" data-connection="<?php echo esc_attr( $_REQUEST['connection'] ); ?>" rel="<?php echo wp_create_nonce( 'save_'.$service_name.'_token_' . $_REQUEST['connection'] ) ?>" />
			</p> <br />
		</div>
		<?php
	}

	/**
	* CSS for styling the publicize message box and counter that displays on the post page.
	* There is also some Javascript for length counting and some basic display effects.
	*/
	function post_page_metabox_assets() {
		global $post;
		$user_id = empty( $post->post_author ) ? $GLOBALS['user_ID'] : $post->post_author;

		$default_prefix = $this->publicize->default_prefix;
		$default_prefix = preg_replace( '/%([0-9])\$s/', "' + %\\1\$s + '", esc_js( $default_prefix ) );

		$default_message = $this->publicize->default_message;
		$default_message = preg_replace( '/%([0-9])\$s/', "' + %\\1\$s + '", esc_js( $default_message ) );

		$default_suffix = $this->publicize->default_suffix;
		$default_suffix = preg_replace( '/%([0-9])\$s/', "' + %\\1\$s + '", esc_js( $default_suffix ) ); ?>

<script type="text/javascript">
jQuery( function($) {
	var wpasTitleCounter    = $( '#wpas-title-counter' ),
	    wpasTwitterCheckbox = $( '#wpas-submit-twitter' ).size(),
	    wpasTitle = $('#wpas-title').keyup( function() {
		var length = wpasTitle.val().length;
		wpasTitleCounter.text( length );
		if ( wpasTwitterCheckbox && length > 140 ) {
			wpasTitleCounter.addClass( 'wpas-twitter-length-limit' );
		} else {
			wpasTitleCounter.removeClass( 'wpas-twitter-length-limit' );
		}
	    } ),
	    authClick = false;

	$('#publicize-disconnected-form-show').click( function() {
		$('#publicize-form').slideDown( 'fast' );
		$(this).hide();
	} );

	$('#publicize-disconnected-form-hide').click( function() {
		$('#publicize-form').slideUp( 'fast' );
		$('#publicize-disconnected-form-show').show();
	} );

	$('#publicize-form-edit').click( function() {
		$('#publicize-form').slideDown( 'fast', function() {
			wpasTitle.focus();
			if ( !wpasTitle.text() ) {
				var url = $('#shortlink').size() ? $('#shortlink').val() : '';

				var defaultMessage = $.trim( '<?php printf( $default_prefix, 'url' ); printf( $default_message, '$("#title").val()', 'url' ); printf( $default_suffix, 'url' ); ?>' );

				wpasTitle.append( defaultMessage );

				var selBeg = defaultMessage.indexOf( $("#title").val() );
				if ( selBeg < 0 ) {
					selBeg = 0;
					selEnd = 0;
				} else {
					selEnd = selBeg + $("#title").val().length;
				}

				var domObj = wpasTitle.get(0);
				if ( domObj.setSelectionRange ) {
					domObj.setSelectionRange( selBeg, selEnd );
				} else if ( domObj.createTextRange ) {
					var r = domObj.createTextRange();
					r.moveStart( 'character', selBeg );
					r.moveEnd( 'character', selEnd );
					r.select();
				}
			}
			wpasTitle.keyup();
		} );
		$('#publicize-defaults').hide();
		$(this).hide();
		return false;
	} );

	$('#publicize-form-hide').click( function() {
		var newList = $.map( $('#publicize-form').slideUp( 'fast' ).find( ':checked' ), function( el ) {
			return $.trim( $(el).parent( 'label' ).text() );
		} );
		$('#publicize-defaults').html( '<strong>' + newList.join( '</strong>, <strong>' ) + '</strong>' ).show();
		$('#publicize-form-edit').show();
		return false;
	} );

	$('.authorize-link').click( function() {
		if ( authClick ) {
			return false;
		}
		authClick = true;
		$(this).after( '<img src="images/loading.gif" class="alignleft" style="margin: 0 .5em" />' );
		$.ajaxSetup( { async: false } );
		autosave();
		return true;
	} );

	$( '.pub-service' ).click( function() {
		var service = $(this).data( 'service' ),
		    fakebox = '<input id="wpas-submit-' + service + '" type="hidden" value="1" name="wpas[submit][' + service + ']" />';
		$( '#add-publicize-check' ).append( fakebox );
	} );
} );
</script>

<style type="text/css">
#publicize {
	line-height: 1.5;
}
#publicize ul {
	margin: 4px 0 4px 6px;
}
#publicize li {
	margin: 0;
}
#publicize textarea {
	margin: 4px 0 0;
	width: 100%
}
#publicize ul.not-connected {
	list-style: square;
	padding-left: 1em;
}
.post-new-php .authorize-link, .post-php .authorize-link {
	line-height: 1.5em;
}
.post-new-php .authorize-message, .post-php .authorize-message {
	margin-bottom: 0;
}
#poststuff #publicize .updated p {
	margin: .5em 0;
}
.wpas-twitter-length-limit {
	color: red;
}
</style><?php
	}

	/**
	* Controls the metabox that is displayed on the post page
	* Allows the user to customize the message that will be sent out to the social network, as well as pick which
	* networks to publish to. Also displays the character counter and some other information.
	*/
	function post_page_metabox() {
		global $post;

		if ( 'post' != $post->post_type )
			return;

		$user_id = empty( $post->post_author ) ? $GLOBALS['user_ID'] : $post->post_author;
		$services = $this->publicize->get_services( 'connected' );
		$available_services = $this->publicize->get_services( 'all' );

		if ( ! is_array( $available_services ) )
			$available_services = array();

		if ( ! is_array( $services ) )
			$services = array();

		$active = array(); ?>

		<div id="publicize" class="misc-pub-section misc-pub-section-last">
			<?php
			_e( 'Publicize:', 'jetpack' );

			if ( 0 < count( $services ) ) :
					ob_start();
				?>

				<div id="publicize-form" class="hide-if-js">
					<ul>

					<?php
					foreach ( $services as $name => $connections ) {
						foreach ( $connections as $connection ) {
							if ( !$continue = apply_filters( 'wpas_submit_post?', true, $post->ID, $name ) )
								continue;

							if ( !empty( $connection->unique_id ) )
								$unique_id = $connection->unique_id;
							else if ( !empty( $connection['connection_data']['token_id'] ) )
								$unique_id = $connection['connection_data']['token_id'];

							// Should we be skipping this one?
							$skip = get_post_meta( $post->ID, $this->publicize->POST_SKIP . $unique_id, true );

							// Was this connections (OR, old-format service) already Publicized to?
							$done = ( 1 == get_post_meta( $post->ID, $this->publicize->POST_DONE . $unique_id, true ) ||  1 == get_post_meta( $post->ID, $this->publicize->POST_DONE . $name, true ) ); // New and old style flags

							// If this one has already been publicized to, don't let it happen again
							$disabled = '';
							if ( $done )
								$disabled = ' disabled="disabled"';

							// If this is a global connection and this user doesn't have enough permissions to modify
							// those connections, don't let them change it
							$cmeta = $this->publicize->get_connection_meta( $connection );

							$hidden_checkbox = false;
							if ( !$done && ( 0 == $cmeta['user_id'] && !current_user_can( Publicize::GLOBAL_CAP ) ) ) {
								$disabled = ' disabled="disabled"';
								$hidden_checkbox = true;
							}

							// Post was published prior to plugin activation
							if ( $skip && 'publish' == $post->post_status && !$done )
								$checked = false;

							$label = sprintf(
								_x( '%1$s: %2$s', 'Service: Account connected as' ),
								esc_html( $this->publicize->get_service_label( $name ) ),
								esc_html( $this->publicize->get_display_name( $name, $connection ) )
							);
							if ( !$skip || $done ) {
								$active[] = $label;
							}
							?>
							<li>
								<label for="wpas-submit-<?php echo esc_attr( $unique_id ); ?>">
									<input type="checkbox" name="wpas[submit][<?php echo $unique_id; ?>]" id="wpas-submit-<?php echo $unique_id; ?>" value="1" <?php
										checked( true, $skip != 1 || $done );
										echo $disabled;
									?> />
									<?php
									if ( $hidden_checkbox ) {
										// Need to submit a value to force a global connection to post
										echo '<input type="hidden" name="wpas[submit][' . $unique_id . ']" value="1" />';
									}
									echo esc_html( $label );
									?>
								</label>
							</li>
							<?php
						}
					}

					if ( $title = get_post_meta( $post->ID, $this->publicize->POST_MESS, true ) )
						$title = esc_html( $title );
					else
						$title = '';
 					?>

					</ul>

					<label for="wpas-title"><?php _e( 'Custom Message:', 'jetpack' ); ?></label>
					<span id="wpas-title-counter" class="alignright hide-if-no-js">0</span>

					<textarea name="wpas_title" id="wpas-title"><?php echo $title; ?></textarea>

					<a href="#" class="hide-if-no-js" id="publicize-form-hide"><?php _e( 'Hide', 'jetpack' ); ?></a>
					<input type="hidden" name="wpas[0]" value="1" />

				</div> <?php // #publicize-form

				$this->publicize->refresh_tokens_message();

				$publicize_form = ob_get_clean();
			else :
				echo "&nbsp;" . __( 'Not Connected', 'jetpack' );
					ob_start();
				?>

				<div id="publicize-form" class="hide-if-js">
					<div id="add-publicize-check" style="display: none;"></div>

					<strong><?php _e( 'Connect to', 'jetpack' ); ?>:</strong>

					<ul class="not-connected">
						<?php foreach ( $available_services as $name => $service ) :
							$service_name = "Yahoo! Updates" == $this->publicize->get_service_label( $service_name ) ? 'yahoo' : strtolower( $this->publicize->get_service_label( $service_name ) );
						?>
						<li>
							<a class="pub-service" data-service="<?php esc_attr_e( $name ); ?>" title="<?php esc_attr_e( sprintf( __( 'Connect and share your posts on %s', 'jetpack' ), $this->publicize->get_service_label( $service_name ) ) ); ?>" target="_blank" href="<?php echo $this->publicize->connect_url( $service_name ); ?>">
								<?php echo esc_html( $this->publicize->get_service_label( $service_name ) ); ?>
							</a>
						</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( 0 < count( $services ) ) : ?>
						<a href="#" class="hide-if-no-js" id="publicize-form-hide"><?php _e( 'Hide', 'jetpack' ); ?></a>
					<?php else : ?>
						<a href="#" class="hide-if-no-js" id="publicize-disconnected-form-hide"><?php _e( 'Hide', 'jetpack' ); ?></a>
					<?php endif; ?>
				</div> <?php // #publicize-form

				$publicize_form = ob_get_clean();
			endif;
			?>

			<span id="publicize-defaults"><strong><?php echo join( '</strong>, <strong>', array_map( 'esc_html', $active ) ); ?></strong></span>

			<?php if ( 0 < count( $services ) ) : ?>
				<a href="#" id="publicize-form-edit"><?php _e( 'Edit', 'jetpack' ); ?></a>&nbsp;<a href="<?php echo admin_url( 'options-general.php?page=sharing' ); ?>" target="_blank"><?php _e( 'Settings', 'jetpack' ); ?></a><br />
			<?php else : ?>
				<a href="#" id="publicize-disconnected-form-show"><?php _e( 'Show', 'jetpack' ); ?></a><br />
			<?php endif; ?>

			<?php echo apply_filters( 'publicize_form', $publicize_form ); ?>

		</div> <?php // #publicize
	}

}
