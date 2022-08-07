<?php
/**
 * Plugin Name: Artist Registration (WPT ELEMENTOR) 
 * Description: Create a new Pending Artist using elementor form
 * Author:      WPT
 * Author URI:  https://www.webprotime.com
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

add_action( 'elementor_pro/forms/new_record',  'wpt_elementor_form_create_artist_user' , 10, 2 );

function wpt_elementor_form_create_artist_user($record,$ajax_handler)
{
    $form_name = $record->get_form_settings('form_name');
    //Check that the form is the "07AUG2022 Artist Registration" if not - stop and return;
    if ('07AUG2022 Artist Registration' !== $form_name) {
        return;
    }
    //echo "called=========";
    $form_data = $record->get_formatted_data();
    //$username=$form_data['USER NAME']; //Get tne value of the input with the label "User Name"
    $password = $form_data['Password']; //Get tne value of the input with the label "Password"
    $email=$form_data['Email'];  //Get tne value of the input with the label "Email"

    $first_name_ready = strtolower( trim( $form_data["First Name"] ) );
    $last_name_ready = strtolower( trim( $form_data["Last Name"] ) );
    $g_user_name = $first_name_ready.$last_name_ready;

require_once ABSPATH . WPINC . '/user.php';

     while (username_exists( $g_user_name )) {
      
        $g_user_name = $g_user_name.strval( mt_rand(0,9) );
      
         }
         

    $user = wp_create_user($g_user_name,$password,$email); // Create a new user, on success return the user_id no failure return an error object
    if (is_wp_error($user)){ // if there was an error creating a new user
        $ajax_handler->add_error_message("Failed to create new user: ".$user->get_error_message()); //add the message
        $ajax_handler->is_success = false;
        return;
    }
    $first_name=$form_data["First Name"]; //Get tne value of the input with the label "First Name"
    $last_name=$form_data["Last Name"]; //Get tne value of the input with the label "Last Name"
    $disp_name = $first_name." ".$last_name;
    wp_update_user(array("ID"=>$user,"first_name"=>$first_name,"last_name"=>$last_name, 'display_name' => $disp_name,"role"=>dc_pending_vendor)); // Update the user with the first name and last name

    // for updateing vendor slug and vendor page title accoring to firstname and last name;
    update_user_meta($user,'nickname',$disp_name);
    update_user_meta($user,'_vendor_page_slug',$first_name.$last_name);
    update_user_meta($user,'_vendor_page_title',$first_name." ".$last_name);
	
	/* Enable flat rate shipping default for vendor when vendor register. */
	if ( is_plugin_active( 'dc-woocommerce-multi-vendor/dc_product_vendor.php' ) )
	{
		global $wpdb;
		$zone_id = $wpdb->get_var("select zone_id from ".$wpdb->prefix."woocommerce_shipping_zone_methods where method_id='wcmp_vendor_shipping' and is_enabled='1'");
		if($zone_id)
		{
			$table_name = "{$wpdb->prefix}wcmp_shipping_zone_methods";
			$result = $wpdb->insert(
				$table_name,
				array(
					'method_id' => 'flat_rate',
					'zone_id'   => $zone_id,
					'vendor_id' => $user
				),
				array(
					'%s',
					'%d',
					'%d'
				)
			);
			$instance_id = $wpdb->insert_id;
			$arr['method_id']='flat_rate';
            $arr['instance_id'] =$instance_id;
            $arr['zone_id'] = $zone_id;
            $arr['title'] = "Shipping Charge";
            $arr['cost'] = 0;
            $arr['tax_status'] = 'none';
            $arr['description'] = 'Lets you charge a fixed rate for shipping.';
            $arr['class_cost_487'] = '';
            $arr['calculation_type'] = "class";
            $data['settings'] = maybe_serialize($arr);
			$updated = $wpdb->update( $table_name, $data, array( 'instance_id' => $instance_id ), array( '%s') );
			
		}
	}
    $ajax_handler->add_success_message("Successfully registered!"); //add the message
	$ajax_handler->is_success = true;
	return;
    
    /* Automatically log in the user and redirect the user to the home page */
    $creds= array( // credientials for newley created user
        "user_login"=>$username,
        "user_password"=>$password,
        "remember"=>true
    );
    $signon = wp_signon($creds); //sign in the new user
    if ($signon)
        $ajax_handler->add_response_data( 'redirect_url', get_home_url() ); // optinal - if sign on succsfully - redierct the user to the home page
}
