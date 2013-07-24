function rideshare_connect(rideshare_id, mode, receiver, sender, destination, departure, date, spaces) {
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_name").show();
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_email").show();
	
	jQuery(".idealien_rideshareComments_wrapper .gfield input").val("");
	jQuery(".idealien_rideshareComments_wrapper .gfield textfield").val("");
	
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_mode input").val(mode);
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_receiver input").val(receiver);
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_rideshare_id input").val(rideshare_id);
	
	rideshareDetails = "<dl>";
	rideshareDetails += "<div><dt>Rideshare #</dt><dd>" + rideshare_id + "</dd></div>";
	rideshareDetails += "<div><dt>Date</dt><dd>" + date + "</dd></div>";
	rideshareDetails += "<div><dt>Departure</dt><dd>" + departure + "</dd></div>";
	rideshareDetails += "<div><dt>Destination</dt><dd>" + destination + "</dd></div>";
	rideshareDetails += "<div><dt>User</dt><dd>" + receiver + "</dd></div>";
	rideshareDetails += "<div><dt>Spaces</dt><dd>" + spaces + "</dd></div>";
	
	rideshareDetails += "</dl>";
	
	
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_details_display").html(rideshareDetails);
	
	
	if(mode == 'buddypress') {
		jQuery(".idealien_rideshareComments_wrapper ul li.connect_sender input").val(sender);
		jQuery(".idealien_rideshareComments_wrapper ul li.connect_name").hide();
		jQuery(".idealien_rideshareComments_wrapper ul li.connect_email").hide();
	}
	
	connectPosition = "";
	connectPosition = jQuery("#rideshare_" + rideshare_id).position();
	
	jQuery(".idealien_rideshareComments_wrapper").css("left",connectPosition.left - 260);
	jQuery(".idealien_rideshareComments_wrapper").css("top",connectPosition.top - 10);
	
	jQuery(".idealien_rideshareComments_wrapper").show();
} 

function rideshare_cancel() {
	//Reset Connect Form
	jQuery(".idealien_rideshareComments_wrapper").hide();
	
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_name").show();
	jQuery(".idealien_rideshareComments_wrapper ul li.connect_email").show();
	
	jQuery(".idealien_rideshareComments_wrapper .gfield input").val("");
	jQuery(".idealien_rideshareComments_wrapper .gfield textfield").val("");
	
	//Reset Delete Form
	jQuery(".idealien_rideshareDelete_wrapper").hide();
	jQuery(".idealien_rideshareComments_wrapper .gfield input").val("");
	return false;
} 

function rideshare_delete(rideshare_id, username) {
	jQuery(".idealien_rideshareDelete_wrapper .gfield input").val("");
	
	jQuery(".idealien_rideshareDelete_wrapper ul li.delete_username input").val(username);
	jQuery(".idealien_rideshareDelete_wrapper ul li.delete_rideshare_id input").val(rideshare_id);
	
	rideshareQuestion = "Are you sure you wish to delete Rideshare #" + rideshare_id + "?";
	
	jQuery(".idealien_rideshareDelete_wrapper ul li.delete_details").html(rideshareQuestion);

	deletePosition = "";
	deletePosition = jQuery("#rideshare_delete_" + rideshare_id).position();
	
	jQuery(".idealien_rideshareDelete_wrapper").css("left",deletePosition.left - 260);
	jQuery(".idealien_rideshareDelete_wrapper").css("top",deletePosition.top - 10);
	
	jQuery(".idealien_rideshareDelete_wrapper").show();
	
}  

jQuery(function() {
	if (jQuery( ".rideshareDate .datepicker" ).length>0) {
    	jQuery( ".rideshareDate .datepicker" ).datepicker({ minDate: 0 });
	}
	
	if (jQuery( ".idealien_rideshareDelete_wrapper .gform_button" )) {
			jQuery( ".idealien_rideshareDelete_wrapper .gform_button" ).click(function() {
  				jQuery( ".idealien_rideshareDelete_wrapper" ).hide();
			});
	}
	
});