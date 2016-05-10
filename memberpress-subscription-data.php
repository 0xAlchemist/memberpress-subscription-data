<?php
/**
 * Plugin Name: MemberPress Subscription Data
 * Plugin URI: https://github.com/br3wb0n1k/memberpress-subscription-data
 * Description: This plugin adds meta data to Subscriptions and outputs fields on the Account form.
 * Version: 0.1.0
 * Author: Eric Breuers
 * Author URI: http://ericbreuers.com
 * License: GPL2
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Enqueue our stylesheet for admin only.
 */
function mepr_plugin_admin_init() {
  if ( is_admin() ) 
    wp_enqueue_style( 'mepr-sub-styles', plugin_dir_url( __FILE__ ) . '/styles/mepr-sub-styles.css' );
}
add_action( 'admin_enqueue_scripts', 'mepr_plugin_admin_init' );

//Signup form functions
function mepr_show_signup_fields() {
  ?>
    <div class="mp-form-row mepr_website_field">
      <div class="mp-form-label">
        <label>Website:*</label>
      </div>
      <input type="text" name="mepr_website_field" id="mepr_website_field" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_website_field']))?stripslashes($_POST['mepr_website_field']):''; ?>" />
    </div>
    <div class="mp-form-row mepr_website_field">
      <div class="mp-form-label">
        <label>WordPress Maintenance Admin Username:*</label>
      </div>
      <input type="text" name="mepr_wp_admin_username" id="mepr_wp_admin_username" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_wp_admin_username']))?stripslashes($_POST['mepr_wp_admin_username']):''; ?>" />
    </div>
    <div class="mp-form-row mepr_website_field">
      <div class="mp-form-label">
        <label>WordPress Maintenance Admin Password:*</label>
      </div>
      <input type="password" name="mepr_wp_admin_password" id="mepr_wp_admin_password" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_wp_admin_password']))?stripslashes($_POST['mepr_wp_admin_password']):''; ?>" />
    </div>
  <?php
}
function mepr_validate_fields($errors, $user = null) {
  if(!isset($_POST['mepr_website_field']) || empty($_POST['mepr_website_field'])) {
    $errors[] = "You must enter a website URL";
  }
  if(!isset($_POST['mepr_wp_admin_username']) || empty($_POST['mepr_wp_admin_username'])) {
    $errors[] = "You must enter a WordPress Admin Username";
  }
  if(!isset($_POST['mepr_wp_admin_password']) || empty($_POST['mepr_wp_admin_password'])) {
    $errors[] = "You must enter a WordPress Admin Password";
  }
  return $errors;
}
function mepr_save_signup_fields($amount, $user, $prod_id, $txn_id) {
  if(!isset($_POST['mepr_website_field']) || empty($_POST['mepr_website_field'])) {
    return; //Nothing to do bro!
  }
  $txn            = new MeprTransaction($txn_id);
  $sub            = $txn->subscription();
  $website_fields = get_user_meta($user->ID, 'mepr_custom_website_fields', true);
  if(!$website_fields) {
    $website_fields = array();
  }
  if($sub !== false && $sub instanceof MeprSubscription) {
    $website_fields[] = array('txn_id'    => $txn_id,
                              'recurring' => true,
                              'sub_id'    => $sub->ID,
                              'website'   => stripslashes($_POST['mepr_website_field']),
                              'wp_admin_user' => stripslashes($_POST['mepr_wp_admin_username']),
                              'wp_admin_pass' => stripslashes($_POST['mepr_wp_admin_password'])
    );
  }
  else {
    $website_fields[] = array('txn_id'    => $txn_id,
                              'recurring' => false,
                              'sub_id'    => 0,
                              'website'   => stripslashes($_POST['mepr_website_field']),
                              'wp_admin_user' => stripslashes($_POST['mepr_wp_admin_username']),
                              'wp_admin_pass' => stripslashes($_POST['mepr_wp_admin_password'])
    );
  }
  update_user_meta($user->ID, 'mepr_custom_website_fields', $website_fields);
}
//Signup form hooks
add_action('mepr-before-coupon-field', 'mepr_show_signup_fields');
add_filter('mepr-validate-signup', 'mepr_validate_fields');
add_action('mepr-process-signup', 'mepr_save_signup_fields', 10, 4);
//Account Subscriptions table Functions
function mepr_add_subscriptions_th($user, $subs) {
  ?>
    <th>Site</th>
  <?php
}
function mepr_add_subscriptions_td($user, $sub, $txn, $is_recurring) {
  $website = 'None';
  $website_fields = get_user_meta($user->ID, 'mepr_custom_website_fields', true);
  if($website_fields) {
    foreach($website_fields as $f) {
      if($is_recurring && $sub->ID == $f['sub_id']) {
        $website = $f['website'];
        break;
      }
      elseif(!$is_recurring && $txn->id == $f['txn_id']) {
        $website = $f['website'];
        break;
      }
    }
  }
  ?>
    <td data-label="Website">
      <div class="mepr-account-website"><?php echo $website; ?></div>
    </td>
  <?php
}
//Account Subscriptions table hooks
add_action('mepr-account-subscriptions-th', 'mepr_add_subscriptions_th', 10, 2);
add_action('mepr-account-subscriptions-td', 'mepr_add_subscriptions_td', 10, 4);
//Admin Subscriptions table functions
function mepr_add_admin_subscriptions_cols($cols, $prefix, $lifetime) {
  $cols[$prefix.'site'] = 'Site Info';
  return $cols;
}
//NOT NEEDED
// function mepr_add_admin_subscriptions_sortable_cols($cols, $prefix, $lifetime) {
  // $cols[$prefix.'site'] = false;
  // return $cols;
// }
function mepr_add_admin_subscriptions_cell($column_name, $rec, $table, $attributes) {
  $user = new MeprUser($rec->user_id);
  if(strpos($column_name, '_site') !== false && (int)$user->ID > 0) {
    $website = 'None';
    $mepr_user = 'None';
    $mepr_pass = 'None';
    $website_fields = get_user_meta($user->ID, 'mepr_custom_website_fields', true);
    if($website_fields) {
      foreach($website_fields as $f) {
        if(!$table->lifetime && $rec->ID == $f['sub_id']) {
          $website   = $f['website'];
          $mepr_user = $f['wp_admin_user'];
          $mepr_pass = $f['wp_admin_pass'];
          break;
        }
        elseif($table->lifetime && $rec->ID == $f['txn_id']) {
          $website = $f['website'];
          $mepr_user = $f['wp_admin_user'];
          $mepr_pass = $f['wp_admin_pass'];
          break;
        }
      }
    }
    ?>
      <td <?php echo $attributes; ?>>
        <strong><?php echo $website; ?></strong><br>
        <strong>User: <?php echo $mepr_user; ?></strong><br>
        <strong>Pass: <?php echo $mepr_pass; ?></strong><br>
      </td>
    <?php
  }
}
//Admin Subscriptions table hooks
add_filter('mepr-admin-subscriptions-cols', 'mepr_add_admin_subscriptions_cols', 10, 3);
// add_filter('mepr-admin-subscriptions-sortable-cols', 'mepr_add_admin_subscriptions_sortable_cols', 10, 3); //NOT NEEDED
add_action('mepr-admin-subscriptions-cell', 'mepr_add_admin_subscriptions_cell', 10, 4);
?>