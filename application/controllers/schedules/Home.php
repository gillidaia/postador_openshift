<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

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
		$this->load->database();
		$this->load->library(array('session'));
		$this->load->helper(array('url','json_helper'));
		
		$this->load->model('User_Model');

		// If user is not logged in redirect to login page
		if(!$this->User_Model->isLoggedIn()){
			redirect('/login');
		}

		$this->currentUser = $this->User_Model->currentUser();

		// If the user account has expired show expiry page
		if($this->currentUser['expired'] == 1){
			redirect('account_expiry');
			return;
		}
		
		$this->load->model('Settings_Model');
		$this->load->helper(array('form'));
		$this->load->model('Schedule_Model');
		$this->load->library('twig');

		$this->settings = $this->Settings_Model->get();

		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('userdata', $this->User_Model->get($this->currentUser['user_id']));

		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));
		$this->twig->addGlobal('lang', $this->lang);

		$this->twig->addGlobal('user', $this->User_Model);

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));
		
		// Set User Timezone
		date_default_timezone_set($this->currentUser['timezone']);
	}
	
	public function index()
	{	
		$this->load->library('pagination');
		$this->load->helper("pagination");

		$this->Schedule_Model->setUserId($this->currentUser['user_id']);

		$userOptions = $this->User_Model->options($this->currentUser['user_id']);
		$perPage = $userOptions->row('per_page');
		if(!$perPage) $perPage = 25;
		
		$config = pagination_config();
		$config['base_url'] = base_url()."/schedules/";
		$config['total_rows'] = $this->Schedule_Model->count();
		$config['per_page'] = $perPage;
 		    
		$this->pagination->initialize($config);
		$pagination = $this->pagination->create_links();
		
		$SchedulePosts = $this->Schedule_Model->get((int)$this->input->get('per_page', TRUE),$perPage);

		$twigData = array();
		$twigData['schedulesList'] = $SchedulePosts;
		$twigData['User_Model'] = $this->User_Model;

		$this->load->model('FbApps_Model');
		$twigData['FbApps'] = $this->FbApps_Model;

		$twigData['pagination'] = $pagination;
		$twigData['total_posts'] = $config['total_rows'];
		$twigData['perPage'] = $perPage >= $config['total_rows'] ? $config['total_rows'] : $perPage;

		$this->load->model('FbAccount_Model');
		$twigData['fbaccount'] = $this->FbAccount_Model;
		$twigData['fbaccountDetails'] = $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount());

		$this->twig->display('schedule_posts/schedules', $twigData);
	}
}