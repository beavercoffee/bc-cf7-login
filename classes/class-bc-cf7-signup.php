<?php

if(!class_exists('BC_CF7_Signup')){
    final class BC_CF7_Signup {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	public static function get_instance($file = ''){
            if(null === self::$instance){
                if(@is_file($file)){
                    self::$instance = new self($file);
                } else {
                    wp_die(__('File doesn&#8217;t exist?'));
                }
            }
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $file = '';

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('wpcf7_mail_sent', [$this, 'wpcf7_mail_sent']);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('wpcf7_validate_email*', [$this, 'wpcf7_validate_email'], 11, 2);
            add_filter('wpcf7_validate_password*', [$this, 'wpcf7_validate_password'], 11, 2);
            add_filter('wpcf7_validate_text*', [$this, 'wpcf7_validate_text'], 11, 2);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function first_p($text = '', $dot = true){
            if(strpos($text, '.') === false){
                if($dot){
                    $text .= '.';
                }
                return $text;
            } else {
                $text = explode('.', $text, 2);
                $text = $text[0];
                if($dot){
                    $text .= '.';
                }
                return $text;
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function is_type($contact_form = null){
            if($contact_form === null){
                return false;
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return false;
            }
            if($type !== 'signup'){
                return false;
            }
            return true;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
            if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if($contact_form === null){
                return $output;
            }
            if(!$this->is_type($contact_form)){
                return $output;
            }
            $tags = wp_list_pluck($contact_form->scan_form_tags(), 'type', 'name');
            $missing = [];
            if(!isset($tags['user_email'])){
                $missing[] = 'user_email';
            }
            if(isset($tags['user_password_confirm']) and !isset($tags['user_password'])){
                $missing[] = 'user_password';
            }
            if($missing){
                $error = current_user_can('manage_options') ? sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            $invalid = [];
            if(isset($tags['user_email']) and $tags['user_email'] !== 'email*'){
                $invalid[] = 'user_email';
            }
            if(isset($tags['user_login']) and $tags['user_login'] !== 'text*'){
                $invalid[] = 'user_login';
            }
            if(isset($tags['user_password']) and $tags['user_password'] !== 'password*'){
                $invalid[] = 'user_password';
            }
            if(isset($tags['user_password_confirm']) and $tags['user_password_confirm'] !== 'password*'){
                $invalid[] = 'user_password_confirm';
            }
            if($invalid){
                $error = current_user_can('manage_options') ? sprintf(__('Invalid parameter(s): %s'), implode(', ', $invalid)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            if(is_user_logged_in() and !current_user_can('create_users')){
                $error = __('Sorry, you are not allowed to create new users.') . ' ' . __('You need a higher level of permission.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_mail_sent($contact_form){
            if(!$this->is_type($contact_form)){
                return;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return;
            }
            $user_email = $submission->get_posted_data('user_email');
            $user_login = $submission->get_posted_data('user_login');
            $user_password = $submission->get_posted_data('user_password');
            if(null === $user_login){
                $user_login = $user_email;
            }
            $generated_password = false;
            if(null === $user_password){
                $generated_password = true;
                $user_password = wp_generate_password(12, false);
            }
            $role = $contact_form->pref('bc_signup_role');
            if(null === $role){
                $role = get_option('default_role');
            }
            $userdata = [
                'role' => $role,
                'user_email' => wp_slash($user_email),
                'user_login' => wp_slash($user_login),
                'user_pass' => $user_password,
            ];
            $user_id = wp_insert_user($userdata);
            if(is_wp_error($user_id)){
                $message = $user_id->get_error_message();
                $submission->set_response(wp_strip_all_tags($message));
                $submission->set_status('aborted');
                return;
            }
            $posted_data = $submission->get_posted_data();
            if(isset($posted_data['user_email'])){
                unset($posted_data['user_email']);
            }
            if(isset($posted_data['user_login'])){
                unset($posted_data['user_login']);
            }
            if(isset($posted_data['user_password'])){
                unset($posted_data['user_password']);
            }
            if(isset($posted_data['user_password_confirm'])){
                unset($posted_data['user_password_confirm']);
            }
            if($posted_data){
                foreach($posted_data as $key => $value){
                    if(is_array($value)){
    					delete_user_meta($user_id, $key);
    					foreach($value as $single){
    						add_user_meta($user_id, $key, $single);
    					}
    				} else {
    					update_user_meta($user_id, $key, $value);
    				}
                }
            }
            if(!$contact_form->is_true('skip_mail')){
                if($generated_password){
                    wp_new_user_notification($user_id, null, 'both');
                } else {
                    wp_new_user_notification($user_id, null, 'admin');
                }
            }
            $message = sprintf(__('Registration complete. Please check your email, then visit the <a href="%s">login page</a>.'), wp_login_url());
            if(!$generated_password){
                $message = $this->first_p($message);
            }
            $submission->set_response(wp_strip_all_tags($message));
            if($contact_form->is_true('bc_login')){
                if(!is_user_logged_in()){
                    $user = wp_signon([
                        'remember' => $submission->get_posted_data('remember'),
                        'user_login' => $user_login,
                        'user_password' => $user_password,
                    ]);
                    if(is_wp_error($user)){
                        $message = $user->get_error_message();
                        $submission->set_response(wp_strip_all_tags($message));
                        $submission->set_status('aborted');
                        return;
                    }
                    $message = __('You have logged in successfully.');
                    $submission->set_response(wp_strip_all_tags($message));
                }
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_email($result, $tag){
            if('user_email' !== $tag->name){
                return $result;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if(null === $contact_form){
                return $result;
            }
            if(!$this->is_type($contact_form)){
                return $result;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return $result;
            }
            $user_email = $submission->get_posted_data('user_email');
            if(email_exists($user_email)){
                $message = __('<strong>Error</strong>: This email is already registered. Please choose another one.');
                $message = $this->first_p($message);
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_password($result, $tag){
            if(!in_array($tag->name, ['user_password', 'user_password_confirm'])){
                return $result;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if(null === $contact_form){
                return $result;
            }
            if(!$this->is_type($contact_form)){
                return $result;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return $result;
            }
            $user_password = $submission->get_posted_data('user_password');
            $user_password_confirm = $submission->get_posted_data('user_password_confirm');
            switch($tag->name){
                case 'user_password':
                    if(false !== strpos(wp_unslash($user_password), '\\')){
                        $message = __('<strong>Error</strong>: Passwords may not contain the character "\\".');
                        $result->invalidate($tag, wp_strip_all_tags($message));
                        return $result;
                    }
                    break;
                case 'user_password_confirm':
                    if($user_password_confirm !== $user_password){
                        $message = __('<strong>Error</strong>: Passwords don&#8217;t match. Please enter the same password in both password fields.');
                        $message = $this->first_p($message);
                        $result->invalidate($tag, wp_strip_all_tags($message));
                        return $result;
                    }
                    break;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_text($result, $tag){
            if('user_login' !== $tag->name){
                return $result;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if(null === $contact_form){
                return $result;
            }
            if(!$this->is_type($contact_form)){
                return $result;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return $result;
            }
            $user_login = $submission->get_posted_data('user_login');
            if(!validate_username($user_login)){
                $message = __('<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.');
                $message = $this->first_p($message);
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            $illegal_user_logins = (array) apply_filters('illegal_user_logins', ['admin']);
            if(in_array(strtolower($user_login), array_map('strtolower', $illegal_user_logins), true)){
                $message = __('<strong>Error</strong>: Sorry, that username is not allowed.');
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            if(username_exists($user_login)){
                $message = __('<strong>Error</strong>: This username is already registered. Please choose another one.');
                $message = $this->first_p($message);
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            if(wpcf7_is_email($user_login)){
                if(email_exists($user_login)){
                    $message = __('<strong>Error</strong>: This email is already registered. Please choose another one.');
                    $message = $this->first_p($message);
                    $result->invalidate($tag, wp_strip_all_tags($message));
                    return $result;
                }
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
