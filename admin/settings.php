<?php

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

?>

<div class="wrap">

<h2><?php _e( 'Advanced Schedule Posts Settings', 'hasp' ); ?></h2>

<?php if ( isset($_GET['msg'] )) : ?>
<div id="message" class="updated below-h2">
	<?php if ( $_GET['msg'] == 'update' ) : ?>
		<p><?php _e( 'Settings saved.' ); ?></p>
	<?php endif; ?>
</div>
<?php endif; ?>

<form method="post">

<?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'nonce_hasp' ); ?>

<div id="hasp_select_objects">

<table width="100%" class="widefat striped">
	<thead>
		<tr>
			<td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
			<th><?php _e( 'Select Post Types', 'hasp' ) ?></th>
			<th><?php _e( 'Datetime of expiration', 'hasp' ) ?></th>
			<th><?php _e( 'Overwrite the another post', 'hasp' ) ?></th>
		</tr>
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
								} elseif( !$hasp_activate_expire_setting ){
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
								} elseif( !$hasp_activate_expire_setting ){
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
	<tfoot>
		<tr>
			<td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
			<th><?php _e( 'Select Post Types', 'hasp' ) ?></th>
			<th><?php _e( 'Datetime of expiration', 'hasp' ) ?></th>
			<th><?php _e( 'Overwrite the another post', 'hasp' ) ?></th>
		</tr>
	</tfoot>
</table>

</div>


</div>

<p class="submit">
	<input type="submit" class="button-primary" name="hasp_submit" value="<?php _e( 'Update' ); ?>">
</p>

</form>

</div>