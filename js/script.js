(function($){

	if (($('#postbox-container-1').length > 0) || ($('#hasp_meta_box').length > 0)) {
	    var args = {
			dateFormat: 'yy-mm-dd',
			showMonthAfterYear: true,
			controlType: 'select',
			beforeShow: function() {
				setTimeout(function(){
					$('.ui-datepicker').css('z-index', 10000);
				}, 0);
			}
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

		// Block Editor Publish Button
		$(document).on('click', '.editor-post-publish-button, .editor-post-publish-panel__toggle', function(e) {
			if ( '' !== $("[id^=edit-post-post-schedule__toggle-]").text() ) {
				$('.hasp_error_mes').hide();

				var error = false;

				if ( hasp_expire_enable.attr('checked') ) {
					var publish_text = $("[id^=edit-post-post-schedule__toggle-]").text();

					publish_text = new Date( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'date' ) );
					if ( publish_text === undefined ){
						var pre_publish_date = new Date();
					}else{
						var pre_publish_date = new Date( publish_text );
					}

					var month = pre_publish_date.getMonth()+1;
					var day = pre_publish_date.getDate();
					var hour = pre_publish_date.getHours();
					var minute = pre_publish_date.getMinutes();

					var publish_date = pre_publish_date.getFullYear() + '-' +
						( ( '' + month ).length < 2 ? '0' : '' ) + month + '-' +
						( ( '' + day ).length < 2 ? '0' : '' ) + day + ' ' +
						( ( '' + hour ).length < 2 ? '0' :'' ) + hour + ':' +
						( ( '' + minute ).length < 2 ? '0' :'' ) + minute;

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

				if ( error ) {
					e.stopImmediatePropagation();
				}
			}
		});


		// Classic Editor Publish Button
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
	}

	if ($('#the-list').length > 0) {
		//Prepopulating our quick-edit post info
		var $inline_editor = inlineEditPost.edit;
		inlineEditPost.edit = function(id){
			//call old copy
			$inline_editor.apply( this, arguments);
			//our custom functionality below
			var post_id = 0;
			if( typeof(id) == 'object'){
				post_id = parseInt(this.getId(id));
			}

			//if we have our post
			if(post_id != 0){
				//find our row
				var $edit_row = $( '#edit-' + post_id );
				var $post_row = $( '#post-' + post_id );
				//hasp value input
				var $hasp_expire_enable = $( '#hasp_expire_enable', $post_row ).val();
				if ($hasp_expire_enable === "1") {
					var $hasp_expire_date = $( '#hasp_expire_date', $post_row ).val();
					$('#hasp_expire_enable', $edit_row).val($hasp_expire_enable);
					$('#hasp_expire_date', $edit_row).val($hasp_expire_date);
				} else {
					$('#hasp_expire_enable', $edit_row).remove();
					$('#hasp_expire_date', $edit_row).remove();
				}
				var $hasp_overwrite_enable = $( '#hasp_overwrite_enable', $post_row ).val();
				if ($hasp_overwrite_enable === "1") {
					var $hasp_overwrite_post_id = $( '#hasp_overwrite_post_id', $post_row ).val();
					$('#hasp_overwrite_enable', $edit_row).val($hasp_overwrite_enable);
					$('#hasp_overwrite_post_id', $edit_row).val($hasp_overwrite_post_id);
				} else {
					$('#hasp_overwrite_enable', $edit_row).remove();
					$('#hasp_overwrite_post_id', $edit_row).remove();
				}
			}
		}
	}

	if ($('.toplevel_page_hasp-list').length > 0) {
		// Admin Setting Option
		$("#hasp_allcheck_objects").on('click', function(){
			var items = $("#hasp_select_objects input");
			if ( $(this).is(':checked') ) $(items).prop('checked', true);
			else $(items).prop('checked', false);
		});

		$('#asp_tabs').tabs({});

		$('#wd_srch').on('click', function(){
			$('#hasp_list').submit();
			return false;
		});

		$('#view_date').datepicker({
			dateFormat: 'yy-mm-dd',
			onSelect: function(dateText, inst) {
				var href = window.location.href;
				if (href.indexOf("&") > 0) {
					href = href.slice(0, href.indexOf("&"));
				}
				window.location = href + "&view_date=" + encodeURIComponent(dateText);
				return false;
			}
		}).keyup(function(e) {
			if(e.keyCode == 8 || e.keyCode == 46) {
				$.datepicker._clearDate(this);
			}
		});
	}

})(jQuery)