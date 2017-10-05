<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class App_settings extends CI_Controller {

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
			exit();
		}

		// User must be an admin to access this area
		if(!$this->User_Model->HasPermission('admin')){
			show_404();
			exit();
		}
		
		$this->currentUser = $this->User_Model->currentuser();

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

	public function index()
	{

		$this->load->model('Role_Model');
		$twigData = array();

		$roles = $this->Role_Model->getAll();

		if($this->session->flashdata('app_settings_success')) {
			foreach ((array)$this->session->flashdata('app_settings_success') as $v) {
				$twigData['flash'][] = flash_bag($v,"success");
			}
		}

		if($this->session->flashdata('app_settings_danger')) {
			foreach ((array)$this->session->flashdata('app_settings_danger') as $v) {
				$twigData['flash'][] = flash_bag($v,"danger");
			}
		}

		// Post request
		$this->load->helper('form');
		$this->load->library('form_validation');

		$this->form_validation->set_rules('sitename',$this->lang->s('Site name'),'trim|required');
		$this->form_validation->set_rules('default_role',$this->lang->s('Default role'),'trim|required|integer');
		$this->form_validation->set_rules('default_timezone',$this->lang->s('Default timezone'),'trim|required');
		$this->form_validation->set_rules('default_lang',$this->lang->s('Default language'),'trim|required');
		$this->form_validation->set_rules('minInterval',$this->lang->s('min interval'),'trim|required|integer');

		if ($this->form_validation->run() === true) {

			$flashdataD = array();

			$usersCanRegister = $this->input->post("usersCanRegister",true) == "on" ? 1 : 0;
			$usersMustConfirmEmail = $this->input->post("usersMustConfirmEmail",true) == "on" ? 1 : 0;
			$userActiveByAdmin = $this->input->post("userActiveByAdmin",true) == "on" ? 1 : 0;

			$newData['users_can_register'] =  $usersCanRegister;
			$newData['users_must_confirm_email'] = $usersMustConfirmEmail;
			$newData['user_active_by_admin'] = $userActiveByAdmin;

			$newData['enable_instant_post'] = $this->input->post("enable_instant_post",true) == "on" ? 1 : 0;

			$newData['enable_sale_post_type'] = $this->input->post("enable_sale_post_type",true) == "on" ? 1 : 0;

			$newData['enable_link_customize'] = $this->input->post("enable_link_customize",true) == "on" ? 1 : 0;
			
			$newData['sitename'] = $this->input->post("sitename",TRUE);
			$newData['site_logo'] = $this->input->post("site_logo",TRUE);
			$newData['site_logo_50'] = $this->input->post("site_logo_50",TRUE);
			$newData['site_logo_large'] = $this->input->post("site_logo_large",TRUE);
			$newData['site_favicon'] = $this->input->post("site_favicon",TRUE);

			$newData['fb_login_app'] = $this->input->post("fb_login_app",TRUE);

			// Generate theme color  css fie
			$newData['theme_color'] = $this->input->post("theme_color",TRUE);
			$newData['links_color'] = $this->input->post("links_color",TRUE);
			$this->load->helper("themecolor_helper");
			generate_css_file($this->input->post("theme_color",TRUE),$this->input->post("links_color",TRUE));

			$newData['public_bg_image'] = $this->input->post("public_bg_image",TRUE);
			$newData['public_bg_color'] = $this->input->post("public_bg_color",TRUE);

			$newData['default_role'] = (int)$this->input->post("default_role",TRUE);
			
			if(!in_array($this->input->post("default_timezone",true), DateTimeZone::listIdentifiers(DateTimeZone::ALL))){
				$flashdataD[] = $this->lang->s("Invalid timezone");
			}else{
				$newData['default_timezone'] = $this->input->post("default_timezone",TRUE);
			}

			if(!in_array($this->input->post("default_lang",true), $this->lang->availableLanguages())){
				$flashdataD[] = $this->lang->s("Invalid language");
			}else{
				$newData['default_lang'] = $this->input->post("default_lang",TRUE);
			}

			$newData['min_interval'] = (int)$this->input->post("minInterval") < 10 ? 10 : $this->input->post("minInterval");
			$newData['min_interval_schedule'] = abs($this->input->post("min_interval_schedule"));

			$newData['footer_text'] = $this->input->post("footer_text");

			$newData['footer_js'] = $this->input->post("footer_js",false);
			$newData['head_js'] = $this->input->post("head_js",false);
			$newData['ads_code'] = $this->input->post("ads_code",false);
			$newData['ads_code_public_p'] = $this->input->post("ads_code_public_p",true) == "on" ? 1 : 0;

			$newData['site_description'] = $this->input->post("site_description");

			$newData['maintenance_mode'] = $this->input->post("maintenance_mode",true) == "on" ? 1 : 0;

			switch ($this->input->post("date_format")) {
				case '1':
					$newData['date_format'] = "m/d/Y";
					break;
				case '2':
					$newData['date_format'] = "d/m/Y";
					break;
				case '3':
					$newData['date_format'] = "Y/d/m";
					break;
				case '4':
					$newData['date_format'] = "Y/m/d";
					break;
				default:
					$flashdataD[] = $this->lang->s('Incorrect date format');
					break;
			}

			$newData['site_description'] = $this->input->post("site_description");

			$this->Settings_Model->update($newData);

			$flashdataS = array();

			$flashdataS[] = $this->lang->s('Settings has been update');

			$this->session->set_flashdata("app_settings_success",$flashdataS);
			$this->session->set_flashdata("app_settings_danger",$flashdataD);
			redirect("settings/app_settings");
		}

		$twigData['roles'] = $roles;
		$twigData['lang'] = $this->lang;
		$twigData['timezones'] = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

		$this->twig->display('settings/app_settings',$twigData);
	}
	
	public function clear_cache()
	{	
		// Clear the cache
		$keepFiles = array("index.html","phpsessions");
		$cachePath = APPPATH . "cache/";
		$dirList = glob($cachePath.'*', GLOB_BRACE);

		$this->load->helper('rrmdir_helper');

		foreach ($dirList as $file) {
		    if(!in_array(pathinfo($file)['basename'], $keepFiles)){
		        if (is_dir($file)) {
		            rrmdir($file);
		        } else {
		            unlink($file);
		        }
		    }
		}
		display_json(array(
			'status' => 'success',
			'message' => $this->lang->s('Cache has been cleared')
		));
	}

}
?>