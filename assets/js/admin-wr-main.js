/**
 * Author: NetTantra
 * @package Widget Revisions
 */

var app = {
	init: function() {
		jQuery('body').on('click', '.nt-wr-restore-btn', function() {
			widget_id= (jQuery(this).data('widget-id')) ? jQuery(this).data('widget-id') : '';
			option_name= (jQuery(this).data('name')) ? jQuery(this).data('name') : '';
			revision_id= (jQuery(this).data('revision-id')) ? jQuery(this).data('revision-id') : '';
			ajax_nonce= (jQuery(this).data('nonce')) ? jQuery(this).data('nonce') : '';
			app.restoreRevision(widget_id, option_name, revision_id, ajax_nonce, jQuery(this));
		});

		jQuery('body').on('click', '.wr-revision-window', function() {
			widget_id= (jQuery(this).data('id')) ? jQuery(this).data('id') : '';
			option_name= (jQuery(this).data('name')) ? jQuery(this).data('name') : '';
			app.getRevisions(option_name, widget_id, jQuery(this));
		});

	},

	restoreRevision(widget_id, option_name, revision_id, ajax_nonce, selector) {
		if(!widget_id || !option_name || !revision_id || !ajax_nonce) {
			alert('Can\'t procced this time.');
			return;
		}
		selector.next('.nt-wr-restore-revision-msg').find('.nt-wr-loader').show();
		var json_data = {
			action: 'wp_widget_revisions_restore_ajax',
			revision_id: revision_id,
			option_name: option_name,
      widget_id: widget_id,
			nonce : ajax_nonce
		};
		jQuery.ajax({
      type: "POST",
      url: wp_widget_revisions.ajax_url,
      data: json_data,
      dataType: 'json',
      success: function(result) {
				selector.next('.nt-wr-restore-revision-msg').find('.nt-wr-loader').remove();
				selector.next('.nt-wr-restore-revision-msg').html('<span style="padding-left: 10px; color: #4e9203; font-weight: bold;">'+result.msg+'</span>');
				selector.addClass('disabled');
				if(result.error == false) {
					setTimeout(function() {
						window.location.reload();
					}, 500);
        }
      },
			error: function (data, errorThrown) {
				selector.next('.nt-wr-restore-revision-msg').find('.nt-wr-loader').remove();
				selector.next('.nt-wr-restore-revision-msg').html('<span style="padding-left: 10px; color: #F00; font-weight: bold;">'+errorThrown+'</span>');
			}
    });
	},

	getRevisions(name, id, selector) {
		if(!name || !id ) {
			jQuery('#TB_ajaxContent').find('.wr-modal-content').html('No data found!');
			return;
		}
		jQuery("#TB_ajaxContent").find('.nt-wr-loading').show();
		var json_data = {
			action: 'wp_widget_revisions_ajax',
      option_name: name,
      widget_id: id,
			nonce : wp_widget_revisions.nonce
		};
		jQuery.ajax({
      type: "POST",
      url: wp_widget_revisions.ajax_url,
      data: json_data,
      dataType: 'json',
      success: function(result) {
				jQuery("#TB_ajaxContent").find('.nt-wr-loading').remove();
				if(result.error == true) {
					jQuery('#TB_ajaxContent').find('.wr-modal-content').html('<p> style="padding: 100px;"'+result.msg+'</p>');
        } else {
					jQuery('#TB_ajaxContent').find('.wr-modal-content').html(result.data);
        }
			},
			error: function (data, errorThrown) {
			  jQuery("#TB_ajaxContent").find('.nt-wr-loading').remove();
			  jQuery('#TB_ajaxContent').find('.wr-modal-content').html(errorThrown);
			}
    });
	}

}

jQuery(document).ready(function() {
	app.init();
});

jQuery(window).on("popstate", function(e) {
  location.reload();
});
