<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Insights extends CI_Controller {

	private $currentUser = array();
	private $settings;

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

		$this->userOptions = $this->User_Model->options();

		$this->load->model('Settings_Model');
		$this->load->model('Post_Model');

		$this->settings = $this->Settings_Model->get();
		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('user', $this->User_Model);

		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));

		$this->load->model('Statistic_Model');
		$this->load->model('Role_Model');
		$this->load->library('twig');
	}

	public function index()
	{	
		$twigData = array();

		$this->Statistic_Model->setUserId($this->currentUser['user_id']);

		$twigData['Insights_num_day'] = $this->Statistic_Model->getUserStatDay();
		$twigData['Insights_num_week'] = $this->Statistic_Model->getUserStatWeek();
		$twigData['Insights_num_month'] = $this->Statistic_Model->getUserStatMonth();
		$twigData['Insights_num_alltime'] = $this->Statistic_Model->getUserStatAllTime();

		$twigData['Insights_day'] = $this->Statistic_Model->getUserStat('day');
		$twigData['Insights_week'] = $this->Statistic_Model->getUserStat('week');
		$twigData['Insights_month'] = $this->Statistic_Model->getUserStat('month');
		$twigData['Insights_alltime'] = $this->Statistic_Model->getUserStat();

		// The user group
		$this->Role_Model->setId($this->currentUser['role']);
		$twigData['group'] = $this->Role_Model->getRoleById();
		$twigData['User_Model'] = $this->User_Model;

		$this->load->model('FbAccount_Model');

		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);

		// Facebook Accounts 
		$twigData['total_fb_account'] = $this->FbAccount_Model->countFbAccount((int)$this->currentUser['user_id']);	

		$twigData['total_groups'] = $this->FbAccount_Model->countFBAccountsGroups();
		$twigData['total_pages'] = $this->FbAccount_Model->countFBAccountsPages();

		$this->Post_Model->setUserId((int)$this->currentUser['user_id']);
		$twigData['total_sposts'] = $this->Post_Model->count();

		$this->load->model('Schedule_Model');
		$this->Schedule_Model->setUserId((int)$this->currentUser['user_id']);
		$twigData['total_schedules'] = $this->Schedule_Model->count();

		/* My account */
		$this->Role_Model->setId((int)$this->currentUser['role']);
		$twigData['group'] = $this->Role_Model->getById();

		$twigData['current_user'] = $this->currentUser;

		$twigData['fbaccount'] = $this->FbAccount_Model;
		$twigData['fbaccountDetails'] = $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount());

		// User Storage (From KB to MB)
		$twigData['storageSize'] = $this->User_Model->userStorageSize(UPLOADS_FOLDER . "/" . $this->currentUser['username']);

		$this->twig->display('insights', $twigData);
	}
}
