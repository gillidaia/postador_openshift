<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Errors extends MX_Controller {

	private $settings;

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {	
		parent::__construct();
		$this->load->helper(array('flash_helper'));
		$this->load->library('twig');

		$this->load->model('Settings_Model');
		$this->settings = $this->Settings_Model->get();
		$this->twig->addGlobal('app_settings', $this->settings);
	
		$this->lang->load(array("general"));
	}
	
	public function error_404() {
		header("HTTP/1.0 404 Not Found");
		$this->twig->display('errors/error_404');
	}
	
}
