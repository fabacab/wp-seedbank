<?php
/*
    Plugin Name: WP-SeedBank
    Plugin URI: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/
    Description: Add a seed exchange post type to turn your WordPress install into a community seed bank! :D
    Author: Cleveland GiveCamp Developers
    Version: 0.2
    Author URI: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/#authors
    License: GPL
    Requires at least: 3.5.2
    Stable tag: 0.2
*/

//Modify the following two variables to identify the ID for the comment and delete forms
define ('WP_SEEDBANK_COMMENTFORM_ID' , "REPLACEME");
define ('WP_SEEDBANK_DELETEFORM_ID' , "REPLACEME");

//Do not modify anything else below 
define ('WP_SEEDBANK_PATH', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) );
define ('WP_SEEDBANK_VERSION', '0.2');

class WP_Seedbank {

	//Define all meta data fields
	var $meta_fields = array(
		"wp_seedbank_type",
		"wp_seedbank_quantity",
		"wp_seedbank_common_name",
		"wp_seedbank_departureCity",
		"wp_seedbank_departureStateProv", 
		"wp_seedbank_seed_expiry_date", 
		"wp_seedbank_destinationCity", 
		"wp_seedbank_destinationStateProv", 
		"wp_seedbank_exchange_expiry_date",
		"wp_seedbank_unit",
		"wp_seedbank_name",
		"wp_seedbank_email",
		"wp_seedbank_phone", 
		"wp_seedbank_addInfo",
		"wp_seedbank_status"
	);
	
	function WP_Seedbank() {
		
		//Register CPT, Taxonomies, etc
		WP_Seedbank::create_data_types();
		
		//Setup and customize columns of data on admin list view
		add_filter("manage_edit-wp_seedbank_columns", array(&$this, "edit_columns"));
		add_action("manage_posts_custom_column", array(&$this, "custom_columns"));		
		add_filter("manage_edit-wp_seedbank_sortable_columns", array(&$this, "register_sortable"));
		add_filter("request", array(&$this, "wp_seedbank_status_column_orderby"));
		add_filter("request", array(&$this, "wp_seedbank_type_column_orderby"));
		add_filter("request", array(&$this, "wp_seedbank_quantity_column_orderby"));
		
		//FUTURE: Date columns sortable
		//add_filter("request", array(&$this, "wp_seedbank_seed_expiry_date_column_orderby"));
		
		//Setup function to instantiate meta boxes and scripts on admin page
		add_action("admin_init", array(&$this, "admin_init"));
		
        // Create new page for import CSV function.
        // We call a whole new class so we can begin to handle some of this legacy stuff. :(
        add_action('admin_menu', array('WP_SeedbankAdmin', 'registerAdminMenus'));

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

        wp_register_script('seedbank_exchange', WP_SEEDBANK_PATH . 'seedbank_exchange.js', array('jquery') );
        wp_enqueue_script('seedbank_exchange');
		
		//Ensure all 3 forms are executed if being called from a buddyPress page
		if(function_exists('bp_core_get_userid')) {
			add_action( "init", array(&$this, "bbg_switch_gf_hooks"), 99 );
		}
		
	//End wp_seedbank constructor function
	}
	
	//Ensure all 3 forms are executed if being called from a buddyPress page
	//Called From: wp_seedbank constructor
	function bbg_switch_gf_hooks() {
    	remove_action('wp',  array('RGForms', 'maybe_process_form'), 9);
		add_action( 'bp_actions', array( 'RGForms', 'maybe_process_form' ), 1 );
	}


	//Setup filters for the rideshare Gravity Form to populate from taxonomies into custom fields.
	//Called From: gform_pre_render filter
	function populate_rideshare_data($form){
    
		//Only execute for the primary rideshare form
		$rideshareFormType = strpos($form['cssClass'], 'wp_seedbank_gf');

		if( $rideshareFormType === false)
			return $form;
		
		//Pre-populate drop-downs		
    	foreach($form['fields'] as $key=>&$field) {
			$terms = null;
			
			//Match against fields that have specific custom field names
			switch ($field['postCustomFieldName']) {
    			
				//each case buidls up the list of $terms to display
				case 'wp_seedbank_status':
        			$terms = get_terms( 'wp_seedbank_status', array(
 						'hide_empty' => 0
 					) );
					$choices = array(array('text' => ' ', 'value' => ' '));
					break;
					
				case 'wp_seedbank_type':
        			$terms = get_terms( 'wp_seedbank_type', array(
 						'hide_empty' => 0
 					) );
					$choices = array(array('text' => ' ', 'value' => ' '));
					break;
					
				case 'wp_seedbank_common_name':
        			$terms = get_terms( 'wp_seedbank_common_name', array(
 						'hide_empty' => 0
 					) );
					$choices = array(array('text' => 'Select', 'value' => ' '));
					$choices[] = array('text' => 'Add New Common Name', 'value' => 'Add New Event');
					break;
					
				case 'wp_seedbank_destinationStateProv':
				case 'wp_seedbank_departureStateProv':
					$terms = get_terms( 'wp_seedbank_genus', array(
						'hide_empty' => 0
 					) );
					$choices = array(array('text' => 'Select', 'value' => ' '));
					break;
				
				case 'wp_seedbank_name':
				case 'wp_seedbank_email':
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
		$rideshareFormType = strpos($form['cssClass'], 'wp_seedbankComments');
		
		if( $rideshareFormType !== false) {

				//Retrieve rideshare details
				$queryParameters = array(
					'post_type' => 'wp_seedbank',
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
						$message .= "Type: " . get_post_meta($ID, "wp_seedbank_type", true) . "\r\n";
						$message .= "Date: " . get_post_meta($ID, "wp_seedbank_seed_expiry_date", true) . "\r\n";
						$message .= "Quantity: " . get_post_meta($ID, "wp_seedbank_quantity", true) . "\r\n";
						$message .= "Seed Expiry Date: " . get_post_meta($ID, "wp_seedbank_departureCity", true) . ", " . get_post_meta($ID, "wp_seedbank_departureStateProv", true) . "\r\n";
						
						//Display either City/Stave or Event - whichever is populated
						if (get_post_meta($ID, "wp_seedbank_common_name", true)) {
							$message .= "Genus/species: " . get_post_meta($ID, "wp_seedbank_common_name", true) . "\r\n";
						} else {
							$message .= "Genus/species: " . get_post_meta($ID, "wp_seedbank_destinationCity", true) . ", " . get_post_meta($ID, "wp_seedbank_destinationStateProv", true) . "\r\n";
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
		
		$rideshareFormType = strpos($form['cssClass'], 'wp_seedbankDelete');
		
		//Only execute when dealing with the Delete form
		if( $rideshareFormType !== false) {
			update_post_meta($entry[14], 'wp_seedbank_status', 'Deleted');

		}
	} 


	//Custom validation for primary rideshare form based on css identifier
	//Called From: gform_validation filter
	function validate_rideshare($validation_result){
		
		//Ensure this validation only fires on the primary form
		$form = $validation_result["form"];
		$rideshareFormType = strpos($form['cssClass'], 'wp_seedbank_gf');
		if( $rideshareFormType === false )
			return $validation_result;

		//Validation State: City Mode defined in form
		if( $_POST["input_27"] == 'City' ) {
			
			//Loop through custom field fields for appropriate validation		
    		foreach($form['fields'] as $key=>&$field) {
				
				switch ($field['postCustomFieldName']) {
    				
					//Destination City is required
					case 'wp_seedbank_destinationCity':
						if( !$_POST["input_25"] ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						} 
					break; 
					
					//Destination State / Prov is required
					case 'wp_seedbank_destinationStateProv':
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
    				
					case 'wp_seedbank_common_name':
						
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
    				
					case 'wp_seedbank_name':
			
						//Name is required
						if( !$_POST["input_30"] ) {
							$validation_result['is_valid'] = false;
							$field['failed_validation'] = true;
							$field['validation_message'] = 'This field is required.';
						}
					break;
					
					//Email is required	
					case 'wp_seedbank_email':
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
		$rideshareFormType = strpos($form['cssClass'], 'wp_seedbank_gf');
		
		if( $rideshareFormType === false )
			return $form;

		//If Add New Event text field has a value - create the term for it and force the drop-down to new value.
		if( $_POST["input_29"] ) {
			$newEvent = preg_replace( '/[^a-z]/i', "", $_POST["input_29"]);
			wp_insert_term( $newEvent , 'wp_seedbank_common_name');
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
			
		//Different title format based on common_name / citystate mode
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
		register_post_type('wp_seedbank',
			array(
				'labels' => array(
					'name' => __( 'Seed Exchanges', 'wp-seedbank' ),
					'singular_label' => __( 'Seed Exchange', 'wp-seedbank' ),
					'add_new' => __( 'Add Seed Exchange', 'wp-seedbank' ),
					'add_new_item' => __( 'Add Seed Exchange', 'wp-seedbank' ),
					'edit' => __( 'Edit Seed Exchange', 'wp-seedbank' ),
					'edit_item' => __( 'Edit Seed Exchange', 'wp-seedbank' ),
					'new_item' => __( 'New Seed Exchange', 'wp-seedbank' ),
					'view' => __( 'View Seed Exchange', 'wp-seedbank' ),
					'view_item' => __( 'View Seed Exchange', 'wp-seedbank' ),
					'search' => __( 'Search Seed Exchanges', 'wp-seedbank' ),
					'not_found' => __( 'No Seed Exchanges found', 'wp-seedbank' ),
					'not_found_in_trash' => __( 'No Seed Exchanges found in trash', 'wp-seedbank' )
				),
				'supports' => array('title', 'custom-fields'),
				'rewrite' => array('slug' => 'rideshare'),
				'public' => true,
				'description' => __( 'Seedbank Seed Exchanges', 'wp-seedbank' ),
				'menu_icon' => WP_SEEDBANK_PATH . 'seedexchange_icon.png'
			)
		);
		
		register_taxonomy('wp_seedbank_type', 'wp_seedbank', array(
				'labels' => array(
					'name' => __( 'Exchange Types', 'wp-seedbank' ),
					'singular_name' => __( 'Exchange Type', 'wp-seedbank' ),
					'all_items' => __( 'All Exchange Types', 'wp-seedbank' ),
					'edit_item' => __( 'Edit Exchange Type', 'wp-seedbank' ),
					'update_item' => __( 'Update Exchange Type', 'wp-seedbank' ),
					'add_new_item' => __( 'Add New Exchange Type', 'wp-seedbank' ),
					'new_item_name' => __( 'New Exchange Type Name', 'wp-seedbank' )
				),
				'hierarchical' => false,
				'label' => 'Exchange Types'
			)
		);
		
		register_taxonomy('wp_seedbank_common_name', 'wp_seedbank', array(
				'labels' => array(
					'name' => __( 'Common Names', 'wp-seedbank' ),
					'singular_name' => __( 'Common Name', 'wp-seedbank' ),
					'all_items' => __( 'All Common Names', 'wp-seedbank' ),
					'edit_item' => __( 'Edit Common Name', 'wp-seedbank' ),
					'update_item' => __( 'Update Common Name', 'wp-seedbank' ),
					'add_new_item' => __( 'Add New Common Name', 'wp-seedbank' ),
					'new_item_name' => __( 'New Common Name', 'wp-seedbank' )
				),
				'hierarchical' => false,
				'label' => __( 'Common Names', 'wp-seedbank' )
			)
		);
		
		register_taxonomy('wp_seedbank_genus', 'wp_seedbank', array(
				'labels' => array(
					'name' => __( 'Seed Genera', 'wp-seedbank' ),
					'singular_name' => __( 'Genus', 'wp-seedbank' ),
					'all_items' => __( 'All Genera', 'wp-seedbank' ),
					'edit_item' => __( 'Edit Genera', 'wp-seedbank' ),
					'update_item' => __( 'Update Genus', 'wp-seedbank' ),
					'add_new_item' => __( 'Add New Genus', 'wp-seedbank' ),
					'new_item_name' => __( 'New Genus Name', 'wp-seedbank' )
				),
				'hierarchical' => false,
				'label' => __( 'Seed Genera', 'wp-seedbank' )
			)
		);
		
		register_taxonomy('wp_seedbank_status', 'wp_seedbank', array(
				'labels' => array(
					'name' => __( 'Exchange Statuses', 'wp-seedbank' ),
					'singular_name' => __( 'Exchange Status', 'wp-seedbank' ),
					'all_items' => __( 'All Exchange Statuses', 'wp-seedbank' ),
					'edit_item' => __( 'Edit Exchange Status', 'wp-seedbank' ),
					'update_item' => __( 'Update Exchange Status', 'wp-seedbank' ),
					'add_new_item' => __( 'Add New Exchange Status', 'wp-seedbank' ),
					'new_item_name' => __( 'New Exchange Status Name', 'wp-seedbank' )
				),
				'hierarchical' => false,
				'label' => __( 'Exchange Status', 'wp-seedbank' )
			)
		);
		
		// instruction to only load shortcode and front-end CSS if it is not the admin area
		if ( !is_admin() ) { 
			add_shortcode('ridesharelist', array('wp_seedbank','wp_seedbank_shortcode'));
			add_action( "wp_print_styles", array($this, 'enqueue_display_styles') );
		}
	}
	
	//Setup function to instantiate meta boxes and scripts on admin page
	function admin_init() 
	{
		// Custom meta boxes for the edit rideshare screen
		add_meta_box("idealienRideshareDetails-meta", "Seed Exchange Details", array(&$this, "meta_options_details"), "wp_seedbank", "normal", "high");
		
		//Remove meta boxes that are added by default with taxonomy
		remove_meta_box( 'tagsdiv-wp_seedbank_type', 'wp_seedbank', 'normal' );
		remove_meta_box( 'tagsdiv-wp_seedbank_common_name', 'wp_seedbank', 'normal' );
		
		// Setup scripts / styles for date picker
		add_action( "admin_print_scripts-post.php", array($this, 'enqueue_admin_scripts') );
        add_action( "admin_print_scripts-post-new.php", array($this, 'enqueue_admin_scripts') );
		add_action( "admin_print_styles", array($this, 'enqueue_admin_styles') );

	}

	// add scripts for admin UI treatment
     function enqueue_admin_scripts() {
		global $current_screen;
		if ($current_screen->post_type == 'wp_seedbank') {
				wp_register_script('jquery-ui-datepicker', WP_SEEDBANK_PATH . 'jquery.ui.datepicker.min.js', array('jquery', 'jquery-ui-core') );
				wp_enqueue_script('jquery-ui-datepicker');
				
		}

	}

	 // add css for admin UI treatment
	 function enqueue_admin_styles() {
		 
	 	global $current_screen;

		//Only apply to the wp_seedbank post type
		if(isset($current_screen->post_type)) {

			switch ($current_screen->post_type) {
				case 'wp_seedbank':
                    // TODO: Fix these paths? We've removed some of this for simplicity, causing slight datepicker UI issues.
					wp_enqueue_style('jquery-ui-theme', WP_SEEDBANK_PATH . 'css/ui-lightness/jquery-ui-1.8.16.custom.css');
					wp_enqueue_style('wp_seedbank_admin', WP_SEEDBANK_PATH . 'css/wp_seedbank_admin.css');
					break;
			}
		}
		
	 }
	 
	 //Register & activate the JS to enable comment / delete form functionality
	 //FUTURE: Only have this fire on pages where the shortcode is in use.
	 function frontend_scripts_init() {
		wp_register_script('rideshare-connect', WP_SEEDBANK_PATH . 'wp_seedbank_connect.js', array('jquery') );
		wp_enqueue_script('rideshare-connect');
	}    
 
 
	 // add css for admin UI treatment
	 function enqueue_display_styles() {
	 	wp_enqueue_style('wp_seedbank_styles', WP_SEEDBANK_PATH . 'css/wp_seedbank.css');
	 }
	
	//Tweak format of datepicker for admin
	function admin_footer() {
		
	global $current_screen;
		if(isset($current_screen->post_type)) {
			if ($current_screen->post_type == 'wp_seedbank') {
			?>
			<script type="text/javascript">
			jQuery(document).ready(function(){
				if(jQuery( ".datepicker" ).length) {
					jQuery( ".datepicker" ).datepicker({ 
						dateFormat : 'mm/dd/yy',
						showOn: "button",
						buttonImage: "<?php echo WP_SEEDBANK_PATH; ?>/calendar.gif",
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
			$wp_seedbank_type = $custom["wp_seedbank_type"][0];
			$wp_seedbank_quantity = $custom["wp_seedbank_quantity"][0];
			$wp_seedbank_common_name = $custom["wp_seedbank_common_name"][0];
			$wp_seedbank_departureCity = $custom["wp_seedbank_departureCity"][0];
			$wp_seedbank_departureStateProv = $custom["wp_seedbank_departureStateProv"][0]; 
			$wp_seedbank_seed_expiry_date = $custom["wp_seedbank_seed_expiry_date"][0];
			$wp_seedbank_exchange_expiry_date = $custom["wp_seedbank_exchange_expiry_date"][0];
			$wp_seedbank_destinationCity = $custom["wp_seedbank_destinationCity"][0];
			$wp_seedbank_destinationStateProv = $custom["wp_seedbank_destinationStateProv"][0]; 
			$wp_seedbank_unit = $custom["wp_seedbank_unit"][0];
			$wp_seedbank_name = $custom["wp_seedbank_name"][0];
			$wp_seedbank_email = $custom["wp_seedbank_email"][0];
			$wp_seedbank_phone = $custom["wp_seedbank_phone"][0];
			$wp_seedbank_addInfo = $custom["wp_seedbank_addInfo"][0];
			$wp_seedbank_status = $custom["wp_seedbank_status"][0];
		} else {
			$wp_seedbank_type = "";
			$wp_seedbank_quantity = "";
			$wp_seedbank_common_name = "";
			$wp_seedbank_departureCity = "";
			$wp_seedbank_departureStateProv = ""; 
			$wp_seedbank_seed_expiry_date = "";
			$wp_seedbank_exchange_expiry_date = "";
			$wp_seedbank_destinationCity = "";
			$wp_seedbank_destinationStateProv = "";
			$wp_seedbank_unit = "";
			$wp_seedbank_name = "";
			$wp_seedbank_email = "";
			$wp_seedbank_phone = "";
			$wp_seedbank_addInfo = "";
			$wp_seedbank_status = "";
		}
		
        // Create HTML for the drop-down menus.
        ob_start();
        $typeOptions = get_terms('wp_seedbank_type', 'hide_empty=0&order=ASC');
        print '<select name="wp_seedbank_type">';
        foreach ($typeOptions as $type) {
            if ($type->name == $wp_seedbank_type) {
                echo "<option SELECTED value='" . $type->name . "'>" . strtolower($type->name) . "</option>\n";
            } else {
                echo "<option value='" . $type->name . "'>" . strtolower($type->name) . "</option>\n";
            }
        }
        print '</select>';
        $type_select = ob_get_contents();
        ob_end_clean();
        
        ob_start();
        print '<select id="wp-seedbank-common-name" name="wp_seedbank_common_name">';
        $common_nameOptions = get_terms('wp_seedbank_common_name', 'hide_empty=0&order=ASC');
        foreach ($common_nameOptions as $common_name) {
            if ($common_name->name == $wp_seedbank_common_name) {
                echo "<option SELECTED value='" . $common_name->name . "'>" . $common_name->name . "</option>\n";
            } else {
                echo "<option value='" . $common_name->name . "'>" . $common_name->name . "</option>\n";
            }
        }
        $common_name_select = ob_get_contents();
        print '</select>';
        ob_end_clean();

        ob_start();
        $statusOptions = get_terms('wp_seedbank_status', 'hide_empty=0&order=ASC');
        print '<select name="wp_seedbank_status">';
        foreach ($statusOptions as $status) {
            if ($status->name == $wp_seedbank_status) {
                echo "<option SELECTED value='" . $status->name . "'>" . $status->name . "</option>\n";
            } else {
                echo "<option value='" . $status->name . "'>" . $status->name . "</option>\n";
            }
        }
        print '</select>';
        $status_select = ob_get_contents();
        ob_end_clean();
    ?>
    <p><label>I would like to <?php print $type_select;?></label> <input name="wp_seedbank_quantity" value="<?php print $wp_seedbank_quantity;?>" placeholder="enter a number" /> <?php print $common_name_select;?> <input name="wp_seedbank_unit" value="<?php echo $wp_seedbank_unit;?>" placeholder="packets" />.</p>
    <p><label>These seeds will expire on or about <input name="wp_seedbank_seed_expiry_date" class="rideshare_inputLine datepicker" value="<?php echo $wp_seedbank_seed_expiry_date;?>" />.</label> <span class="description">(If these seeds are in a packet, the wrapping might have an expiration date. Put that here.)</span></p>
    <p><label>If I don't hear from anyone by <input name="wp_seedbank_exchange_expiry_date" class="rideshare_inputLine datepicker" value="<?php echo $wp_seedbank_exchange_expiry_date;?>" />, I'll stop being available to make this exchange.</label> <span class="description">(If you don't get a response by this date, your request will automatically close.)</span></p>
    <p>
        <label>Some additional relevant things about this exchange are&hellip;</label><br />
        <textarea name="wp_seedbank_addInfo" class="rideshare_inputBox" rows="10" cols="90"><?php echo $wp_seedbank_addInfo;?></textarea><br />
        <span class="description">(Enter any additional details about this exchange here, such as what kinds of seeds you're hoping to swap for, or other ways interested users might be able to contact you.)</span><br />
    </p>
    <p><label>This seed exchange is <?php print $status_select;?>.</label> <span id="wp-seedbank-status-helptext" class="description">(<?php foreach ($statusOptions as $x) :?>The <code><?php echo $x->name;?></code> type is for <?php print strtolower($x->description);?>, <?php endforeach;?>)</span></p>

<!-- TODO: These are commented out, no need for them in minimum viable product. Add later? -->
<!--
    <fieldset id="wp-seedbank-bioclassification"><legend>Seed bioclassifcation</legend>
        <p>
            <label><?php _e('Species' , 'wp-seedbank'); ?>:</label>
            <input name="wp_seedbank_destinationCity" class="rideshare_inputLine" value="<?php echo $wp_seedbank_destinationCity; ?>" />
            <span class="description">What specific species of seed is this?</span>
        </p>
        <p>
            <label><?php _e('Genus' , 'wp-seedbank'); ?>:</label>
            <?php // Get all rideshare city terms (taxonomy)
                $stateProvOptions = get_terms('wp_seedbank_genus', 'hide_empty=0&order=ASC'); ?>
                <select name='wp_seedbank_destinationStateProv' class='rideshare_selectLine'>
                <option value="">I don't know.</option>
                <?php 
                foreach ($stateProvOptions as $stateProv) {
                    if ($stateProv->name == $wp_seedbank_destinationStateProv) {
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

	<p>
		<label><?php _e('From' , 'wp-seedbank'); ?>:</label>
		<input name="wp_seedbank_departureCity" class="rideshare_inputLine" value="<?php echo $wp_seedbank_departureCity; ?>" />
	
		<?php // Get all rideshare city terms (taxonomy)
			$stateProvOptions = get_terms('wp_seedbank_genus', 'hide_empty=0&order=ASC'); ?>
			<select name='wp_seedbank_departureStateProv' class='rideshare_selectLine'>
			<option></option>
			<?php 
			foreach ($stateProvOptions as $stateProv) {
				if ($stateProv->name == $wp_seedbank_departureStateProv) {
					echo "<option SELECTED value='" . $stateProv->name . "'>" . $stateProv->name . "</option>\n";
				} else {
					echo "<option value='" . $stateProv->name . "'>" . $stateProv->name . "</option>\n";
				}
			}
   		?>
		</select>
	</p>

    <p>
		<label><?php _e('Name' , 'wp-seedbank'); ?>:</label>
		<input name="wp_seedbank_name" class="rideshare_inputLine" value="<?php echo $wp_seedbank_name;?>" /><br />
		<span class="description">If different from your member name, enter a contact person's name for this exchange here.</span>
	</p>
    
    <p>
		<label><?php _e('Email' , 'wp-seedbank'); ?>:</label>
		<input name="wp_seedbank_email" class="rideshare_inputLine" value="<?php echo $wp_seedbank_email;?>" /><br />
		<span class="description">Enter your email address if you'd like other members to contact you privately about this exchange.</span>
	</p> 
-->

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
		if ($post->post_type == "wp_seedbank")
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
                if ('wp_seedbank_addInfo' === $key) {
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
			"title" => __( 'Seed Exchange', 'wp-seedbank' ),
			"wp_seedbank_status" => __( 'Status', 'wp-seedbank' ),
			"wp_seedbank_type" => __( 'Type', 'wp-seedbank' ),
			"wp_seedbank_seed_expiry_date" => __( 'Seed Expiry Date', 'wp-seedbank' ),
			"wp_seedbank_quantity" => __( 'Quantity', 'wp-seedbank' ),
			"wp_seedbank_destination" => __( 'Common Name', 'wp-seedbank' ),
			//"wp_seedbank_departure" => __( 'Departure', 'wp-seedbank' )
		);
		
		return $columns;
	}
	
	// Display custom field in the admin view for the custom post type
	function custom_columns($column) {
		//Customize based on value select statement to consolidate city / state columns
		global $post;
		//
		
		switch($column) {
			
			//Build destination from city, state and common_name meta data
			case 'wp_seedbank_destination':
				$destinationEvent = get_post_meta($post->ID, 'wp_seedbank_common_name', "true");
				if ($destinationEvent) {
					echo $destinationEvent;
				} else {
					$destinationCityState = get_post_meta($post->ID, 'wp_seedbank_departureCity', "true") . ", " . get_post_meta($post->ID, 'wp_seedbank_departureStateProv', "true");
					echo $destinationCityState;
				}
				break; 
			
			//Build departure from city, state meta data
			case 'wp_seedbank_departure':
				echo get_post_meta($post->ID, 'wp_seedbank_departureCity', "true") . ", " . get_post_meta($post->ID, 'wp_seedbank_departureStateProv', "true");
				break;
			
			//Show custom field direct
			default:
				echo get_post_meta($post->ID, $column, "true") ;
				break;
		}

	}

	//Make specific columns sortable where data makes sense
	function register_sortable( $columns ) {
		
		$columns['wp_seedbank_status'] = 'wp_seedbank_status';
		$columns['wp_seedbank_type'] = 'wp_seedbank_type';
		$columns['wp_seedbank_quantity'] = 'wp_seedbank_quantity';
		//FUTURE: When WP supports custom field orderby date, make it sortable
		//$columns['wp_seedbank_seed_expiry_date'] = 'wp_seedbank_seed_expiry_date';
		return $columns;	
	}
	
	//Augment the sort query for custom field - wp_seedbank_status
	function wp_seedbank_status_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'wp_seedbank_status' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'wp_seedbank_status',
				'orderby' => 'meta_value'
			) );
		}
 
		return $vars;
	}
	
	//Augment the sort query for custom field - wp_seedbank_type
	function wp_seedbank_type_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'wp_seedbank_type' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'wp_seedbank_type',
				'orderby' => 'meta_value'
			) );
		}
 
		return $vars;
	}
	
	//Augment the sort query for custom field - wp_seedbank_quantity
	function wp_seedbank_quantity_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'wp_seedbank_quantity' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'wp_seedbank_quantity',
				'orderby' => 'meta_value_num'
			) );
		}
 
		return $vars;
	}

	//FUTURE: Revisit when WP has orderby option for dates
	function wp_seedbank_seed_expiry_date_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'wp_seedbank_seed_expiry_date' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'wp_seedbank_seed_expiry_date',
				'orderby' => 'meta_value_num'
			) );
		}
 
		return $vars;
	}


	//Generate the table list of rideshares with parameters for filtering and dynamic filtering
	function wp_seedbank_shortcode( $atts ) {
		extract( shortcode_atts( array(
			'style' => 'full', //full, common_names
			'dynamic' => 'off', //off, on
			'type' => 'all', //all, give, get
			'destination' => 'all', //all, city, state, common_name
			'destination_filter' => null, //Populate if destination = city, state or common_name - overridden by dynamic
			'departure' => 'all', //all, state, city
			'departure_filter' => null, //Populate if departure = city or state - overridden by dynamic
			'date' => 'current', //all, past, current, single
			'date_filter' => null, //Populate if date = single
			'unit' => 'all', //all or specific WP unit
			'quantity' => 'all', //all or 1 - 5
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
		$unit = esc_attr(strtolower($unit));
		$quantity = esc_attr(strtolower($quantity));
		$contact = esc_attr(strtolower($contact));
		//$status = esc_attr(strtolower($status));
		
		//Step 2 - Create filters for non-dynamic scenario
		
		//Filter meta_query based on rideshare status - always != deleted
		$statusMetaQuery = array(
			'key' => 'wp_seedbank_status',
			'value' => 'Deleted',
			'compare' => '!='
		);
		
		//Filter meta_query based on rideshare type from shortcode
		if($type != 'all') {
			$typeMetaQuery = array(
				'key' => 'wp_seedbank_type',
				'value' => $type,
				'compare' => '='
			);
		} 
		
		//Filter meta_query based on destination from shortcode
		if($destination != 'all') {
			 switch($destination) {
				case 'city':
					$destinationKey = 'wp_seedbank_destinationCity';
					break; 
				case 'state':
					$destinationKey = 'wp_seedbank_destinationStateProv';
					break; 
				case 'common_name';
					$destinationKey = 'wp_seedbank_common_name';
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
					$departureKey = 'wp_seedbank_departureCity';
					break; 
				case 'state':
					$departureKey = 'wp_seedbank_departureStateProv';
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
				'key' => 'wp_seedbank_seed_expiry_date',
				'value' => $dateValue,
				'compare' => $dateCompare
			);
		}
		
		//Filter meta_query based on date from shortcode
		if($unit != 'all') {
			$userMetaQuery = array(
				'key' => 'wp_seedbank_unit',
				'value' => $unit,
				'compare' => '='
			);
		} 
		
		//Filter meta_query based on rideshare type from shortcode
		if($quantity != 'all') {
			$typeSpacesQuery = array(
				'key' => 'wp_seedbank_quantity',
				'value' => $quantity,
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
					'key' => 'wp_seedbank_type',
					'value' => $dType,
					'compare' => '='
				);
			}
			
			//Filter meta_query based on destination from querystring
			$dDestination = esc_attr(strtolower($_GET['destination']));
			if($dDestination != 'all') {
				switch($dDestination) {
					case 'city':
						$destinationKey = 'wp_seedbank_destinationCity';
						break; 
					case 'state':
						$destinationKey = 'wp_seedbank_destinationStateProv';
						break; 
					case 'common_name';
						$destinationKey = 'wp_seedbank_common_name';
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
						$departureKey = 'wp_seedbank_departureCity';
						break; 
					case 'state':
						$departureKey = 'wp_seedbank_departureStateProv';
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
					'key' => 'wp_seedbank_seed_expiry_date',
					'value' => $dateValue,
					'compare' => $dateCompare
				);
			}
		
			//Filter meta_query based on unit type from querystring
			$dUsername = esc_attr(strtolower($_GET['unit']));
			if($dUsername) {
				$userMetaQuery = array(
					'key' => 'wp_seedbank_unit',
					'value' => $dUsername,
					'compare' => '='
				);
			}
			
			//Filter meta_query based on rideshare type from querystring
			$dSpaces = esc_attr(strtolower($_GET['quantity']));
			if($dSpaces) {
				$quantityMetaQuery = array(
					'key' => 'wp_seedbank_quantity',
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
		if ( $quantityMetaQuery ) { $meta_query[] = $quantityMetaQuery; }


		//$output .= print_r($meta_query,true);
		//Step 5 - Generate output
		switch ($style) {
    		case 'full':
				
				if ($meta_query == null) {
					$queryParameters = array(
					'post_type' => 'wp_seedbank',
					'posts_per_page' => '-1',
    				'orderby' => 'meta_value',
					'meta_key' => 'wp_seedbank_type',
					'order' => 'ASC'
					);
				} else {
					
					$queryParameters = array(
						'post_type' => 'wp_seedbank',
						'posts_per_page' => '-1',
						'meta_query' => $meta_query,
    					'orderby' => 'meta_value',
						'meta_key' => 'wp_seedbank_type',
						'order' => 'ASC'
					);	
					
				}
				
				//DEBUG: $output .= '<pre>' . var_export($queryParameters, true) . '</pre>';
				
				$IRQuery = new WP_Query();
				$IRQuery->query($queryParameters);
				
				//Loop through results
				if ( $IRQuery->have_posts() ) :
					//Create single instances of the sub-forms to be tweaked based on wp_seedbank_connect.js on button click
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
					$output .= '<th class="rideshareType">' . __('Type' , 'wp-seedbank') . '</th>';
					$output .= '<th class="rideshareEvent">' . __('To' , 'wp-seedbank') . '</th>';
					$output .= '<th class="rideshareCity">' . __('From' , 'wp-seedbank') . '</th>';
					$output .= '<th class="rideshareSpaces">' . __('For' , 'wp-seedbank') . '</th>';
					$output .= '<th class="rideshareDDate">' . __('On' , 'wp-seedbank') . '</th>';
					//$output .= '<th class="rideshareRDate">' . __('Return' , 'wp-seedbank') . '</th>';
					$output .= '<th class="rideshareInfo">' . __('Add. Info' , 'wp-seedbank') . '</th>';
					
					//Add Engagement Column based on current user = filtered (profile scenario)
					if( $current_user->user_login != $unit ) {
						$output .= '<th class="rideshareContact">' . __('Contact' , 'wp-seedbank') . '</th>';
					} else {
						$output .= '<th class="rideshareContact">' . __('Actions' , 'wp-seedbank') . '</th>';
					}
						
					$output .= '</tr>';
			
					//Repeat for each row of data
					 while ( $IRQuery->have_posts() ) : $IRQuery->the_post(); 

						$ID = get_the_ID();
						$output .= '<tr>';

						//Type
						$output .= '<td class="rideshareType">' . get_post_meta($ID, "wp_seedbank_type", true) . '</td>';
						
						//Destination
						$output .= '<td class="rideshareEvent">';
						//Event for City, State
						if (get_post_meta($ID, "wp_seedbank_common_name", true)) {
							$destinationOutput = get_post_meta($ID, "wp_seedbank_common_name", true);
						} else {
							$destinationOutput = 	get_post_meta($ID, "wp_seedbank_destinationCity", true) . ", " . 
								   get_post_meta($ID, "wp_seedbank_destinationStateProv", true);
						}
						$output .= $destinationOutput;
						$output .='</td>';
						
						//Departure
						$departureOutput = get_post_meta($ID, "wp_seedbank_departureCity", true) . ', ' . get_post_meta($ID, "wp_seedbank_departureStateProv", true);
						$output .= '<td class="rideshareCity">' . $departureOutput . '</td>';
						
						//Spaces
						$quantityOutput = get_post_meta($ID, "wp_seedbank_quantity", true);
						$output .= '<td class="rideshareSpaces">' . $quantityOutput . '</td>';
						
						//Departure Date
						$dateOutput = get_post_meta($ID, "wp_seedbank_seed_expiry_date", true);
						$output .= '<td class="rideshareDDate">' . $dateOutput . '</td>';
						//$output .= '<td class="rideshareRDate">' . get_post_meta($ID, "wp_seedbank_exchange_expiry_date", true) . '</td>';
						
						//FUTURE: Return Date
						
						//Add Info
						$output .= '<td class="rideshareInfo">';
							$addInfoOutput = get_post_meta($ID, "wp_seedbank_addInfo", true);
							if ($addInfoOutput) { $output .=  $addInfoOutput; } else { $output .= '&nbsp;'; }
						$output .= '</td>';
						
						
						//Contact Info
						$selected_rideshare_unit = get_post_meta($ID, "wp_seedbank_unit", true);
						
						if($selected_rideshare_unit) {
							if($current_user->user_login != $selected_rideshare_unit) {
								//Viewer is not the creator of the rideshare
								$userData = get_user_by('login', $selected_rideshare_unit);
								$name = $userData->display_name;
								$emailAddress = $userData->user_email;
							} else {
								//Viewer IS the creator of the rideshare
								$name = $current_user->display_name;
								$emailAddress = $current_user->user_email;
							}
										
						} else {
							//A non-registered user entry
							$name = get_post_meta($ID, "wp_seedbank_name", true);
							$emailAddress = get_post_meta($ID, "wp_seedbank_email", true);
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
										$name = get_post_meta($ID, "wp_seedbank_name", true);
										$emailAddress = get_post_meta($ID, "wp_seedbank_email", true);

										$output .= 'Name: ' . $name . '<br/>';
										$output .= 'Email: ' . antispambot($emailAddress) . '';
									}
									

								}
								$output .= '</td>'; */

								break;
								
							case 'buddypress':
								
								//confirm buddypress is active to send message
								if ($selected_rideshare_unit && function_exists('bp_core_get_userid')) {
							
									//Which user generated rideshare
									$userID = bp_core_get_userid( $selected_rideshare_unit );
									$bp_displayName=bp_core_get_user_displayname( $userID );
									$bp_userDomain = bp_core_get_user_domain( $userID );
							
									if( $current_user->user_login != $selected_rideshare_unit ) {
										//Not being displayed on profile page or filtered for current signed-in user
										$output .= '<td class="rideshareContact">';
								
										//Create profile link
										$output .= '<a href=' . $bp_userDomain . '>' . $bp_displayName . '</a><br/>';
								
										//Button to display comment / connect form
										$output .= '<input type="button" value="Connect!" id="rideshare_' . $ID . '" ';
										$output .= 'onclick="rideshare_connect(\'' . $ID . '\', \'buddypress\', \'' . $selected_rideshare_unit . '\', \'' . $current_user->user_login . '\', ';
										$output .= '\'' . $destinationOutput . '\', \'' . $departureOutput . '\', \'' . $dateOutput . '\', \'' . $quantityOutput . '\' )" />';
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
					$output .= '<p>' . __('There are no seed exchanges available at this time' , 'wp-seedbank') . '.</p>';
					
				endif;
			
			break;
				
			case 'common_names':
				//FUTURE: Refactor this to match updated 'full' version logic, perhaps even incorporating into the output generation of columns.
				$terms = get_terms( 'wp_seedbank_common_name', 'hide_empty=0&order=ASC' );
				
				if (isset($terms)) :
					
					$output = "";
					
					foreach ( $terms as $common_name ) {
					
						//Retrieve all rideshares by common_name
						$queryParameters = array(
							'post_type' => 'wp_seedbank',
							'posts_per_page' => '-1',
							'orderby' => 'meta_value',
							'meta_key' => 'wp_seedbank_common_name',
							'meta_value' => $common_name->name
						);
				
			
						$IRQuery = new WP_Query();
						$IRQuery->query($queryParameters);
						
						
						
						if ( $IRQuery->have_posts() ) :
				
							$output .= '<h2>' . $common_name->name . '</h2>';
							$output .= '<table id="rideshare" class="tablestripe">';
							$output .= '<tr>';
							$output .= '<th class="rideshareCity">' . __('Location' , 'wp-seedbank') . '</th>';
							$output .= '<th class="rideshareType">' . __('Type' , 'wp-seedbank') . '</th>';
							$output .= '<th class="rideshareSpaces">' . __('Quantity' , 'wp-seedbank') . '</th>';
							$output .= '<th class="rideshareDDate">' . __('Seed Expiry Date' , 'wp-seedbank') . '</th>';
							$output .= '<th class="rideshareRDate">' . __('Expiry Date' , 'wp-seedbank') . '</th>';
							$output .= '<th class="rideshareContact">' . __('Contact' , 'wp-seedbank') . '</th>';
							$output .= '<th class="rideshareInfo">' . __('Additional Info' , 'wp-seedbank') . '</th>';
							$output .= '</tr>';
					
							 while ( $IRQuery->have_posts() ) : $IRQuery->the_post(); 
		
								$ID = get_the_ID();
								$output .= '<tr>';
								$output .= '<td class="rideshareCity">' . get_post_meta($ID, "wp_seedbank_departureCity", true) . '</td>';
								$output .= '<td class="rideshareType">' . get_post_meta($ID, "wp_seedbank_type", true) . '</td>';
								$output .= '<td class="rideshareSpaces">' . get_post_meta($ID, "wp_seedbank_quantity", true) . '</td>';
								$output .= '<td class="rideshareDDate">' . get_post_meta($ID, "wp_seedbank_seed_expiry_date", true) . '</td>';
								$output .= '<td class="rideshareRDate">' . get_post_meta($ID, "wp_seedbank_exchange_expiry_date", true) . '</td>';
								$output .= '<td class="rideshareContact">';
								
									$output .= '<span class="name">' . get_post_meta($ID, "wp_seedbank_name", true) . '</span>';
									
									$output .= '<span class="email">';
										$emailAddress = get_post_meta($ID, "wp_seedbank_email", true);
										$output .= '<a href="mailto:' . antispambot($emailAddress) . '">' . antispambot($emailAddress) . '</a>';
									$output .= '</span>';
									
									$phone = get_post_meta($ID, "wp_seedbank_phone", true);
									if ($phone) { $output .=  '<span class="phone">' . get_post_meta($ID, "wp_seedbank_phone", true) . '</span>'; }					
								
								$output .= '</td>';
								$output .= '<td class="rideshareInfo">';
									$addInfo = get_post_meta($ID, "wp_seedbank_addInfo", true);
									if ($addInfo) { $output .=  $addInfo; } else { $output .= '&nbsp;'; }
								$output .= '</td>';
								//$output .= '<td>' . get_the_title() . '</td>';
								$output .= '</tr>';
			
							endwhile;
							
							$output .= '</table>';
							
						else: 
							$output .= '<h2>' . $common_name->name . '</h2>';
							$output .= "<p>There are no exchanges available for {$common_name->name} seeds at this time.</p>";
							
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
		WP_Seedbank::create_data_types();
		
        // DEV NOTE: For now, forcibly assume we're always doing a new install when we "activate."
		$version = ""; //get_option('wp_seedbank_version');
		
        // New installation - pre-load some fields
		if($version == "") {

            // Exchange Types (verbs)
			wp_insert_term(__( 'Swap', 'wp-seedbank' ), 'wp_seedbank_type', array('description' => 'Exchanges offering seeds for other seeds.'));
			wp_insert_term(__( 'Sell', 'wp-seedbank' ), 'wp_seedbank_type', array('description' => 'Exchanges offering seeds for money.'));
			wp_insert_term(__( 'Give', 'wp-seedbank' ), 'wp_seedbank_type', array('description' => 'Exchanges offering free seeds being given away.'));
			wp_insert_term(__( 'Get', 'wp-seedbank' ), 'wp_seedbank_type', array('description' => 'Exchanges requesting seeds of a variety not already listed.'));
			
            // Genera
            wp_insert_term( __( 'Abelmoschus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'abelmoschus' ) );
            wp_insert_term( __( 'Agastache', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'agastache' ) );
            wp_insert_term( __( 'Allium', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'allium' ) );
            wp_insert_term( __( 'Amaranthus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'amaranthus' ) );
            wp_insert_term( __( 'Anagallis', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'anagallis' ) );
            wp_insert_term( __( 'Anethum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'anethum' ) );
            wp_insert_term( __( 'Anthenum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'anthenum' ) );
            wp_insert_term( __( 'Antirrhinum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'antirrhinum' ) );
            wp_insert_term( __( 'Apium', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'apium' ) );
            wp_insert_term( __( 'Asclepias', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'asclepias' ) );
            wp_insert_term( __( 'Basella', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'basella' ) );
            wp_insert_term( __( 'Beta', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'beta' ) );
            wp_insert_term( __( 'Brassica', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'brassica' ) );
            wp_insert_term( __( 'Calendula', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'calendula' ) );
            wp_insert_term( __( 'Capsicum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'capsicum' ) );
            wp_insert_term( __( 'Cardiospermum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cardiospermum' ) );
            wp_insert_term( __( 'Centaurea', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'centaurea' ) );
            wp_insert_term( __( 'Chrysanthemum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'chrysanthemum' ) );
            wp_insert_term( __( 'Cichorium', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cichorium' ) );
            wp_insert_term( __( 'Citrullus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'citrullus' ) );
            wp_insert_term( __( 'Cleome', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cleome' ) );
            wp_insert_term( __( 'Cobaea', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cobaea' ) );
            wp_insert_term( __( 'Consolida', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'consolida' ) );
            wp_insert_term( __( 'Convolvulus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'convolvulus' ) );
            wp_insert_term( __( 'Coreopsis', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'coreopsis' ) );
            wp_insert_term( __( 'Coriandrum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'coriandrum' ) );
            wp_insert_term( __( 'Cosmos', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cosmos' ) );
            wp_insert_term( __( 'Cucumis', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cucumis' ) );
            wp_insert_term( __( 'Cucurbita', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'cucurbita' ) );
            wp_insert_term( __( 'Dalea', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'dalea' ) );
            wp_insert_term( __( 'Daucus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'daucus' ) );
            wp_insert_term( __( 'Diplotaxis', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'diplotaxis' ) );
            wp_insert_term( __( 'Dolichos', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'dolichos' ) );
            wp_insert_term( __( 'Echinacea', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'echinacea' ) );
            wp_insert_term( __( 'Eruca', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'eruca' ) );
            wp_insert_term( __( 'Eschscholzia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'eschscholzia' ) );
            wp_insert_term( __( 'Foeniculum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'foeniculum' ) );
            wp_insert_term( __( 'Fragaria', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'fragaria' ) );
            wp_insert_term( __( 'Gaillardia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'gaillardia' ) );
            wp_insert_term( __( 'Glycine', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'glycine' ) );
            wp_insert_term( __( 'Helianthus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'helianthus' ) );
            wp_insert_term( __( 'Ipomoea', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'ipomoea' ) );
            wp_insert_term( __( 'Koeleria', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'koeleria' ) );
            wp_insert_term( __( 'Lactuca', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'lactuca' ) );
            wp_insert_term( __( 'Lagenaria', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'lagenaria' ) );
            wp_insert_term( __( 'Lathyrus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'lathyrus' ) );
            wp_insert_term( __( 'Lupinus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'lupinus' ) );
            wp_insert_term( __( 'Lycopersicon', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'lycopersicon' ) );
            wp_insert_term( __( 'Malope', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'malope' ) );
            wp_insert_term( __( 'Matricaria', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'matricaria' ) );
            wp_insert_term( __( 'Mentha', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'mentha' ) );
            wp_insert_term( __( 'Mirabilis', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'mirabilis' ) );
            wp_insert_term( __( 'Nigella', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'nigella' ) );
            wp_insert_term( __( 'Ocimum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'ocimum' ) );
            wp_insert_term( __( 'Origanum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'origanum' ) );
            wp_insert_term( __( 'Papaver', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'papaver' ) );
            wp_insert_term( __( 'Passiflora', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'passiflora' ) );
            wp_insert_term( __( 'Penstemon', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'penstemon' ) );
            wp_insert_term( __( 'Petrolselinum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'petrolselinum' ) );
            wp_insert_term( __( 'Phaseolus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'phaseolus' ) );
            wp_insert_term( __( 'Physalis', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'physalis' ) );
            wp_insert_term( __( 'Pisum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'pisum' ) );
            wp_insert_term( __( 'Poterium', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'poterium' ) );
            wp_insert_term( __( 'Raphanus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'raphanus' ) );
            wp_insert_term( __( 'Rosmarinus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'rosmarinus' ) );
            wp_insert_term( __( 'Rudbeckia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'rudbeckia' ) );
            wp_insert_term( __( 'Salvia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'salvia' ) );
            wp_insert_term( __( 'Scorpiurus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'scorpiurus' ) );
            wp_insert_term( __( 'Solanum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'solanum' ) );
            wp_insert_term( __( 'Spinachia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'spinachia' ) );
            wp_insert_term( __( 'Tagetes', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'tagetes' ) );
            wp_insert_term( __( 'Thunbergia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'thunbergia' ) );
            wp_insert_term( __( 'Thymus', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'thymus' ) );
            wp_insert_term( __( 'Triticum ', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'triticum ' ) );
            wp_insert_term( __( 'Tropaeolum', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'tropaeolum' ) );
            wp_insert_term( __( 'Zea', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'zea' ) );
            wp_insert_term( __( 'Zinnia', 'wp-seedbank' ), 'wp_seedbank_genus', array( 'slug' => 'zinnia' ) );
			
            // Common Names
            wp_insert_term( __( 'Asian Vegetable', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'asian-vegetable' ) );
            wp_insert_term( __( 'Bean', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'bean' ) );
            wp_insert_term( __( 'Beet', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'beet' ) );
            wp_insert_term( __( 'Berry', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'berry' ) );
            wp_insert_term( __( 'Broccoli', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'broccoli' ) );
            wp_insert_term( __( 'Brussels Sprout', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'brussels-sprout' ) );
            wp_insert_term( __( 'Cabbage', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'cabbage' ) );
            wp_insert_term( __( 'Carrot', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'carrot' ) );
            wp_insert_term( __( 'Cauliflower', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'cauliflower' ) );
            wp_insert_term( __( 'Chard', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'chard' ) );
            wp_insert_term( __( 'Corn', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'corn' ) );
            wp_insert_term( __( 'Collard', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'collard' ) );
            wp_insert_term( __( 'Cover Crop', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'cover-crop' ) );
            wp_insert_term( __( 'Eggplant', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'eggplant' ) );
            wp_insert_term( __( 'Cucumber', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'cucumber' ) );
            wp_insert_term( __( 'Fava', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'fava' ) );
            wp_insert_term( __( 'Flower', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'flower' ) );
            wp_insert_term( __( 'Gourd', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'gourd' ) );
            wp_insert_term( __( 'Green', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'green' ) );
            wp_insert_term( __( 'Herb', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'herb' ) );
            wp_insert_term( __( 'Kale', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'kale' ) );
            wp_insert_term( __( 'Kohlrabi', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'kohlrabi' ) );
            wp_insert_term( __( 'Legume', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'legume' ) );
            wp_insert_term( __( 'Lettuce', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'lettuce' ) );
            wp_insert_term( __( 'Melon', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'melon' ) );
            wp_insert_term( __( 'Mustard', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'mustard' ) );
            wp_insert_term( __( 'Okra', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'okra' ) );
            wp_insert_term( __( 'Onion', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'onion' ) );
            wp_insert_term( __( 'Parsnip/Root Parsley', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'parsnip-root-parsley' ) );
            wp_insert_term( __( 'Potato', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'potato' ) );
            wp_insert_term( __( 'Pea', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'pea' ) );
            wp_insert_term( __( 'Peppers', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'peppers' ) );
            wp_insert_term( __( 'Pumpkin', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'pumpkin' ) );
            wp_insert_term( __( 'Radish', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'radish' ) );
            wp_insert_term( __( 'Strawberry', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'strawberry' ) );
            wp_insert_term( __( 'Root', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'root' ) );
            wp_insert_term( __( 'Rutabaga', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'rutabaga' ) );
            wp_insert_term( __( 'Spinach', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'spinach' ) );
            wp_insert_term( __( 'Summer Squash', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'summer-squash' ) );
            wp_insert_term( __( 'Tomatoes', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'tomatoes' ) );
            wp_insert_term( __( 'Turnip', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'turnip' ) );
            wp_insert_term( __( 'Winter Squash', 'wp-seedbank' ), 'wp_seedbank_common_name', array( 'slug' => 'winter-squash' ) );
			
            // Exchange statuses
			wp_insert_term(__( 'Active', 'wp-seedbank' ), 'wp_seedbank_status', array('description' => 'New/open seed exchange requests or offers.'));
			wp_insert_term(__( 'Deleted', 'wp-seedbank' ), 'wp_seedbank_status', array('description' => 'Expired or completed seed exchanges.'));
		}

		//Update version number in DB
		update_option('wp_seedbank_version', IDEALIEN_VERSION);

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
					
					WP_Seedbank::uninstall_heavy_lifting();
					
        			restore_current_blog();
				}
    		}
		} else {
    		
			WP_Seedbank::uninstall_heavy_lifting();
		}
	}
	
	static function uninstall_heavy_lifting() {
		//FUTURE: Extend this to delete all taxonomies
		//Delete options
		delete_option('wp_seedbank_version');
		
		//Delete all terms in the taxonomies which are rideshare specific.
		$taxonomies = array('wp_seedbank_common_name', 'wp_seedbank_city', 'wp_seedbank_type');
		
		foreach ( $taxonomies as $taxonomy ) {
			
			$terms = get_terms( $taxonomy, 'hide_empty=0&order=ASC' );
	
			if ($terms != null) :

				foreach ( $terms as $term ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
				
			endif;
		}
		
		//Delete all posts of type wp_seedbank
		$queryParameters = array(
		'post_type' => 'wp_seedbank',
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
		global $wp_seedbank;
		$wp_seedbank = new WP_Seedbank();
	}
	

}

class WP_SeedbankAdmin {
    function WP_SeedbankAdmin () {
        // Do nothing.
    }

    function registerAdminMenus () {
        add_submenu_page('edit.php?post_type=wp_seedbank', 'batch-request', 'Batch Exchange', 'edit_posts', 'seedbank_batch_exchange', array('WP_SeedbankAdmin', 'dispatchBatchExchangePages'));
    }

    // Dispatcher for batch exchange functionality.
    function dispatchBatchExchangePages ($step = 0) {
        $step = (int) $_POST['wp-seedbank-batch-exchange-step'];
        if (0 === $step) {
            self::printBatchExchangeForm();
        } else if (1 === $step) {
            self::processBatchExchangeForm($_POST);
        }
    }
	
    // Produce HTML for showing the submenu page.
    function printBatchExchangeForm () {?>
<h2>Batch Seed Exchange</h2>
<p>This page allows you to upload a comma-separated values (CSV) file that will be translated to seed exchange requests or offers. The CSV file should have the structure like <a href="#wp-seedbank-batch-exchange-example">the example shown in the table below</a>.</p>
<form id="wp-seedbank-batch-exchange-form" name="wp_seedbank_batch_exchange" action="<?php print $_SERVER['PHP_SELF']?>?post_type=wp_seedbank&amp;page=seedbank_batch_exchange" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('wp-seedbank-batch-exchange', 'batch-exchange');?>
    <input type="hidden" name="wp-seedbank-batch-exchange-step" value="1" />
    <p>
        My batch exchange file is located on
        <select id="wp-seedbank-batch-exchange-data-source">
            <option>another website</option>
            <option>my computer</option>
        </select>
        . It <select name="wp-seedbank-batch-exchange-strip-headers"><option value="1">has</option><option value="0">does not have</option></select> column labels (a header row).
    </p>
    <fieldset id="wp-seedbank-batch-exchange-web-fetch"><legend>Web fetch options</legend>
        <p>The address of the file containing my seed exchange data is <input name="wp-seedbank-batch-exchange-file-url" value="" placeholder="http://mysite.com/file.csv" />.</p>
    </fieldset>
    <fieldset id="wp-seedbank-batch-exchange-file-upload"><legend>File upload options</legend>
        <p>The file on my computer containing my seed exchange data is <input type="file" name="wp-seedbank-batch-exchange-file-data" value="" />.</p>
    </fieldset>
    <p><label><input type="checkbox" name="wp-seedbank-batch-exchange-post_status" value="draft" /> Let me review each seed exchange before publishing.</label></p>
    <p><input type="submit" name="wp-seedbank-batch-exchange-submit" value="Make seed exchanges" /></p>
</form>
<table summary="Example of batch seed exchange data." id="wp-seedbank-batch-exchange-example">
    <thead>
        <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Common Name</th>
            <th>Unit label</th>
            <th>Seed expiration date</th>
            <th>Exchange expiration date</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Looking to swap peppers for carrots</td>
            <td>Swap</td>
            <td>5</td>
            <td>Pepper</td>
            <td>seeds</td>
            <td>2016-05-01</td>
            <td>2014-05-01</td>
            <td>Ideally, I'd like to receive carrot seeds in exchange. Thanks!</td>
        </tr>
        <tr>
            <td>For sale: tomato seed packets, negotiable price</td>
            <td>Sell</td>
            <td>100</td>
            <td>Tomato</td>
            <td>seed packets</td>
            <td>2017-01-01</td>
            <td>2015-06-01</td>
            <td>Price is negotiable. Reply here or by phone at (555) 555-5555 if interested.</td>
        </tr>
        <tr>
            <td colspan="8">&hellip;</td>
        </tr>
        <tr>
            <td>These are the best bean seeds!</td>
            <td>Swap</td>
            <td>20</td>
            <td>Bean</td>
            <td>packets</td>
            <td>2015-03-30</td>
            <td>2014-05-01</td>
            <td>These beans are kidney beans. They're delicious and nutritious, but taste nothing like chicken.</td>
        </tr>
    <tbody>
</table>
<?php
    }

    function processBatchExchangeForm ($fields) {
        if (!wp_verify_nonce($_POST['batch-exchange'], 'wp-seedbank-batch-exchange')) { ?>
            <p>Your batch exchange request has expired or is invalid. Please <a href="<?php print home_url();?>/wp-admin/edit.php?post_type=wp_seedbank&page=seedbank_batch_exchange">start again</a>.</p>
<? 
        }
        $where = ($_FILES['wp-seedbank-batch-exchange-file-data']['tmp_name']) ?
            $_FILES['wp-seedbank-batch-exchange-file-data']['tmp_name'] :
            $_POST['wp-seedbank-batch-exchange-file-url'];
        if (!$where) { ?>
            <p>Please let us know where to find your data. You'll need to <a href="<?php print home_url();?>/wp-admin/edit.php?post_type=wp_seedbank&page=seedbank_batch_exchange">start again</a>.</p>
<?php
            return;
        }
        $strip = ($_POST['wp-seedbank-batch-exchange-strip-headers']) ? true : false; 
        $post_status = ($_POST['wp-seedbank-batch-exchange-post_status']) ? 'draft' : 'publish';
        $data = WP_SeedbankUtilities::csvToMultiArray($where, $strip); // true means "strip headers"
        $new_post_ids = array();
        // For each line in the CSV,
        foreach ($data as $x) {
            list(
                $title,
                $exch_type,
                $exch_quantity,
                $exch_common_name,
                $exch_unit,
                $exch_seed_expiry,
                $exch_expiry,
                $body
            ) = $x;
            // convert it into a new seed exchange post.
            // TODO: Refactor this, along with the wp_seedbank::wp_insert_post()
            $post = array(
                'comment_status' => 'open',
                'ping_status' => 'open', 
                'post_author' => get_current_user_id(), // TODO: Get the user ID.
                'post_content' => $body,
//                'post_date' => , // should be "now"?
//                'post_date_gmt' => , // should be "now"?
//                'post_name' => , // automatic?
//                'post_parent' => , // automatic?
                'post_status' => $post_status,
                'post_title' => $title,
                'post_type' => 'wp_seedbank', // this is the "Title" position in the CSV.
            );

            $p = wp_insert_post($post);
            if (!$p) {
                // TODO: Handle error?
            } else {
                $new_post_ids[] = $p;
                update_post_meta($p, 'wp_seedbank_addInfo', $body);
                update_post_meta($p, 'wp_seedbank_common_name', $exch_common_name);
                update_post_meta($p, 'wp_seedbank_quantity', $exch_quantity);
                update_post_meta($p, 'wp_seedbank_status', 'Active'); // New posts are always active?
                update_post_meta($p, 'wp_seedbank_type', $exch_type);
                update_post_meta($p, 'wp_seedbank_unit', $exch_unit);
                update_post_meta($p, 'wp_seedbank_seed_expiry_date', date('m/d/Y', strtotime($exch_seed_expiry)));
                update_post_meta($p, 'wp_seedbank_exchange_expiry_date', date('m/d/Y', strtotime($exch_expiry)));
            }
        }

        // Display success message.
        $n = count($new_post_ids);
        if ($n) { ?>
            <p>Successfully imported <?php print $n;?> new <a href="<?php print home_url();?>/wp-admin/edit.php?post_type=wp_seedbank">seed exchange posts</a>.</p>
<?php
        }
    }
}

class WP_SeedbankUtilities {
    function WP_SeedbankUtilities () {
        // Do nothing.
    }

    function csvToMultiArray ($infile, $strip_headers = false) {
        $f = fopen($infile, 'r');
        $r = array();
        while (($data = fgetcsv($f)) !== false) {
            $r[] = $data;
        }
        if ($strip_headers) {
            array_shift($r);
        }
        return $r;
    }
}

// Initiate the plugin
add_action("init", "WP_Seedbank::initialize");

register_activation_hook(__FILE__, 'WP_Seedbank::activate');
register_deactivation_hook(__FILE__, 'WP_Seedbank::deactivate');

//FUTURE: Uninstallation routine
//register_uninstall_hook(__FILE__, 'WP_Seedbank::uninstall');
//Uninstall has not been activated because it does not properly activate.
//If you want to do a proper clean-out of data:
//	-activate the plugin
//	-delete all of the terms in the taxonomies within the rideshares menu
//	-delete all rideshare custom posts
// 	-de-activate and delete the plugin

?>
