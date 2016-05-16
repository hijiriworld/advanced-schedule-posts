<?php
/*
Plugin Name: Advanced Schedule Posts
Plugin URI: 
Description: Allows you to set datetime of expiration and to set schedule which overwrites the another post.
Version: 1.1.2
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

/*
* Class & Methods
*/

$cptg = new Hasp;
class Hasp
{
	function __construct()
	{
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
			$post_types = get_post_types();
			foreach( $post_types as $post_type )
			{
				add_meta_box(
					'hasp_meta_box',
					__( 'Advanced Schedule', 'hasp' ),
					array( $this, 'add_meta_box' ),
					$post_type,
					'side',
					'default'
				);
			}
		}
	}

	function add_meta_box()
	{
		require HASP_DIR.'/include/meta_box.php';
	}
	
	function get_post_list( $post_type, $post_id )
	{
		global $wpdb;
		$publish_posts = $wpdb->get_results(
			"SELECT ID, post_title FROM $wpdb->posts WHERE ID != '$post_id' AND post_type = '$post_type' AND post_status = 'publish'"
		);
		
		$sql = "SELECT DISTINCT meta_value 
			FROM $wpdb->postmeta 
			WHERE meta_key = 'hasp_overwrite_post_id'
		";
		$future_overwrite_posts = $wpdb->get_row( $sql, ARRAY_N );
		
		if ( empty( $future_overwrite_posts ) ) return $publish_posts;
		
		foreach( $publish_posts as $key => $publish_post ) {
			if ( in_array( $publish_post->ID, $future_overwrite_posts ) ) {
				unset( $pulish_posts[$key] );
			}
		}
		
		return $publish_posts;
	}

	function load_script_css() {
		// JavaScript
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'hasp-jquery-ui-timepicker-addon', HASP_URL.'/js/jquery-ui-timepicker-addon.js', array( 'jquery-ui-datepicker' ), '1.4.5', true );
		wp_enqueue_script( 'hasp-js', HASP_URL.'/js/script.js', array( 'jquery' ), '1.0', true );
		// CSS
		wp_enqueue_style( 'jquery-ui-theme', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.min.css', '', '1.11.2', 'all' );
		wp_enqueue_style( 'hasp-css', HASP_URL.'/css/style.css', array(), null );
	}
	
	function hasp_add_columns()
	{
		$post_types = get_post_types(array(
			'public' => true,
		));
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
			}
			if ( $hasp_overwrite_enable && $hasp_overwrite_post_id ) {
				echo __( 'Overwrite:', 'hasp' ).'<br>'.get_the_title( $hasp_overwrite_post_id );
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
	
				$from_overwrite_post = array();
				$from_overwrite_post['ID'] = $hasp_overwrite_post_id;
				$from_overwrite_post['post_status'] = 'draft';
				$from_overwrite_post['post_name'] = $overwrite_post_name. '-'. date( 'Ymd' );
				wp_update_post( $from_overwrite_post );
				
				$to_overwrite_post = array();
				$to_overwrite_post['ID'] = $post_id;
				$to_overwrite_post['post_name'] = $overwrite_post_name;
				wp_update_post( $to_overwrite_post );
				
				$this->clear_overwrite( $post_id );

				// for nav-menus
				$sql = "UPDATE $wpdb->postmeta SET meta_value = {$post_id} WHERE meta_key = '_menu_item_object_id' AND meta_value = {$hasp_overwrite_post_id};";
				$result = $wpdb->query( $sql );
			}
		}
	}
}

?>