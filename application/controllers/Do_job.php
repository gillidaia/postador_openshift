<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * 
 * @extends CI_Controller
 */
class Do_job extends CI_Controller {
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {	
		parent::__construct();
		$this->load->database();
	}
	
	public function check_expired_accounts() {
		$this->load->model("User_Model");
		$this->User_Model->checkExpiredAccounts();
	}
	
}
