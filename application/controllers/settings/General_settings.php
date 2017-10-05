<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class General_settings extends CI_Controller {

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
		$this->twig->addGlobal('userData', $this->currentUser);

		// Set User Timezone
		date_default_timezone_set($this->currentUser['timezone']);

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));
	}

	public function index() {

		// Post request
		$this->load->helper('form');
		$this->load->library('form_validation');

		$this->form_validation->set_rules('perPage',$this->lang->s('Per page'),'trim|required|integer');
		$this->form_validation->set_rules('timezone', $this->lang->s('Timezone'), 'trim');
		$this->form_validation->set_rules('lang', $this->lang->s('Language'), 'trim');

		if ($this->form_validation->run() === true) {
			
			$newOptions = array();
			$newInfo = array();
			$currentUserData = (array)$this->session->userdata('user');

			if(!in_array($this->input->post("timezone",true), DateTimeZone::listIdentifiers(DateTimeZone::ALL))){
				$twigData['flash'][] = flash_bag($this->lang->s("Invalid timezone"),"danger");
			}else{
				$newInfo['timezone'] = $this->input->post('timezone',true);
				$currentUserData['timezone'] = $this->input->post("timezone",true);
			}

			if(!in_array($this->input->post("lang",true), $this->lang->availableLanguages())){
				$twigData['flash'][] = flash_bag($this->lang->s("Invalid language"),"danger");
			}else{
				$newInfo['lang'] = $this->input->post('lang',true);
				$currentUserData['lang'] = $this->input->post("lang",true);
			}

			$newOptions['per_page'] = (int)$this->input->post('perPage') < 10 ? 10 : (int)$this->input->post('perPage') ;
			
			$this->User_Model->setId($this->currentUser['user_id']);
	
			$this->session->set_userdata('user',$currentUserData);
			
			$this->twig->addGlobal('userData', $this->User_Model->currentuser());

			$update = $this->User_Model->update($newInfo);
			$update += $this->User_Model->UpdateOptions($newOptions);
				
			if($update>0){
				$twigData['flash'][] = flash_bag($this->lang->s('Your details has been update'),"success");
			}else{
				$twigData['flash'][] = flash_bag($this->lang->s('Nothing has been changed'),"info");
			}

		}else{
			foreach ($this->form_validation->error_array() as $key) {
				$twigData['flash'][] = flash_bag($key,"danger");
			}
		}

		$twigData['userOptions'] = $this->User_Model->options();
		$twigData['timezones'] = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
		$twigData['lang'] = $this->lang;

		$this->twig->display('settings/general_settings',$twigData);
	}
}
?>