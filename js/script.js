var reload_check = false;
var publish_button_click = false;
var draft_button_click = false;
var reloader_d_check = false;
var future_overwrite_enable_check = "off";

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
			if ( '' !== $(".edit-post-post-schedule__toggle").text() ) {
				$('.hasp_error_mes').hide();

				var error = false;

				if ( hasp_expire_enable.prop('checked') ) {
					var publish_text = new Date( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'date' ) );
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

				if ( hasp_overwrite_enable.prop("checked") && hasp_overwrite_post_id.val() == 0 ) {
					$('#hasp_overwrite_error').show();
					error = true;
				}
				if ( hasp_overwrite_enable.prop("checked") || future_overwrite_enable_check === "on") {
					const currentPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
					const postDate = new Date( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'date' ) );
					const currentDate = new Date();
					if ( currentPostStatus === "future" && postDate.getTime() <= (currentDate.getTime() + 60) ) {
						var hasp_future_to_publish_message = $("#hasp_future_to_publish_message").text();
						var hasp_future_to_publish_message_sub = $("#hasp_future_to_publish_message_sub").text();
						alert(hasp_future_to_publish_message + "\n" + hasp_future_to_publish_message_sub);
						error = true;
					}
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
			var year = $('#aa').val();
			var month = $('#mm').val();
			var day = $('#jj').val();
			var hour = $('#hh').val();
			var minute = $('#mn').val();

			var now = new Date();
			var y = now.getFullYear();
			var m = now.getMonth() + 1;
			var d = now.getDate();
			var h = now.getHours();
			var i = now.getMinutes();
			var mm = ('0' + m).slice(-2);
			var dd = ('0' + d).slice(-2);
			var hh = ('0' + h).slice(-2);
			var ii = ('0' + i).slice(-2);
			var now_date = y + "-" + mm + "-" + dd + " " + hh + ":" + ii + ":00";

			if ( hasp_expire_enable.prop('checked') ) {

				var publish_date = year + '-' +
					month + '-' +
					( ( '' + day ).length < 2 ? '0' : '' ) + day + ' ' +
					( ( '' + hour ).length < 2 ? '0' :'' ) + hour + ':' +
					( ( '' + minute ).length < 2 ? '0' :'' ) + minute +
					':00';

				if ( hasp_expire_date.val() == '' ) {
					$('#hasp_expire_error_1').show();
					error = true;
				} else if  ( hasp_expire_date.val() <= publish_date ) {
					$('#hasp_expire_error_2').show();
					error = true;
				}
			}

			if ( hasp_overwrite_enable.prop('checked')) {
				if(hasp_overwrite_post_id.val() == 0 ) {
					$('#hasp_overwrite_error').show();
					error = true;
				}
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

	// For block-editor Submit
	if ((($('#postbox-container-1').length > 0) || ($('#hasp_meta_box').length > 0)) && ($('.block-editor').length > 0)) {
		add_publish_button_click = setInterval(function() {
			$publish_button = $('.editor-post-publish-button');
			if ($publish_button && !publish_button_click) {
				$publish_button.on('click', function() {
					var $hasp_overwrite_post_id = $( '#hasp_overwrite_post_id' ).val();
					if ( hasp_overwrite_enable.prop("checked") && $hasp_overwrite_post_id != 0 ) {
						const postDate = new Date( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'date' ) );
						const currentDate = new Date();
						if ( postDate.getTime() <= (currentDate.getTime() + 60) ) {
							if(publish_button_click === false) {
								publish_button_click = true;
								var reloader = setInterval(function() {
									if (reload_check) {return;} else {reload_check = true;}
									isEditedPostDirty = wp.data.select('core/editor').isEditedPostDirty();
									postsaving = wp.data.select('core/editor').isSavingPost();
									autosaving = wp.data.select('core/editor').isAutosavingPost();
									success = wp.data.select('core/editor').didPostSaveRequestSucceed();
									console.log('Saving: '+postsaving+' - Autosaving: '+autosaving+' - Success: '+success);
									if (isEditedPostDirty || autosaving || !success) {
										// clearInterval(reloader);
										publish_button_click = false;
										reload_check = false;
										return;
									}
									const new_currentPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
									if(new_currentPostStatus !== 'publish') {
										publish_button_click = false;
										reload_check = false;
									} else {
										wp.data.dispatch( 'core/editor' ).lockPostSaving( 'my-lock' );
										var message = $('#hasp_overwrite_message').text();
										alert(message);
										window.location.href = "post.php?post="+$hasp_overwrite_post_id+"&action=edit";
									}
									clearInterval(reloader);
								}, 1000);
							}
						}
					}
				});
			}
			$switch_to_draft_button = $('.editor-post-switch-to-draft');
			if ($switch_to_draft_button && !draft_button_click) {
				$switch_to_draft_button.on('click', function() {
					const currentPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
					if(currentPostStatus === 'future') {
						if ( hasp_overwrite_enable.prop("checked") ) {
							if(draft_button_click === false) {
								draft_button_click = true;
								var reloader_d = setInterval(function() {
									if (reloader_d_check) {return;} else {reloader_d_check = true;}
									isEditedPostDirty = wp.data.select('core/editor').isEditedPostDirty();
									postsaving = wp.data.select('core/editor').isSavingPost();
									autosaving = wp.data.select('core/editor').isAutosavingPost();
									success = wp.data.select('core/editor').didPostSaveRequestSucceed();
									console.log('Saving: '+postsaving+' - Autosaving: '+autosaving+' - Success: '+success);
									if (isEditedPostDirty || autosaving || !success) {
										draft_button_click = false;
										reloader_d_check = false;
										return;
									}
									const new_currentPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
									if(new_currentPostStatus === 'draft') {
										value = document.getElementById('hasp_meta_box').value;
										wp.data.dispatch( 'core/editor' ).lockPostSaving( 'my-lock' );
										var message = $('#hasp_delete_overwrite_message').text();
										alert(message);
										window.location.reload();
									} else {
										draft_button_click = false;
										reloader_d_check = false;
									}
									clearInterval(reloader_d);
								}, 1000);
							}
						}
					}
				});
			}
		}, 500);
		$hasp_overwrite_enable = $('#hasp_overwrite_enable');
		$hasp_overwrite_enable.on('click', function() {
			const new_currentPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
			if ( new_currentPostStatus === "future" && $(this).prop('checked') == false ) {
				future_overwrite_enable_check = "on";
			}
		})
	}

	if ($('#hasp_select_objects').length > 0) {
		var disable_message = $('#disable_message').val();
		$('input[type="checkbox"]').change(function(){
			if( $(this).prop('checked') ){
			}else{
				if (!confirm(disable_message)) {
					$(this).prop('checked', true);
				}
			}
		});
	}

})(jQuery)
