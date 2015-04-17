<?php
/*
Plugin Name: Captcha-img
Plugin URI: http://bestwebsoft.com/products/
Description: Plugin Captcha intended to prove that the visitor is a human being and not a spam robot. Plugin asks the visitor to answer a math question. Modified by @trawg to use images instead of text for the maths.
Author: BestWebSoft, trogau
Version: 4.1.0
Author URI: http://bestwebsoft.com/
License: GPLv2 or later
*/

/*  © Copyright 2015  BestWebSoft  ( http://support.bestwebsoft.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! function_exists( 'cptch_admin_menu' ) ) {
	function cptch_admin_menu() {
		bws_add_general_menu( plugin_basename( __FILE__ ) );
		add_submenu_page( 'bws_plugins', __( 'Captcha Settings', 'captcha' ), __( 'Captcha', 'captcha' ), 'manage_options', "captcha.php", 'cptch_settings_page' );
	}
}

if ( ! function_exists ( 'cptch_init' ) ) {
	function cptch_init() {
		global $cptch_plugin_info;
		/* Internationalization */
		load_plugin_textdomain( 'captcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_functions.php' );

		if ( ! $cptch_plugin_info ) {
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$cptch_plugin_info = get_plugin_data( __FILE__ );
		}

		/* Function check if plugin is compatible with current WP version  */
		bws_wp_version_check( plugin_basename( __FILE__ ), $cptch_plugin_info, "3.1" );

		if ( ! is_admin() ) 
			cptch_contact_form_options();
		
		/* Call register settings function */
		if ( ! is_admin() || ( isset( $_GET['page'] ) && "captcha.php" == $_GET['page'] ) )
			cptch_settings();
	}
}

if ( ! function_exists ( 'cptch_admin_init' ) ) {
	function cptch_admin_init() {
		global $bws_plugin_info, $cptch_plugin_info;		
		/* Add variable for bws_menu */
		if ( ! isset( $bws_plugin_info ) || empty( $bws_plugin_info ) )
			$bws_plugin_info = array( 'id' => '75', 'version' => $cptch_plugin_info["Version"] );
		
	}
}

/* Register settings function */
if ( ! function_exists( 'cptch_settings' ) ) {
	function cptch_settings() {
		global $cptch_options, $cptch_plugin_info, $cptch_option_defaults;

		$cptch_option_defaults = array(
			'plugin_option_version' 		=> $cptch_plugin_info["Version"],
			'cptch_str_key'					=>	array( 'time' => '', 'key' => '' ),
			'cptch_login_form'				=>	'1',
			'cptch_register_form'			=>	'1',
			'cptch_lost_password_form'		=>	'1',
			'cptch_comments_form'			=>	'1',
			'cptch_hide_register'			=>	'1',
			'cptch_contact_form'			=>	'0',
			'cptch_math_action_plus'		=>	'1',
			'cptch_math_action_minus'		=>	'1',
			'cptch_math_action_increase'	=>	'1',
			'cptch_label_form'				=>	'',
			'cptch_required_symbol'			=>	'*',
			'cptch_error_empty_value'		=>	__( 'Please enter a CAPTCHA value.', 'captcha' ),
			'cptch_error_incorrect_value'	=>	__( 'Please enter a valid CAPTCHA value.', 'captcha' ),
			'cptch_difficulty_number'		=>	'1',
			'cptch_difficulty_word'			=>	'1'
		);

		/* Install the option defaults */
		if ( ! get_option( 'cptch_options' ) )
			add_option( 'cptch_options', $cptch_option_defaults );

		/* Get options from the database */
		$cptch_options = get_option( 'cptch_options' );

		/* Array merge incase this version has added new options */
		if ( ! isset( $cptch_options['plugin_option_version'] ) || $cptch_options['plugin_option_version'] != $cptch_plugin_info["Version"] ) {
			$cptch_options = array_merge( $cptch_option_defaults, $cptch_options );
			$cptch_options['plugin_option_version'] = $cptch_plugin_info["Version"];
			update_option( 'cptch_options', $cptch_options );
		}		
	}
}

/* Generate key */
if ( ! function_exists( 'cptch_generate_key' ) ) {
	function cptch_generate_key( $lenght = 15 ) {
		global $cptch_options;
		/* Under the string $simbols you write all the characters you want to be used to randomly generate the code. */
		$simbols = get_bloginfo( "url" ) . time();
		$simbols_lenght = strlen( $simbols );
		$simbols_lenght--;
		$str_key = NULL;
		for ( $x = 1; $x <= $lenght; $x++ ) {
			$position = rand( 0, $simbols_lenght );
			$str_key .= substr( $simbols, $position, 1 );
		}

		$cptch_options['cptch_str_key']['key']	=	md5( $str_key );
		$cptch_options['cptch_str_key']['time']	=	time();
		update_option( 'cptch_options', $cptch_options );
	}
}

/* Add global setting for Captcha */
global $cptch_time;
$cptch_options = get_option( 'cptch_options' );
$cptch_time = time();

/* Add captcha into login form */
if ( 1 == $cptch_options['cptch_login_form'] ) {
	add_action( 'login_form', 'cptch_login_form' );
	add_filter( 'authenticate', 'cptch_login_check', 21, 1 );	
}
/* Add captcha into comments form */
if ( 1 == $cptch_options['cptch_comments_form'] ) {
	global $wp_version;
	if ( version_compare( $wp_version,'3','>=' ) ) { /* wp 3.0 + */
		add_action( 'comment_form_after_fields', 'cptch_comment_form_wp3', 1 );
		add_action( 'comment_form_logged_in_after', 'cptch_comment_form_wp3', 1 );
	}
	/* For WP before WP 3.0 */
	add_action( 'comment_form', 'cptch_comment_form' );
	add_filter( 'preprocess_comment', 'cptch_comment_post' );	 
}
/* Add captcha in the register form */
if ( 1 == $cptch_options['cptch_register_form'] ) {
	add_action( 'register_form', 'cptch_register_form' );
	add_action( 'register_post', 'cptch_register_post', 10, 3 );
	add_action( 'signup_extra_fields', 'wpmu_cptch_register_form' );
	if ( function_exists('is_multisite') ) {
		if ( is_multisite() ) {
			add_filter( 'wpmu_validate_user_signup', 'cptch_register_validate' );
		}
	}
}
/* Add captcha into lost password form */
if ( 1 == $cptch_options['cptch_lost_password_form'] ) {
	add_action( 'lostpassword_form', 'cptch_register_form' );
	add_action( 'lostpassword_post', 'cptch_lostpassword_post', 10, 3 );
}

/* Function for display captcha settings page in the admin area */
if ( ! function_exists( 'cptch_settings_page' ) ) {
	function cptch_settings_page() {
		global $cptch_options, $wp_version, $cptch_plugin_info, $cptch_option_defaults;
		$error = $message = "";

		/* These fields for the 'Enable CAPTCHA on the' block which is located at the admin setting captcha page */
		$cptch_admin_fields_enable = array (
			array( 'cptch_login_form', __( 'Login form', 'captcha' ), __( 'Login form', 'captcha' ) ),
			array( 'cptch_register_form', __( 'Registration form', 'captcha' ), __( 'Register form', 'captcha' ) ),
			array( 'cptch_lost_password_form', __( 'Reset Password form', 'captcha' ), __( 'Lost password form', 'captcha' ) ),
			array( 'cptch_comments_form', __( 'Comments form', 'captcha' ), __( 'Comments form', 'captcha') ),
			array( 'cptch_hide_register', __( 'Hide CAPTCHA in Comments form for registered users', 'captcha' ), __( 'Hide CAPTCHA in Comments form for registered users', 'captcha' ) ),		
		);

		/* These fields for the 'Arithmetic actions for CAPTCHA' block which is located at the admin setting captcha page */
		$cptch_admin_fields_actions = array (
			array( 'cptch_math_action_plus', __( 'Plus (&#43;)', 'captcha' ), __( 'Plus', 'captcha' ) ),
			array( 'cptch_math_action_minus', __( 'Minus (&minus;)', 'captcha' ), __( 'Minus', 'captcha' ) ),
			array( 'cptch_math_action_increase', __( 'Multiplication (&times;)', 'captcha' ), __( 'Multiply', 'captcha' ) ),
		);

		/* This fields for the 'Difficulty for CAPTCHA' block which is located at the admin setting captcha page */
		$cptch_admin_fields_difficulty = array (
			array( 'cptch_difficulty_number', __( 'Numbers', 'captcha' ), __( 'Numbers', 'captcha' ) ),
			array( 'cptch_difficulty_word', __( 'Words', 'captcha' ), __( 'Words', 'captcha' ) ),
		);

		if ( ! function_exists( 'get_plugins' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$all_plugins = get_plugins();
	
		/* Save data for settings page */
		if ( isset( $_REQUEST['cptch_form_submit'] ) && check_admin_referer( plugin_basename(__FILE__), 'cptch_nonce_name' ) ) {
			$cptch_request_options = array();
			
			$cptch_request_options['cptch_login_form']				=	isset( $_REQUEST['cptch_login_form'] ) ? 1 : 0;
			$cptch_request_options['cptch_register_form']			=	isset( $_REQUEST['cptch_register_form'] ) ? 1 : 0;
			$cptch_request_options['cptch_lost_password_form']		=	isset( $_REQUEST['cptch_lost_password_form'] ) ? 1 : 0;
			$cptch_request_options['cptch_comments_form'] 			=	isset( $_REQUEST['cptch_comments_form'] ) ? 1 : 0;
			$cptch_request_options['cptch_hide_register'] 			=	isset( $_REQUEST['cptch_hide_register'] ) ? 1 : 0;
			$cptch_request_options['cptch_contact_form'] 			=	isset( $_REQUEST['cptch_contact_form'] ) ? 1 : 0;

			$cptch_request_options['cptch_label_form'] 				=	isset( $_REQUEST['cptch_label_form'] ) ? stripslashes( esc_html( $_REQUEST['cptch_label_form'] ) ) : '';
			$cptch_request_options['cptch_required_symbol'] 		=	isset( $_REQUEST['cptch_required_symbol'] ) ? stripslashes( esc_html( $_REQUEST['cptch_required_symbol'] ) ) : '';

			$cptch_request_options['cptch_error_empty_value']		=	isset( $_REQUEST['cptch_error_empty_value'] ) ? stripslashes( esc_html( $_REQUEST['cptch_error_empty_value'] ) ) : '';
			$cptch_request_options['cptch_error_incorrect_value']	=	isset( $_REQUEST['cptch_error_incorrect_value'] ) ? stripslashes( esc_html( $_REQUEST['cptch_error_incorrect_value'] ) ) : '';

			if ( $cptch_request_options['cptch_error_empty_value'] == '' )
				$cptch_request_options['cptch_error_empty_value'] = $cptch_option_defaults['cptch_error_empty_value'];
			if ( $cptch_request_options['cptch_error_incorrect_value'] == '' )
				$cptch_request_options['cptch_error_incorrect_value'] = $cptch_option_defaults['cptch_error_incorrect_value'];
						
			$cptch_request_options['cptch_math_action_plus']		=	isset( $_REQUEST['cptch_math_action_plus'] ) ? 1 : 0;
			$cptch_request_options['cptch_math_action_minus'] 		=	isset( $_REQUEST['cptch_math_action_minus'] ) ? 1 : 0;
			$cptch_request_options['cptch_math_action_increase']	=	isset( $_REQUEST['cptch_math_action_increase'] ) ? 1 : 0;

			$cptch_request_options['cptch_difficulty_number']		=	isset( $_REQUEST['cptch_difficulty_number'] ) ? 1 : 0;
			$cptch_request_options['cptch_difficulty_word'] 		=	isset( $_REQUEST['cptch_difficulty_word'] ) ? 1 : 0;


			/* Array merge incase this version has added new options */
			$cptch_options = array_merge( $cptch_options, $cptch_request_options );

			/* Check select one point in the blocks Arithmetic actions and Difficulty on settings page */
			if ( ( ! isset ( $_REQUEST['cptch_difficulty_number'] ) && ! isset ( $_REQUEST['cptch_difficulty_word'] ) ) || 	
				( ! isset ( $_REQUEST['cptch_math_action_plus'] ) && ! isset ( $_REQUEST['cptch_math_action_minus'] ) && ! isset ( $_REQUEST['cptch_math_action_increase'] ) ) ) {
				$error = __( "Please select one item in the block Arithmetic and Complexity for CAPTCHA", 'captcha' );			
			} else {				
				/* Update options in the database */
				update_option( 'cptch_options', $cptch_options );
				$message = __( "Settings saved.", 'captcha' );
			}
		}

		/* GO PRO */
		if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) {
			$go_pro_result = bws_go_pro_tab_check( plugin_basename( __FILE__ ) );
			if ( ! empty( $go_pro_result['error'] ) )
				$error = $go_pro_result['error'];
		} /* Display form on the setting page */ ?>
		<div class="wrap">
			<div class="icon32 icon32-bws" id="icon-options-general"></div>
			<h2><?php _e( 'Captcha Settings', 'captcha' ); ?></h2>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab<?php if ( ! isset( $_GET['action'] ) ) echo ' nav-tab-active'; ?>"  href="admin.php?page=captcha.php"><?php _e( 'Settings', 'captcha' ); ?></a>
				<a class="nav-tab" href="http://bestwebsoft.com/products/captcha/faq/" target="_blank"><?php _e( 'FAQ', 'captcha' ); ?></a>
				<a class="nav-tab bws_go_pro_tab<?php if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=captcha.php&amp;action=go_pro"><?php _e( 'Go PRO', 'captcha' ); ?></a>
			</h2>
			<div id="cptch_settings_notice" class="updated fade" style="display:none"><p><strong><?php _e( "Notice:", 'captcha' ); ?></strong> <?php _e( "The plugin's settings have been changed. In order to save them please don't forget to click the 'Save Changes' button.", 'captcha' ); ?></p></div>
			<div class="updated fade" <?php if ( ! isset( $_REQUEST['cptch_form_submit'] ) || "" != $error ) echo "style=\"display:none\""; ?>><p><strong><?php echo $message; ?></strong></p></div>
			<div class="error" <?php if ( "" == $error ) echo "style=\"display:none\""; ?>><p><strong><?php echo $error; ?></strong></p></div>
			<?php if ( ! isset( $_GET['action'] ) ) { ?>
				<form id="cptch_settings_form" method="post" action="admin.php?page=captcha.php">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e( 'Enable CAPTCHA for:', 'captcha' ); ?> </th>
							<td>
								<?php foreach ( $cptch_admin_fields_enable as $fields ) { ?>
									<label><input type="checkbox" name="<?php echo $fields[0]; ?>" value="<?php echo $fields[0]; ?>" <?php if ( 1 == $cptch_options[ $fields[0] ] ) echo "checked=\"checked\""; ?> /> <?php echo __( $fields[1], 'captcha' ); ?></label><br />
								<?php }						
								if ( array_key_exists('contact-form-plugin/contact_form.php', $all_plugins ) || array_key_exists('contact-form-pro/contact_form_pro.php', $all_plugins ) ) {
									if ( is_plugin_active( 'contact-form-plugin/contact_form.php' ) || is_plugin_active( 'contact-form-pro/contact_form_pro.php' ) ) { ?>
										<label><input type="checkbox" name="cptch_contact_form" value="1" <?php if( 1 == $cptch_options['cptch_contact_form'] ) echo "checked=\"checked\""; ?> /> <?php _e( 'Contact form', 'captcha' ); ?></label> <span class="cptch_span">(<?php _e( 'powered by', 'captcha' ); ?> <a href="http://bestwebsoft.com/products/">bestwebsoft.com</a>)</span><br />
									<?php } else { ?>
										<label><input disabled='disabled' type="checkbox" name="cptch_contact_form" value="1" <?php if ( 1 == $cptch_options['cptch_contact_form'] ) echo "checked=\"checked\""; ?> /> <?php _e( 'Contact form', 'captcha' ); ?></label> <span class="cptch_span">(<?php _e( 'powered by', 'captcha' ); ?> <a href="http://bestwebsoft.com/products/">bestwebsoft.com</a>) <a href="<?php echo bloginfo("url"); ?>/wp-admin/plugins.php"><?php _e( 'Activate contact form', 'captcha' ); ?></a></span><br />
									<?php }
								} else { ?>
									<label><input disabled='disabled' type="checkbox" name="cptch_contact_form" value="1" <?php if ( 1 == $cptch_options['cptch_contact_form'] ) echo "checked=\"checked\""; ?> /> <?php _e( 'Contact form', 'captcha' ); ?></label> <span class="cptch_span">(<?php _e( 'powered by', 'captcha' ); ?> <a href="http://bestwebsoft.com/products/">bestwebsoft.com</a>) <a href="http://bestwebsoft.com/products/contact-form/?k=d70b58e1739ab4857d675fed2213cedc&pn=75&v=<?php echo $cptch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>"><?php _e( 'Download contact form', 'captcha' ); ?></a></span><br />
								<?php } ?>
								<?php echo apply_filters( 'cptch_forms_list', '' ); ?>
								<span class="cptch_span"><?php _e( 'If you would like to add Captcha to a custom form, please see', 'captcha' ); ?> <a href="http://bestwebsoft.com/products/captcha/faq" target="_blank">FAQ</a></span>
							</td>
						</tr>
					</table>
					<div class="bws_pro_version_bloc">
						<div class="bws_pro_version_table_bloc">	
							<div class="bws_table_bg"></div>											
							<table class="form-table bws_pro_version">
								<tr valign="top">
									<th scope="row">
										<?php _e( 'Enable CAPTCHA for:', 'captcha' ); ?>
									</th>
									<td>
										<label><input disabled='disabled' type="checkbox" name="cptchpr_subscriber" value="1" /> <?php _e( 'Subscriber', 'captcha' ); ?></label> <span class="cptch_span">(<?php _e( 'powered by', 'captcha' ); ?> <a href="http://bestwebsoft.com/products/">bestwebsoft.com</a>)</span>
									</td>
								</tr>	
								<tr valign="top">						
									<th scope="row">
										<strong>Buddypress</strong><br/>
										<?php _e( 'Enable CAPTCHA for:', 'captcha' ); ?>
									</th>
									<td><br/>
										<input disabled='disabled' type="checkbox" name="cptchpr_buddypress_register_form" value="1" /> <label for="cptchpr_buddypress_register_form"><?php _e( 'Registration form', 'captcha' ); ?></label><br />
										<input disabled='disabled' type="checkbox" name="cptchpr_buddypress_comment_form" value="1" /> <label for="cptchpr_buddypress_comment_form"><?php _e( 'Comments form', 'captcha' ); ?></label><br />
										<input disabled='disabled' type="checkbox" name="cptchpr_buddypress_group_form" value="1"  /> <label for="cptchpr_buddypress_group_form"><?php _e( '"Create a Group" form', 'captcha' ); ?></label>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">
										<strong>Contact Form 7</strong><br/>
										<?php _e( 'Enable CAPTCHA:', 'captcha' ); ?>
									</th>
									<td><br/>
										<input disabled='disabled' type="checkbox" name="cptchpr_cf7" value="1" /><br />
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" colspan="2">
										* <?php _e( 'If you upgrade to Pro version all your settings will be saved.', 'captcha' ); ?>
									</th>
								</tr>							
							</table>	
						</div>
						<div class="bws_pro_version_tooltip">
							<div class="bws_info">
								<?php _e( 'Unlock premium options by upgrading to a PRO version.', 'captcha' ); ?> 
								<a href="http://bestwebsoft.com/products/captcha/?k=9701bbd97e61e52baa79c58c3caacf6d&pn=75&v=<?php echo $cptch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>" target="_blank" title="Captcha Pro"><?php _e( 'Learn More', 'captcha' ); ?></a>				
							</div>
							<a class="bws_button" href="http://bestwebsoft.com/products/captcha/buy/?k=9701bbd97e61e52baa79c58c3caacf6d&pn=75&v=<?php echo $cptch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>" target="_blank" title="Captcha Pro">
								<?php _e( 'Go', 'captcha' ); ?> <strong>PRO</strong>
							</a>	
							<div class="clear"></div>					
						</div>
					</div>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e( 'Title for CAPTCHA in the form', 'captcha' ); ?></th>
							<td><input class="cptch_settings_input" type="text" name="cptch_label_form" value="<?php echo $cptch_options['cptch_label_form']; ?>" <?php if ( 1 == $cptch_options['cptch_label_form'] ) echo "checked=\"checked\""; ?> /></td>
						</tr>
						<tr valign="top">
							<th scope="row" style="width:200px;"><?php _e( "Required symbol", 'captcha' ); ?></th>
							<td colspan="2">
								<input class="cptch_settings_input" type="text" name="cptch_required_symbol" value="<?php echo $cptch_options['cptch_required_symbol']; ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row" style="width:200px;"><?php _e( "Error messages", 'captcha' ); ?></th>
							<td colspan="2">
								<p><input class="cptch_settings_input" type="text" name="cptch_error_empty_value" value="<?php echo $cptch_options['cptch_error_empty_value']; ?>" />  <?php _e( 'If CAPTCHA field is empty', 'captcha' ); ?></p>
								<p><input class="cptch_settings_input" type="text" name="cptch_error_incorrect_value" value="<?php echo $cptch_options['cptch_error_incorrect_value']; ?>" />  <?php _e( 'If CAPTCHA is incorrect', 'captcha' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e( 'Arithmetic actions for CAPTCHA', 'captcha' ); ?></th>
							<td>
						<?php foreach ( $cptch_admin_fields_actions as $actions ) { ?>
								<div style="float:left; width:150px;clear: both;">
									<label><input type="checkbox" name="<?php echo $actions[0]; ?>" value="<?php echo $cptch_options[$actions[0]]; ?>" <?php if ( 1 == $cptch_options[$actions[0]] ) echo "checked=\"checked\""; ?> /> <?php echo __( $actions[1], 'captcha' ); ?></label>
								</div>
								<div class="cptch_help_box">
									<div class="cptch_hidden_help_text" style="display: none;width: auto;"><?php cptch_display_example( $actions[0] ); ?></div>
								</div>							
								<br />
						<?php } ?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e( 'CAPTCHA complexity level', 'captcha' ); ?></th>
							<td>
						<?php foreach ( $cptch_admin_fields_difficulty as $diff ) { ?>
								<div style="float:left; width:150px;clear: both;">
									<label><input type="checkbox" name="<?php echo $diff[0]; ?>" value="<?php echo $cptch_options[$diff[0]]; ?>" <?php if ( 1 == $cptch_options[$diff[0]] ) echo "checked=\"checked\""; ?> /> <?php echo __( $diff[1], 'captcha' ); ?></label>
								</div>
								<div class="cptch_help_box">
									<div class="cptch_hidden_help_text" style="display: none;width: auto;"><?php cptch_display_example( $diff[0] ); ?></div>
								</div>
								<br />
						<?php } ?>
							</td>
						</tr>
					</table>    
					<input type="hidden" name="cptch_form_submit" value="submit" />
					<p class="submit">
						<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
					</p>
					<?php wp_nonce_field( plugin_basename( __FILE__ ), 'cptch_nonce_name' ); ?>
				</form>
				<?php bws_plugin_reviews_block( $cptch_plugin_info['Name'], 'captcha' );
			} elseif ( 'go_pro' == $_GET['action'] ) {
				bws_go_pro_tab( $cptch_plugin_info, plugin_basename( __FILE__ ), 'captcha.php', 'captcha_pro.php', 'captcha-pro/captcha_pro.php', 'captcha', '9701bbd97e61e52baa79c58c3caacf6d', '75', isset( $go_pro_result['pro_plugin_is_activated'] ) );
			} ?>
		</div>
	<?php } 
}

/* This function adds captcha to the login form */
if ( ! function_exists( 'cptch_login_form' ) ) {
	function cptch_login_form() {
		if ( "" == session_id() )
			@session_start();
		global $cptch_options;

		if ( isset( $_SESSION["cptch_login"] ) ) 
			unset( $_SESSION["cptch_login"] );
		
		echo '<p class="cptch_block">';
		if ( "" != $cptch_options['cptch_label_form'] )	
			echo '<label>' . $cptch_options['cptch_label_form'] . '</label><br />';
		if ( isset( $_SESSION['cptch_error'] ) ) {
			echo "<br /><span style='color:red'>" . $_SESSION['cptch_error'] . "</span><br />";
			unset( $_SESSION['cptch_error'] );
		}
		echo '<br />';
		cptch_display_captcha();
		echo '</p>
		<br />';
		return true;
	}
}
/* End function cptch_login_form */

/* This function checks the captcha posted with a login when login errors are absent */
if ( ! function_exists( 'cptch_login_check' ) ) {
	function cptch_login_check( $user ) {
		global $cptch_options;
		$str_key = $cptch_options['cptch_str_key']['key'];

		if ( "" == session_id() )
			@session_start();

		if ( isset( $_SESSION["cptch_login"] ) && true === $_SESSION["cptch_login"] )
			return $user;

		/* Delete errors, if they set */
		if ( isset( $_SESSION['cptch_error'] ) )
			unset( $_SESSION['cptch_error'] );

		if ( ! function_exists( 'is_plugin_active' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( is_plugin_active( 'limit-login-attempts/limit-login-attempts.php' ) ) { 
			if ( isset( $_REQUEST['loggedout'] ) && isset( $_REQUEST['cptch_number'] ) && "" ==  $_REQUEST['cptch_number'] ) {
				return $user;
			}	
		}		

		/* Add error if captcha is empty */			
		if ( ( ! isset( $_REQUEST['cptch_number'] ) || "" ==  $_REQUEST['cptch_number'] ) && isset( $_REQUEST['loggedout'] ) ) {
			$error = new WP_Error();
			$error->add( 'cptch_error', '<strong>' . __( 'ERROR', 'captcha' ) . '</strong>: ' . $cptch_options['cptch_error_empty_value'] );
			wp_clear_auth_cookie();
			return $error;
		}
		if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] ) ) {
			if ( 0 == strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) {
				/* Captcha was matched */
				$_SESSION['cptch_login'] = true;
				return $user;								
			} else {
				$_SESSION['cptch_login'] = false;
				wp_clear_auth_cookie();
				/* Add error if captcha is incorrect */
				$error = new WP_Error();
				$error->add( 'cptch_error', '<strong>' . __( 'ERROR', 'captcha' ) . '</strong>: ' . $cptch_options['cptch_error_incorrect_value'] );
				return $error;
			}
		} else {
			if ( isset( $_REQUEST['log'] ) && isset( $_REQUEST['pwd'] ) ) {
				/* captcha was not found in _REQUEST */
				$error = new WP_Error();
				$error->add( 'cptch_error', '<strong>' . __( 'ERROR', 'captcha' ) . '</strong>: ' . $cptch_options['cptch_error_empty_value'] );
				return $error;
			} else {
				/* it is not a submit */
				return $user;
			}
		}
	}
}
/* End function cptch_login_check */

/* This function adds captcha to the comment form */
if ( ! function_exists( 'cptch_comment_form' ) ) {
	function cptch_comment_form() {
		global $cptch_options;
		/* Skip captcha if user is logged in and the settings allow */
		if ( is_user_logged_in() && 1 == $cptch_options['cptch_hide_register'] ) {
			return true;
		}

		/* captcha html - comment form */
		echo '<p class="cptch_block">';
		if ( "" != $cptch_options['cptch_label_form'] )	
			echo '<label>' . $cptch_options['cptch_label_form'] . '<span class="required"> ' . $cptch_options['cptch_required_symbol'] . '</span></label>';
		echo '<br />';
		cptch_display_captcha();
		echo '</p>';

		return true;
	}
}
/* End function cptch_comment_form */

/* This function adds captcha to the comment form */
if ( ! function_exists( 'cptch_comment_form_default_wp3' ) ) {
	function cptch_comment_form_default_wp3( $args ) {
		global $cptch_options;

		/* skip captcha if user is logged in and the settings allow */
		if ( is_user_logged_in() && 1 == $cptch_options['cptch_hide_register'] ) {
			return $args;
		}
		/* captcha html - comment form */
		$args['comment_notes_after'] .= cptch_custom_form( "" );

		remove_action( 'comment_form', 'cptch_comment_form' );

		return $args;
	}
}
/* End function cptch_comment_form_default_wp3 */

/* This function adds captcha to the comment form */
if ( ! function_exists( 'cptch_comment_form_wp3' ) ) {
	function cptch_comment_form_wp3() {
		global $cptch_options;

		/* Skip captcha if user is logged in and the settings allow */
		if ( is_user_logged_in() && 1 == $cptch_options['cptch_hide_register'] ) {
			return true;
		}

		/* captcha html - comment form */
		echo '<p class="cptch_block">';
		if ( "" != $cptch_options['cptch_label_form'] )	
			echo '<label>' . $cptch_options['cptch_label_form'] . '<span class="required"> ' . $cptch_options['cptch_required_symbol'] . '</span></label>';
		echo '<br />';
		cptch_display_captcha();
		echo '</p>';

		remove_action( 'comment_form', 'cptch_comment_form' );

		return true;
	}
}
/* End function cptch_comment_form_wp3 */

/* This function checks captcha posted with the comment */
if ( ! function_exists( 'cptch_comment_post' ) ) {
	function cptch_comment_post( $comment ) {	
		global $cptch_options;

		if ( is_user_logged_in() && 1 == $cptch_options['cptch_hide_register'] ) {
			return $comment;
		}
	    
		$str_key = $cptch_options['cptch_str_key']['key'];

		/* Added for compatibility with WP Wall plugin */
		/* This does NOT add CAPTCHA to WP Wall plugin, */
		/* It just prevents the "Error: You did not enter a Captcha phrase." when submitting a WP Wall comment */
		if ( function_exists( 'WPWall_Widget' ) && isset( $_REQUEST['wpwall_comment'] ) ) {
			/* Skip capthca */
			return $comment;
		}

		/* Skip captcha for comment replies from the admin menu */
		if ( isset( $_REQUEST['action'] ) && 'replyto-comment' == $_REQUEST['action'] &&
		( check_ajax_referer( 'replyto-comment', '_ajax_nonce', false ) || check_ajax_referer( 'replyto-comment', '_ajax_nonce-replyto-comment', false ) ) ) {
			/* Skip capthca */
			return $comment;
		}

		/* Skip captcha for trackback or pingback */
		if ( '' != $comment['comment_type'] && 'comment' != $comment['comment_type'] ) {
			/* Skip captcha */
			return $comment;
		}
		
		/* If captcha is empty */
		if ( isset( $_REQUEST['cptch_number'] ) && "" ==  $_REQUEST['cptch_number'] )
			wp_die( $cptch_options['cptch_error_empty_value'] );

		if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] ) && 0 == strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) {
			/* Captcha was matched */
			return( $comment );
		} else {
			wp_die( __( 'Error', 'captcha' ) . ':&nbsp' . $cptch_options['cptch_error_incorrect_value'] . ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? '' : ' ' . __( "Click the BACK button on your browser, and try again.", 'captcha' ) ) );
		}
	}
}
/* End function cptch_comment_post */

/* This function adds the captcha to the register form */
if ( ! function_exists( 'cptch_register_form' ) ) {
	function cptch_register_form() {
		global $cptch_options;

		/* the captcha html - register form */
		echo '<p class="cptch_block" style="text-align:left;">';
		if ( "" != $cptch_options['cptch_label_form'] )	
			echo '<label>'. $cptch_options['cptch_label_form'] .'</label><br />';
		echo '<br />' . cptch_display_captcha() . '</p><br />';
		return true;
	}
}
/* End function cptch_register_form */

/* this function adds the captcha to the register form in multisite */
if ( ! function_exists ( 'wpmu_cptch_register_form' ) ) {
	function wpmu_cptch_register_form( $errors ) {	
		global $cptch_options;

		/* the captcha html - register form */
		echo '<div class="cptch_block">';
		if ( "" != $cptch_options['cptch_label_form'] )	
			echo '<label>' . $cptch_options['cptch_label_form'] . '</label><br />';

		if ( is_wp_error( $errors ) ) {
			$error_codes = $errors->get_error_codes();
			if ( is_array( $error_codes ) && ! empty( $error_codes ) ) {
				foreach ( $error_codes as $error_code ) {
					if ( "captcha_" == substr( $error_code, 0, 8 ) ) {
						$error_message = $errors->get_error_message( $error_code );
						echo '<p class="error">' . $error_message . '</p>';
					}
				}
			}
		}
		echo cptch_display_captcha() . '</div><br />';
	}
}
/* End function wpmu_cptch_register_form */

/* This function checks captcha posted with registration */
if ( ! function_exists( 'cptch_register_post' ) ) {
	function cptch_register_post( $login,$email,$errors ) {
		global $cptch_options;
		$str_key = $cptch_options['cptch_str_key']['key'];

		/* If captcha is blank - add error */
		if ( isset( $_REQUEST['cptch_number'] ) && "" ==  $_REQUEST['cptch_number'] ) {
			$errors->add( 'captcha_blank', '<strong>' . __( 'ERROR', 'captcha' ) . '</strong>: ' . $cptch_options['cptch_error_empty_value'] );
			return $errors;
		}

		if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] )
			&& 0 == strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) {
			/* Captcha was matched */
		} else {
			$errors->add( 'captcha_wrong', '<strong>'. __( 'ERROR', 'captcha') . '</strong>: ' . $cptch_options['cptch_error_incorrect_value'] );
		}
		return( $errors );
	}
}

/* End function cptch_register_post */

if ( ! function_exists( 'cptch_register_validate' ) ) {
	function cptch_register_validate( $results ) {
		global $current_user, $cptch_options;
		$str_key = $cptch_options['cptch_str_key']['key'];

		if ( ! isset( $current_user->data ) ) {
			/* If captcha is blank - add error */
			if ( isset( $_REQUEST['cptch_number'] ) && "" == $_REQUEST['cptch_number'] ) {
				$results['errors']->add( 'captcha_blank', '<strong>' . __( 'ERROR', 'captcha' ) . '</strong>: ' . $cptch_options['cptch_error_empty_value'] );
				return $results;
			}

			if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] )
				&& 0 == strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) {
				/* Captcha was matched */
			} else {
				$results['errors']->add( 'captcha_wrong', '<strong>' . __( 'ERROR', 'captcha' ) . '</strong>: ' . $cptch_options['cptch_error_incorrect_value'] );
			}
			return( $results );
		} else
			return( $results );
	}
}
/* End function cptch_register_post */

/* This function checks the captcha posted with lostpassword form */
if ( ! function_exists( 'cptch_lostpassword_post' ) ) {
	function cptch_lostpassword_post() {
		global $cptch_options;
		$str_key = $cptch_options['cptch_str_key']['key'];

		/* If field 'user login' is empty - return */
		if( isset( $_REQUEST['user_login'] ) && "" == $_REQUEST['user_login'] )
			return;

		/* If captcha doesn't entered */
		if ( isset( $_REQUEST['cptch_number'] ) && "" ==  $_REQUEST['cptch_number'] ) {
			wp_die( $cptch_options['cptch_error_empty_value'] );
		}
		
		/* Check entered captcha */
		if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] ) 
			&& 0 == strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) {
			return;
		} else {			
			wp_die( __( 'Error', 'captcha' ) . ':&nbsp' . $cptch_options['cptch_error_incorrect_value'] . ' ' . __( "Click the BACK button on your browser, and try again.", 'captcha' ) );
		}
	}
}
/* function cptch_lostpassword_post */

/* Functionality of the captcha logic work */
if ( ! function_exists( 'cptch_display_captcha' ) ) {
	function cptch_display_captcha() {
		global $cptch_options, $cptch_time, $cptch_plugin_info;

		if ( ! $cptch_plugin_info ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$cptch_plugin_info = get_plugin_data( __FILE__ );
		}

		if ( ! isset( $cptch_options['cptch_str_key'] ) )
			$cptch_options = get_option( 'cptch_options' );
		if ( '' == $cptch_options['cptch_str_key']['key'] || $cptch_options['cptch_str_key']['time'] < time() - ( 24 * 60 * 60 ) )
			cptch_generate_key();
		$str_key = $cptch_options['cptch_str_key']['key'];
		
		/* trog */
		/* In letters presentation of numbers 0-9 */
		$number_string          =       array(); 
		$number_string[0] = __( '<img src="/wp-content/plugins/captcha/zero.png" style="vertical-align:middle">', 'captcha' );
		$number_string[1] = __( '<img src="/wp-content/plugins/captcha/one.png" style="vertical-align:middle">', 'captcha' );
		$number_string[2] = __( '<img src="/wp-content/plugins/captcha/two.png" style="vertical-align:middle">', 'captcha' );
		$number_string[3] = __( '<img src="/wp-content/plugins/captcha/three.png" style="vertical-align:middle">', 'captcha' );
		$number_string[4] = __( '<img src="/wp-content/plugins/captcha/four.png" style="vertical-align:middle">', 'captcha' );
		$number_string[5] = __( '<img src="/wp-content/plugins/captcha/five.png" style="vertical-align:middle">', 'captcha' );
		$number_string[6] = __( '<img src="/wp-content/plugins/captcha/six.png" style="vertical-align:middle">', 'captcha' );
		$number_string[7] = __( '<img src="/wp-content/plugins/captcha/seven.png" style="vertical-align:middle">', 'captcha' );
		$number_string[8] = __( '<img src="/wp-content/plugins/captcha/eight.png" style="vertical-align:middle">', 'captcha' );
		$number_string[9] = __( '<img src="/wp-content/plugins/captcha/nine.png" style="vertical-align:middle">', 'captcha' ); 

		/* In letters presentation of numbers 11 -19 */
		$number_two_string              =       array();
		$number_two_string[1] = __( '<img src="/wp-content/plugins/captcha/eleven.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[2] = __( '<img src="/wp-content/plugins/captcha/twelve.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[3] = __( '<img src="/wp-content/plugins/captcha/thirteen.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[4] = __( '<img src="/wp-content/plugins/captcha/fourteen.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[5] = __( '<img src="/wp-content/plugins/captcha/fifteen.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[6] = __( '<img src="/wp-content/plugins/captcha/sixteen.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[7] = __( '<img src="/wp-content/plugins/captcha/seventeen.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[8] = __( '<img src="/wp-content/plugins/captcha/eighteen.png" style="vertical-align:middle">', 'captcha' );
		$number_two_string[9] = __( '<img src="/wp-content/plugins/captcha/nineteen.png" style="vertical-align:middle">', 'captcha' );

		/* In letters presentation of numbers 10, 20, 30, 40, 50, 60, 70, 80, 90 */
		$number_three_string    =       array();
		$number_three_string[1] = __( '<img src="/wp-content/plugins/captcha/ten.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[2] = __( '<img src="/wp-content/plugins/captcha/twenty.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[3] = __( '<img src="/wp-content/plugins/captcha/thirty.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[4] = __( '<img src="/wp-content/plugins/captcha/forty.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[5] = __( '<img src="/wp-content/plugins/captcha/fifty.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[6] = __( '<img src="/wp-content/plugins/captcha/sixty.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[7] = __( '<img src="/wp-content/plugins/captcha/seventy.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[8] = __( '<img src="/wp-content/plugins/captcha/eighty.png" style="vertical-align:middle">', 'captcha' );
		$number_three_string[9] = __( '<img src="/wp-content/plugins/captcha/ninety.png" style="vertical-align:middle">', 'captcha' );


		/* The array of math actions */
		$math_actions = array();

		/* If value for Plus on the settings page is set */
		if ( 1 == $cptch_options['cptch_math_action_plus'] )
			$math_actions[] = '&#43;';
		/* If value for Minus on the settings page is set */
		if ( 1 == $cptch_options['cptch_math_action_minus'] )
			$math_actions[] = '&minus;';
		/* If value for Increase on the settings page is set */
		if ( 1 == $cptch_options['cptch_math_action_increase'] )
			$math_actions[] = '&times;';
			
		/* Which field from three will be the input to enter required value */
		$rand_input = rand( 0, 2 );
		/* Which field from three will be the letters presentation of numbers */
		$rand_number_string = rand( 0, 2 );
		/* If don't check Word in setting page - $rand_number_string not display */
		if ( 0 == $cptch_options["cptch_difficulty_word"] )
			$rand_number_string = -1;
		/* Set value for $rand_number_string while $rand_input = $rand_number_string */
		while ( $rand_input == $rand_number_string ) {
			$rand_number_string = rand( 0, 2 );
		}
		/* What is math action to display in the form */
		$rand_math_action = rand( 0, count( $math_actions ) - 1 );

		$array_math_expretion = array();

		/* Add first part of mathematical expression */
		$array_math_expretion[0] = rand( 1, 9 );
		/* Add second part of mathematical expression */
		$array_math_expretion[1] = rand( 1, 9 );
		/* Calculation of the mathematical expression result */
		switch ( $math_actions[ $rand_math_action ] ) {
			case "&#43;":
				$array_math_expretion[2] = $array_math_expretion[0] + $array_math_expretion[1];
				break;
			case "&minus;":
				/* Result must not be equal to the negative number */
				if ( $array_math_expretion[0] < $array_math_expretion[1] ) {
					$number = $array_math_expretion[0];
					$array_math_expretion[0] = $array_math_expretion[1];
					$array_math_expretion[1] = $number;
				}
				$array_math_expretion[2] = $array_math_expretion[0] - $array_math_expretion[1];
				break;
			case "&times;":
				$array_math_expretion[2] = $array_math_expretion[0] * $array_math_expretion[1];
				break;
		}
		
		/* String for display */
		$str_math_expretion = "";
		/* First part of mathematical expression */
		if ( 0 == $rand_input )
			$str_math_expretion .= "<input id=\"cptch_input\" class=\"cptch_input\" type=\"text\" autocomplete=\"off\" name=\"cptch_number\" value=\"\" maxlength=\"2\" size=\"2\" aria-required=\"true\" required=\"required\" style=\"margin-bottom:0;display:inline;font-size: 12px;width: 40px;\" />";
		else if ( 0 == $rand_number_string || 0 == $cptch_options["cptch_difficulty_number"] )
			$str_math_expretion .= cptch_converting( $number_string[ $array_math_expretion[0] ] );
		else
			$str_math_expretion .= $array_math_expretion[0];
		
		/* Add math action */
		$str_math_expretion .= " " . $math_actions[ $rand_math_action ];
		
		/* Second part of mathematical expression */
		if ( 1 == $rand_input )
			$str_math_expretion .= " <input id=\"cptch_input\" class=\"cptch_input\" type=\"text\" autocomplete=\"off\" name=\"cptch_number\" value=\"\" maxlength=\"2\" size=\"2\" aria-required=\"true\" required=\"required\" style=\"margin-bottom:0;display:inline;font-size: 12px;width: 40px;\" />";
		else if ( 1 == $rand_number_string || 0 == $cptch_options["cptch_difficulty_number"] )
			$str_math_expretion .= " " . cptch_converting( $number_string[ $array_math_expretion[1] ] );
		else
			$str_math_expretion .= " " . $array_math_expretion[1];
		
		/* Add = */
		$str_math_expretion .= " = ";
		
		/* Add result of mathematical expression */
		if ( 2 == $rand_input ) {
			$str_math_expretion .= " <input id=\"cptch_input\" class=\"cptch_input\" type=\"text\" autocomplete=\"off\" name=\"cptch_number\" value=\"\" maxlength=\"2\" size=\"2\" aria-required=\"true\" required=\"required\" style=\"margin-bottom:0;display:inline;font-size: 12px;width: 40px;\" />";
		} else if ( 2 == $rand_number_string || 0 == $cptch_options["cptch_difficulty_number"] ) {
			if ( 10 > $array_math_expretion[2] )
				$str_math_expretion .= " " . cptch_converting( $number_string[ $array_math_expretion[2] ] );
			else if ( 20 > $array_math_expretion[2] && 10 < $array_math_expretion[2] )
				$str_math_expretion .= " " . cptch_converting( $number_two_string[ $array_math_expretion[2] % 10 ] );
			else {
				if ( "nl-NL" == get_bloginfo( 'language', 'Display' ) ) {
					$str_math_expretion .= " " . ( 0 != $array_math_expretion[2] % 10 ? $number_string[ $array_math_expretion[2] % 10 ] . __( "and", 'captcha' ) : '' ) . $number_three_string[ $array_math_expretion[2] / 10 ];
				} else {
					$str_math_expretion .= " " . cptch_converting( $number_three_string[ $array_math_expretion[2] / 10 ] ) . " " . ( 0 != $array_math_expretion[2] % 10 ? cptch_converting( $number_string[ $array_math_expretion[2] % 10 ] ) : '' );
				}
			}
		} else {
			$str_math_expretion .= $array_math_expretion[2];
		}
		/* Add hidden field with encoding result */ ?>
		<input type="hidden" name="cptch_result" value="<?php echo $str = cptch_encode( $array_math_expretion[ $rand_input ], $str_key, $cptch_time ); ?>" />
		<input type="hidden" name="cptch_time" value="<?php echo $cptch_time; ?>" />
		<input type="hidden" value="Version: <?php echo $cptch_plugin_info["Version"]; ?>" />
		<?php echo $str_math_expretion;
	}
}

if ( ! function_exists ( 'cptch_converting' ) ) {
	function cptch_converting( $number_string ) {
		global $cptch_options;

		// 2014-04-09 - trog - fixes for the image stuff 
		if (strstr($number_string, "img src"))
			return $number_string;

		if ( 1 == $cptch_options["cptch_difficulty_word"] && 'en-US' == get_bloginfo( 'language' ) ) {
			/* Array of htmlspecialchars for numbers and english letters */
			$htmlspecialchars_array			=	array();
			$htmlspecialchars_array['a']	=	'&#97;';
			$htmlspecialchars_array['b']	=	'&#98;';
			$htmlspecialchars_array['c']	=	'&#99;';
			$htmlspecialchars_array['d']	=	'&#100;';
			$htmlspecialchars_array['e']	=	'&#101;';
			$htmlspecialchars_array['f']	=	'&#102;';
			$htmlspecialchars_array['g']	=	'&#103;';
			$htmlspecialchars_array['h']	=	'&#104;';
			$htmlspecialchars_array['i']	=	'&#105;';
			$htmlspecialchars_array['j']	=	'&#106;';
			$htmlspecialchars_array['k']	=	'&#107;';
			$htmlspecialchars_array['l']	=	'&#108;';
			$htmlspecialchars_array['m']	=	'&#109;';
			$htmlspecialchars_array['n']	=	'&#110;';
			$htmlspecialchars_array['o']	=	'&#111;';
			$htmlspecialchars_array['p']	=	'&#112;';
			$htmlspecialchars_array['q']	=	'&#113;';
			$htmlspecialchars_array['r']	=	'&#114;';
			$htmlspecialchars_array['s']	=	'&#115;';
			$htmlspecialchars_array['t']	=	'&#116;';
			$htmlspecialchars_array['u']	=	'&#117;';
			$htmlspecialchars_array['v']	=	'&#118;';
			$htmlspecialchars_array['w']	=	'&#119;';
			$htmlspecialchars_array['x']	=	'&#120;';
			$htmlspecialchars_array['y']	=	'&#121;';
			$htmlspecialchars_array['z']	=	'&#122;';

			$simbols_lenght = strlen( $number_string );
			$simbols_lenght--;
			$number_string_new	=	str_split( $number_string );
			$converting_letters	=	rand( 1, $simbols_lenght );
			while ( $converting_letters != 0 ) {
				$position = rand( 0, $simbols_lenght );
				$number_string_new[ $position ] = isset( $htmlspecialchars_array[ $number_string_new[ $position ] ] ) ? $htmlspecialchars_array[ $number_string_new[ $position ] ] : $number_string_new[ $position ];
				$converting_letters--;
			}
			$number_string = '';
			foreach ( $number_string_new as $key => $value ) {
				$number_string .= $value;
			}
			return $number_string;			
		} else
			return $number_string;
	}
}

/* Function for encodinf number */
if ( ! function_exists( 'cptch_encode' ) ) {
	function cptch_encode( $String, $Password, $cptch_time ) {
		/* Check if key for encoding is empty */
		if ( ! $Password ) die ( __( "Encryption password is not set", 'captcha' ) );

		$Salt	=	md5( $cptch_time, true );
		$String	=	substr( pack( "H*", sha1( $String ) ), 0, 1 ) . $String;
		$StrLen	=	strlen( $String );
		$Seq	=	$Password;
		$Gamma	=	'';
		while ( strlen( $Gamma ) < $StrLen ) {
			$Seq = pack( "H*", sha1( $Seq . $Gamma . $Salt ) );
			$Gamma.=substr( $Seq, 0, 8 );
		}

		return base64_encode( $String ^ $Gamma );
	}
}

/* Function for decoding number */
if ( ! function_exists( 'cptch_decode' ) ) {
	function cptch_decode( $String, $Key, $cptch_time ) {
		/* Check if key for encoding is empty */
		if ( ! $Key ) die ( __( "Decryption password is not set", 'captcha' ) );

		$Salt	=	md5( $cptch_time, true );
		$StrLen	=	strlen( $String );
		$Seq	=	$Key;
		$Gamma	=	'';
		while ( strlen( $Gamma ) < $StrLen ) {
			$Seq = pack( "H*", sha1( $Seq . $Gamma . $Salt ) );
			$Gamma.= substr( $Seq, 0, 8 );
		}

		$String = base64_decode( $String );
		$String = $String^$Gamma;

		$DecodedString = substr( $String, 1 );
		$Error = ord( substr( $String, 0, 1 ) ^ substr( pack( "H*", sha1( $DecodedString ) ), 0, 1 )); 

		if ( $Error ) 
			return false;
		else 
			return $DecodedString;
	}
}

/* This function adds captcha to the custom form */
if ( ! function_exists( 'cptch_custom_form' ) ) {
	function cptch_custom_form( $error_message ) {
		$cptch_options = get_option( 'cptch_options' );
		$content = "";
		
		/* captcha html - login form */
		$content .= '<p class="cptch_block" style="text-align:left;">';
		if ( "" != $cptch_options['cptch_label_form'] )	
			$content .= '<label>' . $cptch_options['cptch_label_form'] . '<span class="required"> ' . $cptch_options['cptch_required_symbol'] . '</span></label><br />';
		else
			$content .= '<br />';
		if ( isset( $error_message['error_captcha'] ) ) {
			$content .= "<span class='cptch_error' style='color:red'>" . $error_message['error_captcha'] . "</span><br />";
		}
		$content .= cptch_display_captcha_custom();
		$content .= '</p>';
		return $content;
	}
}
/*  End function cptch_contact_form */

/* This function check captcha in the custom form */
if ( ! function_exists( 'cptch_check_custom_form' ) ) {
	function cptch_check_custom_form() {
		global $cptch_options;
		$str_key = $cptch_options['cptch_str_key']['key'];

		if ( isset( $_REQUEST['cntctfrm_contact_action'] ) || isset( $_REQUEST['cntctfrmpr_contact_action'] ) ) {
			/* If captcha doesn't entered */
			if ( isset( $_REQUEST['cptch_number'] ) && "" ==  $_REQUEST['cptch_number'] ) {
				return false;
			}
			
			/* Check entered captcha */
			if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] )
				&& 0 == strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) {
				return true;
			} else {
				return false;
			}
		} else
			return false;
	}
}
/* End function cptch_check_contact_form */

/* Functionality of the captcha logic work for custom form */
if ( ! function_exists( 'cptch_display_captcha_custom' ) ) {
	function cptch_display_captcha_custom() {
		global $cptch_options, $cptch_time, $cptch_plugin_info;

		if ( ! $cptch_plugin_info ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$cptch_plugin_info = get_plugin_data( __FILE__ );
		}

		if ( ! isset( $cptch_options['cptch_str_key'] ) )
			$cptch_options = get_option( 'cptch_options' );
		if ( '' == $cptch_options['cptch_str_key']['key'] || $cptch_options['cptch_str_key']['time'] < time() - ( 24 * 60 * 60 ) )
			cptch_generate_key();
		$str_key = $cptch_options['cptch_str_key']['key'];

		$content = "";
		
		/* In letters presentation of numbers 0-9 */
		$number_string		=	array(); 
		$number_string[0]	=	__( 'zero', 'captcha' );
		$number_string[1]	=	__( 'one', 'captcha' );
		$number_string[2]	=	__( 'two', 'captcha' );
		$number_string[3]	=	__( 'three', 'captcha' );
		$number_string[4]	=	__( 'four', 'captcha' );
		$number_string[5]	=	__( 'five', 'captcha' );
		$number_string[6]	=	__( 'six', 'captcha' );
		$number_string[7]	=	__( 'seven', 'captcha' );
		$number_string[8]	=	__( 'eight', 'captcha' );
		$number_string[9]	=	__( 'nine', 'captcha' ); 
		/* In letters presentation of numbers 11 -19 */
		$number_two_string		=	array();
		$number_two_string[1]	=	__( 'eleven', 'captcha' );
		$number_two_string[2]	=	__( 'twelve', 'captcha' );
		$number_two_string[3]	=	__( 'thirteen', 'captcha' );
		$number_two_string[4]	=	__( 'fourteen', 'captcha' );
		$number_two_string[5]	=	__( 'fifteen', 'captcha' );
		$number_two_string[6]	=	__( 'sixteen', 'captcha' );
		$number_two_string[7]	=	__( 'seventeen', 'captcha' );
		$number_two_string[8]	=	__( 'eighteen', 'captcha' );
		$number_two_string[9]	=	__( 'nineteen', 'captcha' );
		/* In letters presentation of numbers 10, 20, 30, 40, 50, 60, 70, 80, 90 */
		$number_three_string	=	array();
		$number_three_string[1]	=	__( 'ten', 'captcha' );
		$number_three_string[2]	=	__( 'twenty', 'captcha' );
		$number_three_string[3]	=	__( 'thirty', 'captcha' );
		$number_three_string[4]	=	__( 'forty', 'captcha' );
		$number_three_string[5]	=	__( 'fifty', 'captcha' );
		$number_three_string[6]	=	__( 'sixty', 'captcha' );
		$number_three_string[7]	=	__( 'seventy', 'captcha' );
		$number_three_string[8]	=	__( 'eighty', 'captcha' );
		$number_three_string[9]	=	__( 'ninety', 'captcha' );
		/* The array of math actions */
		$math_actions = array();

		/* If value for Plus on the settings page is set */
		if ( 1 == $cptch_options['cptch_math_action_plus'] )
			$math_actions[] = '&#43;';
		/* If value for Minus on the settings page is set */
		if ( 1 == $cptch_options['cptch_math_action_minus'] )
			$math_actions[] = '&minus;';
		/* If value for Increase on the settings page is set */
		if ( 1 == $cptch_options['cptch_math_action_increase'] )
			$math_actions[] = '&times;';
			
		/* Which field from three will be the input to enter required value */
		$rand_input = rand( 0, 2 );
		/* Which field from three will be the letters presentation of numbers */
		$rand_number_string = rand( 0, 2 );
		/* If don't check Word in setting page - $rand_number_string not display */
		if ( 0 == $cptch_options["cptch_difficulty_word"] )
			$rand_number_string = -1;
		/* Set value for $rand_number_string while $rand_input = $rand_number_string */
		while ( $rand_input == $rand_number_string ) {
			$rand_number_string = rand( 0, 2 );
		}
		/* What is math action to display in the form */
		$rand_math_action = rand( 0, count( $math_actions ) - 1 );

		$array_math_expretion = array();

		/* Add first part of mathematical expression */
		$array_math_expretion[0] = rand( 1, 9 );
		/* Add second part of mathematical expression */
		$array_math_expretion[1] = rand( 1, 9 );
		/* Calculation of the mathematical expression result */
		switch ( $math_actions[$rand_math_action] ) {
			case "&#43;":
				$array_math_expretion[2] = $array_math_expretion[0] + $array_math_expretion[1];
				break;
			case "&minus;":
				/* Result must not be equal to the negative number */
				if ( $array_math_expretion[0] < $array_math_expretion[1] ) {
					$number = $array_math_expretion[0];
					$array_math_expretion[0] = $array_math_expretion[1];
					$array_math_expretion[1] = $number;
				}
				$array_math_expretion[2] = $array_math_expretion[0] - $array_math_expretion[1];
				break;
			case "&times;":
				$array_math_expretion[2] = $array_math_expretion[0] * $array_math_expretion[1];
				break;
		}
		
		/* String for display */
		$str_math_expretion = "";
		/* First part of mathematical expression */
		if ( 0 == $rand_input )
			$str_math_expretion .= "<input class=\"cptch_input\" type=\"text\" autocomplete=\"off\" name=\"cptch_number\" value=\"\" maxlength=\"1\" size=\"1\" style=\"margin-bottom:0;display:inline;font-size: 12px;width: 40px;\" />";
		else if ( 0 == $rand_number_string || 0 == $cptch_options["cptch_difficulty_number"] )
			$str_math_expretion .= cptch_converting( $number_string[ $array_math_expretion[0] ] );
		else
			$str_math_expretion .= $array_math_expretion[0];
		
		/* Add math action */
		$str_math_expretion .= " " . $math_actions[ $rand_math_action ];
		
		/* Second part of mathematical expression */
		if ( 1 == $rand_input )
			$str_math_expretion .= " <input class=\"cptch_input\" type=\"text\" autocomplete=\"off\" name=\"cptch_number\" value=\"\" maxlength=\"1\" size=\"1\" style=\"margin-bottom:0;display:inline;font-size: 12px;width: 40px;\" />";
		else if ( 1 == $rand_number_string || 0 == $cptch_options["cptch_difficulty_number"] )
			$str_math_expretion .= " " . cptch_converting( $number_string[ $array_math_expretion[1] ] );
		else
			$str_math_expretion .= " " . $array_math_expretion[1];
		
		/* Add = */
		$str_math_expretion .= " = ";
		
		/* Add result of mathematical expression */
		if ( 2 == $rand_input ) {
			$str_math_expretion .= " <input class=\"cptch_input\" type=\"text\" autocomplete=\"off\" name=\"cptch_number\" value=\"\" maxlength=\"2\" size=\"1\" style=\"margin-bottom:0;display:inline;font-size: 12px;width: 40px;\" />";
		} else if ( 2 == $rand_number_string || 0 == $cptch_options["cptch_difficulty_number"] ) {
			if ( 10 > $array_math_expretion[2] )
				$str_math_expretion .= " " . cptch_converting( $number_string[ $array_math_expretion[2] ] );
			else if ( 20 > $array_math_expretion[2] && 10 < $array_math_expretion[2] )
				$str_math_expretion .= " " . cptch_converting( $number_two_string[ $array_math_expretion[2] % 10 ] );
			else {
				if ( "nl-NL" == get_bloginfo( 'language','Display' ) ) {
					$str_math_expretion .= " " . ( 0 != $array_math_expretion[2] % 10 ? $number_string[ $array_math_expretion[2] % 10 ] . __( "and", 'captcha' ) : '' ) . $number_three_string[ $array_math_expretion[2] / 10 ];
				} else {
					$str_math_expretion .= " " . cptch_converting( $number_three_string[ $array_math_expretion[2] / 10 ] ) . " " . ( 0 != $array_math_expretion[2] % 10 ? cptch_converting( $number_string[ $array_math_expretion[2] % 10 ] ) : '' );
				}
			}
		} else {
			$str_math_expretion .= $array_math_expretion[2];
		}
		/* Add hidden field with encoding result */
		$content .= '<input type="hidden" name="cptch_result" value="' . $str = cptch_encode( $array_math_expretion[ $rand_input ], $str_key, $cptch_time ) . '" />
			<input type="hidden" name="cptch_time" value="' . $cptch_time . '" />
			<input type="hidden" value="Version: ' . $cptch_plugin_info["Version"] . '" />' .
			$str_math_expretion; 
		return $content;
	}
}

if ( ! function_exists( 'cptch_contact_form_options' ) ) {
	function cptch_contact_form_options() {
		global $cptch_options;
		if ( ! $cptch_options )
			$cptch_options = get_option( 'cptch_options' );
		if ( isset( $cptch_options['cptch_contact_form'] ) && 1 == $cptch_options['cptch_contact_form'] ) {
			add_filter( 'cntctfrm_display_captcha', 'cptch_custom_form' );
			add_filter( 'cntctfrm_check_form', 'cptch_check_custom_form' );
			add_filter( 'cntctfrmpr_display_captcha', 'cptch_custom_form' );
			add_filter( 'cntctfrmpr_check_form', 'cptch_check_custom_form' );
		}
	}
}

if ( ! function_exists ( 'cptch_display_example' ) ) {
	function cptch_display_example( $action ) {
		echo "<div class='cptch_example_fields_actions'>";
		switch( $action ) {
			case "cptch_math_action_plus":
				echo __( 'seven', 'captcha' ) . ' &#43; 1 = <img src="' . plugins_url( 'images/cptch_input.jpg' , __FILE__ ) . '" alt="" title="" width="" height="" />';
				break;
			case "cptch_math_action_minus":
				echo __( 'eight', 'captcha' ) . ' &minus; 6 = <img src="' . plugins_url( 'images/cptch_input.jpg' , __FILE__ ) . '" alt="" title="" width="" height="" />';
				break;
			case "cptch_math_action_increase":
				echo '<img src="' . plugins_url( 'images/cptch_input.jpg' , __FILE__ ) . '" alt="" title="" width="" height="" /> &times; 1 = ' . __( 'seven', 'captcha' );
				break;
			case "cptch_difficulty_number":
				echo '5 &minus; <img src="' . plugins_url( 'images/cptch_input.jpg' , __FILE__ ).'" alt="" title="" width="" height="" /> = 1';
				break;
			case "cptch_difficulty_word":
				echo __( 'six', 'captcha' ) . ' &#43; ' . __( 'one', 'captcha' ) . ' = <img src="' . plugins_url( 'images/cptch_input.jpg' , __FILE__ ) . '" alt="" title="" width="" height="" />';
				break;
		}
		echo "</div>";
	}
}

if ( ! function_exists ( 'cptch_admin_head' ) ) {
	function cptch_admin_head() {
		if ( isset( $_REQUEST['page'] ) && 'captcha.php' == $_REQUEST['page'] ) {			
			wp_enqueue_style( 'cptch_stylesheet', plugins_url( 'css/style.css', __FILE__ ) );
			wp_enqueue_script( 'cptch_script', plugins_url( 'js/script.js', __FILE__ ) );
		}
		if ( ! is_admin() )
			wp_enqueue_style( 'cptch_stylesheet', plugins_url( 'css/style.css', __FILE__ ) );
	}
}

/* Function for interaction with Limit Attempts plugin */
if ( ! function_exists( 'cptch_lmtttmpts_interaction' ) ) {
	function cptch_lmtttmpts_interaction() {
		global $cptch_options;
		$str_key = $cptch_options['cptch_str_key']['key'];
		if ( 1 == $cptch_options['cptch_login_form'] ) { /* check for captcha existing in login form */
			if ( isset( $_REQUEST['cptch_result'] ) && isset( $_REQUEST['cptch_number'] ) && isset( $_REQUEST['cptch_time'] ) ) { /* check for existing request by captcha */
				if ( 0 != strcasecmp( trim( cptch_decode( $_REQUEST['cptch_result'], $str_key, $_REQUEST['cptch_time'] ) ), $_REQUEST['cptch_number'] ) ) { /* is captcha wrong */
					if ( isset( $_SESSION["cptch_login"] ) && false === $_SESSION["cptch_login"] ) {
						return false; /* wrong captcha */
					}
				}
			}
		}
		return true; /* no captcha in login form or its right */
	}
}

if ( ! function_exists( 'cptch_plugin_action_links' ) ) {
	function cptch_plugin_action_links( $links, $file ) {		
		if ( ! is_network_admin() ) {
			static $this_plugin;
			if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

			if ( $file == $this_plugin ) {
				$settings_link = '<a href="admin.php?page=captcha.php">' . __( 'Settings', 'captcha' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
}

if ( ! function_exists( 'cptch_register_plugin_links' ) ) {
	function cptch_register_plugin_links( $links, $file ) {
		$base = plugin_basename( __FILE__ );
		if ( $file == $base ) {
			if ( ! is_network_admin() )
				$links[]	=	'<a href="admin.php?page=captcha.php">' . __( 'Settings', 'captcha' ) . '</a>';
			$links[]	=	'<a href="http://wordpress.org/plugins/captcha/faq/" target="_blank">' . __( 'FAQ', 'captcha' ) . '</a>';
			$links[]	=	'<a href="http://support.bestwebsoft.com">' . __( 'Support', 'captcha' ) . '</a>';
		}
		return $links;
	}
}

if ( ! function_exists ( 'cptch_plugin_banner' ) ) {
	function cptch_plugin_banner() {
		global $hook_suffix;	
		if ( 'plugins.php' == $hook_suffix ) {   
			global $cptch_plugin_info;
			bws_plugin_banner( $cptch_plugin_info, 'cptch', 'captcha', '345f1af66a47b233cd05bc55b2382ff0', '75', plugins_url( 'images/banner.png', __FILE__ ) ); 
		}
	}
}

/* Function for delete delete options */
if ( ! function_exists ( 'cptch_delete_options' ) ) {
	function cptch_delete_options() {
		delete_option( 'cptch_options' );
	}
}

register_activation_hook( __FILE__, 'cptch_settings' );

add_action( 'admin_menu', 'cptch_admin_menu' );

add_action( 'init', 'cptch_init' );
add_action( 'admin_init', 'cptch_admin_init' );

/* Additional links on the plugin page */
add_filter( 'plugin_action_links', 'cptch_plugin_action_links', 10, 2 );
add_filter( 'plugin_row_meta', 'cptch_register_plugin_links', 10, 2 );

add_action( 'admin_enqueue_scripts', 'cptch_admin_head' );
add_action( 'wp_enqueue_scripts', 'cptch_admin_head' );

add_action( 'admin_notices', 'cptch_plugin_banner' );

register_uninstall_hook( __FILE__, 'cptch_delete_options' );
?>
