<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * @Author: Abdullah A Almsaeed
 * @Date: Sep 23, 2013
 * @link https://github.com/almasaeed2010/CI_AuthLTE Documentation and download link
 * @Abstract:
 *  Authorization Library. Controls all authintication processes such as login,
 *      logout and registering new users
 *  Main features:
 *      1- PHPass for password encryption
 *      2- Limited login attempts
 *      3- Secured remember me with limited time period
 *      4- Forgetton password help
 *      5- Optional activation email
 *      6- Groups and priviledges control
 * @license http://opensource.org/licenses/MIT MIT
 *
 * The MIT License (MIT)
 *
 * @Copyright (c) 2013 Almsaeed
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */
class Auth_model extends CI_Model {

    /**
     * Library vars
     * @var mixed Holds the status messages
     * @var mixed Holds the error messages
     * @var settings Holds the library settings
     */
    private $status;
    private $errors;
    private $settings;

    public function __construct() {
        parent::__construct();

        //Load database driver
        $this->load->database();
        //Load session library
        $this->load->library('session');

        //Ini variables
        $this->status = array();
        $this->errors = array();

        //Load language file
        $this->lang->load('auth');

        //Ini settengs data
        $this->settings = array(
            //Website Title. This is used when sending emails            
            "website_title" => 'This is an Example',
            //Automatically sign user in after registration
            "auto_sign_in" => FALSE,
            //send verification email upon registration
            "verify_email" => FALSE,
            //Remember me cookies names
            "remember_me_cookie_name" => 'hhp_remember_me_tk',
            //Remember me cookie expiration time in SECONDS
            "remember_me_cookie_expiration" => 60 * 60 * 24 * 30, #Default 30 days
            //ip login attempts limit
            "ip_login_limit" => 10,
            //Identity login attempts limit
            "identity_login_limit" => 10,
            //Ban time in SECONDS if login attempts excedded.
            "ban_time" => 10, #Default = 10 Sec
            //When should the password reset email expire? in seconds
            "password_reset_date_limit" => 60 * 3, #Default = 2 hours
            //This is the email used to send emails through the website
            "webmaster_email" => 'no_reply@example.com',
            //Email templates. NOTE: templates must be in the application/views folder
            //and must end with .php extension
            "email_templates" => array(
                //Template for activating account
                "activation" => "emails/email_verification",
                //Template for a forgotten password retrieval
                "password_reset" => "emails/password_reset",
            ),
            //Email type. Either text or html
            "email_type" => 'html',
            //Links to be included in the emails
            "links" => array(
                //controler/method
                //The method should accept paramaters
                //Do not change the vars in the links below unless you know 
                //what you are doing.
                'activation' => 'users/activate', //1st param user_id. 2nd param token
                'password_reset' => 'users/reset_password' //1st param user_id. 2nd param token
            )
        );
    }

    /**
     * Create new user account
     *
     * $custom_data example
     *
     * $custom_data = array("column" => "value", "column2" => "value2");
     *
     * To activate a user immedietly add this to your custom data array
     *
     * $custom_data = array("is_active" => 1);
     * @param string $email
     * @param string $password
     * @param int $group_id
     * @param mixed $custom_data
     * @return mixed user_id on success or FALSE otherwise
     */
    public function create_account($email, $password, $group_id = NULL, $custom_data = NULL) {
        //Make sure the email is unique
        if ($this->email_exists($email)) {
            //Set error message and return FALSE
            $this->set_error_message('auth.email_exists');
            return FALSE;
        }

        //Load PHPass library to encrypt password
        $this->load->library('phpass');

        //Encrupt pass
        $encrypted_pass = $this->phpass->hash($password);

        //Insert data and return;
        $this->db->set('email', $email);
        $this->db->set('password', $encrypted_pass);
        //Add date of registration
        $this->db->set('date_added', 'NOW()', FALSE);
        //Add group id
        if (!empty($group_id) && $group_id > 0) {
            $this->db->set('group_id', $group_id);
        }
        //Add custom data
        if (!empty($custom_data) && is_array($custom_data)) {
            foreach ($custom_data as $key => $value) {
                //The key would be the column name
                //The value should be the value to add
                $this->db->set($key, $value);
            }
        }

        //Run query
        $this->db->insert('accounts');

        //Insert ID || user id
        $user_id = $this->db->insert_id();

        //Send verification email if config is set to true
        if ($this->settings['verify_email']) {
            $this->send_verification_email($user_id);
        }

        //Return user id
        return $user_id;
    }

    /**
     * Activate user account
     * @param int $user_id
     * @return int number of affected rows
     */
    public function activate($user_id) {
        //Validate user id
        if (!$user_id || $user_id < 1) {
            return FALSE;
        }
        //Activate user
        $this->db->set('is_active', 1);
        $this->db->where('user_id', $user_id);
        $this->db->update('accounts');
        //return 1 or 0
        return $this->db->affected_rows();
    }

    /**
     * Deactivate user account
     * @param int $user_id
     * @return int affected rows
     */
    public function deactivate_account($user_id) {

        $this->db->set('is_active', 0);
        $this->db->where('user_id', $user_id);
        $this->db->update('accounts');
        return $this->db->affected_rows();
    }

    /**
     * Edit user custom data
     * $data example:
     * $data = array('email' => 'value@example.com', 'column' => 'value');
     *
     * @param int $user_id
     * @param array $data
     */
    public function edit_user($user_id, $data = array()) {

        if (!$user_id || !is_array($data)) {
            //Error: Invalid arguments
            $this->set_error_message('auth.invalid_args', 'Auth::edit_user');
        }

        $this->db->where($user_id);
    }

    /**
     * Perform login operation
     * @param string $email
     * @param string $password
     * @param bool $remember_me
     * @return boolean
     */
    public function login($email, $password, $remember_me = FALSE) {

        //Check database records to match identity
        $this->db->where('email', $email);
        $this->db->limit(1);
        $query = $this->db->get('accounts');

        //If email found in db, check password
        if ($query->num_rows() < 1) {
            //Set error message and return false
            $this->set_error_message('auth.invalid_credentials');
            return FALSE;
        }

        //fetch user account row
        $row = $query->row();

        //Check if user has a ban
        $now = new DateTime('now');
        $login_ban = new DateTime($row->login_ban);
        if ($login_ban >= $now) {
            $this->set_error_message('auth.user_banned');
            return FALSE;
        }

        //Check if the number of failed logins exceeds the limit
        if ((int) $row->login_attempts >= $this->settings['identity_login_limit']) {
            //Add ban to user identity
            $now->modify("+{$this->settings['ban_time']} SECONDS");
            $login_ban_until = $now->format('Y-m-d H:i:s');
            $this->db->set('login_ban', $login_ban_until);
            $this->db->where('user_id', $row->user_id);
            $this->db->update('accounts');

            //Reset faild login attempts
            $this->_reset_attempts($row->user_id);
            $this->set_error_message('auth.user_banned');
            return FALSE;
        }

        //Check ip ban
        if ($this->ip_ban()) {
            //Set error message and return false
            $this->set_error_message('auth.user_banned');
            return FALSE;
        }

        //Load encryption library phpass
        $this->load->library('phpass');

        //Check password
        if (!$this->phpass->check($password, $row->password)) {
            //Password not found
            $this->set_error_message('auth.invalid_credentials');

            //Increment failed login attempt
            $this->_increment_login_attempts($row->user_id, $row->login_attempts);
            return FALSE;
        }

        //User has valid credentials
        if ($row->login_attempts > 0) {
            //Reset faild login attempts
            $this->_reset_attempts($row->user_id);
        }

        //Set session
        $this->_set_session($row, TRUE);

        //Set remember me cookie if set to true
        if ($remember_me) {
            $this->set_remember_me_cookie($row->user_id);
        }

        return TRUE;
    }

    /**
     * check if user has ban by ip address
     * @return boolean
     */
    private function ip_ban() {
        //Check database records to match IP address
        //We only need the ip that has a ban on it
        $this->db->where('ip', $this->input->ip_address());
        $this->db->where('number_of_attempts >=', (int) $this->settings['ip_login_limit']);
        $this->db->where("DATE_ADD(last_failed_attempt, INTERVAL {$this->settings['ban_time']} SECOND) <= ", 'NOW()', FALSE);
        $ip_query = $this->db->get('ip_attempts');
        //If record exists return true
        if ($ip_query->num_rows() > 0) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     *
     * @param object $user
     * @param bool $via_password
     * @return boolean
     */
    private function _set_session($user, $via_password = FALSE) {

        if (!$user->user_id) {
            $this->set_error_message('');
            return FALSE;
        }

        if ($user->group_id) {
            $query = $this->db->select("is_admin")
                    ->where('group_id', $user->group_id)
                    ->get('groups');
        }

        $group = $query->row();

        $sess_data = array(
            "is_logged_in" => TRUE,
            "is_admin" => (bool) $group->is_admin,
            "via_password" => (bool) $via_password,
            "user_id" => (int) $user->user_id
        );

        $this->session->set_userdata($sess_data);
    }

    /**
     * Check if user is logged in. The function will check if the user is remembered
     * only if the user is not logged in.
     *
     * @return bool
     */
    public function is_logged_in() {
        //Check if session is available
        if ($this->session->userdata('is_logged_in'))
            return TRUE;

        //Check if user is remembered
        if ($this->is_remembered())
            return TRUE;

        //User has not logged in
        return FALSE;
    }

    /**
     * Checks for remember me cookie and match against database
     * @return boolean
     */
    public function is_remembered() {
        //Get cookies
        $cookie = $this->fetch_remember_me_cookie();

        if (!$cookie) {
            return FALSE;
        }

        $user_id = $cookie['user_id'];
        $token = $cookie['token'];

        //Match token against database
        //Load token from database
        $where = array('user_id' => $user_id);
        $this->db->where($where);
        $query = $this->db->get('remembered_users');
        //Check to see if user_id->token association exists
        if ($query->num_rows() < 1) {
            return FALSE;
        }

        //Encrypted token match control
        $token_found = FALSE;

        $this->load->library('phpass');

        //Check if the user_id->token match our encrypted records in the database
        foreach ($query->result() as $row) {
            if ($this->phpass->check($token, $row->token)) {
                //Token found
                $token_found = TRUE;
                break;
            }
        }

        //Token has not been matched
        if (!$token_found) {
            //Remove the invalid cookie
            $this->unset_remember_me_cookie($user_id);
            return FALSE;
        }

        //Get user account data
        $user = $this->db->get_where('accounts', $where)->row();

        //Set login session
        //User has not logged in via password
        $this->_set_session($user, FALSE);

        //Regenerate remember me token
        //Delete old cookie
        $this->unset_remember_me_cookie($user->user_id);
        //Generate new cookie. The new cookie will have the
        //life of the settings' expiration date
        $this->set_remember_me_cookie($user->user_id);
        return TRUE;
    }

    /**
     * Check if user has logged in via password
     * @return mixed
     */
    public function is_logged_in_via_password() {
        return $this->session->userdata('via_password');
    }

    /**
     * Returns the user id of the logged in user
     * @return False if user is not logged in | (int) ID otherwise
     */
    public function user_id() {
        return $this->session->userdata('user_id');
    }

    /**
     * check if user is logged in and is admin
     * @return bool
     */
    public function is_admin() {
        return $this->session->userdata('is_admin');
    }

    /**
     * Get all session data
     * @return mixed
     */
    public function session_data() {
        return $this->session->all_userdata();
    }

    /**
     * Perform logout operation
     * @return void
     */
    public function logout() {
        //Unset rember me cookie
        $this->unset_remember_me_cookie($this->user_id());
        //Distroy session
        $this->session->sess_destroy(); //unset_userdata($sess_data);
    }

    /**
     * Get failed login attempts login attempts
     *
     * @param int $user_id
     * @return int
     */
    public function login_attempts($user_id) {
        $this->db->select('login_attempts');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('accounts');
        //If user exists, return login attempts
        if ($query->num_rows() > 0) {
            return $query->row()->login_attempts;
        }

        return 0;
    }

    /**
     * Adds 1 to the failed login attempts
     *
     * @param int $user_id
     * @param int $attempts
     * @return int affected rows
     */
    private function _increment_login_attempts($user_id, $attempts = 0) {
        //Increment identity login attempts
        $this->db->set('login_attempts', $attempts + 1);
        $this->db->where('user_id', $user_id);
        $this->db->update('accounts');
        $update_affected_rows = $this->db->affected_rows();

        //Check if ip exists
        $this->db->select('number_of_attempts');
        $this->db->where('ip', $this->input->ip_address());
        $query = $this->db->get('ip_attempts');
        if ($query->num_rows() > 0) {
            $row = $query->row();
            //Increment ip login attempts
            $this->db->set('number_of_attempts', $row->number_of_attempts + 1);
            $this->db->set('last_failed_attempt', 'NOW()', FALSE);
            $this->db->update('ip_attempts');
        } else {
            //Insert first attempt
            $this->db->set('ip', $this->input->ip_address());
            $this->db->set('number_of_attempts', 1);
            $this->db->set('last_failed_attempt', 'NOW()', FALSE);
            $this->db->insert('ip_attempts');
        }

        return $this->db->affected_rows() + $update_affected_rows;
    }

    /**
     * Reset login attempts
     *
     * @param type $user_id
     * @return (int) affected rows
     */
    private function _reset_attempts($user_id) {
        //reset Identity failed login attempts
        $this->db->set('login_attempts', 0);
        $this->db->where('user_id', $user_id);
        $this->db->update('accounts');
        $u = $this->db->affected_rows();

        //reset ip failed login attempts
        $this->db->set('number_of_attempts', 0);
        $this->db->where('ip', $this->input->ip_address());
        $this->db->update('ip_attempts');

        return $this->db->affected_rows() + $u;
    }

    /**
     * Create remember me cookie
     *
     * @param (int) $user_id
     * @param (int) $expiration
     * @return boolean
     */
    public function set_remember_me_cookie($user_id = NULL, $expiration = NULL) {
        //Check user id availability
        if (empty($user_id)) {
            $user_id = $this->user_id();
            if (!$user_id) {
                $this->set_error_message('auth.user_id_unavailable');
                return FALSE;
            }
        }

        //Validate expiration time value
        if (empty($expiration) || !is_numeric($expiration)) {
            $expiration = $this->settings['remember_me_cookie_expiration'];
        }

        //Generate value token
        $token = $this->generate_remember_me_token($user_id);

        //Set cookie data array
        $cookie = array(
            'name' => $this->settings['remember_me_cookie_name'],
            'value' => $user_id . ':' . $token,
            'expire' => $expiration
        );
        $this->input->set_cookie($cookie);

        return TRUE;
    }

    /**
     * generate remember me token
     * @param int user_id
     */
    private function generate_remember_me_token($user_id) {
        //Load PHPass library to encrypt password
        $this->load->library('phpass');

        //Generate a random token and encrypt it
        $random = $this->_rand_str(32);
        $token = $this->phpass->hash($random);

        //Store tocken in the database
        $this->db->set('token', $token);
        $this->db->set('user_id', $user_id);
        $this->db->set('date', 'NOW()', FALSE);
        $this->db->insert('remembered_users');

        return $random;
    }

    /**
     * Delete remember me cookie
     * @param int $user_id
     * @return void
     */
    public function unset_remember_me_cookie($user_id) {
        $cookie_data = $this->fetch_remember_me_cookie();

        //Set cookie data array
        $cookie = array(
            'name' => $this->settings['remember_me_cookie_name'],
            'value' => '',
            'expire' => 0
        );
        $this->input->set_cookie($cookie);

        //Check cookie data validity
        if (!$cookie_data)
            return;

        //Remove record of token from database
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('remembered_users');
        if ($query->num_rows() > 0) {
            //Load Phpass Library
            $connection_id = FALSE;
            $this->load->library('phpass');
            foreach ($query->result() as $row) {
                if ($this->phpass->check($cookie_data['token'], $row->token)) {
                    $connection_id = $row->connection_id;
                    break;
                }
            }
            if ($connection_id) {
                $this->db->where('connection_id', $connection_id);
                $this->db->delete('remembered_users');
            }
        }

        return;
    }

    /**
     * Gets the data of the remember me cookie
     * @return mixed array ("user_id" => 'id', "token" => 'hashed token') | FALSE if cookie does not exits
     */
    private function fetch_remember_me_cookie() {
        //Get cookies
        $cookie = $this->input->cookie($this->config->item('cookie_prefix') . $this->settings['remember_me_cookie_name'], TRUE);
        if (!$cookie) {
            //Cookie does not exist
            return FALSE;
        }

        //Cookie value must contain the : char and must be larger than 32 chars
        if (strlen($cookie) < 32) {
            //Invalid cookie
            return FALSE;
        }

        $data = explode(':', $cookie);

        if (!is_array($data) && count($data) == 2) {
            return FALSE;
        }

        //Make sure value exist
        if (empty($data[0]) || empty($data[1])) {
            return FALSE;
        }

        return array("user_id" => $data[0], "token" => $data[1]);
    }

    /**
     * Send activtion email
     * @param int $user_id The user id
     * 
     * @return boolean
     * 
     */
    public function send_verification_email($user_id) {
        //Get user from db   
        $this->db->select('email');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('accounts');
        if($query->num_rows() != 1) {
            //User does not exist
            return FALSE;
        }
        
        //Fetch data
        $row = $query->row();
        
        //Generate token
        $token = $this->_generate_email_verfication_token($user_id);
        
        //URL Helper
        $this->load->helper('url');
        
        //create link
        $link = $this->settings['links']['email_verification']
                . "/{$user_id}/{$token}";
        $data['link'] = site_url($link);
        
        //email configration        
        $this->load->library('email');
        $from = $this->settings['webmaster_email'];
        $website_title = $this->settings['website_title'];
        $to = $row->email;
        $subject = lang('auth.email_verification_email_subject');
        $message = $this->load->view($this->settings['email_templates']['activation'], $data, TRUE);

        //Email settings
        $config['mailtype'] = $this->settings['email_type'];
        $this->email->initialize($config);

        //Set email
        $this->email->from($from, $website_title);
        $this->email->to($to);
        $this->email->subject($subject);
        $this->email->message($message);
        //Send
        $this->email->send();

        return TRUE;
        
    }
    
    /**
     * Generate email verification token
     * @param int $user_id The user id
     * @return string
     */
    private function _generate_email_verfication_token($user_id) {
        $token = $this->_rand_str(32);
        
        $this->load->library('phpass');
        $encrypted_token = $this->phpass->hash($token);
        $this->db->set('email_verification_tk', $encrypted_token);
        $this->db->set('email_verification_date', 'NOW()');
        $this->db->where('user_id', $user_id);
        $this->db->update('accounts');
        
        return $token;
    }
    
    /**
     * Verify that the user clicked a valid verification email link.     
     * @param int $user_id
     * @param string $token
     * 
     * @return boolean If the user has a valid token and expiration date, TRUE is
     * returned. Otherwise, FALSE.
     */
    public function verify_email_verification_token($user_id, $token) {
        //Get hashed token and token generation date
        $this->db->select('email_verfication_tk AS token, email_verfication_date AS date');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('accounts');
        
        if($query->num_rows() != 1) {
            //Invalid user id
            return FALSE;
        }
        
        //Fetch user data
        $row = $query->row();
        
        //Check token expiration date
        $now = new DateTime('now');
        $tk_date = new DateTime($row->date);
        $tk_date->modify("+{$this->settings['password_reset_date_limit']} SECONDS");

        if ($tk_date >= $now) {
            //Token has expired
            return FALSE;
        }
        
        //Load phpass
        $this->load->library('phpass');
        //Check token
        if(!$this->phpass->check($token, $row->token)) {
            //Invalid token
        }
    }


    /**
     * Reset user password
     * @param type $user_id
     * @param type $new_password
     * 
     * @return boolean
     */
    public function reset_password($user_id, $new_password) {
        //Hash password and store it in DB
        $this->load->library('phpass');
        $encrypted = $this->phpass->hash($new_password);
        $this->db->set('password', $encrypted);
        //Reset token
        $this->db->set('reset_password_tk', '');
        $this->db->where('user_id', $user_id);
        $this->db->update('accounts');

        return $this->db->affected_rows();
    }

    /**
     * Verify that the provided password reset token is correct.
     * Use this function if you are using the send_reset_password_email() fumction
     * before calling the reset_password() function.
     * 
     * @param string $email
     * @param string $token
     * 
     * @return boolean
     */
    public function verify_password_reset_tk($email, $token) {
        $this->db->select('reset_password_tk, reset_password_tk_date');
        $this->db->where('email', $email);
        $query = $this->db->get('accounts');

        if ($query->num_rows() != 1) {
            //email does not exist
            return FALSE;
        }

        //fetch row
        $row = $query->row();

        //chack if email expired
        if (empty($row->reset_password_tk)) {
            //Token does not exist
            return FALSE;
        }

        $now = new DateTime('now');
        $tk_date = new DateTime($row->reset_password_tk_date);
        $tk_date->modify("+{$this->settings['password_reset_date_limit']} SECONDS");;

        if ($tk_date >= $now) {
            //Token has expired
            return FALSE;
        }

        $this->load->library('phpass');
        if (!$this->phpass->check($token, $row->reset_password_tk)) {
            //Token does not match
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Send reset password email
     * @param string $email
     * @return boolean True if sending was successful | False otherwise
     */
    public function send_reset_password_email($email) {
        $this->load->helper('url');
        $email = trim($email);
        //Check if email exists
        $this->db->select('user_id');
        $this->db->where('email', $email);
        $query = $this->db->get('accounts');

        if ($query->num_rows() != 1) {
            //Email does not exist
            return FALSE;
        }

        //fetch
        $row = $query->row();

        //generate token and hash it
        $token = $this->_rand_str();

        //load phpass
        $this->load->library('phpass');
        $encrypted_token = $this->phpass->hash($token);

        //Store token in DB
        $this->db->set('reset_password_tk', $encrypted_token);
        $this->db->set('reset_password_tk_date', 'NOW()', FALSE);
        $this->db->where('user_id', $row->user_id);
        $this->db->update('accounts');

        //Edit email to make it ok to pass through the url
        $email_address = explode('@', $email);
        //Generate link
        $link = $this->settings['links']['password_reset']
                . "/{$row->user_id}/{$token}";

        //email configration        
        $this->load->library('email');
        $from = $this->settings['webmaster_email'];
        $website_title = $this->settings['website_title'];
        $to = $email;
        $subject = lang('auth.password_reset_email_subject');
        $data['link'] = site_url($link);
        $message = $this->load->view($this->settings['email_templates']['password_reset'], $data, TRUE);

        //Email settings
        $config['mailtype'] = $this->settings['email_type'];
        $this->email->initialize($config);

        //Set email
        $this->email->from($from, $website_title);
        $this->email->to($to);
        $this->email->subject($subject);
        $this->email->message($message);
        //Send
        $this->email->send();

        return TRUE;
    }

    /**
     * Check if email exists in database
     * @param string $email
     * @return boolean
     */
    public function email_exists($email) {
        //Check database
        $this->db->select('user_id');
        $this->db->where('email', $email);
        $num = $this->db
                ->get('accounts')
                ->num_rows();
        //if num > 0 then emails exists so return true, false otherwise
        return ($num > 0);
    }

    /**
     * Make user a member of a group
     *
     * @param type $user_id
     * @param type $group_id
     * @return int affected rows
     */
    public function set_user_group($user_id, $group_id) {
        $this->db->set('group_id', $group_id);
        $this->db->where("user_id", $user_id);
        $this->db->update('accounts');

        return $this->db->affected_rows();
    }

    /**
     *
     * @param int $user_id
     * @param int $privilege_id
     * @return mixed number of affected rows | FALSE if connection exisits previously
     */
    public function add_privilege_to_user($user_id, $privilege_id) {
        //Make sure user hasn't the privilege already
        $this->db->where('user_id', $user_id);
        $this->db->where('privilege_id', $privilege_id);
        $query = $this->db->get('account_privilege');
        if ($query->num_rows() > 0) {
            $this->set_error_message('auth.privilege_connection_exists');
            return FALSE;
        }

        //Connect new privilege to user
        $this->db->set('user_id', $user_id);
        $this->db->set('privilege_id', $privilege_id);
        $this->db->insert('account_privilege');

        return $this->db->affected_rows();
    }

    /**
     * Create group
     *
     * @param string $name
     * @param string $description
     * @param bool $is_admin
     * @return boolean
     */
    public function create_group($name, $description, $is_admin = FALSE) {

        //Name has to be unique
        $this->db->select('group_id');
        $this->db->where('name', $name);
        $num = $this->db->get('groups')->num_rows();
        if ($num > 0) {
            $this->set_error_message('auth.group_name_exists');
            return FALSE;
        }

        $this->db->set('name', $name);
        $this->db->set('description', $description);
        $this->db->set('is_admin', $is_admin);
        $this->db->insert('groups');

        return $this->db->insert_id();
    }

    /**
     * Create priviledge
     * @param string $name
     * @param string $description
     * @return int privilege id
     */
    public function create_priviledge($name, $description) {
        //Privilege name must be unique
        $this->db->select('privilege_id');
        $this->db->where('name', $name);
        $num = $this->db->get('privileges')->num_rows();
        if ($num > 0) {
            $this->set_error_message('auth.privilege_name_exists');
            return FALSE;
        }

        $this->db->set('name', $name);
        $this->db->set('description', $description);
        $this->db->insert('privileges');

        return $this->db->insert_id();
    }

    /**
     * connect a priviledge to a group
     * The param $unique is to test if the connection is unique before insertion
     *
     * @param int $priviledge_id
     * @param int $group_id
     * @param bool $unique
     * @return boolean
     */
    public function connect_privilede_to_group($priviledge_id, $group_id, $unique = TRUE) {
        $insert = TRUE;
        if ($unique) {
            //Make sure the connection is unique
            $this->db->where('group_id', $group_id);
            $this->db->where('privilege_id', $priviledge_id);
            $query = $this->db->get('group_privilege');

            $num = $query->num_rows();

            if ($num > 0)
                $insert = FALSE;
        }

        if ($insert) {
            $this->db->set('privilege_id', $priviledge_id);
            $this->db->set('group_id', $group_id);
            $this->db->insert('group_privilege');

            return $this->db->insert_id();
        }

        $this->set_error_message('auth.priv_group_connection_exists');
        return FALSE;
    }

    /**
     * Does user have privilege
     * @param int $privilege_id
     * @param int $user_id
     *
     * @return (mixed) False if connection exists | int number of rows affected on success
     */
    public function has_privilege($privilege_id, $user_id = NULL) {
        $this->db->select('connection_id');
        $this->db->where(array('user_id' => $user_id, 'privilege_id' => $privilege_id));
        $num = $this->db->get('account_privilege')->num_rows();
        if ($num > 0) {
            $this->set_error_message('auth.privilege_connection_exists');
            return FALSE;
        }

        $this->db->set('privilege_id', $privilege_id);
        $this->db->set('user_id', $user_id);
        $this->db->insert('account_privilege');

        return $this->db->affected_rows();
    }

    /**
     * Is user in group
     * @param int $group_id Must provide the group id
     * @param int $user_id Leave null if checking current logged in user
     */
    public function in_group($group_id, $user_id = NULL) {
        if (empty($user_id)) {
            $user_id = $this->user_id();
            if (!$user_id) {
                $this->set_error_message('auth.user_id_param');
                return FALSE;
            }
        }

        $this->db->select('group_id');
        $this->db->where('user_id', $user_id);
        $this->db->where('group_id', $group_id);
        $query = $this->db->get('accounts');

        if ($query->num_rows() < 1)
            return FALSE;
    }

    /**
     * list all groups
     *
     * @return object If no groups found the function will return null.
     * If groups were found the object structure will be as follows:
     *
     * $obj->group_id
     *
     * $obj->name
     *
     * $obj->description
     *
     */
    public function list_groups() {
        $query = $this->db->get('groups');

        if ($query->num_rows() > 0)
            return $query->result();

        return NULL;
    }

    /**
     * List all privileges
     *
     * @return object If no privileges found the function will return null.
     * If privileges were found the object structure will be as follows:
     *
     * $obj->privilege_id
     *
     * $obj->name
     *
     * $obj->description
     */
    public function list_privileges() {
        $query = $this->db->get('privileges');

        if ($query->num_rows() > 0)
            return $query->result();

        return NULL;
    }

    /**
     * list groups and their connected priviliges
     * @return (object) If no connections found the function will return null.
     * If connections were found the object structure will be as follows:
     *
     * $obj->group_id
     *
     * $obj->privilege_id
     *
     * $obj->group_name
     *
     * $obj->privilege_name
     *
     * $obj->group_description
     *
     * $obj->privilege_description
     */
    public function list_groups_privileges() {
        $select = 'groups.group_id AS group_id, privileges.privilege_id AS privilege_id, '
                . 'privileges.name AS privilege_name, '
                . 'groups.name AS group_name, groups.description AS group_description, '
                . 'privileges.description AS privilege_description';

        $this->db->select($select);
        $this->db->join('groups', 'group_privilege.group_id = groups.group_id');
        $this->db->join('privileges', 'group_privilege.privilege_id = privileges.privilege_id');
        $query = $this->db->get('group_privilege');
        if ($query->num_rows() < 1) {
            return NULL;
        }

        return $query->result();
    }

    /**
     * Get the group of a user
     * @param int $user_id The user id
     * 
     * @return object If group found, an object of the following structure is returned.
     * 
     * $obj->group_id
     * 
     * $obj->name
     * 
     * $obj->description
     * 
     * Otherwise, (bool) FALSE is returned
     * 
     */
    public function user_group($user_id) {
        $this->db->select('group_id');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('accounts');
        if ($query->num_rows() != 1) {
            //User doesn't exists
            return false;
        }
        $user = $query->row();
        if ($user->group_id < 1) {
            //user is not in a group
            return FALSE;
        }

        $this->db->where('group_id', $user->group_id);
        $group_query = $this->db->get('groups');
        if ($group_query->num_rows() != 1) {
            //couldn't find group
            return false;
        }

        return $group_query->row();
    }

    /**
     * List the privileges a user is connected to
     * @param int $user_id
     * 
     * @return object If the user has privileges an object of the following structure is returned.
     * 
     * $obj->privilege_id
     * 
     * $obj->privilage_name
     * 
     * $obj->privilage_description
     * 
     * Otherwise, (bool) FALSE is returned
     */
    public function list_user_privilege($user_id) {
        $select = 'privileges.name AS privilege_name, privileges.description AS privilege_description, '
                . 'privileges.privilege_id AS privilege_id';
        $this->db->select($select);
        $this->db->join('privileges', 'privileges.privilege_id = account_privilege.privilege_id');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('account_privilege');

        return $query->result();
    }

    /**
     * Get status messages
     * @return string holding the status messages
     *
     * @param string $prefix HTML opening tag
     * @param string $suffix HTML closing tag
     * @return string Status message(s)
     */
    public function status_messages($prefix = "<p>", $suffix = "</p>") {
        $str = '';
        foreach ($this->status as $value) {
            $str .= $prefix;
            $str .= $value;
            $str .= $suffix;
        }

        return $str;
    }

    /**
     * Get error messages
     * @return string holding the error messages
     *
     * @param string $prefix HTML opening tag
     * @param string $suffix HTML closing tag
     * @return string Error message(s)
     */
    public function error_messages($prefix = "<p>", $suffix = "</p>") {
        $str = '';
        foreach ($this->errors as $value) {
            $str .= $prefix;
            $str .= $value;
            $str .= $suffix;
        }

        return $str;
    }

    /**
     * Adds an error message
     *
     * @param string $message Has to be a key in the language file
     * @param mixed $sprintf If the message requires the sprintf function, pass
     *  an array of what parameters to give the sprintf or vsprintd
     * @return void
     */
    public function set_error_message($message, $sprintf = NULL) {
        if (!empty($sprintf)) {
            if (is_array($sprintf)) {
                $this->errors[] = vsprintf(lang($message), $sprintf);
            } else {
                $this->errors[] = sprintf(lang($message), $sprintf);
            }
            return;
        }
        $this->errors[] = lang($message);
        return;
    }

    /**
     * Adds an status message
     * @param string $message Has to be a key in the language file
     * @param mixed $sprintf If the message requires the sprintf function, pass
     *  an array of what parameters to give the sprintf or vsprintd
     * @return void
     */
    public function set_status_message($message, $sprintf = NULL) {
        if (!empty($sprintf)) {
            if (is_array($sprintf)) {
                $this->status[] = vsprintf(lang($message), $sprintf);
            } else {
                $this->status[] = sprintf(lang($message), $sprintf);
            }
            return;
        }
        $this->status[] = lang($message);
        return;
    }

    /**
     * Reset error and status messages
     * @return void
     */
    public function reset_messages() {
        $this->errors = array();
        $this->status = array();
        return;
    }

    /**
     * generates random string of length $length
     * @author haseydesign http://haseydesign.com/
     * @param int $length Length of generated string
     * @return (string)
     */
    private function _rand_str($length = 32) {
        $characters = '23456789BbCcDdFfGgHhJjKkMmNnPpQqRrSsTtVvWwXxYyZz';
        $count = mb_strlen($characters);

        for ($i = 0, $token = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $token .= mb_substr($characters, $index, 1);
        }
        return $token;
    }

    /**
     * ----------------
     * Install Library
     * ----------------
     * This function should be used only once.
     * It inserts initial data to the DB. The values are the following:
     *  -Groups:
     *      -name: admin
     *      -description: controls website content and members
     *      -name: user
     *      -description: a website public member
     *  -Privileges:
     *      Admin group privileges:
     *      -name: Edit profiles
     *      -description: Edit user profiles
     *      -name: Add account
     *      -description: Add new admin or user
     *      -name: Add group
     *      -description: Add new group and privileges
     *      -name: Edit group
     *      -description: Edit groups and privileges
     *      -name: Connect groups
     *      -description: Connect user to groups and priviliges
     *
     *  -Account:
     *      *
     *      -email: admin@example.com
     *      -password: demo_password
     *      -group: admin
     *      *
     *      -email: user@emaple.com
     *      -password: demo_password
     *      -group: user
     *
     *
     *
     * The function connects the privileges to the group admin
     * Note: You must use the SQL_dump file first before using this function
     * @return boolean
     */
    public function install() {
        //Set up options
        $options = array(
            //Ini groups
            'groups' => array(
                'admin' => array(
                    //The id field must be left empty
                    'id' => 0,
                    'description' => 'Controls website content and members',
                    'is_admin' => TRUE
                ),
                'user' => array(
                    'id' => 0,
                    'description' => 'A website public member',
                    'is_admin' => FALSE
                )
            ),
            //Ini privileges
            'privileges' => array(
                array(
                    'name' => 'Edit profiles',
                    'description' => 'Edit user profiles',
                    'group' => 'admin'
                ),
                array(
                    'name' => 'Add account',
                    'description' => 'Add new admin or user',
                    'group' => 'admin'
                ),
                array(
                    'name' => 'Edit group',
                    'description' => 'Edit groups and privileges',
                    'group' => 'admin'
                ),
                array(
                    'name' => 'Add group',
                    'description' => 'Add new group and privileges',
                    'group' => 'admin'
                ),
                array(
                    'name' => 'Connect groups',
                    'description' => 'Connect user to groups and priviliges',
                    'group' => 'admin'
                )
            ),
            //Ini accounts
            'accounts' => array(
                array(
                    'email' => 'admin@example.com',
                    'password' => 'demo_password',
                    'group' => 'admin'
                ),
                array(
                    'email' => 'user@example.com',
                    'password' => 'demo_password',
                    'group' => 'user'
                )
            )
        );

        //Start installation by adding the groups
        foreach ($options['groups'] as $key => $group) {
            //Save the created group id in the array
            $options['groups'][$key]['id'] = $this->create_group($key, $group['description'], $group['is_admin']);
        } unset($group);

        //Insert privileges
        foreach ($options['privileges'] as $privilege) {
            //check if group id is available
            $group_id = $options['groups'][$privilege['group']]['id'];
            //create new privilege and connect it to the group
            $id = $this->create_priviledge($privilege['name'], $privilege['description']);
            //Connect to group if the privilege id and group id exists
            if ($id && $group_id && !empty($privilege['group']))
                $this->connect_privilede_to_group($id, $group_id, TRUE);
        } unset($privilege);

        //Insert Accounts
        foreach ($options['accounts'] as $account) {
            //Get group id
            $group_id = $options['groups'][$account['group']]['id'];
            //create new privilege and connect it to the group
            $user_id = $this->create_account($account['email'], $account['password'], $group_id);
            //Activate user
            $this->activate($user_id);
        } unset($account);

        return TRUE;
    }

}

//End of file  ./application/models/auth.php