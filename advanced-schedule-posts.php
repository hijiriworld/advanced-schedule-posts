<?php
/*
Plugin Name: Advanced Schedule Posts
Plugin URI:
Description: Allows you to set datetime of expiration and to set schedule which overwrites the another post.
Version: 2.1.8
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
				delete_option( 'hasp_cron_started' );
				$_rtn = wp_clear_scheduled_hook( 'hasp_cron_execute' );
			}
			switch_to_blog( $curr_blog );
		} else {
			delete_option( 'hasp_activation' );
			delete_option( 'hasp_options' );
			delete_option( 'hasp_cron_started' );
			$_rtn = wp_clear_scheduled_hook( 'hasp_cron_execute' );
		}
	} else {
		delete_option( 'hasp_activation' );
		delete_option( 'hasp_options' );
		delete_option( 'hasp_cron_started' );
		$_rtn = wp_clear_scheduled_hook( 'hasp_cron_execute' );
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
		// Add a new wp-cron schedule for ASP
		add_filter('cron_schedules', array( $this, 'hasp_cron_schedules' ));

		// Set the reservation time to 00 seconds
		add_action( 'transition_post_status', array( $this, 'save_future' ), 1 ,6 );

		// overwrite in real time when published
		add_action( 'save_post', array( $this, 'publish_overwrite'), 10, 2 );

		// to publish - save expire unsave overwrite
		add_action( 'new_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'auto-draft_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'publish_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'pending_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'draft_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'future_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'private_to_publish', array( $this, 'action_to_publish' ) );
		add_action( 'inherit_to_publish', array( $this, 'action_to_publish' ) );

		// to future - save expire save overwrite
		add_action( 'new_to_future', array( $this, 'action_to_future' ) );
		add_action( 'auto-draft_to_future', array( $this, 'action_to_future' ) );
		add_action( 'publish_to_future', array( $this, 'action_to_future' ) );
		add_action( 'pending_to_future', array( $this, 'action_to_future' ) );
		add_action( 'draft_to_future', array( $this, 'action_to_future' ) );
		add_action( 'future_to_future', array( $this, 'action_to_future' ) );
		add_action( 'private_to_future', array( $this, 'action_to_future' ) );
		add_action( 'inherit_to_future', array( $this, 'action_to_future' ) );

		// to draft
		add_action( 'future_to_draft', array( $this, 'action_to_publish' ) );
		add_action( 'draft_to_draft', array( $this, 'action_to_publish' ) );

		// clear wp-cron publish_future_post
		add_action( 'save_post', array( $this, 'clear_cron_schedule'), 13, 2 );
		// wp-cron action
		add_action( 'hasp_expire_cron', array( $this, 'cron_execute_trigger' ), 10, 1 );
		add_action( 'hasp_overwrite_cron', array( $this, 'cron_execute_trigger' ) );
		add_action( 'hasp_cron_execute', array( $this, 'cron_execute_trigger' ), 10, 1 );

		// to trash - unsave expire unsave overwrite
		add_action( 'trashed_post', array( $this, 'action_to_trash' ) );

		// admin_init
		add_action( 'add_meta_boxes', array( $this, 'hasp_add_meta_box' ) );
		add_action( 'admin_init', array( $this, 'load_script_css' ) );
		add_action( 'admin_init', array( $this, 'hasp_add_columns' ) );
		add_action( 'quick_edit_custom_box', array($this, 'hasp_quick_edit_custom'), 10, 2);

		add_action( 'admin_menu', array( $this, 'admin_menu') );
		add_action( 'admin_init', array( $this, 'update_options') );

		// Message when overwriting and publishing immediately
		add_action( 'admin_notices', array( $this, 'overerite_admin_notice__success') );

		// do
		if( !is_admin() )
		{
		// add_action( 'init', array( $this, 'do_expire' ) );
		// add_action( 'init', array( $this, 'do_overwrite' ) );
			add_action( 'init', array( $this, 'cron_start' ) );
		}
	}


	/*
	* Setting Method
	*/

	/**
	 * Set the reservation time to 00 seconds
	 * @param String $new_status
	 * @param String $old_status
	 * @param Object $post
	 */
	function save_future( $new_status, $old_status, $post ) {
		if ($new_status === 'future' && $old_status !== 'future') {
			$post_id = $post->ID;
			$update = ( $old_status == 'auto-draft' ) ? false : true;
			do_action('save_post', $post_id, $post, $update);
			$post_date = $post->post_date;
			$post_date = date("Y-m-d H:i:00", strtotime($post_date));
			$post_date_gmt = $post->post_date_gmt;
			$post_date_gmt = date("Y-m-d H:i:00", strtotime($post_date_gmt));
			$post_val = array(
					'ID'            => $post_id,
					'post_date'     => $post_date,
					'post_date_gmt' => $post_date_gmt,
			);
			wp_update_post( $post_val );
		}
		return;
	}
	
	function hasp_add_meta_box()
	{

		if ( current_user_can( 'publish_posts' ) ) {
//			$post_types = get_post_types();
			$post_types = $this->get_hasp_options_objects();
			foreach( $post_types as $post_type )
			{
				$obj = get_post_type_object( $post_type );
/*
				$show_ui_value = $obj->show_ui;
				if ( !$show_ui_value || 'attachment' == $post_type) {
					continue;
				}
*/

				$activate_expire_flg = $this->hasp_activate_function_by_posttype( $post_type );
				if(!$activate_expire_flg['expire'] && !$activate_expire_flg['overwrite'] ) continue;

				// Check the editor
				if ( $this->get_classic_editor_state() ){
					if(get_current_screen()->post_type === $post_type){
						add_action('post_submitbox_misc_actions', array($this, 'add_submitbox'), 5);
					}
				} else {
					add_meta_box(
						'hasp_meta_box',
						__( 'Advanced Schedule', 'hasp' ),
						array( $this, 'add_meta_box_block' ),
						$post_type,
						'side',
						'default'
					);
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

	function add_meta_box_block()
	{
		require HASP_DIR.'/include/meta_box_block.php';
	}

	function get_post_list( $post_type, $post_id )
	{
		global $wpdb;
/*
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
				unset( $publish_posts[$key] );
			}
		}
*/
		// Respect for Intuitive Custom Post Order plugin
		$hicpo_options = get_option( 'hicpo_options' ) ? get_option( 'hicpo_options' ) : array();
		$hicpo_objects = isset( $hicpo_options['objects'] ) && is_array( $hicpo_options['objects'] ) ? $hicpo_options['objects'] : array();
		$orderby = in_array( $post_type, $hicpo_objects ) ? 'menu_order ASC' : 'post_date DESC';

		$publish_posts = $wpdb->get_results(
			"SELECT ID, post_title FROM $wpdb->posts WHERE ID != '$post_id' AND post_type = '$post_type' AND (post_status = 'publish' or post_status = 'future') ORDER BY $orderby"
		);
		$sql = "SELECT DISTINCT postmeta.post_id
			FROM $wpdb->postmeta postmeta
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( postmeta.post_id = postmeta1.post_id )
			WHERE postmeta.meta_key = 'hasp_overwrite_post_id' and (postmeta.meta_value <> '' and postmeta.meta_value <> '0')
			AND ( postmeta1.meta_key = 'hasp_overwrite_enable' AND postmeta1.meta_value = '1' )
		";
		$future_overwrite_posts = $wpdb->get_results( $sql );

		if ( empty( $future_overwrite_posts ) ) return $publish_posts;

		foreach( $publish_posts as $key => $publish_post ) {
			foreach( $future_overwrite_posts as $future_overwrite_post ) {
				if ( $publish_post->ID === $future_overwrite_post->post_id ) {
					unset( $publish_posts[$key] );
					continue;
				}
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
		wp_enqueue_script( 'jquery-ui-tabs', array('jquery-ui-core') );
		wp_enqueue_script( 'hasp-jquery-ui-timepicker-addon', HASP_URL.'/js/jquery-ui-timepicker-addon.js', array( 'jquery-ui-datepicker' ), '1.4.5', true );

		// URI check of this page
		$uri_chk = $this->hasp_uri_check();
		if ( $uri_chk ){
			wp_enqueue_script( 'hasp-js', HASP_URL.'/js/script.js', array( 'jquery', 'inline-edit-post' ), '2.0', true );
		}

		// CSS
		wp_enqueue_style( 'jquery-ui-theme', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.min.css', '', '1.11.2', 'all' );
		wp_enqueue_style( 'hasp-css', HASP_URL.'/css/style.css', array(), null );
	}

	/*
		Function content：Check if the keyword specified in the URI is included
		Function result： include=>True   Not included=>False
	*/
	private function hasp_uri_check(){
		$patterns = array('/post.php\?post=/','/post-new.php/','/edit.php/','/admin.php\?page=hasp-list/','/admin.php\?page=hasp-settings/');
		$res = false;

		$cur_uri = add_query_arg( NULL, NULL );
		foreach( $patterns as $pat){
			preg_match($pat, $cur_uri, $matches);
			if( count($matches) > 0 ){
				$res = true;
				break;
			}
		}

		return $res;
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
				echo __( 'Expire', 'hasp' ).'<br>'.date( 'Y/m/d H:i', strtotime( $hasp_expire_date ) ).'<br>';
				echo '<input type="hidden" id="hasp_expire_enable" value="' . $hasp_expire_enable . '">';
				echo '<input type="hidden" id="hasp_expire_date" value="' . date( 'Y-m-d H:i', strtotime( $hasp_expire_date )) . '">';
			}
			if ( $hasp_overwrite_enable && $hasp_overwrite_post_id ) {
				echo __( 'Overwrite', 'hasp' ).'<br>'.get_the_title( $hasp_overwrite_post_id );
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
		if ( isset($_POST['blog_id']) && $_POST['blog_id'] != get_current_blog_id() ) return false;
		if ( isset($_POST['ID']) && $_POST['ID'] != $post->ID ) return false;

		$post_id = $post->ID;

		$this->save_expire( $post_id );

		// heartbeat action does not run
		if($_POST['action'] === "heartbeat") {
			$this->hasp_log_out("/-- " . __FUNCTION__ . " --");
			$this->hasp_log_out( $post_id );
			$this->hasp_log_out("-- " . __FUNCTION__ . " --/");
			return;
		}

		if(isset($_POST['hasp_overwrite_enable']) && $_POST['hasp_overwrite_enable'] === "on" && $_POST['post_status'] !== "draft") {
			// overwrite in real time when published
			$this->save_overwrite( $post_id );
		} else {
			$this->clear_overwrite( $post_id );
		}
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
		$this->trash_hasp_overwrite_post_id ( $post_id );
	}

	function save_expire( $post_id )
	{
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return $post_id;
		}
		$hasp_expire_enable = isset( $_POST['hasp_expire_enable'] ) ? 1 : 0;
		$hasp_expire_date = isset( $_POST['hasp_expire_date'] ) && $_POST['hasp_expire_date'] ? $_POST['hasp_expire_date'].':00' : '';

		update_post_meta( $post_id, 'hasp_expire_enable', $hasp_expire_enable );
		update_post_meta( $post_id, 'hasp_expire_date', $hasp_expire_date );

		// set hasp wp-cron
		if($hasp_expire_enable > 0) {
			$timezone = date_default_timezone_get();
			$this_timezone = get_option('timezone_string');
			if(empty($this_timezone)) {
				$gmt_offset = get_option('gmt_offset');
				if(intval($gmt_offset) > 0) {
					$gmt_offset = "-" . intval($gmt_offset);
				} else {
					$gmt_offset = "+" . abs(intval($gmt_offset));
				}
				$date = new DateTime( $hasp_expire_date );
				$date->modify($gmt_offset . ' hours');
			} else {
				$date = new DateTime( $hasp_expire_date, new DateTimeZone($this_timezone) );
			}
			if($timezone === "Asia/Tokyo") {
				if(empty($this_timezone)) {
					$date = new DateTime( $hasp_expire_date );
					$this_timezone = "Asia/Tokyo";
				}
				$date->setTimezone( new DateTimeZone($this_timezone) );
			} else {
				$date->setTimezone( new DateTimeZone($timezone) );
			}
			$hasp_expire_date_gmt = $date->format( "Y-m-d H:i:s" );
			$hook_args = array($post_id);
			wp_clear_scheduled_hook( 'hasp_expire_cron', $hook_args );
			wp_schedule_single_event( strtotime( $hasp_expire_date_gmt ), 'hasp_expire_cron', $hook_args );
		}

		return $post_id;
	}
	function save_overwrite( $post_id )
	{
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return $post_id;
		}
		$hasp_overwrite_enable = isset( $_POST['hasp_overwrite_enable'] ) ? 1 : 0;
		$hasp_overwrite_post_id = $_POST['hasp_overwrite_post_id'];

		update_post_meta( $post_id, 'hasp_overwrite_enable', $hasp_overwrite_enable );
		update_post_meta( $post_id, 'hasp_overwrite_post_id', $hasp_overwrite_post_id );

		// set hasp wp-cron
		$hook_args = array($post_id);
		wp_clear_scheduled_hook( 'hasp_overwrite_cron', $hook_args );
		if($hasp_overwrite_enable > 0) {
			$timezone = date_default_timezone_get();
			if($timezone === "Asia/Tokyo") {
				$hasp_overwrite_time = get_the_time( 'U', get_post($post_id) );
			} else {
				$hasp_overwrite_time = get_post_time( 'U', true, get_post($post_id) );
			}
			wp_schedule_single_event( $hasp_overwrite_time, 'hasp_overwrite_cron', $hook_args );
		}

		return $post_id;
	}
	function clear_expire( $post_id )
	{
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return $post_id;
		}
		wp_clear_scheduled_hook( 'hasp_expire_cron', array( $post_id ) );
		update_post_meta( $post_id, 'hasp_expire_enable', '' );
		update_post_meta( $post_id, 'hasp_expire_date', '' );
		$this->hasp_log_out("/--- " . __FUNCTION__ . " ---");
		$this->hasp_log_out( $post_id );
		$this->hasp_log_out("--- " . __FUNCTION__ . " ---/");
	}

	function clear_overwrite( $post_id )
	{
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return $post_id;
		}
		$ret = update_post_meta( $post_id, 'hasp_overwrite_enable', '' );
		update_post_meta( $post_id, 'hasp_overwrite_post_id', '' );
		wp_clear_scheduled_hook( 'hasp_overwrite_cron', array( $post_id ) );
		if($ret !== false) {
			$this->hasp_log_out("/--- " . __FUNCTION__ . " ---/");
			$this->hasp_log_out( $post_id );
		}
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

		$this->hasp_log_out("/--- " . __FUNCTION__ . " ---");
		foreach ( $result as $post ) {

			$post_id = $post->ID;

			if(!$this->hasp_setting_check($post_id, 2)) continue;

			$hasp_expire_date = get_post_meta($post_id, 'hasp_expire_date', true);

			// publish → draft
			$overwrite_post = array();
			$overwrite_post['ID'] = $post_id;
			$overwrite_post['post_status'] = 'draft';
			wp_update_post( $overwrite_post );
			$this->hasp_log_out( $post_id );

			$this->clear_expire( $post_id );
			$this->trash_hasp_overwrite_post_id ( $post_id );
			/* Create action hook
			 * args : $post_id: Draft post_id
			 *        $hasp_expire_date: Time to draft
			 */
			do_action('hasp_do_expire_post', $post_id, $hasp_expire_date);
		}
		$this->hasp_log_out("--- " . __FUNCTION__ . " ---/\n");

		/* Create action hook
		 * args : $result: post_id array of delete list
		 */
		do_action('hasp_do_expire_end', $result);
	}
	// Generation update support processing（When the overwritten article is in the trash or when it is no longer published）
	function trash_hasp_overwrite_post_id ( $post_id ) {
		global $wpdb;
		$sql = "SELECT posts.ID
			FROM $wpdb->posts AS posts
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id )
			INNER JOIN $wpdb->postmeta AS postmeta2 ON ( posts.ID = postmeta2.post_id )
			WHERE posts.post_status = 'future'
			AND ( postmeta1.meta_key = 'hasp_overwrite_post_id' AND postmeta1.meta_value = '{$post_id}' )
			AND ( postmeta2.meta_key = 'hasp_overwrite_enable' AND postmeta2.meta_value = '1' )
			GROUP BY posts.ID
		";
		$result = $wpdb->get_results( $sql );
		if ( empty( $result ) ) return;
		foreach( $result as $post ) {
			$up_post_id = $post->ID;
			update_post_meta( $up_post_id, 'hasp_overwrite_enable', '' );
			update_post_meta( $up_post_id, 'hasp_overwrite_post_id', '' );
			$from_overwrite_post = array();
			$from_overwrite_post['ID'] = $up_post_id;
			$from_overwrite_post['post_status'] = 'draft';
			wp_update_post( $from_overwrite_post );
			wp_clear_scheduled_hook( 'hasp_overwrite_cron', array( $up_post_id ) );
		}
		return;
	}
	function do_overwrite()
	{
		global $wpdb;

		// Get article data to overwrite
		$sql = "SELECT
					posts.ID, posts.post_date, posts.post_date_gmt, posts.post_content,
					posts.post_title, posts.post_excerpt, posts.post_modified, posts.post_modified_gmt,
					posts.post_content_filtered, posts.post_password
			FROM $wpdb->posts AS posts
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id )
			WHERE (posts.post_status = 'publish' OR posts.post_status = 'future')
			AND ( postmeta1.meta_key = 'hasp_overwrite_enable' AND postmeta1.meta_value = '1' )
			AND posts.post_date <= '".current_time( 'mysql' )."'
			GROUP BY posts.ID
			ORDER BY posts.post_date ASC
		";
		$result = $wpdb->get_results( $sql );

		if ( empty( $result ) ) return false;

		foreach( $result as $post ) {
			$post_id = $post->ID;

			if(!$this->hasp_setting_check($post_id, 1)) continue;

			$hasp_overwrite_enable  = get_post_meta( $post_id, 'hasp_overwrite_enable', true );
			$hasp_overwrite_post_id = get_post_meta( $post_id, 'hasp_overwrite_post_id', true );

			if ( $hasp_overwrite_enable && $hasp_overwrite_post_id ) {

				//$overwrite_post_name = get_post_field( 'post_name', $hasp_overwrite_post_id );

				// Get article data before overwriting
				$sql = "SELECT
							posts.ID, posts.post_date, posts.post_date_gmt, posts.post_content,
							posts.post_title, posts.post_excerpt, posts.post_modified, posts.post_modified_gmt,
							posts.post_content_filtered, posts.post_password
						FROM $wpdb->posts AS posts
							INNER JOIN $wpdb->postmeta AS postmeta ON posts.ID = postmeta.meta_value
						WHERE posts.ID = {$hasp_overwrite_post_id}
 							AND postmeta.meta_key = 'hasp_overwrite_post_id'
 							AND postmeta.post_id = {$post_id}
				";
				$origin_post = $wpdb->get_results( $sql );
				if(!is_array($origin_post) || count($origin_post) !== 1) continue;

				/*
				 * Overwrite postmeta table
				 * Swap article IDs
				 */

				// Get the record ID of the metadata of the original article and arrange it
				$sql = "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = {$hasp_overwrite_post_id} AND meta_key NOT LIKE 'hasp_%';";
				$result = $wpdb->get_results( $sql );
				$overwrite_post_meta_id_list = array();
				if(is_array($result) && count($result)>0){
					foreach ( $result as $result_row ) {
						$overwrite_post_meta_id_list [] = $result_row->meta_id;
					}
				}
				// Get the record ID of the metadata of the overwrite article and arrange it
				$sql = "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = {$post_id} AND meta_key NOT LIKE 'hasp_%';";
				$result = $wpdb->get_results( $sql );
				$post_meta_id_list = array();
				if(is_array($result) && count($result)>0){
					foreach ( $result as $result_row ) {
						$post_meta_id_list [] = $result_row->meta_id;
					}
				}
				$post_hasp_expire_enable = get_post_meta($post_id, "hasp_expire_enable", true);
				$post_hasp_expire_date = get_post_meta($post_id, "hasp_expire_date", true);
				if($post_hasp_expire_enable === '1' && !empty($post_hasp_expire_date)) {
					$meta_input = array(
						'hasp_overwrite_enable' => '',
						'hasp_overwrite_post_id' => '',
						'hasp_expire_enable' => 1,
						'hasp_expire_date' => $post_hasp_expire_date,
					);
					$this->hasp_log_out( '/--- expire date update ---/' );
					$this->hasp_log_out( $post_hasp_expire_date );
				} else {
					$meta_input = array(
						'hasp_overwrite_enable' => '',
						'hasp_overwrite_post_id' => '',
					);
				}

				// Replace the article ID in the metadata of the original article with the article ID of the overwritten article
				if(count($overwrite_post_meta_id_list)>0){
					$where_id_list = implode(',', $overwrite_post_meta_id_list);
					$values = array($post_id);
					$sql = "UPDATE $wpdb->postmeta SET 
							post_id = %d 
						WHERE meta_id IN ({$where_id_list});";
					$query = $wpdb->prepare($sql, $values);
					$result = $wpdb->query( $query );
				}
				// Replace the article ID in the metadata of the overwritten article with the article ID of the original article
				if(count($post_meta_id_list)>0){
					$where_id_list = implode(',', $post_meta_id_list);
					$values = array($hasp_overwrite_post_id);
					$sql = "UPDATE $wpdb->postmeta SET 
							post_id = %d 
						WHERE meta_id IN ({$where_id_list});";
					$query = $wpdb->prepare($sql, $values);
					$result = $wpdb->query( $query );
				}

				/*
				 * Perform taxonomy overwrite
				 * After deleting all records, register the record with the article ID replaced.
				 */

				// Get all taxonomies of the original article
				$sql = "SELECT * FROM $wpdb->term_relationships WHERE object_id = {$hasp_overwrite_post_id};";
				$overwrite_post_term = $wpdb->get_results( $sql );
				// Get all taxonomies of overwritten articles
				$sql = "SELECT * FROM $wpdb->term_relationships WHERE object_id = {$post_id};";
				$post_term = $wpdb->get_results( $sql );
				// Deleted all taxonomies of original articles and overwritten articles
				$values = array($hasp_overwrite_post_id, $post_id);
				$sql = "DELETE FROM $wpdb->term_relationships WHERE object_id IN ( %d, %d );";
				$query = $wpdb->prepare($sql, $values);
				$delete_result = $wpdb->query( $query );
				// Register the taxonomy of the original article with the ID of the overwritten article
				if(is_array($overwrite_post_term) && count($overwrite_post_term)>0){
					foreach ( $overwrite_post_term as $result_row ) {
						$values = array($post_id, $result_row->term_taxonomy_id, $result_row->term_order);
						$sql = "INSERT INTO $wpdb->term_relationships (`object_id`, `term_taxonomy_id`, `term_order`) 
							VALUES (%d, %d, %d)";
						$query = $wpdb->prepare($sql, $values);
						$insert_result = $wpdb->query( $query );
					}
				}
				// Register the taxonomy of the overwritten article with the ID of the original article
				if(is_array($post_term) && count($post_term)>0){
					foreach ( $post_term as $result_row ) {
						$values = array($hasp_overwrite_post_id, $result_row->term_taxonomy_id, $result_row->term_order);
						$sql = "INSERT INTO $wpdb->term_relationships (`object_id`, `term_taxonomy_id`, `term_order`)
							VALUES (%d, %d, %d)";
						$query = $wpdb->prepare($sql, $values);
						$insert_result = $wpdb->query( $query );
					}
				}

				// Overwrite on post table
				$origin_post_value = array(
					'ID' => $post_id,
					'post_date' => $origin_post[0]->post_date,
					'post_date_gmt' => $origin_post[0]->post_date_gmt,
					'post_content' => $origin_post[0]->post_content,
					'post_title' => $origin_post[0]->post_title,
					'post_excerpt' => $origin_post[0]->post_excerpt,
					'post_modified' => $origin_post[0]->post_modified,
					'post_modified_gmt' => $origin_post[0]->post_modified_gmt,
					'post_content_filtered' => $origin_post[0]->post_content_filtered,
					'post_password' => $origin_post[0]->post_password,
					'post_status' => 'draft',
					'meta_input' => array(
						'hasp_overwrite_enable' => '',
						'hasp_overwrite_post_id' => ''
					),
				);
				wp_update_post( $origin_post_value );
				$this->clear_expire( $post_id );
				$this->hasp_log_out("/--- " . __FUNCTION__ . " ---");
				$this->hasp_log_out( $origin_post_value );
				$this->hasp_log_out(" to ");

				$post_value = array(
					'ID' => $hasp_overwrite_post_id,
					'post_date' => $post->post_date,
					'post_date_gmt' => $post->post_date_gmt,
					'post_content' => $post->post_content,
					'post_title' => $post->post_title,
					'post_excerpt' => $post->post_excerpt,
					'post_modified' => $post->post_modified,
					'post_modified_gmt' => $post->post_modified_gmt,
					'post_content_filtered' => $post->post_content_filtered,
					'post_password' => $post->post_password,
					'post_status' => 'publish',
					'meta_input' => $meta_input,
				);
				wp_update_post( $post_value );
				$this->hasp_log_out( $post_value );
				$this->hasp_log_out("--- " . __FUNCTION__ . " ---/\n");

				// Generate a revision.
				wp_save_post_revision( $hasp_overwrite_post_id );
				/* Create action hook 
				 * args : $hasp_overwrite_post_id: Publish post_id
				 *        $post->post_date: Publication time
				 *        $post_id: Draft post_id
				 */
				do_action('hasp_do_overwrite_post', $hasp_overwrite_post_id, $post->post_date, $post_id);
			}
		}

		/* Create action hook 
		 * args : $result: Overwrite post_id array
		 */
		do_action('hasp_do_overwrite_end', $result);
	}

	/*
	* Admin Setting
	*/

	function admin_menu()
	{
		if ( !get_option( 'hasp_activation' ) ) {
			$this->hasp_activation();
		} elseif ( get_option( 'hasp_activation' ) ) {
			$this->cron_start(__FUNCTION__);
		}
		global $_wp_last_object_menu;
		$_wp_last_object_menu++;

		$slug = 'hasp-list';
		$cap = 'manage_options';

		add_menu_page( __( 'Scheduled Posts', 'hasp' ),
		               __( 'Scheduled Posts', 'hasp' ),
					   $cap,
					   $slug,
					   array( $this,'admin_page' )  ,
					   'dashicons-clock' ,
					   $_wp_last_object_menu);
		add_submenu_page( $slug , __( 'List', 'hasp' ), __( 'List', 'hasp' ), $cap, $slug  );
		add_submenu_page( $slug , __( 'Settings', 'hasp' ), __( 'Settings', 'hasp' ), $cap, 'hasp-settings', array( $this,'admin_page_setting' ) );
	}

	/**
	* View Admin Setting Page
	*/
	function admin_page()
	{
		global $wpdb;
		$hasp_url = admin_url() . "admin.php?page=hasp-list";
		$hasp_url .= (isset($_GET['post-status'])) ? "&amp;post-status=".$_GET['post-status'] : '' ;

		// Setting List
		$where = "";
		$view_date = "";
		$orderby_out = "";

		if ( isset($_GET['post-status']) ) {
			switch($_GET['post-status']){
				case '10': $where = " HAVING post_date_publish IS NOT NULL"; break;
				case '20': $where = " HAVING post_date_end IS NOT NULL"; break;
				case '30': $where = " HAVING post_date_overwrite IS NOT NULL"; break;
			}
		}

		if ($view_date = filter_input(INPUT_GET, 'view_date', FILTER_SANITIZE_STRING)) {
			$view_date = str_replace("/", "-", $view_date);
			$where .= ( $where === "" ) ? " HAVING " : " AND ";
			$where .= " ((post_date_publish >= '{$view_date} 00:00:00' AND post_date_publish <= '{$view_date} 23:59:59') OR
						 (post_date_end >= '{$view_date} 00:00:00' AND post_date_end <= '{$view_date} 23:59:59') OR
						 (post_date_overwrite >= '{$view_date} 00:00:00' AND post_date_overwrite <= '{$view_date} 23:59:59')) ";
			$hasp_url = $hasp_url . "&amp;view_date=" . urlencode($view_date);
		}

		$post_order_by = filter_input(INPUT_GET, 'orderby', FILTER_SANITIZE_STRING);
		$post_order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING);
		$order = ($post_order === "asc")?"asc":"desc";
		$sort = ($post_order === "asc")?"ASC":"DESC";
		//$srch_def_title = "";
		if ($post_order_by === "topo") {
			// title of post overwritten
			$orderby = "post_title_overwrite " . $sort ;
			//$srch_def_title = ", posts.post_title";
		} elseif ($post_order_by === "totl") {
			// overwrite post title
			$orderby = "post_title " . $sort ;
		} elseif ($post_order_by === "ptdt1") {
			// post publish date
			$orderby = "post_date_publish " . $sort ;
		} elseif ($post_order_by === "ptdt2") {
			// post draft date
			$orderby = "post_date_end " . $sort;
		} elseif ($post_order_by === "ptdt3") {
			// post overwrite date
			$orderby = "post_date_overwrite " . $sort;
		} elseif ($post_order_by === "poty") {
			// post type
			$orderby = "post_type " . $sort ;
		} elseif ($post_order_by === "tost1") {
			// post status
			$orderby = "post_status " . $sort ;
		} elseif ($post_order_by === "tost2") {
			// post status overwrite
			$orderby = "post_status_overwrite " . $sort ;
		} else {
			// default
			$orderby = "post_id";
		}

		$sql = "SELECT T1.post_id, T1.st, T1.post_title, T1.post_status, T1.post_type,
			MAX(T1.post_date_publish) as post_date_publish,
			MAX(T1.post_date_end) as post_date_end,
			MAX(T1.post_date_overwrite) as post_date_overwrite,
			MAX(T1.post_title_overwrite) as post_title_overwrite,
			MAX(T1.post_status_overwrite) as post_status_overwrite,
			MAX(T1.post_id_overwrite) as post_id_overwrite
			FROM (
			SELECT posts.ID as post_id,posts.post_title, posts.post_status,posts.post_type, 10 as st,
			posts.post_date as post_date_publish, null as post_date_end, null as post_date_overwrite, null as post_title_overwrite, null as post_status_overwrite, null as post_id_overwrite
			FROM `$wpdb->posts` as posts
			WHERE posts.post_status='future'
			AND posts.ID NOT IN (SELECT posts.ID
				FROM `$wpdb->posts` as posts
				INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id )
				INNER JOIN $wpdb->postmeta AS postmeta2 ON ( posts.ID = postmeta2.post_id )
				INNER JOIN `$wpdb->posts` as posts1 ON ( posts1.ID = postmeta1.meta_value)
				WHERE (posts.post_status = 'future'	AND postmeta1.meta_key = 'hasp_overwrite_post_id' )
				AND ( postmeta2.meta_key = 'hasp_overwrite_enable' AND  postmeta2.meta_value = 1 ))
			UNION ALL
			SELECT posts.ID as post_id,posts.post_title, posts.post_status,posts.post_type, 20 as st,
			null as post_date_publish, postmeta2.meta_value as post_date_end, null as post_date_overwrite, null as post_title_overwrite, null as post_status_overwrite, null as post_id_overwrite
			FROM `$wpdb->posts` as posts
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id )
			INNER JOIN $wpdb->postmeta AS postmeta2 ON ( posts.ID = postmeta2.post_id )
			WHERE (postmeta1.meta_key = 'hasp_expire_enable' AND postmeta1.meta_value = 1)
			AND (postmeta2.meta_key = 'hasp_expire_date' AND postmeta2.meta_value IS NOT NULL )
			AND posts.post_status <> 'draft'
			UNION ALL
			SELECT posts.ID as post_id,posts.post_title, posts.post_status,posts.post_type, 30 as st,
			null as post_date_publish, null as post_date_end, posts.post_date as post_date_overwrite, posts1.post_title as post_title_overwrite , posts1.post_status as post_status_overwrite, postmeta1.meta_value as post_id_overwrite
			FROM `$wpdb->posts` as posts
			INNER JOIN $wpdb->postmeta AS postmeta1 ON ( posts.ID = postmeta1.post_id )
			INNER JOIN $wpdb->postmeta AS postmeta2 ON ( posts.ID = postmeta2.post_id )
			INNER JOIN `$wpdb->posts` as posts1 ON ( posts1.ID = postmeta1.meta_value)
			WHERE (posts.post_status = 'future'	AND postmeta1.meta_key = 'hasp_overwrite_post_id' )
			AND ( postmeta2.meta_key = 'hasp_overwrite_enable' AND  postmeta2.meta_value = 1 )
			) T1";
		$original_sql = $sql;
		$sql .= ' GROUP BY T1.post_id' ;
		if ( $where !== '' ) $sql .= $where;
		$sql .= ' ORDER BY ' . $orderby;
		$future_overwrite_posts = $wpdb->get_results( $sql );


		// Quick Links
		$quick_links = array();
		$quick_links[0]['count'] = 0;
		$quick_links[10]['count'] = 0;
		$quick_links[20]['count'] = 0;
		$quick_links[30]['count'] = 0;
		$original_sql .= ' GROUP BY T1.post_id' ;
		$quick_posts = $wpdb->get_results( $original_sql );
		foreach( $quick_posts as $view ) {
			if ( isset($view->post_date_publish) ) {
				$quick_links[10]['count'] += 1;
			}
			if ( isset($view->post_date_end) ) {
				$quick_links[20]['count'] += 1;
			}
			if ( isset($view->post_date_overwrite) ) {
				$quick_links[30]['count'] += 1;
			}
		}

		$quick_links[0]['status'] = __( 'All', 'hasp' );
		$quick_links[0]['count'] = $quick_links[10]['count'] + $quick_links[20]['count'] + $quick_links[30]['count'];
		$quick_links[0]['href'] = ' ?page=hasp-list';
		$quick_links[0]['current'] = (!isset($_GET['post-status'] ) || !$_GET['post-status']) ? 1 : 0 ;

		$quick_links[10]['status'] = __( 'Schedule', 'hasp' );
		$quick_links[10]['href']  = ' ?page=hasp-list&amp;post-status=10';
		$quick_links[10]['current'] = ( isset($_GET['post-status']) &&  $_GET['post-status']== 10 ) ? 1 : 0 ;

		$quick_links[20]['status'] = __( 'Expire', 'hasp' );
		$quick_links[20]['href']  = ' ?page=hasp-list&amp;post-status=20';
		$quick_links[20]['current'] = ( isset($_GET['post-status']) &&  $_GET['post-status']== 20 ) ? 1 : 0 ;

		$quick_links[30]['status'] = __( 'Overwrite', 'hasp' );
		$quick_links[30]['href']  = ' ?page=hasp-list&amp;post-status=30';
		$quick_links[30]['current'] = ( isset($_GET['post-status']) &&  $_GET['post-status']== 30 ) ? 1 : 0 ;

		require HASP_DIR.'admin/list.php';

	}


	/**
	* View Admin Setting Page Setting
	*/
	function admin_page_setting()
	{
		global $wpdb;

		// Post Type Setting
		$hasp_options = get_option( 'hasp_options' );
		$hasp_objects = isset( $hasp_options['objects'] ) ? $hasp_options['objects'] : array();

		$hasp_activate_expire = array();
		$hasp_activate_overwrite = array();
		$hasp_activate_expire_setting = FALSE;
		$hasp_activate_overwrite_setting = FALSE;

		if(array_key_exists('activate_expire',$hasp_options)){
			$hasp_activate_expire = $hasp_options['activate_expire'];
			$hasp_activate_expire_setting = TRUE;
		}
		if(array_key_exists('activate_overwrite',$hasp_options)){
			$hasp_activate_overwrite = $hasp_options['activate_overwrite'];
			$hasp_activate_overwrite_setting = TRUE;
		}

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
		$input_options['activate_expire'] = isset( $_objects ) ? $_objects : '';
		$input_options['activate_overwrite'] = isset( $_objects ) ? $_objects : '';
		add_option('hasp_options', $input_options, '', 'no');
		add_option('hasp_activation', 1, '', 'no');
		$this->cron_start(__FUNCTION__);
	}

	/**
	 * Get function activate status by post type
	 */
	function hasp_activate_function_by_posttype($post_type){
		$rtn = array('expire' => FALSE, 'overwrite' => FALSE );
		$hasp_options = get_option( 'hasp_options' );
		if((array_key_exists('activate_expire',$hasp_options) && is_array($hasp_options['activate_expire']) && in_array($post_type, $hasp_options['activate_expire'])) || !array_key_exists('activate_expire',$hasp_options)){
			$rtn['expire'] = TRUE;
		}
		if((array_key_exists('activate_overwrite',$hasp_options) && is_array($hasp_options['activate_overwrite']) && in_array($post_type, $hasp_options['activate_overwrite'])) || !array_key_exists('activate_overwrite',$hasp_options)){
			$rtn['overwrite'] = TRUE;
		}
		return $rtn;
	}

  /*
	* Check ACF object record
	*/
	function hasp_record_check( $post_id, $meta_key )
	{
	    if(!function_exists('get_field_object')) return false;
	    $obj = get_field_object($meta_key,$post_id);
	    if(isset($obj['type']) && 'post_object' === $obj['type']) return true;
	    return false;
	}

	/**
	 * Check the editor's type (true = Classic Editor , false = Block Editor(Gutenberg) )
	 */
	function get_classic_editor_state()
	{
		global $post_type;
		$post = get_default_post_to_edit( $post_type, true );

		if (function_exists( 'use_block_editor_for_post' ) ) {
			if ( use_block_editor_for_post( $post ) ) {
				if ( isset( $_GET['classic-editor'] ) ) {
					return true;
				}else{
					return false;
				}
			}else{
				return true;
			}
		} elseif (function_exists( 'is_gutenberg_page' ) ){
		    if ( is_gutenberg_page() ) {
		        return false;
		    } else {
		        return true;
		    }
		}else{
			return true;
		}
	}

	/*
	 * clear wp-cron publish_future_post schedule
	 */
	function clear_cron_schedule( $post_id, $post ) {
		// Exclude if not a public reservation
		if ( 'future' !== $post->post_status ) {
			return;
		}

		// Exclude post types that are not set to overwrite public reservation
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return;
		}

		// Exclude if it is not overwritten
		$hasp_overwrite_enable = get_post_meta($post_id, "hasp_overwrite_enable", true);
		if(empty($hasp_overwrite_enable)) {
			return;
		}

		wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );
	}

	/*
	 * hasp wp-cron execute
	 */
	function cron_execute_trigger($arg1=null) {
		if(!empty($arg1)) {
			$this->hasp_log_out("/--- " . __FUNCTION__ . " ---/");
			$this->hasp_log_out($arg1);
		}
		$this->do_overwrite();
		$this->do_expire();
	}

	/*
	 * Publish overwrites in real time
	 */
	function publish_overwrite($post_ID, $post) {
		if(empty($_POST)) return;
		$hasp_overwrite_enable = get_post_meta($post_ID, "hasp_overwrite_enable", true);
		$hasp_overwrite_post_id = get_post_meta($post_ID, "hasp_overwrite_post_id", true);
		if(empty($hasp_overwrite_enable)) return;
		if ($post->post_status === 'publish' && $hasp_overwrite_enable === '1') {
			remove_action( 'save_post', array( $this, 'publish_overwrite') );
			$_POST['hasp_overwrite_enable'] = "";
			if(isset($_POST['gutenberg_page']) && $_POST['gutenberg_page'] === "true") {
				$this->do_overwrite();
			} else {
				set_theme_mod( '_is_overerite_update', $hasp_overwrite_post_id );
				$this->do_overwrite();
				wp_safe_redirect(admin_url("post.php?post=$hasp_overwrite_post_id&action=edit"));
			}
			exit();
		}
	}

	/*
	 * Message when overwriting and publishing immediately
	 */
	function overerite_admin_notice__success() {
		global $post;
		if ( isset($post) && false !== get_theme_mod( '_is_overerite_update' ) ) {
			$p_id = get_theme_mod( '_is_overerite_update' );
			if($p_id === $post->ID) {
				remove_theme_mod( '_is_overerite_update');
				$message = '<div class="notice notice-success is-dismissible">
					<p>' . __('Overwritten and updated.', 'hasp') . '</p>
				</div>';
				echo $message;
			}
		}
	}

	/*
	 * ASP Setting posttype check
	 */
	function hasp_setting_check($post_id, $hasp_type) {
		$post = get_post( $post_id );
		$post_type = $post->post_type;
		$hasp_options = get_option( 'hasp_options' );

		$activate = false;
		if ( in_array( $post_type , $hasp_options['objects'] )){
			switch($hasp_type){
				case 1:
					if(array_key_exists('activate_overwrite',$hasp_options)){
						if ( in_array( $post_type , $hasp_options['activate_overwrite'] )) $activate = true;
					} else {
						$activate = true;
					}
					break;
				case 2:
					if(array_key_exists('activate_expire',$hasp_options)){
						if ( in_array( $post_type , $hasp_options['activate_expire'] )) $activate = true;
					} else {
						$activate = true;
					}
					break;
			}
		}
		if ( !$activate ) {
			$this->hasp_log_out( "Canceled due to HASP settings. ID: " . $post_id );
		}
		return $activate;
	}

	/*
	 * Add wp-cron for ASP
	 */
	function cron_start($func=null) {
		if ( !get_option( 'hasp_activation' ) ) return;
		if ( !get_option( 'hasp_cron_started' ) || !wp_get_schedule( 'hasp_cron_execute' ) ) {
			wp_schedule_event( strtotime(date("Y-m-d H:i:00", strtotime("+1 minutes"))), 'per_minute', 'hasp_cron_execute' );
			update_option( 'hasp_cron_started', 1,'no' );
			$this->hasp_log_out(__FUNCTION__ . " " . $func);
		}
		return;
	}

	/*
	 * Add a new schedule for wp-cron
	 */
	function hasp_cron_schedules($schedules){
		if(!isset($schedules["per_minute"])){
			$schedules["per_minute"] = array(
				'interval' => 60,
				'display' => __('Once every minutes'));
		}
		return $schedules;
	}

	/*
	* Log output
	* If you place the /logs/ directory directly under the plugin directory, 
	*  logs will be created for each date.
	*/
	function hasp_log_out($message) {
		$log_dir = __DIR__ . "/logs/";
		if( !file_exists( $log_dir ) ) return;
		$blog_id = "";
		if(is_multisite()) {
			$blog_id = "blog_id: " . get_current_blog_id() . ":";
		}

		$timezone = date_default_timezone_get();
		if($timezone === "Asia/Tokyo") {
			$log_datetime = date('Y-m-d H:i:s');
			$log_date = date( "Ymd" );
		} else {
			$log_datetime = date_i18n('Y-m-d H:i:s');
			$log_date = date_i18n( "Ymd" );
		}

		$log_filename = $log_dir . $log_date . '.log';
		if(!file_exists($log_filename)) {
			touch($log_filename);
			chmod($log_filename, 0666);
		}

		$log_message = null;
		if(is_array($message)) {
			if(isset($message['ID']) && isset($message['post_title'])) {
				$log_message = sprintf("%s:%s%s\n", $log_datetime, $blog_id, print_r("ID: {$message['ID']}", true));
				$log_message .= sprintf("%s:%s%s\n", $log_datetime, $blog_id, print_r("SET post_title: {$message['post_title']}", true));
			} elseif(count($message) == 1) {
				$log_message = sprintf("%s:%s%s\n", $log_datetime, $blog_id, print_r("ID: {$message[0]->ID}", true));
				$log_message .= sprintf("%s:%s%s\n", $log_datetime, $blog_id, print_r("SET post_title: {$message[0]->post_title}", true));
			} else {
				$log_message = sprintf("%s:%s%s\n", $log_datetime, $blog_id, print_r($message, true));
			}
		} else {
			$log_message = sprintf("%s:%s%s\n", $log_datetime, $blog_id, print_r($message, true));
		}
		error_log($log_message, 3, $log_filename);
	}
}
?>
