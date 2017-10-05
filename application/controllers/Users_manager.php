<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User class.
 * 
 * @extends CI_Controller
 */
class Users_manager extends CI_Controller {

	private $settings;

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {	
		parent::__construct();
		$this->load->helper(array('flash_helper','json_helper','general_helper'));
		$this->load->model('User_Model');

		// User ust be logged in to access this area
		if(!$this->User_Model->isLoggedIn()){
			redirect('/login');
			exit();
		}

		// User must be an admin to access this area
		if(!$this->User_Model->HasPermission('admin')){
			show_404();
			exit();
		}

		$this->load->model('Settings_Model');
		$this->settings = $this->Settings_Model->get();

		$this->currentUser = $this->User_Model->currentUser();

		$this->config->set_item('language', $this->currentUser['lang']);

		$this->lang->load(array("general"));

	}
	
	public function index() {

		$this->load->library('twig');
		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('user', $this->User_Model);
		$this->twig->addGlobal('userData', $this->currentUser);
		
		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));

		$twigData = array();

		$this->load->model('FbAccount_Model');
		$twigData['fbaccount'] = $this->FbAccount_Model;
		$twigData['fbaccountDetails'] = $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount());

		$this->load->library('pagination');
		$this->load->helper("pagination");

		$userOptions = $this->User_Model->options($this->currentUser['user_id']);
		$perPage = $userOptions->row('per_page');
		if(!$perPage) $perPage = 25;

		$twigData['users'] = $this->User_Model->getAll((int)$this->input->get('per_page', TRUE),$perPage,$this->input->get('searchTerm', TRUE));
		
		$config = pagination_config();
		$pagination_url = "/users_manager/";

		if($this->input->get('searchTerm', TRUE) != null)
			$pagination_url .= "?searchTerm=".$this->input->get('searchTerm', TRUE);

		$config['base_url'] = base_url().$pagination_url;

		// Total users = total -1 exclude the current user
		$config['total_rows'] = $this->User_Model->count($this->input->get('searchTerm', TRUE))-1;
		
		$config['per_page'] = $perPage;

		$this->pagination->initialize($config);
		$pagination = $this->pagination->create_links();

		//$twigData['User_Model'] = $this->User_Model;

		$twigData['pagination'] = $pagination;
		$twigData['total_posts'] = $config['total_rows'];
		$twigData['perPage'] = $perPage >= $config['total_rows'] ? $config['total_rows'] : $perPage;

		$this->load->model('Role_Model');
		$twigData['roles'] = $this->Role_Model->getAll();

		$this->twig->display('users_manager/index',$twigData);
	}
	
	public function profile($id = false) {
		
		if(!$id) show_404();
		
		$profile = $this->User_Model->get((int)$id);

		if(!$profile) show_404();

		$this->load->library('twig');

		$twigData = array();

		$twigData['profile'] = $profile;

		$this->load->model('FbAccount_Model');
		$twigData['fbaccount'] = $this->FbAccount_Model;
		$twigData['fbaccountDetails'] = $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount());

		$twigData['num_fbaccount'] = $this->FbAccount_Model->countFbAccount((int)$id);

		$twigData['User_Model'] = $this->User_Model;

		$this->load->model('Role_Model');
		$twigData['roles'] = $this->Role_Model->getAll();

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));
		
		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('user', $this->User_Model);
		$this->twig->display('users_manager/user_profile',$twigData);
	}

	public function add(){

		$this->load->library('form_validation');
		
		// set validation rules
		$this->form_validation->set_rules('username', $this->lang->s('Username'), 'trim|required|alpha_numeric|min_length[4]|regex_match[/^[a-z0-9]+$/]|is_exists[users.username]', array(
			'is_exists' => $this->lang->s('This username ( %s ) is already taken, please choose another',$this->input->post('username', TRUE)),	
			'regex_match' => $this->lang->s('Username must contain lowercase letters and numbers only.'),	
		));
		
		$this->form_validation->set_rules(
			'email',
			$this->lang->s('E-mail'),
			'trim|required|max_length[64]|valid_email|is_exists[users.email]',
			array('is_exists' => $this->lang->s('The E-mail is already exists'))
		);

		$this->form_validation->set_rules('password', $this->lang->s('Password'), 'trim|required|min_length[6]');
		$this->form_validation->set_rules('re_password', $this->lang->s('Confirm Password'), 'trim|required|min_length[6]|matches[password]');

		$this->form_validation->set_rules('role', $this->lang->s('Role'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$this->User_Model->setUsername($this->input->post('username', TRUE));
		$this->User_Model->setEmail($this->input->post('email', TRUE));
		$this->User_Model->setPassword($this->input->post('password', TRUE));
		$this->User_Model->setRole((int)$this->input->post('role', TRUE));
		$this->User_Model->setTimezone($this->settings['default_timezone']);
		$this->User_Model->setUserLang($this->settings['default_lang']);
		$this->User_Model->setIsActive(1);
		$this->User_Model->setExpired(0);
	
		$this->load->model('Role_Model');
		$this->Role_Model->setId((int)$this->input->post('role', TRUE));
		$role = $this->Role_Model->getById();

		if(!$role){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('The seleled role is not defined!')
			));
			return;
		}

		if($this->input->post('expire_on', TRUE) != NULL){

			$expireOn = dateFromFormat($this->settings['date_format'],$this->input->post("expire_on",TRUE));

			if(!$expireOn){
				display_json(array(
					'status' => 'error',
					'message' => $this->lang->s('Invalid account expiry date')
				));
				exit;
			}
			$this->User_Model->setExpireOn($expireOn->format('Y-m-d'));
		}else{
			if($role->row('account_expiry') > 0){
				date_default_timezone_set('UTC');
		        // Set account Expiry
				$currentDateTime = new DateTime();
				$currentDateTime->modify("+".$role->row('account_expiry')." days");
				$this->User_Model->setExpireOn($currentDateTime->format('Y-m-d'));
			}else{
				$this->User_Model->setExpireOn(NULL);
			}
		}

		if($user_id = $this->User_Model->save()){
			$this->User_Model->setId($user_id);
			$this->User_Model->defaultSettings();
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('New account has been created successfully')
			));
			return;
		}
		
		display_json(array(
			'status' => 'error',
			'message' => $this->lang->s('Unabe to create the user Account')
		));	
	}

	public function update() {
		$this->load->library('form_validation');

		if($this->input->post('userid', TRUE) == null){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('User id must be provided')
			));
			exit();
		}
		
		$user = $this->User_Model->get($this->input->post('userid', TRUE));

		if($user->row('email') && $user->row('email') != $this->input->post('email', TRUE)){
			$this->form_validation->set_rules(
				'email',
				'E-mail',
				'trim|required|max_length[64]|valid_email|is_exists[users.email]',
				array('is_exists' => $this->lang->s('The E-mail is already exists'))
			);
		}

		if($this->input->post('password', TRUE) != null) {
			$this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[6]');
			$this->form_validation->set_rules('re_password', 'Confirm Password', 'trim|required|min_length[6]|matches[password]');
		}

		$this->form_validation->set_rules('role', 'Role', 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;

		}
			
		$this->load->model('Role_Model');
		$this->Role_Model->setId((int)$this->input->post('role', TRUE));
		$role = $this->Role_Model->getById();

		if(!$role){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('The seleled role is not defined!')
			));
			return;
		}

		$newData = array();

		$newData['email'] = $this->input->post('email', TRUE);
		$newData['roles'] = (int)$this->input->post('role', TRUE);

		if($this->input->post('expire_on', TRUE) != NULL){
			$expireOn = dateFromFormat($this->settings['date_format'],$this->input->post("expire_on",TRUE));

			if(!$expireOn){
				display_json(array(
					'status' => 'error',
					'message' => $this->lang->s('Invalid account expiry date')
				));
				exit;
			}
			$newData['expire_on'] = $expireOn->format('Y-m-d');
		}

		if($this->input->post('password', TRUE) != null){
			$salt = substr(md5(uniqid(rand(), true)), 0, 32);
			$newData['password'] = $this->User_Model->hash_password($this->input->post('password', TRUE),$salt);
			$newData['salt'] = $salt;
		}

		$this->User_Model->setId((int)$this->input->post('userid', TRUE));

		if($this->User_Model->update($newData)){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('User account has been updated successfully')
			));
		}else{
			display_json(array(
				'status' => 'notice',
				'message' => 'Nothing has been changed'
			));
		}

	}

	public function delete(){

		$this->load->library('form_validation');

		$this->form_validation->set_rules('ids', 'IDs', 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			exit;
		}

		$ids = (array)json_decode($this->input->post('ids',true),true);

		// If the current user is included remove it from array
		for ($i=0; $i < count($ids) ; $i++) { 
			if($ids[$i] == $this->currentUser['user_id']){
				unset($ids[$i]);
			}
		}
			
		if(count($ids) == 0) {
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("No record has been specified")
			));
			exit;
		}

		if($this->User_Model->deleteAll($ids)){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s("User(s) has been deleted successfully")
			));
			exit;
		}else{
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("Enable to delete the requested records. Please try again")
			));
			exit;
		}
	}

	public function toggle_account_status(){
		$this->load->library('form_validation');

		$this->form_validation->set_rules('userid', 'User id', 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
		}else{

			if($this->User_Model->toggleAccountStatus((int)$this->input->post('userid', TRUE))){
				display_json(array(
					'status' => 'success',
					'message' => $this->lang->s('Account status updated')
				));
			}else{
				display_json(array(
					'status' => 'error',
					'message' => 'Unabe to update Account status'
				));
			}
		}
	}

	public function user_details(){
		$this->load->library('form_validation');

		$this->form_validation->set_rules('userid', 'User id', 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
		}else{

			if($user = $this->User_Model->get((int)$this->input->post('userid', TRUE))){
				$details = array();
				$details['username'] = $user->row('username');
				$details['email'] = $user->row('email');
				$details['role_id'] = $user->row('role_id');
				$details['role_name'] = $user->row('role_name');

				if($user->row('expire_on') == NULL){
					$details['expire_on'] = "";
				}else{
					$details['expire_on'] = date('m/d/Y', strtotime($user->row('expire_on')));
				}
				
				display_json(array(
					'status' => 'success',
					'user' => $details
				));
			}else{
				display_json(array(
					'status' => 'error',
					'message' => 'Unabe to get user Account details'
				));
			}
		}
	}

	public function search(){
		if($this->input->post('term', TRUE) == null){
			display_json(array("data"=> ""));	
			return false;
		}
		display_json(array("data"=>$this->User_Model->search($this->input->post('term', TRUE))));		
	}

	public function search_get(){
		if($this->input->get('q', TRUE) == null){
			display_json(array(""));	
			return false;
		}
		display_json($this->User_Model->search($this->input->get('q', TRUE),"name"));		
	}

	public function access_user_account($uid = false) {

		$user = $this->User_Model->get_user((int)$uid);

		if(!$user || !$user->row()){
			echo "User not found";
			return;
		}

        $userData = array();
        $userData['expired'] = FALSE;
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

		$this->session->set_userdata('user',$userData);
		
		redirect('/');
		exit();
	}


	public function export_emails()
	{
		// Get all users
		$emails = $this->User_Model->usersEmail();

		//var_dump($emails); return;

		// Out put the results as csv file
		header("Content-type: text/plain");
		header("Content-Disposition: attachment; filename=emails-".date("Y-m-d").".csv");

		// Loop through all users
		echo '"E-mail"';

		if(!$emails) return;

		foreach ($emails as $email) {
			echo "\r\n\"".$email->email."\"";
		}
	}

}
