<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Account_expiry extends CI_Controller {

	private $settings;
	private $currentUser = array();

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

		$this->load->model('User_Model');
		$this->load->model('Settings_Model');
		
		// If user is not logged in redirect to login page
		if(!$this->User_Model->isLoggedIn()){
			redirect('/login');
		}
			
		$this->load->library('twig');
		$this->load->model('FbAccount_Model');

		$this->currentUser = $this->User_Model->currentUser();
		
		$this->settings = $this->Settings_Model->get();

		$this->config->set_item('language', $this->currentUser['lang']);

		$this->lang->load(array("general"));

		$this->twig->addGlobal('fbaccount', $this->FbAccount_Model);
		$this->twig->addGlobal('user', $this->User_Model);
		$this->twig->addGlobal('app_settings', $this->settings);

		// Set User Timezone
		date_default_timezone_set($this->currentUser['timezone']);

	}
	
	public function index() {
		$this->twig->display('account_expiry');
	}
	
}
