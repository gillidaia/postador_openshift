<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Fbapps extends CI_Controller {

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
		$this->load->model('FbApps_Model');

		$this->settings = $this->Settings_Model->get();
		$this->twig->addGlobal('app_settings', $this->settings);
		
		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));

		$this->twig->addGlobal('fbaccountDetails', $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount()));
		$this->twig->addGlobal('fbaccount', $this->FbAccount_Model);
		$this->twig->addGlobal('user', $this->User_Model);

		$this->twig->addGlobal('date_format', $this->settings['date_format']);

		// Set User Timezone
		date_default_timezone_set($this->currentUser['timezone']);
	}

	public function index() {
		$twigData = array();
		$twigData['fbapps'] = $this->FbApps_Model;
		$this->twig->display('settings/fbapps',$twigData);
	}

	public function add_fbapp()
	{
		$this->load->library('form_validation');
		$this->form_validation->set_rules('app_id', $this->lang->s('Facebook app id'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		// If the app is already exists
		$this->FbApps_Model->setUserId($this->currentUser['user_id']);
		$this->FbApps_Model->setAppId($this->input->post('app_id', TRUE));
		if($this->FbApps_Model->getUserAppByFbAppId()->row()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('App already exists')
			));
			return;
		}

		// Get App details
		$this->load->model('Facebook_Model');
		$this->Facebook_Model->setAppId($this->input->post('app_id', TRUE));

		$appDetails = $this->Facebook_Model->appDetails();

		if(!isset($appDetails->name)){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('App not exists or Offline')
			));
			return;

		}
		$this->FbApps_Model->setAppName($appDetails->name);
		$this->FbApps_Model->setAppSecret($this->input->post('app_secret', TRUE));
		$this->FbApps_Model->setAppAuthLink($this->input->post('fbapp_auth_Link', TRUE));

		// Make this app public
		if($this->User_Model->HasPermission('admin')){
			$this->FbApps_Model->setIsPublic((int)$this->input->post('is_public', TRUE));
		}

		if($this->FbApps_Model->save()){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Facebook app has been Added successfully')
			));
		}else{
			display_json(array(
				'status' => 'error',
				'message' => 'Nothing has been saved'
			));
		}
		
	}

	public function delete_fbapp()
	{
		$this->load->library('form_validation');
		$this->form_validation->set_rules('id', $this->lang->s('Facebook app id'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
		}else{
			$this->FbApps_Model->setUserId($this->currentUser['user_id']);
			$this->FbApps_Model->setId((int)$this->input->post('id', TRUE));

			if($this->FbApps_Model->delete()){
				display_json(array(
					'status' => 'success',
					'message' => $this->lang->s('Facebook app has been deleted')
				));
				return;
			}

			display_json(array(
				'status' => 'error',
				'message' => 'Nothing has been deleted'
			));
			

		}
	}

	public function deauthenticate($appid){
		$this->FbApps_Model->setId((int)$appid);
		$this->FbApps_Model->setUserId($this->currentUser['user_id']);
		$this->FbApps_Model->DeauthorizeApp($this->FbAccount_Model->UserDefaultFbAccount());	
		Redirect("settings/fbapps");
	}
}
?>