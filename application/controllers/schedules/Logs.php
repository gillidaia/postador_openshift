<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Logs extends CI_Controller {

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
		$this->load->library(array('session','twig'));

		$this->load->model('User_Model');
		$this->load->model('Settings_Model');
			
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

		$this->load->model('ScheduleLogs_Model');

		$this->settings = $this->Settings_Model->get();

		$this->twig->addGlobal('app_settings', $this->settings);

		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));

		$this->twig->addGlobal('user', $this->User_Model);

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));

	}

	public function index($scheduleid = "")
	{	
		$twigData = array();

		$this->load->library('pagination');
		$this->load->helper("pagination");

		$userOptions = $this->User_Model->options($this->currentUser['user_id']);
		$perPage = $userOptions->row('per_page');
		if(!$perPage) $perPage = 25;

		$this->ScheduleLogs_Model->setUserId($this->currentUser['user_id']);

		if($scheduleid != ""){
			$this->ScheduleLogs_Model->setScheduleId((int)$scheduleid);
		}

		$config = pagination_config();
		$config['base_url'] = base_url()."/schedules/logs/index/".$scheduleid;
		$config['total_rows'] = $this->ScheduleLogs_Model->count();
		$config['per_page'] = $perPage;
 		    
		$this->pagination->initialize($config);
		$pagination = $this->pagination->create_links();
		
		$logs = $this->ScheduleLogs_Model->get((int)$this->input->get('per_page', TRUE),$perPage,$scheduleid);
			
		$this->load->model('FbAccount_Model');
		$this->twig->addGlobal('fbaccount', $this->FbAccount_Model);
		$twigData['fbaccountDetails'] = $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount());

		$twigData['logs'] = $logs;
		$twigData['pagination'] = $pagination;
		$twigData['schedule_id'] = $scheduleid;
		$twigData['total_posts'] = $config['total_rows'];
		$twigData['User_Model'] = $this->User_Model;
		$twigData['perPage'] = $perPage >= $config['total_rows'] ? $config['total_rows'] : $perPage;

		$this->twig->display('logs', $twigData);

	}
	
	public function clear(){
		$this->ScheduleLogs_Model->setUserId($this->currentUser['user_id']);
		if($this->input->get('schedule_id',TRUE)){
			$this->ScheduleLogs_Model->setSchedule((int)$this->input->get('schedule_id',TRUE));
		}
		$this->ScheduleLogs_Model->delete();
		redirect("/schedules");
	}

	public function update($id)
	{	

		$this->load->helper(array('json_helper'));

		$this->ScheduleLogs_Model->setScheduleId((int)$id);

		$postID = substr(strrchr($this->input->post('post_id',TRUE), '_'), 1);

		$this->ScheduleLogs_Model->setFbPost($postID);
		$this->ScheduleLogs_Model->setUserId($this->currentUser['user_id']);

		$newData = array();

		$newData['share'] = (int)$this->input->post('sharedposts',TRUE);
		$newData['comments'] = (int)$this->input->post('comments',TRUE);

		$reactions = array(
			"like" => (int)$this->input->post('like',TRUE),
            "love" => (int)$this->input->post('love',TRUE),
            "wow"  => (int)$this->input->post('wow',TRUE),
            "haha" => (int)$this->input->post('haha',TRUE),
            "sad"  => (int)$this->input->post('sad',TRUE),
            "angry"=> (int)$this->input->post('angry',TRUE),
		);

		$newData['reactions'] = json_encode($reactions);

		if($this->ScheduleLogs_Model->updateInsight($newData)) {
			display_json(array(
				'status' => 'error',
				'message' => "Post insight has been updated"
			));
			return;
		}

		display_json(array(
			'status' => 'error',
			'message' => 'Nothing has been updated'
		));
		return;
	}

	public function post_insight($logID)
	{	
		$this->load->helper(array('json_helper'));

		$this->ScheduleLogs_Model->setId($logID);
		$this->ScheduleLogs_Model->setUserId($this->currentUser['user_id']);

		$log = $this->ScheduleLogs_Model->getById();

		if(!$log || !$log->row()){
			display_json(array(
				'status' => 'error',
				'message' => 'Log not found'
			));
			return;
		}

		$insight = (array)json_decode($log->row("reactions"),TRUE);

		$insight['comments'] = $log->row("comments");
		$insight['shares'] = $log->row("share");

		display_json(array(
			'status' => 'ok',
			'insight' => $insight
		));
		return;
	}
}
