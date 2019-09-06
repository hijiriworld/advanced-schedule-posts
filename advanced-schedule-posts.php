<?php
/*
Plugin Name: Advanced Schedule Posts
Plugin URI:
Description: Allows you to set datetime of expiration and to set schedule which overwrites the another post.
Version: 2.1.1
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
		// Set the reservation time to 00 seconds
		add_action( 'transition_post_status', array( $this, 'save_future' ), 1 ,6 );

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

		add_action( 'admin_menu', array( $this, 'admin_menu') );
		add_action( 'admin_init', array( $this, 'update_options') );

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

	/**
	 * Set the reservation time to 00 seconds
	 * @param String $new_status
	 * @param String $old_status
	 * @param Object $post
	 */
	function save_future( $new_status, $old_status, $post ) {
		if ($new_status === 'future' && $old_status !== 'future') {
			$post_id = $post->ID;
			do_action('save_post', $post_id, $post);
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
		wp_enqueue_script( 'hasp-js', HASP_URL.'/js/script.js', array( 'jquery', 'inline-edit-post' ), '2.0', true );

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

		return $post_id;
	}
	function clear_expire( $post_id )
	{
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return $post_id;
		}
		update_post_meta( $post_id, 'hasp_expire_enable', '' );
		update_post_meta( $post_id, 'hasp_expire_date', '' );
	}

	function clear_overwrite( $post_id )
	{
		$post_type = get_post_type( $post_id );
		$post_types = $this->get_hasp_options_objects();
		if (!in_array($post_type, $post_types)) {
			return $post_id;
		}
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
			$this->trash_hasp_overwrite_post_id ( $post_id );
		}
	}
	// 世代更新対応処理（上書き記事がゴミ箱、または、公開終了した時）
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
		}
		return;
	}
	function do_overwrite()
	{
		global $wpdb;

		// 上書き後の記事データを取得
		$sql = "SELECT
					posts.ID, posts.post_date, posts.post_date_gmt, posts.post_content,
					posts.post_title, posts.post_excerpt, posts.post_modified, posts.post_modified_gmt,
					posts.post_content_filtered, posts.post_password
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

				//$overwrite_post_name = get_post_field( 'post_name', $hasp_overwrite_post_id );

				// 上書き前の記事データを取得
				$sql = "SELECT
							posts.ID, posts.post_date, posts.post_date_gmt, posts.post_content,
							posts.post_title, posts.post_excerpt, posts.post_modified, posts.post_modified_gmt,
							posts.post_content_filtered, posts.post_password
						FROM $wpdb->posts AS posts
						WHERE posts.ID = {$hasp_overwrite_post_id}
				";
				$origin_post = $wpdb->get_results( $sql );
				if(!is_array($origin_post) || count($origin_post) !== 1) continue;

				// postテーブルの上書きを実行
				$new_post_sql = "UPDATE $wpdb->posts SET post_date = '{$post->post_date}', post_date_gmt = '{$post->post_date_gmt}', post_content = '{$post->post_content}',
												post_title = '{$post->post_title}', post_excerpt = '{$post->post_excerpt}', post_modified = '{$post->post_modified}',
												post_modified_gmt = '{$post->post_modified_gmt}', post_content_filtered = '{$post->post_content_filtered}', post_password = '{$post->post_password}', post_status = 'publish'
 											WHERE ID = {$hasp_overwrite_post_id};";
				$new_post_result = $wpdb->query( $new_post_sql);

				$old_post_sql = "UPDATE $wpdb->posts SET post_date = '{$origin_post[0]->post_date}', post_date_gmt = '{$origin_post[0]->post_date_gmt}', post_content = '{$origin_post[0]->post_content}',
												post_title = '{$origin_post[0]->post_title}', post_excerpt = '{$origin_post[0]->post_excerpt}', post_modified = '{$origin_post[0]->post_modified}',
												post_modified_gmt = '{$origin_post[0]->post_modified_gmt}', post_content_filtered = '{$origin_post[0]->post_content_filtered}', post_password = '{$origin_post[0]->post_password}', post_status = 'draft'
 											WHERE ID = {$post_id};";
				$old_post_result = $wpdb->query( $old_post_sql);

				// 上書き予約設定を解除する
				$this->clear_overwrite( $post_id );

				// postmetaテーブルの上書きを実行
				$sql = "UPDATE $wpdb->postmeta SET post_id = 0 WHERE post_id = {$hasp_overwrite_post_id};";
				$result = $wpdb->query( $sql );
				$sql = "UPDATE $wpdb->postmeta SET post_id = {$hasp_overwrite_post_id} WHERE post_id = {$post_id};";
				$result = $wpdb->query( $sql );
				$sql = "UPDATE $wpdb->postmeta SET post_id = {$post_id} WHERE post_id = 0;";
				$result = $wpdb->query( $sql );

				// タクソノミーの上書きを実行
				$sql = "UPDATE $wpdb->term_relationships SET object_id = 0 WHERE object_id = {$hasp_overwrite_post_id};";
				$result = $wpdb->query( $sql );
				$sql = "UPDATE $wpdb->term_relationships SET object_id = {$hasp_overwrite_post_id} WHERE object_id = {$post_id};";
				$result = $wpdb->query( $sql );
				$sql = "UPDATE $wpdb->term_relationships SET object_id = {$post_id} WHERE object_id = 0;";
				$result = $wpdb->query( $sql );

				// Generate a revision. 
				wp_save_post_revision( $hasp_overwrite_post_id );
			}
		}
	}

	/*
	* Admin Setting
	*/

	function admin_menu()
	{
		if ( !get_option( 'hasp_activation' ) ) $this->hasp_activation();

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
			AND (postmeta2.meta_key = 'hasp_expire_date' AND postmeta2.meta_value > now() )
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
		add_option('hasp_options', $input_options, '', 'no');
		add_option('hasp_activation', 1, '', 'no');
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
}

?>