jQuery.noConflict();

/**
 * load ajax process
**/
jQuery(document).ready(function() {

	jQuery('form#sh-add-task').submit( function(e) {

		e.preventDefault();

		var data = jQuery(this).serialize();
		jQuery(this).parent().parent('#sh-feed-importer').append( '<div class="form-loading"><span>Adding ...</span></div>' );

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: function(html) {
				jQuery('.form-loading').remove();
				alert(html);
			}
		});

	});

	jQuery('form#sh-download-log').submit( function(e) {

		e.preventDefault();

		var data = jQuery(this).serialize();
		jQuery(this).parent().parent('#sh-feed-importer').append( '<div class="form-loading"><span>Please wait ...</span></div>' );

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: function(html) {
				document.location = html;
			}
		});

	});

	jQuery('form#run').submit( function(e) {

		e.preventDefault();

		var data = jQuery(this).serialize();
		jQuery(this).parent().parent('#sh-feed-importer').append( '<div class="form-loading"><span>Fetching posts ...</span></div>' );

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: function(html) {
				jQuery('form#run').parent().parent().find('.form-loading').remove();
				alert( html );
			}
		});

	});

	jQuery('form#sh-save').submit( function(e) {

		e.preventDefault();
		var data = jQuery(this).serialize();
		jQuery(this).parent().parent('#sh-feed-importer').append( '<div class="form-loading"><span>Saving ...</span></div>' );

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: function(html) {
				jQuery('form#sh-save').parent().parent().find('.form-loading').remove();
				alert(html);
			}
		});

	});

	if( jQuery('input.file-upload').length > 0 ) {
		jQuery('input.file-upload').on("click", function(e) {
			e.preventDefault();
			var input_field = jQuery(this);
			tb_show("Upload a text file", "media-upload.php?&TB_iframe=true&post_id=0",false);
			window.send_to_editor = function(html) {
				var a_src = jQuery(html).attr('href');
				input_field.val(a_src);
				tb_remove();
			}
		})
	}

});