<div class="wrap">

  <?php if ( isset($_GET['msg'] )) : ?>
  <div id="message" class="updated below-h2">
	<?php if ( $_GET['msg'] == 'update' ) : ?>
	<p><?php _e( 'Settings saved.' ); ?></p>
	<?php endif; ?>
  </div>
  <?php endif; ?>

	<h1 class="wp-heading-inline"><?php _e( 'Settings', 'hasp' ) ?></h1>
	<hr class="wp-header-end">
	
  <div id="post_type_setting">
	<form method="post">
	<?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'nonce_hasp' ); ?>

	<?php echo "<input type='hidden' id='disable_message' value='" . __( 'If you disable this setting, reserved posts may be posted by other plugins, so please check if there are any posts that will be overwritten or expired.', 'hasp' )
		. "\n" . __('Do you want to uncheck it?', 'hasp' ) . "'>" ?>

	  <div id="hasp_select_objects">
		<table class="wp-list-table widefat fixed striped">
		  <thead>
			<tr>
			  <td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'hasp' ); ?></label><input id="cb-select-all-1" type="checkbox"></td>
			  <th id="title" class="manage-column column-title"><?php _e( 'Enable Post Type', 'hasp' ) ?></th>
			  <th scope="col" id="author" class="manage-column column-expire"><?php _e( 'Enable Expire', 'hasp' ) ?></th>
			  <th scope="col" id="comments" class="manage-column column-overwrite"><?php _e( 'Enable Overwrite', 'hasp' ) ?></th></tr>
		  </thead>
		  <tbody>
			<tr valign="top">
			  <?php
			  $post_types = get_post_types( array (
				'show_ui' => true,
				'show_in_menu' => true,
			  ), 'objects' );

			  foreach ( $post_types  as $post_type ) {
				if ( $post_type->name == 'attachment' ) continue;
			  ?>

			<tr id="" class="">
			  <th scope="row" class="check-column">
				<input type="checkbox" name="objects[]" value="<?php echo $post_type->name; ?>" <?php if ( isset( $hasp_objects ) && is_array( $hasp_objects ) ) { if ( in_array( $post_type->name, $hasp_objects ) ) { echo 'checked="checked"'; } } ?>>
			  </th>
			  <td class=""><?php echo $post_type->label; ?></td>
			  <td class="">
				<?php
				$checked_msg = "";
				if ( $hasp_activate_expire_setting && isset( $hasp_activate_expire ) && is_array( $hasp_activate_expire ) ) {
				  if ( in_array( $post_type->name, $hasp_activate_expire ) ) {
					$checked_msg = ' checked="checked"';
				  }
				} elseif( (isset( $hasp_objects ) && is_array( $hasp_objects ) && in_array( $post_type->name, $hasp_objects )) && !$hasp_activate_expire_setting ){
				  $checked_msg = ' checked="checked"';
				}
				?>
				<input type="checkbox" name="activate_expire[]" value="<?php echo $post_type->name; ?>"<?php echo $checked_msg; ?>>
			  </td>
			  <td class="">
				<?php
				$checked_msg = "";
				if ( $hasp_activate_overwrite_setting && isset( $hasp_activate_overwrite ) && is_array( $hasp_activate_overwrite ) ) {
				  if ( in_array( $post_type->name, $hasp_activate_overwrite ) ) {
					$checked_msg = ' checked="checked"';
				  }
				} elseif( (isset( $hasp_objects ) && is_array( $hasp_objects ) && in_array( $post_type->name, $hasp_objects )) && !$hasp_activate_expire_setting ){
				  $checked_msg = ' checked="checked"';
				}
				?>
				<input type="checkbox" name="activate_overwrite[]" value="<?php echo $post_type->name; ?>"<?php echo $checked_msg; ?>>
			  </td>
			</tr>

			<?php
			  }
			?>

		  </tbody>
		</table>
	  </div>
	  <p class="submit">
		<input type="submit" class="button-primary" name="hasp_submit" value="<?php _e( 'Update' ); ?>">
	  </p>
	</form>
  </div>
</div>
