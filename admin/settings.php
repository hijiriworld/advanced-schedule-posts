<?php

$hasp_options = get_option( 'hasp_options' );
$hasp_objects = isset( $hasp_options['objects'] ) ? $hasp_options['objects'] : array();

?>

<div class="wrap">

<?php screen_icon( 'plugins' ); ?>

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

<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row"><?php _e( 'Select Post Types', 'hasp' ) ?></th>
			<td>
			<?php
				$post_types = get_post_types( array (
					'show_ui' => true,
					'show_in_menu' => true,
				), 'objects' );
				
				foreach ( $post_types  as $post_type ) {
					if ( $post_type->name == 'attachment' ) continue;
					?>
					<label><input type="checkbox" name="objects[]" value="<?php echo $post_type->name; ?>" <?php if ( isset( $hasp_objects ) && is_array( $hasp_objects ) ) { if ( in_array( $post_type->name, $hasp_objects ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo $post_type->label; ?></label><br>
					<?php
				}
			?>
			</td>
		</tr>
	</tbody>
</table>

</div>

<label><input type="checkbox" id="hasp_allcheck_objects"> <?php _e( 'All Check', 'hasp' ) ?></label>

<p class="submit">
	<input type="submit" class="button-primary" name="hasp_submit" value="<?php _e( 'Update' ); ?>">
</p>
	
</form>

</div>

<script>
(function($){
	
	$("#hasp_allcheck_objects").on('click', function(){
		var items = $("#hasp_select_objects input");
		if ( $(this).is(':checked') ) $(items).prop('checked', true);
		else $(items).prop('checked', false);	
	});

})(jQuery)
</script>