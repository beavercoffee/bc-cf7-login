<?php
/*
Author: Beaver Coffee
Author URI: https://beaver.coffee
Description: Log In (and Sign Up) with Contact Form 7.
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: BC CF7 Login
Plugin URI: https://github.com/beavercoffee/bc-cf7-login
Requires at least: 5.7
Requires PHP: 5.6
Text Domain: bc-cf7-login
Version: 1.6.20
*/

if(defined('ABSPATH')){
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-login.php');
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-logout.php');
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-retrieve-password.php');
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-signup.php');
    BC_CF7_Login::get_instance(__FILE__);
    BC_CF7_Logout::get_instance(__FILE__);
    BC_CF7_Retrieve_Password::get_instance(__FILE__);
    BC_CF7_Signup::get_instance(__FILE__);
}
