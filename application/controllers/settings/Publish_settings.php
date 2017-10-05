<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Publish_settings extends CI_Controller {

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
			
		// Post request
		$this->load->helper('form');
		$this->load->library('form_validation');

		$this->load->model('FbApps_Model');

		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($this->FbAccount_Model->UserDefaultFbAccount());

		$this->form_validation->set_rules('postInterval',$this->lang->s('Post interval'),'trim|required|integer');

		if ($this->form_validation->run() === true) {
			
			$newData = array();

			$openGroupOnly = $this->input->post("openGroupOnly",true) == "on" ? 1 : 0;
			$uniquePost = $this->input->post("uniquePost",true) == "on" ? 1 : 0;
			$uniqueLink = $this->input->post("uniqueLink",true) == "on" ? 1 : 0;

			$newData['openGroupOnly'] = $openGroupOnly;
			$newData['uniquePost'] = $uniquePost;
			$newData['uniqueLink'] = $uniqueLink;
			$newData['postInterval'] = (int)$this->input->post("postInterval");

			// Update the default app for the current facebook account
			$this->FbApps_Model->setId($this->input->post('postApp',TRUE));

			$updateFbAccount = false;

			if($this->FbApps_Model->getById()->row()){
				$this->FbAccount_Model->setDefaultApp($this->input->post('postApp',TRUE));
				if($this->FbAccount_Model->update()){
					$updateFbAccount = true;
				}
			}else{
				$twigData['flash'][] = flash_bag($this->lang->s('Selected app not found!'),"danger");
			}
			
			$this->User_Model->setId($this->currentUser['user_id']);

			if($this->User_Model->UpdateOptions($newData) || $updateFbAccount == true){
				$twigData['flash'][] = flash_bag($this->lang->s('Your details has been update'),"success");
			}else{
				$twigData['flash'][] = flash_bag($this->lang->s('Nothing has been changed'),"info");
			}

		}else{
			foreach ($this->form_validation->error_array() as $key) {
				$twigData['flash'][] = flash_bag($key,"danger");
			}
		}
		
		$twigData['timezones'] = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
		$twigData['lang'] = $this->lang;
		$twigData['userOptions'] = $this->User_Model->options();

		$twigData['fbAccountApps'] = $this->FbAccount_Model->fbAccountApps();

		$twigData['fbAccountDefaultApp'] = $this->FbAccount_Model->UserFbAccountDefaultApp();

		$this->twig->display('settings/publish_settings',$twigData);
	}
}
?>