<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User class.
 * 
 * @extends CI_Model
 */
class User_model extends MY_Model {
	
    private $id;
	private $username;
    private $email;
    private $password;
    private $timezone = "UTC";
    private $userLang = "english";
    private $firstname;
    private $lastname;
    private $fbUserId;
    private $role = 1;
    private $isActive;
    private $isAdmin;
    private $expireOn;
    private $expired = 0;
    private $activationCode;
    private $errors;


    // Setters and getters Auto Generate  
    public function __call($function, $args)
    {
        $functionType = strtolower(substr($function, 0, 3));
        $propName = lcfirst(substr($function, 3));
        switch ($functionType) {
            case 'get':
                if (property_exists($this, $propName)) {
                    return $this->$propName;
                }
                break;
            case 'set':
                if (property_exists($this, $propName)) {
                    $this->$propName = $args[0];
                }
                break;
        }
    }

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->library(array('session'));
	}

	public function save(){
        $this->db->set('username', $this->username);
        $this->db->set('email', $this->email);

        // generate salt & hash password
        $salt = substr(md5(uniqid(rand(), true)), 0, 32);
        $password = $this->hash_password($this->password,$salt);
        $this->db->set('password', $password);
        $this->db->set('salt', $salt);

        $this->db->set('timezone', $this->timezone);
        $this->db->set('lang', $this->userLang);
        $this->db->set('firstname', $this->firstname);
        $this->db->set('lastname', $this->lastname);
        $this->db->set('fbuserid', $this->fbUserId);
        $this->db->set('avatar', null);
        $this->db->set('roles', $this->role);
        $this->db->set('active', $this->isActive);
        $this->db->set('expire_on', $this->expireOn);
        $this->db->set('act_code', $this->activationCode);
        $this->db->set('expired', $this->expired);
        $this->db->set('signup', date('Y-m-d H:i'));
        $this->db->insert('users');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }
	
	/**
	 * checkUserLogin function.
	 * 
	 * @access public
	 * @param mixed $username
	 * @param mixed $password
	 * @return bool true on success, false on failure
	 */
	public function checkUserLogin($username, $password, $rememberMe = NULL) {
		$this->db->from('users');
		$this->db->where('username', $username);
		$queryResult = $this->db->get();

        // Check password
        $makeHash = hash('sha256', $password . $queryResult->row('salt'));
        
        // Check check password
        if($makeHash !== $queryResult->row('password')){
            $this->errors = $this->lang->s("Incorrect username/password.");
            return FALSE;
        }

        if(!$this->userLogin($queryResult)){
            return false;
        }

		if($rememberMe){
			$sessionCode = $this->saveUserSession($hash,(int)$queryResult->row('id'));
			$cookie = array(
		        'name'   => 'user_session',
		        'value'  => $sessionCode,
		        'expire' => '900000',
		        'path'   => '/',
		        'prefix' => $this->config->item('sess_cookie_name'),
		        'secure' => FALSE
			);
			$this->input->set_cookie($cookie);
		}
		return true;
	}
	

    public function userLogin($user){

        // Is user account active
        if((int)$user->row('active') == 0 && $user->row('act_code') == ""){
           $this->errors = $this->lang->s("Your account is not activated. please contact the site administrator in order to activate your account.");
            return false;
        }else if((int)$user->row('active') == 0 && $user->row('act_code') != ""){
             $this->errors = $this->lang->s("Your account is not activated. please check your email to activate your account or Contact the site administrator.");
            return false;
        }

        $userData = array();

        $userData['expired'] = $user->row('expired') == 0 ? FALSE : TRUE;

        $newUserData = array();

        date_default_timezone_set('UTC');

        if($user->row('expire_on') != null && $user->row('expire_on') != 0){
            // Check if the user account has expired

            $currentDateTime = new DateTime();
            $exipreOn = new DateTime($user->row('expire_on'));

            if(strtotime($currentDateTime->format("Y-m-d H:i")) > strtotime($exipreOn->format("Y-m-d H:i"))){
                // Update the user status
                if($user->row('expired') == 0){
                    $newUserData['expired'] = 1;
                }
                $userData['expired'] = TRUE;
            }else{
                 // Update the user status
                if($user->row('expired') == 1){
                    $newUserData['expired'] = 0;
                    $userData['expired'] = FALSE;
                }
            }
        }

        $now = new DateTime();

        $newUserData['last_login'] = $now->format("Y-m-d H:i");

        $this->setId($user->row('id'));
        $this->update($newUserData);

        $userData['user_id'] = $user->row('id');
        $userData['username'] = (string)$user->row('username');
        $userData['firstname'] = (string)$user->row('firstname');
        $userData['lastname'] = (string)$user->row('lastname');
        $userData['avatar'] = (string)$user->row('avatar');
        $userData['email'] = (string)$user->row('email');
        $userData['logged_in'] = TRUE;
        $userData['timezone'] = (string)$user->row('timezone');
        $userData['lang'] = (string)$user->row('lang');
        $userData['active'] = $user->row('active');
        $userData['role'] = $user->row('roles');
        $userData['expire_on'] = $user->row('expire_on');

        $this->setUserSession($userData);

        $this->setUserRole($user);

        return true;
    }

    public function checkAccountExpiry($userId){
        $user = $this->get_user($userId);
        if(!$user){
            $this->errors = $this->lang->s("User account not found");
            return false;
        }
        return $this->userLogin($user);
    }

	public function loginFromCookie($usCode){
		// Get userid that Correspondent to the user session code
		$this->db->select('user_id,hash');
		$this->db->from('users_session');
		$this->db->where('hash',$usCode);
		$queryResult = $this->db->get();
		if($queryResult){
			// Get user
			$user = $this->get_user($queryResult->row('user_id'));
			if($this->generateUSCode($user->row('password')) == $usCode){
				if(!$this->userLogin($user)){
                    return false;
                }
				return true;
			}
		}
		return false;
	}
	/**
	 * get_user_id_from_username function.
	 * 
	 * @access public
	 * @param mixed $username
	 * @return int the user id
	 */
	public function get_user_id_from_username($username) {
		$this->db->select('id');
		$this->db->from('users');
		$this->db->where('username', $username);
		return $this->db->get()->row('id');
	}
	
	/**
	 * get_user function.
	 * 
	 * @access public
	 * @param mixed $user_id
	 * @return object the user object
	 */
	public function get_user($user_id) {
		$this->db->from('users');
		$this->db->where('id', $user_id);
		return $this->db->get();
	}

	/**
	 * get_user function.
	 * 
	 * @access public
	 * @param mixed $user_id
	 * @return object the user object
	 */
	public function get($user_id) {
        $this->db->select("u.*,r.id as 'role_id',r.name as 'role_name' ")
		          ->from('users u')
                  ->join('roles r', 'u.roles = r.id')
		          ->where('u.id', $user_id);
		return $this->db->get();
	}

    /**
     * get_user function.
     * 
     * @access public
     * @param mixed $user_id
     * @return object the user object
     */
    public function getUserByEmail($email) {
        $this->db->from('users');
        $this->db->where('email', $email);
        return $this->db->get()->row();
    }

    /**
     * get_user function.
     * 
     * @access public
     * @param mixed $user_id
     * @return object the user object
     */
    public function search($term,$fieldName = "username") {
        $this->db->select("id,username as '".$fieldName."',email");
        $this->db->from('users');
        $this->db->like('username', $term);
        $this->db->or_like('email', $term);
        return $this->db->get()->result();
    }

    /**
     * get_user function.
     * 
     * @access public
     * @param mixed $user_id
     * @return object the user object
     */
    public function CheckPWResetCode($email,$pw_reset_code) {
        $this->db->from('users');
        $this->db->where('email', $email);
        $this->db->where('pw_reset_code', $pw_reset_code);
        return $this->db->get()->row();
    }

    /**
     * 
     * @access public
     * @param mixed $user_id
     * @return object the user object
     */
    public function CheckActivationCode() {
        $this->db->select('id');
        $this->db->from('users');
        $this->db->where('email', $this->email);
        $this->db->where('act_code', $this->activationCode);
        return $this->db->get();
    }
	
	// Count all record of table "users" in database.
    public function count($term = false) {
        if($term){
            $this->db->like('username', $term);
            $this->db->or_like('email', $term);  
        }
        return $this->db->count_all_results("users");
    }

    // Fetch data according to per_page limit.
    public function getAll($offset = 0,$limit = 25,$term = false) {
        $this->db->select("u.*,r.name as 'role_name'");
        $this->db->from('users u');
        $this->db->join('roles r','r.id = u.roles');
        $this->db->limit($limit,$offset);
        if($term){
            $this->db->like('u.username', $term);
            $this->db->or_like('u.email', $term);  
        }
        $this->db->where_not_in('u.id', $this->currentUser()['user_id']);
        $this->db->order_by('u.id', 'DESC');
        return $this->db->get()->result();
    }

	/**
	 * hash_password function.
	 * 
	 * @access private
	 * @param mixed $password
	 * @return string|bool could be a string on success, or bool false on failure
	 */
	public function hash_password($password,$salt) {
        return hash('sha256', $password . $salt);
	}
	
    public function deleteAll($ids){

        if(!is_array($ids)){
            throw new Exception("Error : Expected array but received ". gettype($ids));
        }
        
        foreach ($ids as $user) {
			$this->deleteUserData($user);
		}

        $this->db->where_in('id', $ids);
        $this->db->delete('users');
        return $this->db->affected_rows() > 0;
    }

    public function deleteUserData($user_id){
		// Delete all data of the user first 
		// Get user
		$user = $this->get($user_id);

		if(!$user->row()){
            return false;
        }

		// Remove user folder
		$this->load->helper('rrmdir_helper');
		$userUploadFolder = FCPATH.UPLOADS_FOLDER.DIRECTORY_SEPARATOR.$user->row('username');
        if (is_dir($userUploadFolder)) {
            rrmdir($userUploadFolder);
        }

        // User_options
        $this->db->where('userid', $user_id)->delete('user_options');
        // Delete Schedule logs
        $this->db->where('user_id', $user_id)->delete('schedule_logs');
        // Delete schedules
        $this->db->where('userid', $user_id)->delete('scheduledposts');
        // Delete user posts
        $this->db->where('userid', $user_id)->delete('posts');
        // Delete nodes categories
        $this->db->where('user_id', $user_id)->delete('nodes_category');
        // delete user_fbapps
        $this->db->where('userid', $user_id)->delete('user_fbapp');
        // Delete fb accounts
        $this->db->where('user_id', $user_id)->delete('fb_accounts');
        // Fbapps
        $this->db->where('user_id', $user_id)->delete('fbapps');
        // Statistics
        $this->db->where('user_id', $user_id)->delete('statistics');
    }

	/**
	 * Check is logged in function.
	 * 
	 * @access private
	 * @return bool
	 */
	public function isLoggedIn(){
		if(isset($this->session->userdata('user')['logged_in']))
			return true;

		return false;
	}

	/**
	 * Check is logout in function.
	 * 
	 * @access private
	 * @return bool
	 */
	public function loggedOut(){
		$this->db->where("user_id",$this->session->userdata('user')['user_id']);
        $this->session->sess_destroy();
		return $this->db->delete('users_session');
	}
	
	private function saveUserSession($hash,$userId){
		$sessionCode = $this->generateUSCode($hash);

        // delete old session 
        $this->db->where("user_id", $userId);
        $this->db->delete('users_session');

		$this->db->set('user_id', $userId);
		$this->db->set('hash', $sessionCode);
		$this->db->insert('users_session');
		return $sessionCode;
	}

	private function generateUSCode($hash){
		return hash('sha256', md5($hash) . md5($_SERVER['HTTP_USER_AGENT']) . $this->input->ip_address());
	}

	private function setUserSession($data){
		// set session user data
		$this->session->set_userdata('user',$data);
	}
	
    private function setUserRole($user){
        $this->db->from("roles");
        $this->db->where("id", $user->row('roles'));
        $role = $this->db->get();

        $userRole = array();

        if($role->row()){
            $userRole['name'] = $role->row("name");
            $userRole['permissions'] = (array)json_decode($role->row('permissions'), true);
            $userRole['max_posts'] = $role->row("max_posts");
            $userRole['max_fbaccount'] = $role->row("max_fbaccount");
            $userRole['max_comments'] = $role->row("max_comments");
            $userRole['max_likes'] = $role->row("max_likes");
            $userRole['upload_videos'] = $role->row("upload_videos");
            $userRole['upload_images'] = $role->row("upload_images");
            $userRole['max_upload'] = $role->row("max_upload");
            $userRole['join_groups'] = $role->row("join_groups");
        }

        $this->session->set_userdata('user_role',$userRole);
    }

	public function currentUser(){
		return $this->session->userdata("user");
	}

	public function update($data){
		foreach ($data as $key => $value) {
			$this->db->set($key, $value);
		}
		$this->db->where("id",(int)$this->id);
		$this->db->update("users");

		return $this->db->affected_rows() > 0;
	}

	public function hasPermission($permission,$user_id = null)
    {

        if($user_id == null){
            $user_id = $this->currentUser()['user_id'];
        }

        $user = $this->get($user_id);

        if(!$user->row() && !$user->row('roles')){
            return false;
        }

        // Get role
        $this->db->from("roles");
        $this->db->where("id", $user->row('roles'));

        $role = $this->db->get();

        if($role->row() == null){
            return FALSE;
        }

        $permissions = json_decode($role->row('permissions'), true);
        if(isset($permissions[$permission])){
            if($permissions[$permission]){
                return TRUE;
            }
        }

        return FALSE;
	}

	public function toggleAccountStatus($user_id){
		$status = $this->get($user_id)->row('active') == 1 ? 0 : 1 ;
        $this->db->set('active', $status);
        $this->db->where('id', $user_id);
        $this->db->update('users');
        return $this->db->affected_rows() > 0;
    }

    // user Options
    public function options($userID = null){
        if($userID == null){
            $userID = $this->currentUser()['user_id'];
        }
        $this->db->from('user_options');
        $this->db->where('userid', $userID);
        return $this->db->get();
    }

    // user Options
    public function userSettings(){
        $user_settings = array();
        if($this->session->userdata('user_settings') == null){
            $this->db->from('user_options');
            $this->db->where('userid', $this->id);
            $us = $this->db->get();
            if($us && $us->row()){
                $this->session->set_userdata('user_settings', (array)$us->row());
                $user_settings = (array)$us->row();  
            }
        }else{
            $user_settings = (array)$this->session->userdata('user_settings');
        }
        return $user_settings;
    }


    public function UpdateOptions(array $params){

        $userOtp = $this->options($this->id);

        foreach ($params as $key => $value) {
            $this->db->set($key, $value);
        }

        if($userOtp->row()){
            $this->db->where("id",$userOtp->row('id'));
            $this->db->update("user_options");
        }else{
            $this->db->set("userid",$this->id);
            $this->db->insert("user_options");
        }
        
        // Update the session
        $this->session->set_userdata('user_settings',null);
        $this->setId($this->currentUser()['user_id']);
        $this->userSettings();

        return $this->db->affected_rows() > 0;
    }

    public function defaultSettings($settings = array()){
        $settings['postInterval']   = 30;
        $settings['openGroupOnly']  = 0;
        $settings['uniquePost']     = 0;
        $settings['uniqueLink']     = 0;
        $settings['limitImportGroups']  = 500;
        $settings['limitImportPages']   = 500;
        $settings['show_groups']    = 1;
        $settings['show_pages']     = 1;
        $settings['today_num_posts'] = 0;
        $settings['last_num_posts_reset'] = date('Y-m-d');
        $settings['load_groups']    = 1;
        $settings['load_pages']     = 1;
        $settings['load_own_pages'] = 0;
        $settings['per_page']       = 30;
        $this->UpdateOptions($settings);
    }

    public function userRole()
    {
        // get user role id
        $user = $this->get($this->id);

        if(!$user->row() && !$user->row('roles')){
            return false;
        }
        // 
        $this->load->model('Role_Model');
        $this->Role_Model->setId($user->row('roles'));

        return  $this->Role_Model->getById();
    }

    public function canDoToday($service){
        // Check user limitation
        $this->load->model("Statistic_Model");
        $this->Statistic_Model->setUserId($this->id);
        $uStat = $this->Statistic_Model->getUserStatDay(array("posts","comments","likes"));

        if(!$uStat || !$uStat->row()){
            // The user Row for today is not exists thats fine
            return true;
        }

        $role = $this->userRole();

        if(!$role->row()) return false;

        // If the user is an admin return true
        $permissions = json_decode($role->row('permissions'), true);
        if(isset($permissions['admin'])){
            return TRUE;
        }

        switch ($service) {
            case 'comment':
                if($role->row("max_comments") == 0) return TRUE;
                if((int)$role->row("max_comments") > $uStat->row('comments')) return TRUE;
                break;
            case 'like':
                if($role->row("max_likes") == 0) return TRUE;
                if((int)$role->row("max_likes") > $uStat->row('likes')) return TRUE;
                break;
            case 'post':
                if($role->row("max_posts") == 0) return TRUE;
                if((int)$role->row("max_posts") > $uStat->row('posts')) return TRUE;
                break;
        }

        return FALSE;
    }

    public function canUse($service){
        $userRole = $this->session->userData("user_role");
        switch ($service) {
            case 'comments':
                if(isset($userRole["max_comments"]) && $userRole["max_comments"] != -1){
                    return TRUE;
                }
                break;
            case 'likes':
                if(isset($userRole["max_likes"]) && $userRole["max_likes"] != -1){
                    return TRUE;
                }
                break;
            case 'join_groups':
                if(isset($userRole["join_groups"]) && $userRole["join_groups"] != -1){
                    return TRUE;
                }
                break;
        }
        return FALSE;
    }

    public function canAddIGAccount($accountToAdd = null){
        $role = $this->userRole();

        if(!$role->row()) return FALSE;

        // If the user is an admin return TRUE
        $permissions = json_decode($role->row('permissions'), TRUE);
        if(isset($permissions['admin'])){
            return TRUE;
        }

        if($role->row('max_fbaccount') == 0){
            return TRUE;
        }

        // Count user totla IG accounts
        $this->db->where('user_id', $this->id);
        $this->db->where('fb_id !=', $accountToAdd);
        $count = $this->db->count_all_results("fb_accounts");

        if($count < $role->row('max_fbaccount')){
            return TRUE;
        }

        return FALSE;
    }

    public function canUploadVideos(){
        $role = $this->userRole();

        if(!$role->row()) return FALSE;

        // If the user is an admin return TRUE
        $permissions = json_decode($role->row('permissions'), TRUE);
        if(isset($permissions['admin'])){
            return TRUE;
        }
        
        if($role->row('upload_videos') == 1){
            return TRUE;
        }

        return FALSE;
    }

    public function canUploadImages(){
        $role = $this->userRole();

        if(!$role->row()) return FALSE;

        // If the user is an admin return TRUE
        $permissions = json_decode($role->row('permissions'), TRUE);
        if(isset($permissions['admin'])){
            return TRUE;
        }

        if($role->row('upload_images') == 1){
            return TRUE;
        }

        return FALSE;
    }

    public function isExceededMaxUpload($path){
        
        $role = $this->userRole();

        if(!$role->row()) return TRUE;

        // If the user is an admin return TRUE
        $permissions = json_decode($role->row('permissions'), TRUE);
        if(isset($permissions['admin'])){
            return FALSE;
        }

        if($role->row('max_upload') == 0){
            return FALSE;
        }

        $this->load->helper("directory_size_helper");

        // Check if the user exceeded the max upload allowed
        $folderSize = $this->userStorageSize($path);

        if($role->row('max_upload') >= $folderSize){
            return FALSE;
        }

        return TRUE;
    }

    public function userStorageSize($path){
        $this->load->helper("directory_size_helper");
        // Check if the user exceeded the max upload allowed
        $folderSize = round((int)directory_size_helper($path)/1000);
        return $folderSize;
    }
   
    public function checkExpiredAccounts()
    {
        $this->db->select("id,expire_on");
        $this->db->from("users");
        $this->db->where("expired", 0);
        $this->db->where("active", 1);
        $this->db->where("expire_on !=", 0);
        $users = $this->db->get();

        if(!$users) return false;

        foreach ($users->result() as $u) {
            if($u->expire_on != null){
            // Check if the user account has expired
            $cdt = new DateTime();
            $exipreOn = new DateTime($u->expire_on);

            if(strtotime($cdt->format("Y-m-d H:i")) > strtotime($exipreOn->format("Y-m-d H:i"))){
                $this->setId($u->id);
                $this->update(array("expired"=>1));
            }
        }
        }
    }

    public function usersEmail() {
        $this->db->select("email");
        $this->db->from('users');
        $this->db->order_by('id', 'DESC');
        return $this->db->get()->result();
    }
}
