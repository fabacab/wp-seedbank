<?php
/*
    Plugin Name: Idealien Rideshare
    Plugin URI: http://rideshare.idealienstudios.com
    Description: Add rideshare post type + front-end submission Gravity Form
    Author: Jamie Oastler
    Version: 0.2.1
    Author URI: http://idealienstudios.com
	License: GPL
	Requires at least: 3.3
	Stable tag: 0.2.1
*/


//Modify the following two variables to identify the ID for the comment and delete forms
define ('IDEALIEN_RIDESHARE_COMMENTFORM_ID' , "REPLACEME");
define ('IDEALIEN_RIDESHARE_DELETEFORM_ID' , "REPLACEME");


//Do not modify anything else below 
define ('IDEALIEN_RIDESHARE_PATH', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) );
define ('IDEALIEN_VERSION', "0.2.1");

class idealien_rideshare {
	
	//Define all meta data fields
	var $meta_fields = array(
		"idealien_rideshare_type",
		"idealien_rideshare_spaces",
		"idealien_rideshare_event",
		"idealien_rideshare_departureCity",
		"idealien_rideshare_departureStateProv", 
		"idealien_rideshare_departureDate", 
		"idealien_rideshare_destinationCity", 
		"idealien_rideshare_destinationStateProv", 
		"idealien_rideshare_returnDate",
		"idealien_rideshare_username",
		"idealien_rideshare_name",
		"idealien_rideshare_email",
		"idealien_rideshare_phone", 
		"idealien_rideshare_addInfo",
		"idealien_rideshare_status"
	);
	
	function idealien_rideshare() {
		
		//Register CPT, Taxonomies, etc
		idealien_rideshare::create_data_types();
		
		//Setup and customize columns of data on admin list view
		add_filter("manage_edit-idealien_rideshare_columns", array(&$this, "edit_columns"));
		add_action("manage_posts_custom_column", array(&$this, "custom_columns"));		
		add_filter("manage_edit-idealien_rideshare_sortable_columns", array(&$this, "register_sortable"));
		add_filter("request", array(&$this, "idealien_rideshare_status_column_orderby"));
		add_filter("request", array(&$this, "idealien_rideshare_type_column_orderby"));
		add_filter("request", array(&$this, "idealien_rideshare_spaces_column_orderby"));
		
		//FUTURE: Date columns sortable
		//add_filter("request", array(&$this, "idealien_rideshare_departureDate_column_orderby"));
		
		//Setup function to instantiate meta boxes and scripts on admin page
		add_action("admin_init", array(&$this, "admin_init"));
		
		//Add JS for front-end users
		add_action("wp_enqueue_scripts", array(&$this, "frontend_scripts_init"));
		
		//Tweak format of datepicker for admin
		add_action('admin_footer', array(&$this, "admin_footer"));
		
		//Ensure CPT saves custom field data
		add_action("wp_insert_post", array(&$this, "wp_insert_post"), 10, 2);
		
		
		//Setup filters for the rideshare Gravity Form to populate from taxonomies into custom fields.
		add_filter("gform_pre_render", array(&$this, "populate_rideshare_data"));

		//Generate email or buddyPress notification upon completion of the comment form
		add_filter("gform_after_submission", array(&$this, "after_submission_custom_notifications"), 10, 2);
		
		//Set rideshare status to 'Deleted' when user confirms delete request
		add_filter("gform_after_submission", array(&$this, "after_submission_delete_rideshare"), 10, 2);
		
		//Custom validation for primary rideshare form based on css identifier
		add_filter('gform_validation', array(&$this, "validate_rideshare"));

		//Change data on primary form before rideshare CPT post is created
		add_filter("gform_pre_submission", array(&$this, "pre_rideshareSubmission"), 10, 2);
		
		wp_register_script('seedbank_exchange', IDEALIEN_RIDESHARE_PATH . 'jquery/seedbank_exchange.js', array('jquery') );
		wp_enqueue_script('seedbank_exchange');
		
		//Ensure all 3 forms are executed if being called from a buddyPress page
		if(function_exists('bp_core_get_userid')) {
			add_action( "init", array(&$this, "bbg_switch_gf_hooks"), 99 );
		}
		
	//End idealien_rideshare constructor function
	}
	
	//Ensure all 3 forms are executed if being called from a buddyPress page
	//Called From: idealien_rideshare constructor
	function bbg_switch_gf_hooks() {
    	remove_action('wp',  array('RGForms', 'maybe_process_form'), 9);
		add_action( 'bp_actions', array( 'RGForms', 'maybe_process_form' ), 1 );
	}


	//Setup filters for the rideshare Gravity Form to populate from taxonomies into custom fields.
	//Called From: gform_pre_render filter
	function populate_rideshare_data($form){
    
		//Only execute for the primary rideshare form
		$rideshareFormType = strpos($form['cssClass'], 'idealien_rideshare_gf');

		if( $rideshareFormType === false)
			return $form;
		
		//Pre-populate drop-downs		
    	foreach($form['fields'] as $key=>&$field) {
			$terms = null;
			
			//Match against fields that have specific custom field names
			switch ($field['postCustomFieldName']) {
    			
				//each case buidls up the list of $terms to display
				case 'idealien_rideshare_status':
        			$terms = get_terms( 'idealien_rideshare_status', array(
 						'hide_empty' => 0
 					) );
					$choices = array(array('text' => ' ', 'value' => ' '));
					break;
					
				case 'idealien_rideshare_type':
        			$terms = get_terms( 'idealien_rideshare_type', array(
 						'hide_empty' => 0
 					) );
					$choices = array(array('text' => ' ', 'value' => ' '));
					break;
					
				case 'idealien_rideshare_event':
        			$terms = get_terms( 'idealien_rideshare_event', array(
 						'hide_empty' => 0
 					) );
					$choices = array(array('text' => 'Select', 'value' => ' '));
					$choices[] = array('text' => 'Add New Event', 'value' => 'Add New Event');
					break;
					
				case 'idealien_rideshare_destinationStateProv':
				case 'idealien_rideshare_departureStateProv':
					$terms = get_terms( 'idealien_rideshare_state_prov', array(
						'hide_empty' => 0
 					) );
					$choices = array(array('text' => 'Select', 'value' => ' '));
					break;
				
				case 'idealien_rideshare_name':
				case 'idealien_rideshare_email':
					if(is_user_logged_in() ) { 
						//Remove name and email fields when dealing with registered & authenticated user
						unset($form['fields'][$key]);
					} else {
						//Make field a mandatory requirement
						$field['isRequired'] = 1;
					}
				break;
				
			} 
			
			//If any terms are identified from switch above, populate the drop-down choices
			if (isset($terms)) {
				
				foreach ( $terms as $displayTerm ) {
					$choices[] = array('text' => $displayTerm->name, 'value' => $displayTerm->name);
     			}
			
				$field['choices'] = $choices;
			
			}

    	}
		
    	return $form;
	}
	
	
	//Generate email or buddyPress notification upon completion of the comment form
	//Called From: gform_after_submission filter
	function after_submission_custom_notifications($entry, $form){
		
		//Ensure this only applies to the comment form
		$rideshareFormType = strpos($form['cssClass'], 'idealien_rideshareComments');
		
		if( $rideshareFormType !== false) {

				//Retrieve rideshare details
				$queryParameters = array(
					'post_type' => 'idealien_rideshare',
					'posts_per_page' => '1',
					'p' => $entry[14]
				);
				
				//Loop based on the rideshare to build message data
				$IRQuery = new WP_Query();
				$IRQuery->query($queryParameters);
				if ( $IRQuery->have_posts() ) :
				
					 while ( $IRQuery->have_posts() ) : $IRQuery->the_post(); 
					 	$ID = get_the_ID();
						
						//Retrieve user
						$user = get_user_by('login', $entry[12]);
						
						//Build message				
						$message =  $user->display_name . " wants to connect with you about the following rideshare.\r\n";
					 	$message .= "Rideshare: " . $ID . "\r\n";
						$message .= "Type: " . get_post_meta($ID, "idealien_rideshare_type", true) . "\r\n";
						$message .= "Date: " . get_post_meta($ID, "idealien_rideshare_departureDate", true) . "\r\n";
						$message .= "Spaces: " . get_post_meta($ID, "idealien_rideshare_spaces", true) . "\r\n";
						$message .= "Departure: " . get_post_meta($ID, "idealien_rideshare_departureCity", true) . ", " . get_post_meta($ID, "idealien_rideshare_departureStateProv", true) . "\r\n";
						
						//Display either City/Stave or Event - whichever is populated
						if (get_post_meta($ID, "idealien_rideshare_event", true)) {
							$message .= "Destination: " . get_post_meta($ID, "idealien_rideshare_event", true) . "\r\n";
						} else {
							$message .= "Destination: " . get_post_meta($ID, "idealien_rideshare_destinationCity", true) . ", " . get_post_meta($ID, "idealien_rideshare_destinationStateProv", true) . "\r\n";
						}
						
						if ($entry[1]) {
							$message .= "Comments:\r\n" . $entry[1];	
						}
						
					 endwhile;
					 
				endif;
							 
							 
			//Confirm BuddyPress is accessible for generating the notification
			//FUTURE: Options page whether form should use buddypress or not	 
			if($entry[11] == 'buddypress' && function_exists('messages_new_message')) {
				messages_new_message( array( 'recipients' => $entry[13], 'subject' => 'Rideshare #' . $ID . ' Connection','content' => $message ) );
			} else {
				//Send response by email
				if($entry[11] == 'email') {
					
					wp_mail( $entry[3], "Rideshare #" . $ID . " Connection Request", $message); 
					//FUTURE: More contextual info in the message
				}
			} 

		}
	}
	
	//Set rideshare status to 'Deleted' when user confirms delete request
	//Called From: gform_after_submission filter
	function after_submission_delete_rideshare($entry, $form){
		//FUTURE: Validate that Rideshare ID matches user in $entry
		
		$rideshareFormType = strpos($form['cssClass'], 'idealien_rideshareDelete');
		
		//Only execute when dealing with the Delete form
		if( $rideshareFormType !== false) {
			update_post_meta($entry[14], 'idealien_rideshare_status', 'Deleted');

		}
	} 


	//Custom validation for primary rideshare form based on css identifier
	//Called From: gform_validation filter
	function validate_rideshare($validation_result){
		
		//Ensure this validation only fires on the primary form
		$form = $validation_result["form"];
		$rideshareFormType = strpos($form['cssClass'], 'idealien_rideshare_gf');
		if( $rideshareFormType === false )
			return $validation_result;

		//Validation State: City Mode defined in form
		if( $_POST["input_27"] == 'City' ) {
			
			//Loop through custom field fields for appropriate validation		
    		foreach($form['fields'] as $key=>&$field) {
				
				switch ($field['postCustomFieldName']) {
    				
					//Destination City is required
					case 'idealien_rideshare_destinationCity':
						if( !$_POST["input_25"] ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						} 
					break; 
					
					//Destination State / Prov is required
					case 'idealien_rideshare_destinationStateProv':
						if( rgpost("input_19") == ' ' ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						} 
					break;
					
				}
			}
		}
		
		//Validation State: Event Mode defined in form
		if( $_POST["input_27"] == 'Event' ) {
				
			//Loop through custom field fields for appropriate validation	
    		foreach($form['fields'] as $key=>&$field) {
				
				switch ($field['postCustomFieldName']) {
    				
					case 'idealien_rideshare_event':
						
						//Event is required
						if( rgpost("input_28") == ' ' ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						} 
					break;

				}
			}
		}
		
		//Validation State: If Add New Event selected, the new taxonomy entry field is required
		
		if( rgpost("input_28") == 'Add New Event' ) {
			if( !$_POST["input_29"] ) {
				foreach($form['fields'] as $key=>&$field) {
				
					switch ($field['cssClass']) {
    				
						case 'rideshare_newEvent':
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						break;
					} 		

				}
			}
		}
		
		//Validation State: If a non-registered user, name + email are required fields
		if(!is_user_logged_in() ) { 
					
    		foreach($form['fields'] as &$field) {
				
				switch ($field['postCustomFieldName']) {
    				
					case 'idealien_rideshare_name':
			
						//Name is required
						if( !$_POST["input_30"] ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						}
					break;
					
					//Email is required	
					case 'idealien_rideshare_email':
						if( !$_POST["input_31"] ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						}
					break;
				
				}
			}
		} 

		// update the form in the validation result with the form object you modified
    	$validation_result["form"] = $form;

    	return $validation_result;
	}
	
	//Change data on primary form before rideshare CPT post is created
	//Called From: gform_pre_submission filter
	function pre_rideshareSubmission($form){
		
		//Only modify the primary form output
		$rideshareFormType = strpos($form['cssClass'], 'idealien_rideshare_gf');
		
		if( $rideshareFormType === false )
			return $form;

		//If Add New Event text field has a value - create the term for it and force the drop-down to new value.
		if( $_POST["input_29"] ) {
			$newEvent = preg_replace( '/[^a-z]/i', "", $_POST["input_29"]);
			wp_insert_term( $newEvent , 'idealien_rideshare_event');
			$_POST["input_28"] = $newEvent;
		}
		
		//Create a title for the post (only visible in admin)
		//Different title format based on registered or unregistered access
		if($_POST["input_30"]) { 
			$displayName = $_POST["input_30"];
		} else {
			global $current_user;
   			get_currentuserinfo();
			$displayName = $current_user->user_login;
		}
			
		//Different title format based on event / citystate mode
		if( $_POST["input_27"] == "Event") {
			//[Name] to [Event] ON [Date] 
			$_POST["input_6"] = $displayName . " TO " . $_POST["input_28"] . " ON " .  $_POST["input_10"];
		} else {
			//[Name] TO [City, State] ON [Date]
			$_POST["input_6"] = $displayName . " TO " . $_POST["input_25"] . ", " . $_POST["input_19"] . " ON " .  $_POST["input_10"];
		}

	}



	function create_data_types() {
		
		// Register custom post type and taxonomies
		register_post_type('idealien_rideshare', 
			array(
				'labels' => array(
					'name' => __( 'Rideshares', 'idealien-rideshare' ),
					'singular_label' => __( 'Rideshare', 'idealien-rideshare' ),
					'add_new' => __( 'Add Rideshare', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add Rideshare', 'idealien-rideshare' ),
					'edit' => __( 'Edit Rideshares', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Rideshare', 'idealien-rideshare' ),
					'new_item' => __( 'New Rideshare', 'idealien-rideshare' ),
					'view' => __( 'View Rideshares', 'idealien-rideshare' ),
					'view_item' => __( 'View Rideshare', 'idealien-rideshare' ),
					'search' => __( 'Search Rideshares', 'idealien-rideshare' ),
					'not_found' => __( 'No Rideshares found', 'idealien-rideshare' ),
					'not_found_in_trash' => __( 'No Rideshare found in trash', 'idealien-rideshare' )
				),
				'supports' => array('title', 'custom-fields'),
				'rewrite' => array('slug' => 'rideshare'),
				'public' => true,
				'description' => __( 'Idealien Rideshare', 'idealien-rideshare' ),
				'menu_icon' => IDEALIEN_RIDESHARE_PATH . 'images/rideshare_icon.png'
			)
		);
		
		register_taxonomy('idealien_rideshare_type', 'idealien_rideshare', array(
				'labels' => array(
					'name' => __( 'Types', 'idealien-rideshare' ),
					'singular_name' => __( 'Type', 'idealien-rideshare' ),
					'all_items' => __( 'All Types', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Type', 'idealien-rideshare' ),
					'update_item' => __( 'Update Type', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add New Type', 'idealien-rideshare' ),
					'new_item_name' => __( 'New Type Name', 'idealien-rideshare' )
				),
				'hierarchical' => false,
				'label' => 'Rideshare Types'
			)
		);
		
		register_taxonomy('idealien_rideshare_event', 'idealien_rideshare', array(
				'labels' => array(
					'name' => __( 'Events', 'idealien-rideshare' ),
					'singular_name' => __( 'Event', 'idealien-rideshare' ),
					'all_items' => __( 'All Events', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Event', 'idealien-rideshare' ),
					'update_item' => __( 'Update Event', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add New Event', 'idealien-rideshare' ),
					'new_item_name' => __( 'New Event Name', 'idealien-rideshare' )
				),
				'hierarchical' => false,
				'label' => __( 'Rideshare Events', 'idealien-rideshare' )
			)
		);
		
		register_taxonomy('idealien_rideshare_state_prov', 'idealien_rideshare', array(
				'labels' => array(
					'name' => __( 'State/Province', 'idealien-rideshare' ),
					'singular_name' => __( 'State/Province', 'idealien-rideshare' ),
					'all_items' => __( 'All States/Provinces', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit State/Province', 'idealien-rideshare' ),
					'update_item' => __( 'Update State/Province', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add New State/Province', 'idealien-rideshare' ),
					'new_item_name' => __( 'New State/Province Name', 'idealien-rideshare' )
				),
				'hierarchical' => false,
				'label' => __( 'Rideshare State/Province', 'idealien-rideshare' )
			)
		);
		
		register_taxonomy('idealien_rideshare_status', 'idealien_rideshare', array(
				'labels' => array(
					'name' => __( 'Status', 'idealien-rideshare' ),
					'singular_name' => __( 'Status', 'idealien-rideshare' ),
					'all_items' => __( 'All Status', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Status', 'idealien-rideshare' ),
					'update_item' => __( 'Update Status', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add New Status', 'idealien-rideshare' ),
					'new_item_name' => __( 'New Status Name', 'idealien-rideshare' )
				),
				'hierarchical' => false,
				'label' => __( 'Rideshare Status', 'idealien-rideshare' )
			)
		);
		
		// instruction to only load shortcode and front-end CSS if it is not the admin area
		if ( !is_admin() ) { 
			add_shortcode('ridesharelist', array('idealien_rideshare','idealien_rideshare_shortcode'));
			add_action( "wp_print_styles", array($this, 'enqueue_display_styles') );
		}
	}
	
	//Setup function to instantiate meta boxes and scripts on admin page
	function admin_init() 
	{
		// Custom meta boxes for the edit rideshare screen
		add_meta_box("idealienRideshareDetails-meta", "Rideshare Details", array(&$this, "meta_options_details"), "idealien_rideshare", "normal", "high");
		
		//Remove meta boxes that are added by default with taxonomy
		remove_meta_box( 'tagsdiv-idealien_rideshare_type', 'idealien_rideshare', 'normal' );
		remove_meta_box( 'tagsdiv-idealien_rideshare_event', 'idealien_rideshare', 'normal' );
		
		// Setup scripts / styles for date picker
		add_action( "admin_print_scripts-post.php", array($this, 'enqueue_admin_scripts') );
        add_action( "admin_print_scripts-post-new.php", array($this, 'enqueue_admin_scripts') );
		add_action( "admin_print_styles", array($this, 'enqueue_admin_styles') );
		
	}
	
	// add scripts for admin UI treatment
     function enqueue_admin_scripts() {
		global $current_screen;
		if ($current_screen->post_type == 'idealien_rideshare') {
				wp_register_script('jquery-ui-datepicker', IDEALIEN_RIDESHARE_PATH . 'jquery/jquery.ui.datepicker.min.js', array('jquery', 'jquery-ui-core') );
				wp_enqueue_script('jquery-ui-datepicker');
				
		}
	}

	 // add css for admin UI treatment
	 function enqueue_admin_styles() {
		 
	 	global $current_screen;

		//Only apply to the idealien_rideshare post type
		if(isset($current_screen->post_type)) {

			switch ($current_screen->post_type) {
				case 'idealien_rideshare':
					wp_enqueue_style('jquery-ui-theme', IDEALIEN_RIDESHARE_PATH . 'css/ui-lightness/jquery-ui-1.8.16.custom.css');
					wp_enqueue_style('idealien_rideshare_admin', IDEALIEN_RIDESHARE_PATH . 'css/idealien_rideshare_admin.css');
					break;
			}
		}
		
	 }
	 
	 //Register & activate the JS to enable comment / delete form functionality
	 //FUTURE: Only have this fire on pages where the shortcode is in use.
	 function frontend_scripts_init() {
    	wp_register_script('rideshare-connect', IDEALIEN_RIDESHARE_PATH . 'jquery/idealien_rideshare_connect.js', array('jquery') );
		wp_enqueue_script('rideshare-connect');
	}    
 
 
	 // add css for admin UI treatment
	 function enqueue_display_styles() {
	 	wp_enqueue_style('idealien_rideshare_styles', IDEALIEN_RIDESHARE_PATH . 'css/idealien_rideshare.css');
	 }
	
	//Tweak format of datepicker for admin
	function admin_footer() {
		
	global $current_screen;
		if(isset($current_screen->post_type)) {
			if ($current_screen->post_type == 'idealien_rideshare') {
			?>
			<script type="text/javascript">
			jQuery(document).ready(function(){
				if(jQuery( ".datepicker" ).length) {
					jQuery( ".datepicker" ).datepicker({ 
						dateFormat : 'mm/dd/yy',
						showOn: "button",
						buttonImage: "<?php echo IDEALIEN_RIDESHARE_PATH; ?>/images/calendar.gif",
						buttonImageOnly: true,
						minDate: 0
					});
				}
				
			});
			</script>
			<?php
			}
		}
		
	}
	
	//Custom meta boxes for the edit rideshare screen
	//Called from: admin_init
	function meta_options_details()
	{
		global $post;
		//include_once('helpers.php');
		$custom = get_post_custom($post->ID);
		
		//Retrieve meta data fields or set to initial blank state
		if (isset($custom)){
			$idealien_rideshare_type = $custom["idealien_rideshare_type"][0];
			$idealien_rideshare_spaces = $custom["idealien_rideshare_spaces"][0];
			$idealien_rideshare_event = $custom["idealien_rideshare_event"][0];
			$idealien_rideshare_departureCity = $custom["idealien_rideshare_departureCity"][0];
			$idealien_rideshare_departureStateProv = $custom["idealien_rideshare_departureStateProv"][0]; 
			$idealien_rideshare_departureDate = $custom["idealien_rideshare_departureDate"][0];
			$idealien_rideshare_returnDate = $custom["idealien_rideshare_returnDate"][0];
			$idealien_rideshare_destinationCity = $custom["idealien_rideshare_destinationCity"][0];
			$idealien_rideshare_destinationStateProv = $custom["idealien_rideshare_destinationStateProv"][0]; 
			$idealien_rideshare_username = $custom["idealien_rideshare_username"][0];
			$idealien_rideshare_name = $custom["idealien_rideshare_name"][0];
			$idealien_rideshare_email = $custom["idealien_rideshare_email"][0];
			$idealien_rideshare_phone = $custom["idealien_rideshare_phone"][0];
			$idealien_rideshare_addInfo = $custom["idealien_rideshare_addInfo"][0];
			$idealien_rideshare_status = $custom["idealien_rideshare_status"][0];
		} else {
			$idealien_rideshare_type = "";
			$idealien_rideshare_spaces = "";
			$idealien_rideshare_event = "";
			$idealien_rideshare_departureCity = "";
			$idealien_rideshare_departureStateProv = ""; 
			$idealien_rideshare_departureDate = "";
			$idealien_rideshare_returnDate = "";
			$idealien_rideshare_destinationCity = "";
			$idealien_rideshare_destinationStateProv = "";
			$idealien_rideshare_username = "";
			$idealien_rideshare_name = "";
			$idealien_rideshare_email = "";
			$idealien_rideshare_phone = "";
			$idealien_rideshare_addInfo = "";
			$idealien_rideshare_status = "";
		}
		
	//Display data into appropriate html field elements within metabox
	?>
	
     <p>
		<label class="rideshare"><?php _e('Status' , 'idealien-rideshare'); ?>:</label>
		<?php // Get all rideshare status terms (taxonomy)
			$statusOptions = get_terms('idealien_rideshare_status', 'hide_empty=0&order=ASC'); ?>
			<select name='idealien_rideshare_status' class='rideshare_selectLine'>
            <option></option>
			<?php 
			foreach ($statusOptions as $status) {
				if ($status->name == $idealien_rideshare_status) {
					echo "<option SELECTED value='" . $status->name . "'>" . $status->name . "</option>\n";
				} else {
					echo "<option value='" . $status->name . "'>" . $status->name . "</option>\n";
				}
			}
   		?>
		</select>
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Type' , 'idealien-rideshare'); ?>:</label>
		<?php // Get all rideshare type terms (taxonomy)
			$typeOptions = get_terms('idealien_rideshare_type', 'hide_empty=0&order=ASC'); ?>
			<select name='idealien_rideshare_type' class='rideshare_selectLine'>
			<?php 
			foreach ($typeOptions as $type) {
				if ($type->name == $idealien_rideshare_type) {
					echo "<option SELECTED value='" . $type->name . "'>" . $type->name . "</option>\n";
				} else {
					echo "<option value='" . $type->name . "'>" . $type->name . "</option>\n";
				}
			}
   		?>
		</select>
	</p>
    
	
    <p>
		<label class="rideshare">Event:</label>
		<?php // Get all rideshare event terms (taxonomy)
			$eventOptions = get_terms('idealien_rideshare_event', 'hide_empty=0&order=ASC'); ?>
			<select name='idealien_rideshare_event' class='rideshare_selectLine'>
			<?php 
			echo "<option></option>\n";
			foreach ($eventOptions as $event) {
				if ($event->name == $idealien_rideshare_event) {
					echo "<option SELECTED value='" . $event->name . "'>" . $event->name . "</option>\n";
				} else {
					echo "<option value='" . $event->name . "'>" . $event->name . "</option>\n";
				}
			}
   		?>
		</select>
	</p>
    
    <p>
		<label class="rideshare"><?php _e('To' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_destinationCity" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_destinationCity; ?>" />
	
		<?php // Get all rideshare city terms (taxonomy)
			$stateProvOptions = get_terms('idealien_rideshare_state_prov', 'hide_empty=0&order=ASC'); ?>
			<select name='idealien_rideshare_destinationStateProv' class='rideshare_selectLine'>
			<option></option>
			<?php 
			foreach ($stateProvOptions as $stateProv) {
				if ($stateProv->name == $idealien_rideshare_destinationStateProv) {
					echo "<option SELECTED value='" . $stateProv->name . "'>" . $stateProv->name . "</option>\n";
				} else {
					echo "<option value='" . $stateProv->name . "'>" . $stateProv->name . "</option>\n";
				}
			}
   		?>
		</select>
	</p>
    

	<p>
		<label class="rideshare"><?php _e('From' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_departureCity" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_departureCity; ?>" />
	
		<?php // Get all rideshare city terms (taxonomy)
			$stateProvOptions = get_terms('idealien_rideshare_state_prov', 'hide_empty=0&order=ASC'); ?>
			<select name='idealien_rideshare_departureStateProv' class='rideshare_selectLine'>
			<option></option>
			<?php 
			foreach ($stateProvOptions as $stateProv) {
				if ($stateProv->name == $idealien_rideshare_departureStateProv) {
					echo "<option SELECTED value='" . $stateProv->name . "'>" . $stateProv->name . "</option>\n";
				} else {
					echo "<option value='" . $stateProv->name . "'>" . $stateProv->name . "</option>\n";
				}
			}
   		?>
		</select>
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Departure Date' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_departureDate" class="rideshare_inputLine datepicker" value="<?php echo $idealien_rideshare_departureDate;?>" />
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Return Date' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_returnDate" class="rideshare_inputLine datepicker" value="<?php echo $idealien_rideshare_returnDate;?>" />
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Spaces' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_spaces" class="rideshare_inputLine" value="<?php if ($idealien_rideshare_spaces >= 1) { 
					echo $idealien_rideshare_spaces;
				} else {
					echo '1';
				} ?>" />
	</p>
    <div class="clear"></div>
	
     <p>
		<label class="rideshare"><?php _e('Username' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_username" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_username;?>" /><br />
		
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Name' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_name" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_name;?>" /><br />
		
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Email' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_email" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_email;?>" /><br />
		
	</p> 
    
    
     <p>
		<label class="rideshare"><?php _e('Additional Info' , 'idealien-rideshare'); ?>:</label>
        <textarea name="idealien_rideshare_addInfo" class="rideshare_inputBox"><?php echo $idealien_rideshare_addInfo;?></textarea><br />
		
	</p>
    
    <?php //For debug inspection purposes ?>
	<span id="post_id_reference" style="display:none;"><?php echo $post->ID; ?></span>
	
<?php
	}
	

	
	//Ensure CPT saves custom field data
	function wp_insert_post($post_id, $post = null)
	{
		// don't run this for quickedit
		if ( defined('DOING_AJAX') )
 		return;
		//Only execute adjustment for the rideshare CPT
		if ($post->post_type == "idealien_rideshare")
		{
			
			// Loop through the POST data
			foreach ($this->meta_fields as $key)
			{
				$value = @$_POST[$key];
				if (empty($value))
				{
					delete_post_meta($post_id, $key);
					continue;
				}

				// If value is a string it should be unique
				if (!is_array($value))
				{
					update_post_meta($post_id, $key, $value);
				}
				else
				{
					// If passed along is an array, we should remove all previous data
					delete_post_meta($post_id, $key);
					
					// Loop through the array adding new values to the post meta as different entries with the same name
					foreach ($value as $entry)
						add_post_meta($post_id, $key, $entry);
				}
			}
		}
	}
	
	
	
	
	// Display custom columns in the admin view for the custom post type
	function edit_columns($columns)
	{
		$columns = array(
			"cb" => "<input type=\"checkbox\" />",
			"title" => __( 'Rideshare', 'idealien-rideshare' ),
			"idealien_rideshare_status" => __( 'Status', 'idealien-rideshare' ),
			"idealien_rideshare_type" => __( 'Type', 'idealien-rideshare' ),
			"idealien_rideshare_departureDate" => __( 'Date', 'idealien-rideshare' ),
			"idealien_rideshare_spaces" => __( 'Spaces', 'idealien-rideshare' ),
			"idealien_rideshare_destination" => __( 'Destination', 'idealien-rideshare' ),
			"idealien_rideshare_departure" => __( 'Departure', 'idealien-rideshare' )
		);
		
		return $columns;
	}
	
	// Display custom field in the admin view for the custom post type
	function custom_columns($column) {
		//Customize based on value select statement to consolidate city / state columns
		global $post;
		//
		
		switch($column) {
			
			//Build destination from city, state and event meta data
			case 'idealien_rideshare_destination':
				$destinationEvent = get_post_meta($post->ID, 'idealien_rideshare_event', "true");
				if ($destinationEvent) {
					echo $destinationEvent;
				} else {
					$destinationCityState = get_post_meta($post->ID, 'idealien_rideshare_departureCity', "true") . ", " . get_post_meta($post->ID, 'idealien_rideshare_departureStateProv', "true");
					echo $destinationCityState;
				}
				break; 
			
			//Build departure from city, state meta data
			case 'idealien_rideshare_departure':
				echo get_post_meta($post->ID, 'idealien_rideshare_departureCity', "true") . ", " . get_post_meta($post->ID, 'idealien_rideshare_departureStateProv', "true");
				break;
			
			//Show custom field direct
			default:
				echo get_post_meta($post->ID, $column, "true") ;
				break;
		}

	}

	//Make specific columns sortable where data makes sense
	function register_sortable( $columns ) {
		
		$columns['idealien_rideshare_status'] = 'idealien_rideshare_status';
		$columns['idealien_rideshare_type'] = 'idealien_rideshare_type';
		$columns['idealien_rideshare_spaces'] = 'idealien_rideshare_spaces';
		//FUTURE: When WP supports custom field orderby date, make it sortable
		//$columns['idealien_rideshare_departureDate'] = 'idealien_rideshare_departureDate';
		return $columns;	
	}
	
	//Augment the sort query for custom field - idealien_rideshare_status
	function idealien_rideshare_status_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'idealien_rideshare_status' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'idealien_rideshare_status',
				'orderby' => 'meta_value'
			) );
		}
 
		return $vars;
	}
	
	//Augment the sort query for custom field - idealien_rideshare_type
	function idealien_rideshare_type_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'idealien_rideshare_type' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'idealien_rideshare_type',
				'orderby' => 'meta_value'
			) );
		}
 
		return $vars;
	}
	
	//Augment the sort query for custom field - idealien_rideshare_spaces
	function idealien_rideshare_spaces_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'idealien_rideshare_spaces' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'idealien_rideshare_spaces',
				'orderby' => 'meta_value_num'
			) );
		}
 
		return $vars;
	}

	//FUTURE: Revisit when WP has orderby option for dates
	function idealien_rideshare_departureDate_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'idealien_rideshare_departureDate' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'idealien_rideshare_departureDate',
				'orderby' => 'meta_value_num'
			) );
		}
 
		return $vars;
	}


	//Generate the table list of rideshares with parameters for filtering and dynamic filtering
	function idealien_rideshare_shortcode( $atts ) {
		extract( shortcode_atts( array(
			'style' => 'full', //full, events
			'dynamic' => 'off', //off, on
			'type' => 'all', //all, give, get
			'destination' => 'all', //all, city, state, event
			'destination_filter' => null, //Populate if destination = city, state or event - overridden by dynamic
			'departure' => 'all', //all, state, city
			'departure_filter' => null, //Populate if departure = city or state - overridden by dynamic
			'date' => 'current', //all, past, current, single
			'date_filter' => null, //Populate if date = single
			'username' => 'all', //all or specific WP username
			'spaces' => 'all', //all or 1 - 5
			'contact' => 'email' //email, form, buddypress
			//'status' => 'active' //active or deleted
	      ), $atts ) );

		//Step 1 - Parse variables to exclude at risk variables to add to query
		$style = esc_attr(strtolower($style));
		$dynamic = esc_attr(strtolower($dynamic));
		$destination = esc_attr(strtolower($destination));
		$destination_filter = esc_attr(strtolower($destination_filter));
		$departure = esc_attr(strtolower($departure));
		$departure_filter = esc_attr(strtolower($departure_filter));
		$date = esc_attr(strtolower($date));
		$date_filter = esc_attr(strtolower($date_filter));
		$username = esc_attr(strtolower($username));
		$spaces = esc_attr(strtolower($spaces));
		$contact = esc_attr(strtolower($contact));
		//$status = esc_attr(strtolower($status));
		
		//Step 2 - Create filters for non-dynamic scenario
		
		//Filter meta_query based on rideshare status - always != deleted
		$statusMetaQuery = array(
			'key' => 'idealien_rideshare_status',
			'value' => 'Deleted',
			'compare' => '!='
		);
		
		//Filter meta_query based on rideshare type from shortcode
		if($type != 'all') {
			$typeMetaQuery = array(
				'key' => 'idealien_rideshare_type',
				'value' => $type,
				'compare' => '='
			);
		} 
		
		//Filter meta_query based on destination from shortcode
		if($destination != 'all') {
			 switch($destination) {
				case 'city':
					$destinationKey = 'idealien_rideshare_destinationCity';
					break; 
				case 'state':
					$destinationKey = 'idealien_rideshare_destinationStateProv';
					break; 
				case 'event';
					$destinationKey = 'idealien_rideshare_event';
					break;
			 }
			
			if($destination_filter) {
				$destinationMetaQuery = array(
					'key' => $destinationKey,
					'value' => $destination_filter,
					'compare' => '='
				);
			}
		} 
		
		//Filter meta_query based on departure city / state from shortcode
		if($departure != 'all') {
			 switch($departure) {
				case 'city':
					$departureKey = 'idealien_rideshare_departureCity';
					break; 
				case 'state':
					$departureKey = 'idealien_rideshare_departureStateProv';
					break; 
			 }
			
			if($departure_filter) {
				$departureMetaQuery = array(
					'key' => $departureKey,
					'value' => $departure_filter,
					'compare' => '='
				);
			}
		} 
		
		
		//Filter meta_query based on date from shortcode
		if($date == 'current' || $date == "past" || $date == "single") {
			switch($date) {
				case 'past':
					$dateCompare = '<';
					$dateValue = date("m/d/Y", time());
					break; 
				case 'current':
					$dateCompare = '>=';
					$dateValue = date("m/d/Y", time());
					break; 
				case 'single':
					$dateCompare = '=';
					$dateValue = date($date_filter);
					break;
			}
			$dateMetaQuery = array(
				'key' => 'idealien_rideshare_departureDate',
				'value' => $dateValue,
				'compare' => $dateCompare
			);
		}
		
		//Filter meta_query based on date from shortcode
		if($username != 'all') {
			$userMetaQuery = array(
				'key' => 'idealien_rideshare_username',
				'value' => $username,
				'compare' => '='
			);
		} 
		
		//Filter meta_query based on rideshare type from shortcode
		if($spaces != 'all') {
			$typeSpacesQuery = array(
				'key' => 'idealien_rideshare_spaces',
				'value' => $spaces,
				'compare' => '='
			);
		} 

		//Step 3: Build dynamic filters if relevant based on usage of shortcode
		//Override any shortcode attribute filters with dynamic versions
		if($dynamic == 'on') {
			
			//Filter meta_query based on rideshare type from querystring
			$dType = esc_attr(strtolower($_GET['type']));
			if($dDestination) {
				$typeMetaQuery = array(
					'key' => 'idealien_rideshare_type',
					'value' => $dType,
					'compare' => '='
				);
			}
			
			//Filter meta_query based on destination from querystring
			$dDestination = esc_attr(strtolower($_GET['destination']));
			if($dDestination != 'all') {
				switch($dDestination) {
					case 'city':
						$destinationKey = 'idealien_rideshare_destinationCity';
						break; 
					case 'state':
						$destinationKey = 'idealien_rideshare_destinationStateProv';
						break; 
					case 'event';
						$destinationKey = 'idealien_rideshare_event';
						break;
				 }
			
				$dDestination_filter = esc_attr(strtolower($_GET['destination_filter']));
				if($dDestination_filter) {
					$destinationMetaQuery = array(
						'key' => $destinationKey,
						'value' => $dDestination_filter,
						'compare' => '='
					);
				}
			} 
			
			//Filter meta_query based on departure from querystring
			$dDeparture = esc_attr(strtolower($_GET['departure']));
			if($dDeparture != 'all') {
				switch($dDDeparture) {
					case 'city':
						$departureKey = 'idealien_rideshare_departureCity';
						break; 
					case 'state':
						$departureKey = 'idealien_rideshare_departureStateProv';
						break; 
				 }
			
				$dDeparture_filter = esc_attr(strtolower($_GET['departure_filter']));
				if($dDeparture_filter) {
					$departureMetaQuery = array(
						'key' => $departureKey,
						'value' => $dDeparture_filter,
						'compare' => '='
					);
				}
			} 
			
			//Filter meta_query based on date from querystring
			$dDate = esc_attr(strtolower($_GET['date']));
			$dDate_filter = esc_attr(strtolower($_GET['date_filter']));
			
			if($dDate) {
				switch($dDate) {
					case 'past':
						$dateCompare = '<';
						$dateValue = date("m/d/y");
						break; 
					case 'current':
						$dateCompare = '>=';
						$dateValue = date("m/d/y");
						break; 
					case 'single':
						$dateCompare = '=';
						$dateValue = date($dDate_filter);
						break;
				}
				$dateMetaQuery = array(
					'key' => 'idealien_rideshare_departureDate',
					'value' => $dateValue,
					'compare' => $dateCompare
				);
			}
		
			//Filter meta_query based on username type from querystring
			$dUsername = esc_attr(strtolower($_GET['username']));
			if($dUsername) {
				$userMetaQuery = array(
					'key' => 'idealien_rideshare_username',
					'value' => $dUsername,
					'compare' => '='
				);
			}
			
			//Filter meta_query based on rideshare type from querystring
			$dSpaces = esc_attr(strtolower($_GET['spaces']));
			if($dSpaces) {
				$spacesMetaQuery = array(
					'key' => 'idealien_rideshare_spaces',
					'value' => $dSpaces,
					'compare' => '='
				);
			}
			
		}
		
		//Step 4 - Prepare the actual $meta_query
		if ( $statusMetaQuery ) { $meta_query[] = $statusMetaQuery; }
		if ( $typeMetaQuery ) { $meta_query[] = $typeMetaQuery; }
		if ( $destinationMetaQuery ) { $meta_query[] = $destinationMetaQuery; }
		if ( $departureMetaQuery ) { $meta_query[] = $departureMetaQuery; }
		if ( $dateMetaQuery ) { $meta_query[] = $dateMetaQuery; }
		if ( $userMetaQuery ) { $meta_query[] = $userMetaQuery; }
		if ( $spacesMetaQuery ) { $meta_query[] = $spacesMetaQuery; }


		//$output .= print_r($meta_query,true);
		//Step 5 - Generate output
		switch ($style) {
    		case 'full':
				
				if ($meta_query == null) {
					$queryParameters = array(
					'post_type' => 'idealien_rideshare',
					'posts_per_page' => '-1',
    				'orderby' => 'meta_value',
					'meta_key' => 'idealien_rideshare_type',
					'order' => 'ASC'
					);
				} else {
					
					$queryParameters = array(
						'post_type' => 'idealien_rideshare',
						'posts_per_page' => '-1',
						'meta_query' => $meta_query,
    					'orderby' => 'meta_value',
						'meta_key' => 'idealien_rideshare_type',
						'order' => 'ASC'
					);	
					
				}
				
				//DEBUG: $output .= '<pre>' . var_export($queryParameters, true) . '</pre>';
				
				$IRQuery = new WP_Query();
				$IRQuery->query($queryParameters);
				
				//Loop through results
				if ( $IRQuery->have_posts() ) :
					//Create single instances of the sub-forms to be tweaked based on idealien_rideshare_connect.js on button click
					if(IDEALIEN_RIDESHARE_COMMENTFORM_ID != "REPLACEME") {
						$output .= do_shortcode('[gravityform id="' . IDEALIEN_RIDESHARE_COMMENTFORM_ID . '" title="false" description="false"]');
					}
					
					if(IDEALIEN_RIDESHARE_DELETEFORM_ID != "REPLACEME") {
						$output .= do_shortcode('[gravityform id="' . IDEALIEN_RIDESHARE_DELETEFORM_ID . '" title="false" description="false"]');
					}
					
					//Retrieve current user
					global $current_user;
					get_currentuserinfo();
					
					//Prepare table headers
					$output .= '<table id="rideshare" class="tablestripe">';
					$output .= '<tr>';
					$output .= '<th class="rideshareType">' . __('Type' , 'idealien-rideshare') . '</th>';
					$output .= '<th class="rideshareEvent">' . __('To' , 'idealien-rideshare') . '</th>';
					$output .= '<th class="rideshareCity">' . __('From' , 'idealien-rideshare') . '</th>';
					$output .= '<th class="rideshareSpaces">' . __('For' , 'idealien-rideshare') . '</th>';
					$output .= '<th class="rideshareDDate">' . __('On' , 'idealien-rideshare') . '</th>';
					//$output .= '<th class="rideshareRDate">' . __('Return' , 'idealien-rideshare') . '</th>';
					$output .= '<th class="rideshareInfo">' . __('Add. Info' , 'idealien-rideshare') . '</th>';
					
					//Add Engagement Column based on current user = filtered (profile scenario)
					if( $current_user->user_login != $username ) {
						$output .= '<th class="rideshareContact">' . __('Contact' , 'idealien-rideshare') . '</th>';
					} else {
						$output .= '<th class="rideshareContact">' . __('Actions' , 'idealien-rideshare') . '</th>';
					}
						
					$output .= '</tr>';
			
					//Repeat for each row of data
					 while ( $IRQuery->have_posts() ) : $IRQuery->the_post(); 

						$ID = get_the_ID();
						$output .= '<tr>';

						//Type
						$output .= '<td class="rideshareType">' . get_post_meta($ID, "idealien_rideshare_type", true) . '</td>';
						
						//Destination
						$output .= '<td class="rideshareEvent">';
						//Event for City, State
						if (get_post_meta($ID, "idealien_rideshare_event", true)) {
							$destinationOutput = get_post_meta($ID, "idealien_rideshare_event", true);
						} else {
							$destinationOutput = 	get_post_meta($ID, "idealien_rideshare_destinationCity", true) . ", " . 
								   get_post_meta($ID, "idealien_rideshare_destinationStateProv", true);
						}
						$output .= $destinationOutput;
						$output .='</td>';
						
						//Departure
						$departureOutput = get_post_meta($ID, "idealien_rideshare_departureCity", true) . ', ' . get_post_meta($ID, "idealien_rideshare_departureStateProv", true);
						$output .= '<td class="rideshareCity">' . $departureOutput . '</td>';
						
						//Spaces
						$spacesOutput = get_post_meta($ID, "idealien_rideshare_spaces", true);
						$output .= '<td class="rideshareSpaces">' . $spacesOutput . '</td>';
						
						//Departure Date
						$dateOutput = get_post_meta($ID, "idealien_rideshare_departureDate", true);
						$output .= '<td class="rideshareDDate">' . $dateOutput . '</td>';
						//$output .= '<td class="rideshareRDate">' . get_post_meta($ID, "idealien_rideshare_returnDate", true) . '</td>';
						
						//FUTURE: Return Date
						
						//Add Info
						$output .= '<td class="rideshareInfo">';
							$addInfoOutput = get_post_meta($ID, "idealien_rideshare_addInfo", true);
							if ($addInfoOutput) { $output .=  $addInfoOutput; } else { $output .= '&nbsp;'; }
						$output .= '</td>';
						
						
						//Contact Info
						$selected_rideshare_username = get_post_meta($ID, "idealien_rideshare_username", true);
						
						if($selected_rideshare_username) {
							if($current_user->user_login != $selected_rideshare_username) {
								//Viewer is not the creator of the rideshare
								$userData = get_user_by('login', $selected_rideshare_username);
								$name = $userData->display_name;
								$emailAddress = $userData->user_email;
							} else {
								//Viewer IS the creator of the rideshare
								$name = $current_user->display_name;
								$emailAddress = $current_user->user_email;
							}
										
						} else {
							//A non-registered user entry
							$name = get_post_meta($ID, "idealien_rideshare_name", true);
							$emailAddress = get_post_meta($ID, "idealien_rideshare_email", true);
						}
						
						
						switch ($contact) {
    						case 'email':
								$output .= '<td class="rideshareContact">';
								$output .= '<a href="mailto:' . antispambot($emailAddress) . '">' . $name . '</a>';							
								$output .= '</td>';
								break;
								
							case 'form':
								$output .= '<td class="rideshareContact">';
								$output .= '<a href="mailto:' . antispambot($emailAddress) . '">' . $name . '</a>';							
								$output .= '</td>';
								break;
								
								/* //RE-WRITE - Detect Delete / Contact should display
								$output .= '<td class="rideshareContact">';
									if(date($dateOutput) >= date("m/d/Y", time())) {
										/* if(IDEALIEN_RIDESHARE_COMMENTFORM_ID != "REPLACEME") {
											$output .= '<input type="button" value="Delete" id="rideshare_delete_' . $ID . '" ';
											$output .= 'onclick="rideshare_delete(\'' . $ID . '\', \'' . $current_user->user_login . '\')" />';$output .= do_shortcode('[gravityform id="' . IDEALIEN_RIDESHARE_COMMENTFORM_ID . '" title="false" description="false"]');
									} else { 
										//Generic implementation
										$name = get_post_meta($ID, "idealien_rideshare_name", true);
										$emailAddress = get_post_meta($ID, "idealien_rideshare_email", true);

										$output .= 'Name: ' . $name . '<br/>';
										$output .= 'Email: ' . antispambot($emailAddress) . '';
									}
									

								}
								$output .= '</td>'; */

								break;
								
							case 'buddypress':
								
								//confirm buddypress is active to send message
								if ($selected_rideshare_username && function_exists('bp_core_get_userid')) {
							
									//Which user generated rideshare
									$userID = bp_core_get_userid( $selected_rideshare_username );
									$bp_displayName=bp_core_get_user_displayname( $userID );
									$bp_userDomain = bp_core_get_user_domain( $userID );
							
									if( $current_user->user_login != $selected_rideshare_username ) {
										//Not being displayed on profile page or filtered for current signed-in user
										$output .= '<td class="rideshareContact">';
								
										//Create profile link
										$output .= '<a href=' . $bp_userDomain . '>' . $bp_displayName . '</a><br/>';
								
										//Button to display comment / connect form
										$output .= '<input type="button" value="Connect!" id="rideshare_' . $ID . '" ';
										$output .= 'onclick="rideshare_connect(\'' . $ID . '\', \'buddypress\', \'' . $selected_rideshare_username . '\', \'' . $current_user->user_login . '\', ';
										$output .= '\'' . $destinationOutput . '\', \'' . $departureOutput . '\', \'' . $dateOutput . '\', \'' . $spacesOutput . '\' )" />';
										$output .= '</td>';
									}
									
								} else {
									//Fallback to standard email link style
									$output .= '<td class="rideshareContact">';
									$output .= 'Name: ' . $name . '<br/>';
									$output .= 'Email: ' . antispambot($emailAddress) . '';	
								
								}
								break;
								
						
								
						}

						$output .= '</tr>';
	
					endwhile;
					
					$output .= '</table>';
					
				else:
					//Empty rideshare list
					$output .= '<p>' . __('There are no rideshares available at this time' , 'idealien-rideshare') . '.</p>';
					
				endif;
			
			break;
				
			case 'events':
				//FUTURE: Refactor this to match updated 'full' version logic, perhaps even incorporating into the output generation of columns.
				$terms = get_terms( 'idealien_rideshare_event', 'hide_empty=0&order=ASC' );
				
				if (isset($terms)) :
					
					$output = "";
					
					foreach ( $terms as $event ) {
					
						//Retrieve all rideshares by event
						$queryParameters = array(
							'post_type' => 'idealien_rideshare',
							'posts_per_page' => '-1',
							'orderby' => 'meta_value',
							'meta_key' => 'idealien_rideshare_event',
							'meta_value' => $event->name
						);
				
			
						$IRQuery = new WP_Query();
						$IRQuery->query($queryParameters);
						
						
						
						if ( $IRQuery->have_posts() ) :
				
							$output .= '<h2>' . $event->name . '</h2>';
							$output .= '<table id="rideshare" class="tablestripe">';
							$output .= '<tr>';
							$output .= '<th class="rideshareCity">' . __('Location' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareType">' . __('Type' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareSpaces">' . __('Spaces' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareDDate">' . __('Departure' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareRDate">' . __('Return' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareContact">' . __('Contact' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareInfo">' . __('Add. Info' , 'idealien-rideshare') . '</th>';
							$output .= '</tr>';
					
							 while ( $IRQuery->have_posts() ) : $IRQuery->the_post(); 
		
								$ID = get_the_ID();
								$output .= '<tr>';
								$output .= '<td class="rideshareCity">' . get_post_meta($ID, "idealien_rideshare_departureCity", true) . '</td>';
								$output .= '<td class="rideshareType">' . get_post_meta($ID, "idealien_rideshare_type", true) . '</td>';
								$output .= '<td class="rideshareSpaces">' . get_post_meta($ID, "idealien_rideshare_spaces", true) . '</td>';
								$output .= '<td class="rideshareDDate">' . get_post_meta($ID, "idealien_rideshare_departureDate", true) . '</td>';
								$output .= '<td class="rideshareRDate">' . get_post_meta($ID, "idealien_rideshare_returnDate", true) . '</td>';
								$output .= '<td class="rideshareContact">';
								
									$output .= '<span class="name">' . get_post_meta($ID, "idealien_rideshare_name", true) . '</span>';
									
									$output .= '<span class="email">';
										$emailAddress = get_post_meta($ID, "idealien_rideshare_email", true);
										$output .= '<a href="mailto:' . antispambot($emailAddress) . '">' . antispambot($emailAddress) . '</a>';
									$output .= '</span>';
									
									$phone = get_post_meta($ID, "idealien_rideshare_phone", true);
									if ($phone) { $output .=  '<span class="phone">' . get_post_meta($ID, "idealien_rideshare_phone", true) . '</span>'; }					
								
								$output .= '</td>';
								$output .= '<td class="rideshareInfo">';
									$addInfo = get_post_meta($ID, "idealien_rideshare_addInfo", true);
									if ($addInfo) { $output .=  $addInfo; } else { $output .= '&nbsp;'; }
								$output .= '</td>';
								//$output .= '<td>' . get_the_title() . '</td>';
								$output .= '</tr>';
			
							endwhile;
							
							$output .= '</table>';
							
						else: 
							$output .= '<h2>' . $event->name . '</h2>';
							$output .= '<p>There are no rideshares available for this event at this time.</p>';
							
						endif;
						
					}
				
				endif;
				
			break;
			
		}
		
	//Say goodnight Gracie
	return $output;
	}
	
	// Plugin activation to setup the default values when plugin activated for the first time.
  	static function activate() {
		
		//Register 
		idealien_rideshare::create_data_types();
		
		$version = get_option('idealien_rideshare_version');
		
		if($version == "") {
			//New installation - pre-load some fields
			wp_insert_term(__( 'Give', 'idealien-rideshare' ), 'idealien_rideshare_type');
			wp_insert_term(__( 'Get', 'idealien-rideshare' ), 'idealien_rideshare_type');
			
			wp_insert_term( __( 'Alabama', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'AL' ) );
			wp_insert_term( __( 'Alaska', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'AK' ) );
			wp_insert_term( __( 'Arizona', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'AZ' ) );
			wp_insert_term( __( 'Arkansas', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'AR' ) );
			wp_insert_term( __( 'California', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'CA' ) );
			wp_insert_term( __( 'Colorado', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'CO' ) );
			wp_insert_term( __( 'Connecticut', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'CT' ) );
			wp_insert_term( __( 'Delaware', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'DE' ) );
			wp_insert_term( __( 'Florida', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'FL' ) );
			wp_insert_term( __( 'Georgia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'GA' ) );
			wp_insert_term( __( 'Hawaii', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'HI' ) );
			wp_insert_term( __( 'Idaho', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'ID' ) );
			wp_insert_term( __( 'Illinois', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'IL' ) );
			wp_insert_term( __( 'Indiana', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'IN' ) );
			wp_insert_term( __( 'Iowa', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'IA' ) );
			wp_insert_term( __( 'Kansas', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'KS' ) );
			wp_insert_term( __( 'Kentucky', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'KY' ) );
			wp_insert_term( __( 'Louisiana', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'LA' ) );
			wp_insert_term( __( 'Maine', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'ME' ) );
			wp_insert_term( __( 'Maryland', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MD' ) );
			wp_insert_term( __( 'Massachusetts', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MA' ) );
			wp_insert_term( __( 'Michigan', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MI' ) );
			wp_insert_term( __( 'Minnesota', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MN' ) );
			wp_insert_term( __( 'Mississippi', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MS' ) );
			wp_insert_term( __( 'Missouri', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MO' ) );
			wp_insert_term( __( 'Montana', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MT' ) );
			wp_insert_term( __( 'Nebraska', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NE' ) );
			wp_insert_term( __( 'Nevada', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NV' ) );
			wp_insert_term( __( 'New Hampshire', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NH' ) );
			wp_insert_term( __( 'New Jersey', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NJ' ) );
			wp_insert_term( __( 'New Mexico', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NM' ) );
			wp_insert_term( __( 'New York', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NY' ) );
			wp_insert_term( __( 'North Carolina', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NC' ) );
			wp_insert_term( __( 'North Dakota', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'ND' ) );
			wp_insert_term( __( 'Ohio', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'OH' ) );
			wp_insert_term( __( 'Oklahoma', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'OK' ) );
			wp_insert_term( __( 'Oregon', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'OR' ) );
			wp_insert_term( __( 'Pennsylvania', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'PA' ) );
			wp_insert_term( __( 'Rhode Island', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'RI' ) );
			wp_insert_term( __( 'South Carolina', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'SC' ) );
			wp_insert_term( __( 'South Dakota', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'SD' ) );
			wp_insert_term( __( 'Tennesse', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'TN' ) );
			wp_insert_term( __( 'Texas', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'TX' ) );
			wp_insert_term( __( 'Utah', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'UT' ) );
			wp_insert_term( __( 'Vermont', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'VT' ) );
			wp_insert_term( __( 'Verginia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'VA' ) );
			wp_insert_term( __( 'Washington', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'WA' ) );
			wp_insert_term( __( 'West Virginia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'WV' ) );
			wp_insert_term( __( 'Wisconsin', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'WI' ) );
			wp_insert_term( __( 'Wyoming', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'WY' ) );
			
			wp_insert_term( __( 'Alberta', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'AB' ) );
			wp_insert_term( __( 'British Columnbia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'BC' ) );
			wp_insert_term( __( 'Manitoba', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'MB' ) );
			wp_insert_term( __( 'New Brunswick', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NB' ) );
			wp_insert_term( __( 'Newfoundland', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NL' ) );
			wp_insert_term( __( 'Nova Scotia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'NS' ) );
			wp_insert_term( __( 'Ontario', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'ON' ) );
			wp_insert_term( __( 'Prince Edward Island', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'PE' ) );
			wp_insert_term( __( 'Quebec', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'QB' ) );
			wp_insert_term( __( 'Saskatchewan', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'SK' ) );
			
			wp_insert_term(__( 'Burning Man', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'Occupy Wall Street', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'WordCamp Toronto', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'Coachella', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'New York City Marathon', 'idealien-rideshare' ), 'idealien_rideshare_event');
			
			wp_insert_term(__( 'Active', 'idealien-rideshare' ), 'idealien_rideshare_status');
			wp_insert_term(__( 'Connected', 'idealien-rideshare' ), 'idealien_rideshare_status');
			wp_insert_term(__( 'Deleted', 'idealien-rideshare' ), 'idealien_rideshare_status');

		}
		
		//Update version number in DB
		update_option('idealien_rideshare_version', IDEALIEN_VERSION);
		
	}
  
  	// Deactivating the plugin
  	static function deactivate() {
		//FUTURE: Delete CPT is too risky - what else shoudl happen?
	}
  
  	// FUTURE: This is not yet called in any meaningful way.
  	static function uninstall() {

		if (is_multisite()) {
    		global $wpdb;
    		$blogs = $wpdb->get_results("SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A);
    		if ($blogs) {
        		foreach($blogs as $blog) {
            		switch_to_blog($blog['blog_id']);
					
					idealien_rideshare::uninstall_heavy_lifting();
					
        			restore_current_blog();
				}
    		}
		} else {
    		
			idealien_rideshare::uninstall_heavy_lifting();
		}
	}
	
	static function uninstall_heavy_lifting() {
		//FUTURE: Extend this to delete all taxonomies
		//Delete options
		delete_option('idealien_rideshare_version');
		
		//Delete all terms in the taxonomies which are rideshare specific.
		$taxonomies = array('idealien_rideshare_event', 'idealien_rideshare_city', 'idealien_rideshare_type');
		
		foreach ( $taxonomies as $taxonomy ) {
			
			$terms = get_terms( $taxonomy, 'hide_empty=0&order=ASC' );
	
			if ($terms != null) :

				foreach ( $terms as $term ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
				
			endif;
		}
		
		//Delete all posts of type idealien_rideshare
		$queryParameters = array(
		'post_type' => 'idealien_rideshare',
		'posts_per_page' => '-1',
		);

		$IRQuery = new WP_Query();
		$IRQuery->query($queryParameters);
	
		if ( $IRQuery->have_posts() ) : while ( $IRQuery->have_posts() ) : $IRQuery->the_post(); 
	
			wp_delete_post( get_the_ID(), 'false' ); 
			
		endwhile;
		endif;	
	} 
	

	//Instantiate the Constructor
	static function initialize() {
		global $idealien_rideshare;
		$idealien_rideshare = new idealien_rideshare();
	}
	

}

// Initiate the plugin
add_action("init", "idealien_rideshare::initialize");

register_activation_hook(__FILE__, 'idealien_rideshare::activate');
register_deactivation_hook(__FILE__, 'idealien_rideshare::deactivate');

//FUTURE: Uninstallation routine
//register_uninstall_hook(__FILE__, 'idealien_rideshare::uninstall');
//Uninstall has not been activated because it does not properly activate.
//If you want to do a proper clean-out of data:
//	-activate the plugin
//	-delete all of the terms in the taxonomies within the rideshares menu
//	-delete all rideshare custom posts
// 	-de-activate and delete the plugin

?>