<?php

global $post_id;

$post_type = get_post_type();
$post_list = $this->get_post_list( $post_type, $post_id );

$hasp_expire_enable = get_post_meta( $post_id, 'hasp_expire_enable', true );
$hasp_expire_date = get_post_meta( $post_id, 'hasp_expire_date', true ) ? date( 'Y-m-d H:i', strtotime( get_post_meta( $post_id, 'hasp_expire_date', true ) ) ) : null;
$hasp_overwrite_enable = get_post_meta( $post_id, 'hasp_overwrite_enable', true );
$hasp_overwrite_post_id = get_post_meta( $post_id, 'hasp_overwrite_post_id', true );

$activate_expire_flg = $this->hasp_activate_function_by_posttype($post_type);

?>

<?php if($activate_expire_flg['expire']): ?>
	<p><label><input type="checkbox" name="hasp_expire_enable" id="hasp_expire_enable" <?php if( $hasp_expire_enable == 1 ) echo 'checked="checked"'; ?>><?php _e( 'Datetime of Expire', 'hasp' ) ?></label></p>
	
	<div id="hasp_expire_div" style="display: none;">
		<span><input type="text" id="hasp_expire_date" name="hasp_expire_date" size="13" placeholder="<?php _e( 'Y-m-d H:i', 'hasp' ) ?>" value="<?php echo $hasp_expire_date ?>"></span>
		<span id="hasp_expire_error_1" class="hasp_error_mes" style="display: none"><?php _e( 'Input datetime.', 'hasp' ) ?></span>
		<span id="hasp_expire_error_2" class="hasp_error_mes" style="display: none"><?php _e( 'Input future datetime.', 'hasp' ) ?></span>
	</div>
<?php endif; ?>

<p></p>

<?php if($activate_expire_flg['overwrite']): ?>
	<p><label><input type="checkbox" name="hasp_overwrite_enable" id="hasp_overwrite_enable" <?php if( $hasp_overwrite_enable == 1 ) echo 'checked="checked"'; ?>><?php _e( 'Overwrite the another post', 'hasp' ) ?></label></p>
	
	<div id="hasp_overwrite_div" style="display: none;">
	
		<select name="hasp_overwrite_post_id" id="hasp_overwrite_post_id">
			<option value="0">— <?php _e( 'Select' ) ?> —</option>
			<?php foreach( $post_list as $post ) : ?>
				<option value="<?php echo $post->ID ?>" <?php if( $hasp_overwrite_post_id == $post->ID ) echo 'selected'?>><?php echo $post->post_title ?></option>
			<?php endforeach; ?>
		</select>
		<span id="hasp_overwrite_error" class="hasp_error_mes" style="display: none"><?php _e( 'Select the post.', 'hasp' ) ?></span>
		<p><?php _e( 'You can set schedule which overwrites the another post.', 'hasp' ) ?></p>
		<span id="hasp_overwrite_message" style="display: none"><?php _e( 'Overwritten and updated.', 'hasp' ) ?></span>
		<span id="hasp_delete_overwrite_message" style="display: none"><?php _e( 'Delete the overwrite reservation publication.', 'hasp' ) ?></span>
		<span id="hasp_future_to_publish_message" style="display: none"><?php _e( 'It cannot be changed to public while the overwrite reservation public is set.', 'hasp' ) ?></span>
		<span id="hasp_future_to_publish_message_sub" style="display: none"><?php _e( 'Please change to a draft once.', 'hasp' ) ?></span>
	</div>
<?php endif; ?>

<?php
if ( is_multisite() ) {
	echo '	<input type="hidden" name="blog_id" value="' . get_current_blog_id() . '">';
}
?>
<input type="hidden" name="gutenberg_page" value="true">
