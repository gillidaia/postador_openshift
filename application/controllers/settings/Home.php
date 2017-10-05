<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Home extends CI_Controller {

	private $settings;
	private $currentUser;

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {	
		parent::__construct();
		$this->load->library(array('session'));
		$this->load->helper(array('flash_helper','json_helper'));
		$this->load->library('twig');
		$this->load->model('User_Model');

		// if the user is logged in redirect to home page
		if(!$this->User_Model->isLoggedIn()){
			redirect('/login');
		}

		$this->currentUser = $this->User_Model->currentuser();

		// If the user account has expired show expiry page
		if($this->currentUser['expired'] == 1){
			redirect('account_expiry');
			exit();
		}
		
		$this->load->model('Settings_Model');
		$this->load->model('FbAccount_Model');

		$this->settings = $this->Settings_Model->get();
		$this->twig->addGlobal('app_settings', $this->settings);
		
		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));
		
		$this->twig->addGlobal('fbaccountDetails', $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount()));
		$this->twig->addGlobal('fbaccount', $this->FbAccount_Model);
		$this->twig->addGlobal('user', $this->User_Model);
	}
	
	public function index() {
		$twigData = array();

		// Current user
		$currentUser = $this->User_Model->get_user($this->currentUser['user_id']);
		
		// Post request
		$this->load->helper('form');
		$this->load->library('form_validation');

		$this->form_validation->set_rules('email',$this->lang->s('E-mail'),'trim|required|max_length[64]|valid_email');
		$this->form_validation->set_rules('firstname', $this->lang->s('Firstname'), 'trim');
		$this->form_validation->set_rules('firstname', $this->lang->s('Firstname'), 'trim');

		if($this->input->post("email",true) != $currentUser->row('email')){
			$this->form_validation->set_rules(
				'email',
				'E-mail',
				'trim|required|max_length[64]|valid_email|is_exists[users.email]',
				array('is_exists' => $this->lang->s('The E-mail is already exists'))
			);
		}

		if ($this->form_validation->run() === true) {
			
			$newData = array();

			$newData['email'] = $this->input->post('email',TRUE);
			$newData['fbuserid'] = $this->input->post('fbuserid',TRUE);
			$newData['firstname'] = $this->input->post('firstname',TRUE);
			$newData['lastname'] = $this->input->post('lastname',TRUE);
			$this->User_Model->setId($this->currentUser['user_id']);
			if($this->User_Model->update($newData)){
				$twigData['flash'][] = flash_bag($this->lang->s('Your details has been update'),"success");

				// Update current user session
				$currentUser = $this->User_Model->get_user($this->currentUser['user_id']);
				$this->User_Model->userLogin($currentUser);

			}else{
				$twigData['flash'][] = flash_bag($this->lang->s('Nothing has been changed'),"info");
			}
		}else{
			foreach ($this->form_validation->error_array() as $key) {
				$twigData['flash'][] = flash_bag($key,"danger");
			}
		}

		$twigData['currentUser'] = $currentUser;

		$this->twig->display('settings/user_settings',$twigData);
	}

	public function change_password() {

		$this->load->helper('form');
		$this->load->library('form_validation');

		$this->form_validation->set_rules('old_password', $this->lang->s('Current password'), 'trim|required');
		$this->form_validation->set_rules('new_password', $this->lang->s('New password'), 'trim|required|min_length[6]');
		$this->form_validation->set_rules('re_new_password', $this->lang->s('Repeat new password '), 'trim|required|min_length[6]|matches[new_password]');

		if ($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;			
		}

		// Get current user
		$user = $this->User_Model->get_user((int)$this->currentUser['user_id']);

        $makeHash = hash('sha256', $this->input->post('old_password', TRUE) . $user->row('salt'));
        
        // Check check password
        if($makeHash !== $user->row('password',TRUE)){
			display_json(array(
				'status' => 'danger',
				'message' => $this->lang->s('Current password is not correct')
			));
			return;
        }

		$newData = array();

		$salt = substr(md5(uniqid(rand(), true)), 0, 32);
		$newData['password'] = $this->User_Model->hash_password($this->input->post('new_password', TRUE),$salt);
		$newData['salt'] = $salt;

		$this->User_Model->setId((int)$this->currentUser['user_id']);

		if($this->User_Model->update($newData)){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Your password has been changed successfully')
			));
			return;
		}

		display_json(array(
			'status' => 'notice',
			'message' => 'Nothing has been changed'
		));

	}
}
?>