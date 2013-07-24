jQuery(window).load(function() {
	jQuery('.idealien_rideshare_status').each(function() {
		if(jQuery(this).text() != "Active") {
			jQuery(this).parent().hide();
		}
	});
});
