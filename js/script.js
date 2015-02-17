(function($){

	var args = {
		dateFormat: 'yy-mm-dd',
		showMonthAfterYear: true,
		controlType: 'select'
	}
	$('#hasp_expire_date').datetimepicker( args );
	
	var hasp_expire_div = $('#hasp_expire_div');
	var hasp_overwrite_div = $('#hasp_overwrite_div');
	
	var hasp_expire_enable = $('#hasp_expire_enable');
	var hasp_expire_date = $('#hasp_expire_date');
	var hasp_overwrite_enable = $('#hasp_overwrite_enable');
	var hasp_overwrite_post_id = $('#hasp_overwrite_post_id');
	
	if ( hasp_expire_enable.is(':checked') ) hasp_expire_div.show();
	if ( hasp_overwrite_enable.is(':checked') ) hasp_overwrite_div.show();
	
	hasp_expire_enable.on( 'click', function(){
		if ( $(this).is(':checked') ) hasp_expire_div.show();
		else hasp_expire_div.hide();
	});
	hasp_overwrite_enable.on( 'click', function(){
		if ( $(this).is(':checked') ) hasp_overwrite_div.show();
		else hasp_overwrite_div.hide();
	});
	// Error check before overwrite post
	
	$('#publish').click( function() {
		
		$('.hasp_error_mes').hide();
		
		var error = false;
		
		if ( hasp_expire_enable.attr('checked') ) {
			
			var publish_date = $('#aa').val() + '-' + $('#mm').val() + '-' + $('#jj').val() + ' ' + $('#hh').val() + ':' + $('#mn').val() + ':00';
			
			if ( hasp_expire_date.val() == '' ) {
				$('#hasp_expire_error_1').show();
				error = true;
			} else if  ( hasp_expire_date.val() <= publish_date ) {
				$('#hasp_expire_error_2').show();
				error = true;
			}
		}
		
		if ( hasp_overwrite_enable.attr('checked') && hasp_overwrite_post_id.val() == 0 ) {
			$('#hasp_overwrite_error').show();
			error = true;
		}
		
		if ( error ) return false;
	});
	
})(jQuery)