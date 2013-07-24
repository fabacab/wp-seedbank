<?php
/*
    Plugin Name: WP-SeedBank
    Plugin URI: http://hummingbirdproject.org/wp-seedbank/
    Description: Add seed exchange post type to turn a WordPress install into a seed bank! :D
    Author: Cleveland GiveCamp Developers (meitar@maymay.net)
    Version: 0.1
    Author URI: http://hummingbirdproject.org/wp-seedbank/#contributors
	License: GPL
	Requires at least: 3.5.2
	Stable tag: 0.1
*/


//Modify the following two variables to identify the ID for the comment and delete forms
define ('WP_SEEDBANK_COMMENTFORM_ID' , "REPLACEME");
define ('WP_SEEDBANK_DELETEFORM_ID' , "REPLACEME");


//Do not modify anything else below 
define ('WP_SEEDBANK_PATH', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) );
define ('WP_SEEDBANK_VERSION', '0.1');

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

        wp_register_script('seedbank_exchange', IDEALIEN_RIDESHARE_PATH . 'seedbank_exchange.js', array('jquery') );
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
					$choices[] = array('text' => 'Add New Common Name', 'value' => 'Add New Event');
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
						$message =  $user->display_name . " wants to connect with you about the following seed exchange.\r\n";
						$message .= "Seed exchange: " . $ID . "\r\n";
						$message .= "Type: " . get_post_meta($ID, "idealien_rideshare_type", true) . "\r\n";
						$message .= "Date: " . get_post_meta($ID, "idealien_rideshare_departureDate", true) . "\r\n";
						$message .= "Quantity: " . get_post_meta($ID, "idealien_rideshare_spaces", true) . "\r\n";
						$message .= "Seed Expiry Date: " . get_post_meta($ID, "idealien_rideshare_departureCity", true) . ", " . get_post_meta($ID, "idealien_rideshare_departureStateProv", true) . "\r\n";
						
						//Display either City/Stave or Event - whichever is populated
						if (get_post_meta($ID, "idealien_rideshare_event", true)) {
							$message .= "Genus/species: " . get_post_meta($ID, "idealien_rideshare_event", true) . "\r\n";
						} else {
							$message .= "Genus/species: " . get_post_meta($ID, "idealien_rideshare_destinationCity", true) . ", " . get_post_meta($ID, "idealien_rideshare_destinationStateProv", true) . "\r\n";
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
					'name' => __( 'Seed Exchanges', 'idealien-rideshare' ),
					'singular_label' => __( 'Seed Exchange', 'idealien-rideshare' ),
					'add_new' => __( 'Add Seed Exchange', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add Seed Exchange', 'idealien-rideshare' ),
					'edit' => __( 'Edit Seed Exchange', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Seed Exchange', 'idealien-rideshare' ),
					'new_item' => __( 'New Seed Exchange', 'idealien-rideshare' ),
					'view' => __( 'View Seed Exchange', 'idealien-rideshare' ),
					'view_item' => __( 'View Seed Exchange', 'idealien-rideshare' ),
					'search' => __( 'Search Seed Exchanges', 'idealien-rideshare' ),
					'not_found' => __( 'No Seed Exchanges found', 'idealien-rideshare' ),
					'not_found_in_trash' => __( 'No Seed Exchanges found in trash', 'idealien-rideshare' )
				),
				'supports' => array('title', 'custom-fields'),
				'rewrite' => array('slug' => 'rideshare'),
				'public' => true,
				'description' => __( 'Seedbank Seed Exchanges', 'idealien-rideshare' ),
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
				'label' => 'Exchange Types'
			)
		);
		
		register_taxonomy('idealien_rideshare_event', 'idealien_rideshare', array(
				'labels' => array(
					'name' => __( 'Common Names', 'idealien-rideshare' ),
					'singular_name' => __( 'Common Name', 'idealien-rideshare' ),
					'all_items' => __( 'All Common Names', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Common Name', 'idealien-rideshare' ),
					'update_item' => __( 'Update Common Name', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add New Common Name', 'idealien-rideshare' ),
					'new_item_name' => __( 'New Common Name', 'idealien-rideshare' )
				),
				'hierarchical' => false,
				'label' => __( 'Common Names', 'idealien-rideshare' )
			)
		);
		
		register_taxonomy('idealien_rideshare_state_prov', 'idealien_rideshare', array(
				'labels' => array(
					'name' => __( 'Seed Genera', 'idealien-rideshare' ),
					'singular_name' => __( 'Genus', 'idealien-rideshare' ),
					'all_items' => __( 'All Genera', 'idealien-rideshare' ),
					'edit_item' => __( 'Edit Genera', 'idealien-rideshare' ),
					'update_item' => __( 'Update Genus', 'idealien-rideshare' ),
					'add_new_item' => __( 'Add New Genus', 'idealien-rideshare' ),
					'new_item_name' => __( 'New Genus Name', 'idealien-rideshare' )
				),
				'hierarchical' => false,
				'label' => __( 'Seed Genera', 'idealien-rideshare' )
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
				'label' => __( 'Exchange Status', 'idealien-rideshare' )
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
		add_meta_box("idealienRideshareDetails-meta", "Seed Exchange Details", array(&$this, "meta_options_details"), "idealien_rideshare", "normal", "high");
		
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
        <span class="description">Choose <code>Active</code> if you're still seeking an exchange, <code>Connected</code> if you're in contact with another member about it, and <code>Deleted</code> if for any reason you are no longer offering this exchange.</span>
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
        <span class="description">Choose <code>Get</code> if you're requesting seeds, <code>Give</code> if you're freely offering to share your own, <code>Sell</code> if you'd like money in return for your seeds, or <code>Trade</code> if you'd like to swap seeds with someone else.</span>
	</p>
    
	
    <p>
		<label class="rideshare">Common Name:</label>
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
        <span class="description">What general variety of seed is this?</span>
	</p>
    
    <fieldset><legend>Seed variety</legend>
        <p>
            <label class="rideshare"><?php _e('Species' , 'idealien-rideshare'); ?>:</label>
            <input name="idealien_rideshare_destinationCity" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_destinationCity; ?>" />
            <span class="description">What specific species of seed is this?</span>
        </p>
        <p>
            <label class="rideshare"><?php _e('Genus' , 'idealien-rideshare'); ?>:</label>
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
            <span class="description">What specific genus of seed is this?</span>
        </p>
    </fieldset>

<!--
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
-->
    
    <p>
		<label class="rideshare"><?php _e('Seed Expiry Date' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_departureDate" class="rideshare_inputLine datepicker" value="<?php echo $idealien_rideshare_departureDate;?>" />
        <span class="description">If these seeds are in a pack, list the expiration date here.</span>
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Exchange expiry date' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_returnDate" class="rideshare_inputLine datepicker" value="<?php echo $idealien_rideshare_returnDate;?>" />
        <span class="description">If you don't get a response by this date, your request will automatically close.</span>
	</p>
    <div class="clear"></div>
    
    <p>
		<label class="rideshare"><?php _e('Quantity' , 'idealien-rideshare'); ?>:</label>
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
		<span class="description">If different from your member name, enter a contact person's name for this exchange here.</span>
	</p>
    
    <p>
		<label class="rideshare"><?php _e('Email' , 'idealien-rideshare'); ?>:</label>
		<input name="idealien_rideshare_email" class="rideshare_inputLine" value="<?php echo $idealien_rideshare_email;?>" /><br />
		<span class="description">Enter your email address if you'd like other members to contact you privately about this exchange.</span>
	</p> 
    
    
     <p>
		<label class="rideshare"><?php _e('Additional Info' , 'idealien-rideshare'); ?>:</label>
        <textarea name="idealien_rideshare_addInfo" class="rideshare_inputBox"><?php echo $idealien_rideshare_addInfo;?></textarea><br />
		<span class="description">Enter any additional details about this exchange here. For instance, if this is a <code>Trade</code>, describe what seeds you most hope to receive in exchange.</span>
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
                // Copy "additional info" into the post body so it becomes searchable with WordPress's default searches.
                if ('idealien_rideshare_addInfo' === $key) {
                    $post->post_content = $value;
                    $post->comment_status = 'open'; // Comments should be allowed.
                    // Unhook so we avoid infinite loop.
                    // See: http://codex.wordpress.org/Plugin_API/Action_Reference/save_post#Avoiding_infinite_loops
                    remove_action('wp_insert_post', array(&$this, 'wp_insert_post'));
                    $seedbank_post_id = wp_insert_post($post);
                    add_action('wp_insert_post', array(&$this, 'wp_insert_post'));
                }
			}
		}
	}
	
	
	
	
	// Display custom columns in the admin view for the custom post type
	function edit_columns($columns)
	{
		$columns = array(
			"cb" => "<input type=\"checkbox\" />",
			"title" => __( 'Seed Exchange', 'idealien-rideshare' ),
			"idealien_rideshare_status" => __( 'Status', 'idealien-rideshare' ),
			"idealien_rideshare_type" => __( 'Type', 'idealien-rideshare' ),
			"idealien_rideshare_departureDate" => __( 'Seed Expiry Date', 'idealien-rideshare' ),
			"idealien_rideshare_spaces" => __( 'Quantity', 'idealien-rideshare' ),
			"idealien_rideshare_destination" => __( 'Common Name', 'idealien-rideshare' ),
			//"idealien_rideshare_departure" => __( 'Departure', 'idealien-rideshare' )
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
					$output .= '<p>' . __('There are no seed exchanges available at this time' , 'idealien-rideshare') . '.</p>';
					
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
							$output .= '<th class="rideshareSpaces">' . __('Quantity' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareDDate">' . __('Seed Expiry Date' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareRDate">' . __('Expiry Date' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareContact">' . __('Contact' , 'idealien-rideshare') . '</th>';
							$output .= '<th class="rideshareInfo">' . __('Additional Info' , 'idealien-rideshare') . '</th>';
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
							$output .= "<p>There are no exchanges available for {$event->name} seeds at this time.</p>";
							
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
		
        // DEV NOTE: For now, forcibly assume we're always doing a new install when we "activate."
		$version = ""; //get_option('idealien_rideshare_version');
		
		if($version == "") {
			//New installation - pre-load some fields
			wp_insert_term(__( 'Give', 'idealien-rideshare' ), 'idealien_rideshare_type', array('description' => 'Exchanges offering free seeds being given away.'));
			wp_insert_term(__( 'Get', 'idealien-rideshare' ), 'idealien_rideshare_type', array('description' => 'Exchanges requesting seeds of a variety not already listed.'));
			wp_insert_term(__( 'Sell', 'idealien-rideshare' ), 'idealien_rideshare_type', array('description' => 'Exchanges offering seeds for money.'));
			wp_insert_term(__( 'Trade', 'idealien-rideshare' ), 'idealien_rideshare_type', array('description' => 'Exchanges offering seeds for other seeds.'));
			
            wp_insert_term( __( 'Abelmoschus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'abelmoschus' ) );
            wp_insert_term( __( 'Agastache', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'agastache' ) );
            wp_insert_term( __( 'Allium', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'allium' ) );
            wp_insert_term( __( 'Amaranthus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'amaranthus' ) );
            wp_insert_term( __( 'Anagallis', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'anagallis' ) );
            wp_insert_term( __( 'Anethum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'anethum' ) );
            wp_insert_term( __( 'Anthenum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'anthenum' ) );
            wp_insert_term( __( 'Antirrhinum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'antirrhinum' ) );
            wp_insert_term( __( 'Apium', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'apium' ) );
            wp_insert_term( __( 'Asclepias', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'asclepias' ) );
            wp_insert_term( __( 'Basella', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'basella' ) );
            wp_insert_term( __( 'Beta', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'beta' ) );
            wp_insert_term( __( 'Brassica', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'brassica' ) );
            wp_insert_term( __( 'Calendula', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'calendula' ) );
            wp_insert_term( __( 'Capsicum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'capsicum' ) );
            wp_insert_term( __( 'Cardiospermum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cardiospermum' ) );
            wp_insert_term( __( 'Centaurea', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'centaurea' ) );
            wp_insert_term( __( 'Chrysanthemum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'chrysanthemum' ) );
            wp_insert_term( __( 'Cichorium', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cichorium' ) );
            wp_insert_term( __( 'Citrullus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'citrullus' ) );
            wp_insert_term( __( 'Cleome', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cleome' ) );
            wp_insert_term( __( 'Cobaea', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cobaea' ) );
            wp_insert_term( __( 'Consolida', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'consolida' ) );
            wp_insert_term( __( 'Convolvulus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'convolvulus' ) );
            wp_insert_term( __( 'Coreopsis', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'coreopsis' ) );
            wp_insert_term( __( 'Coriandrum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'coriandrum' ) );
            wp_insert_term( __( 'Cosmos', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cosmos' ) );
            wp_insert_term( __( 'Cucumis', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cucumis' ) );
            wp_insert_term( __( 'Cucurbita', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'cucurbita' ) );
            wp_insert_term( __( 'Dalea', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'dalea' ) );
            wp_insert_term( __( 'Daucus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'daucus' ) );
            wp_insert_term( __( 'Diplotaxis', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'diplotaxis' ) );
            wp_insert_term( __( 'Dolichos', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'dolichos' ) );
            wp_insert_term( __( 'Echinacea', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'echinacea' ) );
            wp_insert_term( __( 'Eruca', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'eruca' ) );
            wp_insert_term( __( 'Eschscholzia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'eschscholzia' ) );
            wp_insert_term( __( 'Foeniculum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'foeniculum' ) );
            wp_insert_term( __( 'Fragaria', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'fragaria' ) );
            wp_insert_term( __( 'Gaillardia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'gaillardia' ) );
            wp_insert_term( __( 'Glycine', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'glycine' ) );
            wp_insert_term( __( 'Helianthus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'helianthus' ) );
            wp_insert_term( __( 'Ipomoea', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'ipomoea' ) );
            wp_insert_term( __( 'Koeleria', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'koeleria' ) );
            wp_insert_term( __( 'Lactuca', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'lactuca' ) );
            wp_insert_term( __( 'Lagenaria', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'lagenaria' ) );
            wp_insert_term( __( 'Lathyrus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'lathyrus' ) );
            wp_insert_term( __( 'Lupinus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'lupinus' ) );
            wp_insert_term( __( 'Lycopersicon', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'lycopersicon' ) );
            wp_insert_term( __( 'Malope', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'malope' ) );
            wp_insert_term( __( 'Matricaria', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'matricaria' ) );
            wp_insert_term( __( 'Mentha', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'mentha' ) );
            wp_insert_term( __( 'Mirabilis', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'mirabilis' ) );
            wp_insert_term( __( 'Nigella', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'nigella' ) );
            wp_insert_term( __( 'Ocimum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'ocimum' ) );
            wp_insert_term( __( 'Origanum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'origanum' ) );
            wp_insert_term( __( 'Papaver', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'papaver' ) );
            wp_insert_term( __( 'Passiflora', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'passiflora' ) );
            wp_insert_term( __( 'Penstemon', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'penstemon' ) );
            wp_insert_term( __( 'Petrolselinum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'petrolselinum' ) );
            wp_insert_term( __( 'Phaseolus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'phaseolus' ) );
            wp_insert_term( __( 'Physalis', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'physalis' ) );
            wp_insert_term( __( 'Pisum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'pisum' ) );
            wp_insert_term( __( 'Poterium', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'poterium' ) );
            wp_insert_term( __( 'Raphanus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'raphanus' ) );
            wp_insert_term( __( 'Rosmarinus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'rosmarinus' ) );
            wp_insert_term( __( 'Rudbeckia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'rudbeckia' ) );
            wp_insert_term( __( 'Salvia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'salvia' ) );
            wp_insert_term( __( 'Scorpiurus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'scorpiurus' ) );
            wp_insert_term( __( 'Solanum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'solanum' ) );
            wp_insert_term( __( 'Spinachia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'spinachia' ) );
            wp_insert_term( __( 'Tagetes', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'tagetes' ) );
            wp_insert_term( __( 'Thunbergia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'thunbergia' ) );
            wp_insert_term( __( 'Thymus', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'thymus' ) );
            wp_insert_term( __( 'Triticum ', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'triticum ' ) );
            wp_insert_term( __( 'Tropaeolum', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'tropaeolum' ) );
            wp_insert_term( __( 'Zea', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'zea' ) );
            wp_insert_term( __( 'Zinnia', 'idealien-rideshare' ), 'idealien_rideshare_state_prov', array( 'slug' => 'zinnia' ) );
			
			wp_insert_term(__( 'Bean', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'Lettuce', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'Pepper', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'Pumpkin', 'idealien-rideshare' ), 'idealien_rideshare_event');
			wp_insert_term(__( 'Tomato', 'idealien-rideshare' ), 'idealien_rideshare_event');
			
			wp_insert_term(__( 'Active', 'idealien-rideshare' ), 'idealien_rideshare_status', array('description' => 'New/open seed exchange requests or offers.'));
			wp_insert_term(__( 'Connected', 'idealien-rideshare' ), 'idealien_rideshare_status', array('description' => 'Exchanges currently in negotiation.'));
			wp_insert_term(__( 'Deleted', 'idealien-rideshare' ), 'idealien_rideshare_status', array('description' => 'Expired or completed seed exchanges.'));

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
