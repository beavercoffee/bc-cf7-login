<?php

if(!class_exists('BC_CF7_Retrieve_Password')){
    final class BC_CF7_Retrieve_Password {

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
            if($type !== 'retrieve_password'){
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
            if(isset($tags['user_email']) and isset($tags['user_login'])){
                $error = current_user_can('manage_options') ? str_replace('.', ':', __('Invalid user parameter(s).')) . ' ' . __('Duplicated username or email address.') : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            $missing = [];
            if(!isset($tags['user_email']) and !isset($tags['user_login'])){
                $missing[] = 'user_login';
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
            if($invalid){
                $error = current_user_can('manage_options') ? sprintf(__('Invalid parameter(s): %s'), implode(', ', $invalid)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            if(is_user_logged_in()){
                $error = __('You are logged in already. No need to register again!');
                $error = $this->first_p($error);
                return '<div class="alert alert-success" role="alert">' . $error . '</div>';
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
            if(null === $user_login){
                $user_login = $user_email;
            }
            $result = retrieve_password($user_login);
            if(is_wp_error($result)){
                $message = $result->get_error_message();
                $submission->set_response(wp_strip_all_tags($message));
                $submission->set_status('aborted');
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
            if(!email_exists($user_email)){
                $message = __('Unknown email address. Check again or try your username.');
                $message = $this->first_p($message);
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_text($result, $tag){
            if($tag->name !== 'user_login'){
                return $result;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if($contact_form === null){
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
            $message = __('Unknown username. Check again or try your email address.');
            $user = get_user_by('login', $user_login);
            if(!$user and wpcf7_is_email($user_login)){
                $message = __('Unknown email address. Check again or try your username.');
                $user = get_user_by('email', $user_login);
            }
            if(!$user){
                $message = $this->first_p($message);
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
