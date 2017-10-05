<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class User_notifications extends CI_Controller {

	private $settings;
	private $currentUser;
	private $userOptions;

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

		$this->settings = $this->Settings_Model->get();
		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('user', $this->User_Model);

		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));

		$this->load->model('UserNotifications_Model');
	}
	
	public function close($notificationId)
	{	
		$this->UserNotifications_Model->setId((int)$notificationId);
		// get notification
		$n = $this->UserNotifications_Model->getById();

		if($n && !$n->row()){
			display_json(array(
				'status' => 'fail',
				'message' => 'Notification not found'
			));
			return;
		}

		// If the notification has been sent to all users set cookie otherwise update the notification
		if($n->row('to_all') == 1){
			$notifClosed = $this->input->cookie($this->config->item('sess_cookie_name')."_notfi_".(int)$notificationId, TRUE);
			if(!$notifClosed){
				$cookie = array(
			        'name'   => '_notfi_'.(int)$notificationId,
			        'value'  => 1,
			        'expire' => '9000000',
			        'path'   => '/',
			        'prefix' => $this->config->item('sess_cookie_name'),
			        'secure' => FALSE
				);
				$this->input->set_cookie($cookie);
			}
			display_json(array(
				'status' => 'ok',
				'message' => 'Notification has been updated'
			));
			return;
		}

		// Update notification
		$this->UserNotifications_Model->setId((int)$notificationId);
		if($this->UserNotifications_Model->update(array("active"=>0))){
			display_json(array(
				'status' => 'ok',
				'message' => 'Notification has been updated'
			));
			return;
		}

		display_json(array(
				'status' => 'fail',
				'message' => 'Fail to update the notification'
			));
			return;
	}
}
