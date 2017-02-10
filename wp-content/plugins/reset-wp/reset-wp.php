<?php
/*
Plugin Name: Reset WP
Plugin URI: http://www.nikunjsoni.co.in
Description: It resets the WordPress database to the default installation. It deletes all content and custom changes. Resets only database not modify or delete any files.
Version: 1.0
Author: Nikunj Soni
Author URI: http://www.nikunjsoni.co.in
Text Domain: Reset-WP
*/

/*

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

if ( ! defined( 'ABSPATH' ) ){
	exit; // Exit if accessed this file directly
} 

if ( is_admin() ) {

define( 'REACTIVATE_THE_RESET_WP', true );

class ResetWP {
	
	function ResetWP() {
		add_action( 'admin_menu', array( &$this, 'add_page' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_filter( 'favorite_actions', array( &$this, 'add_favorite' ), 100 );
		add_action( 'wp_before_admin_bar_render', array( &$this, 'admin_bar_link' ) );
	}
	
	// Checks reset_wp post value and performs an installation, adding the users previous password also
	function admin_init() {
		global $current_user;

		$reset_wp = ( isset( $_POST['reset_wp'] ) && $_POST['reset_wp'] == 'true' ) ? true : false;
		$reset_wp_confirm = ( isset( $_POST['reset_wp_confirm'] ) && $_POST['reset_wp_confirm'] == 'reset-wp' ) ? true : false;
		$valid_nonce = ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'reset_wp' ) ) ? true : false;

		if ( $reset_wp && $reset_wp_confirm && $valid_nonce ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

			$blogname = get_option( 'blogname' );
			$admin_email = get_option( 'admin_email' );
			$blog_public = get_option( 'blog_public' );

			if ( $current_user->user_login != 'admin' )
				$user = get_user_by( 'login', 'admin' );

			if ( empty( $user->user_level ) || $user->user_level < 10 )
				$user = $current_user;

			global $wpdb;

			$prefix = str_replace( '_', '\_', $wpdb->prefix );
			$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}

			$result = wp_install( $blogname, $user->user_login, $user->user_email, $blog_public );
			extract( $result, EXTR_SKIP );

			$query = $wpdb->prepare( "UPDATE $wpdb->users SET user_pass = '".$user->user_pass."', user_activation_key = '' WHERE ID =  '".$user_id."' ");
			$wpdb->query( $query );

			$get_user_meta = function_exists( 'get_user_meta' ) ? 'get_user_meta' : 'get_usermeta';
			$update_user_meta = function_exists( 'update_user_meta' ) ? 'update_user_meta' : 'update_usermeta';

			if ( $get_user_meta( $user_id, 'default_password_nag' ) )
				$update_user_meta( $user_id, 'default_password_nag', false );

			if ( $get_user_meta( $user_id, $wpdb->prefix . 'default_password_nag' ) )
				$update_user_meta( $user_id, $wpdb->prefix . 'default_password_nag', false );

			
			if ( defined( 'REACTIVATE_THE_RESET_WP' ) && REACTIVATE_THE_RESET_WP === true )
				@activate_plugin( plugin_basename( __FILE__ ) );
			

			wp_clear_auth_cookie();
			
			wp_set_auth_cookie( $user_id );

			wp_redirect( admin_url()."?reset-wp=reset-wp" );
			exit();
		}

		if ( array_key_exists( 'reset-wp', $_GET ) && stristr( $_SERVER['HTTP_REFERER'], 'reset-wp' ) )
			add_action( 'admin_notices', array( &$this, 'admin_notices_successfully_reset' ) );
	}
	
	// admin_menu action hook operations & Add the settings page
	function add_page() {
		if ( current_user_can( 'level_10' ) && function_exists( 'add_management_page' ) )
			$hook = add_management_page( 'Reset WP', 'Reset WP', 'level_10', 'reset-wp', array( &$this, 'admin_page' ) );
			add_action( "admin_print_scripts-{$hook}", array( &$this, 'admin_javascript' ) );
			add_action( "admin_footer-{$hook}", array( &$this, 'footer_javascript' ) );
	}
	
	function add_favorite( $actions ) {
		$reset['tools.php?page=reset-wp'] = array( 'Reset WP', 'level_10' );
		return array_merge( $reset, $actions );
	}

	function admin_bar_link() {
		global $wp_admin_bar;
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'site-name',
				'id'     => 'reset-wp',
				'title'  => 'Reset WP',
				'href'   => admin_url( 'tools.php?page=reset-wp' )
			)
		);
	}
	
	// Inform the user that WordPress has been successfully reset
	function admin_notices_successfully_reset() {
		$user = get_user_by( 'id', 1 );
		echo '<div id="message" class="updated"><p><strong>WordPress has been reset successfully. The user "' . $user->user_login . '" is recreated with its current login password.</strong></p></div>';
		do_action( 'reset_wp_post', $user );
	}
	
	function admin_javascript() {
		wp_enqueue_script( 'jquery' );
	}

	function footer_javascript() {
	?>
	<script type="text/javascript">
		jQuery('#reset_wp_submit').click(function(){
			if ( jQuery('#reset_wp_confirm').val() == 'reset-wp' ) {
				var message = 'This action can not be Undo.\n\nClicking "OK" will reset your database to the default installation.\n\nIt deletes all content and custom changes.\nIt will only reset the database.\nIt will not modify or delete any files.\n\n Click "Cancel" to stop this operation.'
				var reset = confirm(message);
				if ( reset ) {
					jQuery('#reset_wp_form').submit();
				} else {
					jQuery('#reset_wp').val('false');
					return false;
				}
			} else {
				alert('Invalid confirmation. Please type \'reset-wp\' in the confirmation field.');
				return false;
			}
		});
	</script>	
	<?php
	}

	// add_option_page callback operations
	function admin_page() {
		global $current_user;
		if ( isset( $_POST['reset_wp_confirm'] ) && $_POST['reset_wp_confirm'] != 'reset-wp' )
			echo '<div class="error fade"><p><strong>Invalid confirmation. Please type \'reset-wp\' in the confirmation field.</strong></p></div>';
		elseif ( isset( $_POST['_wpnonce'] ) )
			echo '<div class="error fade"><p><strong>Invalid wpnonce. Please try again.</strong></p></div>';
			
	?>
	<div class="wrap">
		<div id="icon-tools" class="icon32"><br /></div>
		<h2>Reset WP</h2>
		<p><strong>After completing this reset operation, you will be automatically redirected to the dashboard.</strong></p>
		
		<?php $admin = get_user_by( 'login', 'admin' ); ?>
		<?php if ( ! isset( $admin->user_login ) || $admin->user_level < 10 ) : $user = $current_user; ?>
		<p>The 'admin' user does not exist. The user '<strong><?php echo esc_html( $user->user_login ); ?></strong>' will be recreated with its <strong>current password</strong> with user level 10.</p>
		<?php else : ?>
		
		<p>The '<strong>admin</strong>' user exists and will be recreated with its <strong>current password</strong>.</p>
		<?php endif; ?>
		<p>This plugin <strong>will be automatically reactivated</strong> after the reset operation.</p>
		
		<hr/>
		
		<p>To confirm the reset operation, type '<strong>reset-wp</strong>' in the confirmation field below and then click the Reset button</p>
		<form id="reset_wp_form" action="" method="post" autocomplete="off">
			<?php wp_nonce_field( 'reset_wp' ); ?>
			<input id="reset_wp" type="hidden" name="reset_wp" value="true" />
			<input id="reset_wp_confirm" type="text" name="reset_wp_confirm" value="" maxlength="8" />
			<input id="reset_wp_submit" style="width: 80px;" type="submit" name="Submit" class="button-primary" value="Reset" />
			
		</form>
	</div>
	<?php
	}
}

$ResetWP = new ResetWP();

}