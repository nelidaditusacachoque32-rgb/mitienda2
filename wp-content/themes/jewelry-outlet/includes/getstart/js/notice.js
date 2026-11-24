jQuery("body").on("click", '#getstart-welcome-notice .notice-dismiss', function (event) {
    event.preventDefault();

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
          action: 'jewelry_outlet_dismissed_notice',
          nonce: jewelryOutletNotice.nonce,
      },
      success: function () {
          // Remove the notice on success
          jQuery('#getstart-welcome-notice').remove();
      }
    });
  }
)