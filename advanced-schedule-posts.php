<?php
/*
Plugin Name: Advanced Schedule Posts
Plugin URI: 
Description: Allows you to set datetime of expiration and to set schedule which overwrites the another post.
Version: 1.2.1
Author: hijiri
Author URI: http://hijiriworld.com/web/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*
* Define
*/

define( 'HASP_URL', plugins_url('', __FILE__) );
define( 'HASP_DIR', plugin_dir_path(__FILE__) );
load_plugin_textdomain( 'hasp', false, basename(dirname(__FILE__)).'/lang' );

/**
* Deactivation hook
*/

register_deactivation_hook( __FILE__, 'hasp_deactivation' );
function hasp_deactivation()
{
	global $wpdb;
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		if (is_network_admin()) {
			$curr_blog = $wpdb->blogid;
			$blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			foreach( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				delete_option( 'hasp_activation' );
				delete_option( 'hasp_options' );
			}
			switch_to_blog( $curr_blog );
		} else {
			delete_option( 'hasp_activation' );
			delete_option( 'hasp_options' );
		}
	} else {
		delete_option( 'hasp_activation' );
		delete_option( 'hasp_options' );
	}
}

/*
* Class & Methods
*/

$cptg = new Hasp;
class Hasp
{
	function __construct()
	{
		add_action( 'admin_menu', array( $this, 'admin_menu') );
		add_action( 'admin_init', array( $this, 'update_options') );

		// to publish - save expire unsave overwrite
		add_action( 'new_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'publish_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'pending_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'draft_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'future_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'private_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'inherit_to_publish', array( $this, 'action_to_publish' ) );
		
		// to future - save expire save overwrite
		add_action( 'new_to_future', array( $this, 'action_to_future' ) );
		add_action( 'publish_to_future', array( $this, 'action_to_future' ) );
		add_action( 'pending_to_future', array( $this, 'action_to_future' ) );
		add_action( 'draft_to_future', array( $this, 'action_to_future' ) );
		add_action( 'future_to_future', array( $this, 'action_to_future' ) );
		add_action( 'private_to_future', array( $this, 'action_to_future' ) );
		add_action( 'inherit_to_future', array( $this, 'action_to_future' ) );
		
		// to trash - unsave expire unsave overwrite
		add_action( 'trashed_post', array( $this, 'action_to_trash' ) );
		
		// admin_init
		add_action( 'add_meta_boxes', array( $this, 'hasp_add_meta_box' ) );
		add_action( 'admin_init', array( $this, 'load_script_css' ) );
		add_action( 'admin_init', array( $this, 'hasp_add_columns' ) );
		add_action( 'quick_edit_custom_box', array($this, 'hasp_quick_edit_custom'), 10, 2);
		
		// do
		if( !is_admin() )
		{
		add_action( 'init', array( $this, 'do_expire' ) );
		add_action( 'init', array( $this, 'do_overwrite' ) );
		}
	}
	
	
	/*
	* Setting Method
	*/
	
	function hasp_add_meta_box()
	{
		
		if ( current_user_can( 'publish_posts' ) ) {
//			$post_types = get_post_types();
			$post_types = $this->get_hasp_options_objects();
			foreach( $post_types as $post_type )
			{
				$obj = get_post_type_object( $post_type );
				$show_ui_value = $obj->show_ui;
				if ( !$show_ui_value || 'attachment' == $post_type) {
					continue;
				}

				$activate_expire_flg = $this->hasp_activate_function_by_posttype( $post_type );
				if(!$activate_expire_flg['expire'] && !$activate_expire_flg['overwrite'] ) continue;
				
				if(get_current_screen()->post_type === $post_type){
					add_action('post_submitbox_misc_actions', array($this, 'add_submitbox'), 5);
				}
			}
		}
	}
	
	function add_submitbox(){
		ob_start();
		require HASP_DIR.'/include/meta_box.php';
		$meta_box = ob_get_clean();
		echo $meta_box;
	}

	function add_meta_box()
	{
		require HASP_DIR.'/include/meta_box.php';
	}
	
	function get_post_list( $post_type, $post_id )
	{
		global $wpdb;
		
		// Respect for Intuitive Custom Post Order plugin
		$hicpo_options = get_option( 'hicpo_options' ) ? get_option( 'hicpo_options' ) : array();
		$hicpo_objects = isset( $hicpo_options['objects'] ) && is_array( $hicpo_options['objects'] ) ? $hicpo_options['objects'] : array();
		$orderby = in_array( $post_type, $hicpo_objects ) ? 'menu_order ASC' : 'post_date DESC';

		$publish_posts = $wpdb->get_results(
			"SELECT ID, post_title FROM $wpdb->posts WHERE ID != '$post_id' AND post_type = '$post_type' AND post_status = 'publish' ORDER BY $orderby"
		);
		
		$sql = "SELECT DISTINCT meta_value 
			FROM $wpdb->postmeta 
			WHERE meta_key = 'hasp_overwrite_post_id'
		";
		$future_overwrite_posts = $wpdb->get_row( $sql, ARRAY_N );
		
		if ( empty( $future_overwrite_posts ) ) return $publish_posts;
		
		foreach( $publish_posts as $key => $publish_post ) {
			if ( in_array( $publish_post->ID, $future_overwrite_posts ) ) {
				unset( $publish_posts[$key] );
			}
		}
		
		return $publish_posts;
	}

	public function hasp_quick_edit_custom($column){
		//Display our custom content on the quick-edit interface, no values can be pre-populated (all done in JavaScript)
		$html = '';

		//output hasp_expire_enable field 
		if($column == 'hasp'){
			$html .= '<input type="hidden" name="hasp_expire_enable" id="hasp_expire_enable" >';
			$html .= '<input type="hidden" name="hasp_expire_date" id="hasp_expire_date" >';
			$html .= '<input type="hidden" name="hasp_overwrite_enable" id="hasp_overwrite_enable" >';
			$html .= '<input type="hidden" name="hasp_overwrite_post_id" id="hasp_overwrite_post_id" >';
		}

		echo $html;
	}

	function load_script_css() {
		// JavaScript
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'hasp-jquery-ui-timepicker-addon', HASP_URL.'/js/jquery-ui-timepicker-addon.js', array( 'jquery-ui-datepicker' ), '1.4.5', true );
		wp_enqueue_script( 'hasp-js', HASP_URL.'/js/script.js', array( 'jquery', 'inline-edit-post' ), false, true );
		// CSS
		wp_enqueue_style( 'jquery-ui-theme', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.min.css', '', '1.11.2', 'all' );
		wp_enqueue_style( 'hasp-css', HASP_URL.'/css/style.css', array(), null );
	}
	
	function hasp_add_columns()
	{
/*
	$post_types = get_post_types(array(
			'public' => true,
		));
*/
		$post_types = $this->get_hasp_options_objects();
		foreach( $post_types as $post_type ) {
			add_filter( 'manage_edit-'.$post_type.'_columns', array( $this, 'add_custom_posts_columns_name' ) );
			if ( $post_type == 'page' ) {
				add_action( 'manage_pages_custom_column', array( $this, 'add_custom_posts_columns' ), 10, 2 );
			} else {
				add_action( 'manage_posts_custom_column', array( $this, 'add_custom_posts_columns' ), 10, 2 );
			}
		}
	}
	function add_custom_posts_columns_name( $columns )
	{
		$columns['hasp'] = __( 'Schedule', 'hasp' );
		return $columns;
	}
	function add_custom_posts_columns( $column, $post_id )
	{
		if( $column == 'hasp' ) {
			$hasp_expire_enable = get_post_meta( $post_id, 'hasp_expire_enable', true );
			$hasp_expire_date = get_post_meta( $post_id, 'hasp_expire_date', true );
			$hasp_overwrite_enable = get_post_meta( $post_id, 'hasp_overwrite_enable', true );
			$hasp_overwrite_post_id = get_post_meta( $post_id, 'hasp_overwrite_post_id', true );
		
			if( $hasp_expire_enable && $hasp_expire_date ) {
				echo __( 'Expired on:', 'hasp' ).'<br>'.date( 'Y/m/d H:i', strtotime( $hasp_expire_date ) ).'<br>';
				echo '<input type="hidden" id="hasp_expire_enable" value="' . $hasp_expire_enable . '">';
				echo '<input type="hidden" id="hasp_expire_date" value="' . date( 'Y-m-d H:i', strtotime( $hasp_expire_date )) . '">';
			}
			if ( $hasp_overwrite_enable && $hasp_overwrite_post_id ) {
				echo __( 'Overwrite:', 'hasp' ).'<br>'.get_the_title( $hasp_overwrite_post_id );
				echo '<input type="hidden" id="hasp_overwrite_enable" value="' . $hasp_overwrite_enable . '">';
				echo '<input type="hidden" id="hasp_overwrite_post_id" value="' . $hasp_overwrite_post_id . '">';
			}
		}
	}

	/*
	* Action Method
	*/
	
	function action_to_publish( $post )
	{
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
		if ( !isset( $_POST['action'] ) ) return false;
		
		$post_id = $post->ID;
		
		$this->save_expire( $post_id );
		$this->clear_overwrite( $post_id );
	}
	
	function action_to_future( $post )
	{
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
		if ( !isset( $_POST['action'] ) ) return false;
		
		$post_id = $post->ID;
		
		$this->save_expire( $post_id );
		$this->save_overwrite( $post_id );
	}
	
	function action_to_trash( $post_id )
	{
		$this->clear_expire( $post_id );
		$this->clear_overwrite( $post_id );
	}

	function save_expire( $post_id )
	{
		$hasp_expire_enable = isset( $_POST['hasp_expire_enable'] ) ? 1 : 0;
		$hasp_expire_date = isset( $_POST['hasp_expire_date'] ) && $_POST['hasp_expire_date'] ? $_POST['hasp_expire_date'].':00' : '';
			
		update_post_meta( $post_id, 'hasp_expire_enable', $hasp_expire_enable );
		update_post_meta( $post_id, 'hasp_expire_date', $hasp_expire_date );
		
		return $post_id;
	}
	function save_overwrite( $post_id )
	{
		$hasp_overwrite_enable = isset( $_POST['hasp_overwrite_enable'] ) ? 1 : 0;
		$hasp_overwrite_post_id = $_POST['hasp_overwrite_post_id'];
		
		update_post_meta( $post_id, 'hasp_overwrite_enable', $hasp_overwrite_enable );
		update_post_meta( $post_id, 'hasp_overwrite_post_id', $hasp_overwrite_post_id );
		
		return $post_id;
	}
	function clear_expire( $post_id )
	{
		update_post_meta( $post_id, 'hasp_expire_enable', '' );
		update_post_meta( $post_id, 'hasp_expire_date', '' );
	}
	
	function clear_overwrite( $post_id )
	{
		update_post_meta( $post_id, 'hasp_overwrite_enable', '' );
		update_post_meta( $post_id, 'hasp_overwrite_post_id', '' );	
	}
	

	/*
	* Run Method
	*/
	
	function do_expire()
	{
		global $wpdb;
		
		$sql = "SELECT posts.ID 
			FROM $wpdb->posts AS posts 
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id ) 
			INNER JOIN $wpdb->postmeta AS postmeta2 ON ( posts.ID = postmeta2.post_id ) 
			WHERE posts.post_status = 'publish' 
			AND ( postmeta1.meta_key = 'hasp_expire_enable' AND postmeta1.meta_value = '1' ) 
			AND ( postmeta2.meta_key = 'hasp_expire_date' AND postmeta2.meta_value <= '".current_time( 'mysql' )."' ) 
			GROUP BY posts.ID
		";
		$result = $wpdb->get_results( $sql );
		
		if ( empty( $result ) ) return false;
		
		foreach ( $result as $post ) {
			
			$post_id = $post->ID;
			
			// publish → draft
			$overwrite_post = array();
			$overwrite_post['ID'] = $post_id;
			$overwrite_post['post_status'] = 'draft';
			wp_update_post( $overwrite_post );
			
			$this->clear_expire( $post_id );
		}
	}
	function do_overwrite()
	{
		global $wpdb;
		
		$sql = "SELECT posts.ID 
			FROM $wpdb->posts AS posts 
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id ) 
			WHERE posts.post_status = 'publish' 
			AND ( postmeta1.meta_key = 'hasp_overwrite_enable' AND postmeta1.meta_value = '1' ) 
			AND posts.post_date <= '".current_time( 'mysql' )."'
			GROUP BY posts.ID
		";
		$result = $wpdb->get_results( $sql );
		
		if ( empty( $result ) ) return false;
		
		foreach( $result as $post ) {
			$post_id = $post->ID;
		
			$hasp_overwrite_enable  = get_post_meta( $post_id, 'hasp_overwrite_enable', true );
			$hasp_overwrite_post_id = get_post_meta( $post_id, 'hasp_overwrite_post_id', true );
			
			if ( $hasp_overwrite_enable && $hasp_overwrite_post_id ) {
	
				$overwrite_post_name = get_post_field( 'post_name', $hasp_overwrite_post_id );

				$from_overwrite_post_sql = "UPDATE $wpdb->posts SET post_status = 'draft',post_name = '{$overwrite_post_name}-". date( 'Ymd' ). "' WHERE ID = {$hasp_overwrite_post_id};";
				$from_result = $wpdb->query( $from_overwrite_post_sql);

				$to_overwrite_post_sql = "UPDATE $wpdb->posts SET post_name = '{$overwrite_post_name}' WHERE ID = {$post_id};";
				$to_result = $wpdb->query( $to_overwrite_post_sql);
				
				$this->clear_overwrite( $post_id );

				// for nav-menus
				$sql = "UPDATE $wpdb->postmeta SET meta_value = {$post_id} WHERE meta_key = '_menu_item_object_id' AND meta_value = {$hasp_overwrite_post_id};";
				$result = $wpdb->query( $sql );
        
				// for ACF Post Object Field
				$sql = "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE meta_value = '{$hasp_overwrite_post_id}';";
				$posts = $wpdb->get_results( $sql );
				foreach( $posts as $post) {
					if ( $this->hasp_record_check( $post->post_id, $post->meta_key ) ){
						$sql = "UPDATE $wpdb->postmeta SET meta_value = {$post_id} WHERE post_id = {$post->post_id} AND meta_key = '{$post->meta_key}' AND meta_value = {$hasp_overwrite_post_id};";
						$result = $wpdb->query( $sql );
					}
				}
			}
		}
	}

	/*
	* Admin Setting
	*/
	
	function admin_menu()
	{
		if ( !get_option( 'hasp_activation' ) ) $this->hasp_activation();
		add_options_page( __( 'Advanced Schedule', 'hasp' ), __( 'Advanced Schedule', 'hasp' ), 'manage_options', 'hasp-settings', array( $this,'admin_page' ) );
	}
	
	function admin_page()
	{
		require HASP_DIR.'admin/settings.php';
	}
	
	/**
	* Load Setting
	*/
	
	function get_hasp_options_objects()
	{
		$hasp_options = get_option( 'hasp_options' ) ? get_option( 'hasp_options' ) : array();
		$objects = isset( $hasp_options['objects'] ) && is_array( $hasp_options['objects'] ) ? $hasp_options['objects'] : array();
		return $objects;
	}

	/**
	* Update Setting
	*/
	
	function update_options()
	{
		if ( !isset( $_POST['hasp_submit'] ) ) return false;

		check_admin_referer( 'nonce_hasp' );

		$input_options = array();
		$input_options['objects'] = isset( $_POST['objects'] ) ? $_POST['objects'] : '';
		$input_options['activate_expire'] = isset( $_POST['activate_expire'] ) ? $_POST['activate_expire'] : '';
		$input_options['activate_overwrite'] = isset( $_POST['activate_overwrite'] ) ? $_POST['activate_overwrite'] : '';

		update_option( 'hasp_options', $input_options );
		wp_redirect( 'admin.php?page=hasp-settings&msg=update' );
	}

	/**
	* Initial Setting
	*/
	
	function hasp_activation()
	{
		$post_types = get_post_types();
		foreach( $post_types as $post_type )
		{
			$obj = get_post_type_object( $post_type );
			$public_value = $obj->public;
			$show_ui_value = $obj->show_ui;
			if (!$public_value || !$show_ui_value || 'attachment' == $post_type) {
				continue;
			}
			$_objects[] = $post_type;
		}
		$input_options = array();
		$input_options['objects'] = isset( $_objects ) ? $_objects : '';
		add_option('hasp_options', $input_options, '', 'no');
		add_option('hasp_activation', 1, '', 'no');
	}
	
	/**
	 * Get function activate status by post type
	 */
	function hasp_activate_function_by_posttype($post_type){
		$rtn = array('expire' => FALSE, 'overwrite' => FALSE );
		$hasp_options = get_option( 'hasp_options' );
		if((array_key_exists('activate_expire',$hasp_options) && in_array($post_type, $hasp_options['activate_expire'])) || !array_key_exists('activate_expire',$hasp_options)){
			$rtn['expire'] = TRUE;
		}
		if((array_key_exists('activate_overwrite',$hasp_options) && in_array($post_type, $hasp_options['activate_overwrite'])) || !array_key_exists('activate_overwrite',$hasp_options)){
			$rtn['overwrite'] = TRUE;
		}
		return $rtn;
	}
  
	/*
	* Check ACF object record
	*/
	function hasp_record_check( $post_id, $meta_key )
	{
		global $wpdb;
		$sql = "SELECT EXISTS (SELECT * FROM $wpdb->postmeta WHERE post_id = {$post_id} AND meta_key = '_{$meta_key}' AND meta_value LIKE 'field_%');";
		return $wpdb->get_var( $sql );
	}
	
}
?>