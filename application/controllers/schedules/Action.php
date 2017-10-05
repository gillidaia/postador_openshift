<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Action extends CI_Controller {

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
		$this->load->helper(array('url','json_helper','general_helper'));
		
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
		$this->load->model('Schedule_Model');
		$this->load->library('twig');

		$this->settings = $this->Settings_Model->get();

		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('userdata', $this->User_Model->get($this->currentUser['user_id']));

		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));
		
		$this->twig->addGlobal('lang', $this->lang);
	}
	
	public function index()
	{	
		redirect('schedules');
	}
	public function add(){

		$this->load->library('form_validation');

		// Required fields
		$this->form_validation->set_rules(
			'post_id', $this->lang->s('Post id'), 'trim|required',
			array('required' => $this->lang->s('No post has been choosed!'))
		);

		$this->form_validation->set_rules(
			'post_app', $this->lang->s('Facebook  app'), 'trim|required',
			array('required' => $this->lang->s('No Facebook app has been selected! Please select a facebook app.'))
		);

		$this->form_validation->set_rules(
			'nodes', $this->lang->s('Nodes'), 'trim|required',
			array('required' => $this->lang->s('At least one node must be selected'))
		);

		$this->form_validation->set_rules(
			'run_at', $this->lang->s('Date and time of publishing'),
			'trim|required',
			array('required' => $this->lang->s('Date and time of publishing must be specified.'))
		);

		$this->form_validation->set_rules(
			'post_interval', $this->lang->s('Post interval'),
			'trim|required|integer',
			array('required' => $this->lang->s('Post interval must be specified.'))
		);
		
		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			exit;
		}
		
		$nextRunTime = dateFromFormat($this->settings['date_format']." H:i",$this->input->post("run_at",TRUE));
		if(!$nextRunTime){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('Schedule post Start date or time is invalid')
			));
			exit;
		}

		$endOn = new Datetime();

		if($this->input->post('repeat_every',TRUE) > 0){
			$endOn = dateFromFormat($this->settings['date_format']." H:i",$this->input->post("end_on",TRUE));
			if(!$endOn){
				display_json(array(
					'status' => 'error',
					'message' => $this->lang->s('End on date or time is invalid')
				));
				exit;
			}
		}

		$nodes = json_decode($this->input->post('nodes', TRUE),TRUE);

		if(!is_array($nodes)){
			display_json(array(
				'status' => 'error',
				'message' => "Invalid list of groups/pages, expected array, '". gettype($nodes) . "' given Instead."
			));
			exit;
		}

		$newNodes = array();
		$this->load->model('FbAccount_Model');
		$nodeBaseList = array_values($this->FbAccount_Model->GetGroupsAndPages());

		if(is_array($nodes) && count($nodes) != 0){
			if(is_array($nodeBaseList) && count($nodeBaseList) != 0){
				for($i = 0; $i<count($nodes); $i++) {
					for($j = 0; $j<count($nodeBaseList);$j++) {
						if(in_array($nodes[$i], $nodeBaseList[$j]) && $nodes[$i] != "me"){
							$node =array();
							$node['id']  = $nodeBaseList[$j]['id'];
							$node['name'] = $nodeBaseList[$j]['name'];
							
							$node['type'] = "-";
							if(!isset($nodeBaseList[$j]['privacy'])) $node['type'] = "page";
							if(isset($nodeBaseList[$j]['privacy'])) $node['type'] = "group";
							
							$newNodes[] = $node;
							break;
						}else if($nodes[$i] == "me"){
							$node =array();
							$node['id']  = "me";
							$node['name'] = "My Profile";
							
							$node['type'] = "Profile";
							
							$newNodes[] = $node;
							break;
						}
					}
				}
			}else{
				display_json(array(
					'status' => 'error',
					'message' => "Could not load the list of groups and pages"
				));
				exit;
			}
		}else{
			display_json(array(
				'status' => 'error',
				'message' => "Invalid value supplied"
			));
			exit;
		}	

		$this->Schedule_Model->setuserId((int)$this->currentUser['user_id']);
		$this->Schedule_Model->setpostId($this->input->post('post_id', TRUE));

	    $this->Schedule_Model->setTargets(json_encode($newNodes));
	    $this->Schedule_Model->setPostInterval((int)$this->input->post('post_interval',TRUE));
	    $this->Schedule_Model->setPostId((int)$this->input->post('post_id',TRUE));
	    $this->Schedule_Model->setPostApp($this->input->post('post_app',TRUE));
	    $this->Schedule_Model->setFbAccount($this->FbAccount_Model->UserDefaultFbAccount());
	    
	    $autoPause = array();
		$autoPause['pause'] = (int)$this->input->post("pause_after",TRUE);
		$autoPause['pause_after'] = (int)$this->input->post("pause_after",TRUE)-1;
		$autoPause['resume'] = $this->input->post("resume_after",TRUE);

	    $this->Schedule_Model->setAutoPause(json_encode($autoPause));

	    $this->Schedule_Model->setRepeatEvery((int)$this->input->post('repeat_every',TRUE));

		$this->Schedule_Model->setNextRunTime($nextRunTime->format('Y-m-d H:i'));
	    $this->Schedule_Model->setRepeatedAt($nextRunTime->format('Y-m-d H:i'));
	    $this->Schedule_Model->setEndOn($endOn->format("Y-m-d H:i"));
	    $this->Schedule_Model->setTotalTargets(count($newNodes));

		if($this->Schedule_Model->save()){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Schedule has been saved successfully')
				));
		}else{
			display_json(array(
				'status' => 'error',
				'message' => 'unable to save your schedule'
				));
		}
	}	
	public function delete(){
		$this->load->library('form_validation');

		$this->form_validation->set_rules('ids', 'IDs', 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			exit;
		}

		$ids = (array)json_decode($this->input->post('ids'),true);
		
		if(count($ids) == 0) {
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("No record has been specified")
			));
			exit;
		}

		$this->Schedule_Model->setUserId((int)$this->currentUser['user_id']);

		if($this->Schedule_Model->deleteAll($ids)){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s("Schedule(s) has been deleted successfully")
			));
			exit;
		}else{
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("Enable to delete the requested records. Please try again")
			));
			exit;
		}
	}
	public function toggle_schedule_pause(){
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('sid', $this->lang->s('Schedule id'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
		}else{

		$this->Schedule_Model->setUserId((int)$this->currentUser['user_id']);
		$this->Schedule_Model->setId((int)$this->input->post('sid', TRUE));

		if($this->Schedule_Model->toggleScheduleStatus()){
				display_json(array(
					'status' => 'success',
					'message' => $this->lang->s('Schedule status updated')
				));
			}else{
				display_json(array(
					'status' => 'error',
					'message' => $this->lang->s('Unabe to update schedule status')
				));
			}
		}
	}
	public function update()
	{

		$this->load->library('form_validation');

		// Required fields
		$this->form_validation->set_rules(
			'schedule_id', $this->lang->s('Schedule  ID'), 'trim|required',
			array('required' => $this->lang->s('The schedule ID is missing.'))
		);

		$this->form_validation->set_rules(
			'post_app', $this->lang->s('Facebook  app'), 'trim|required',
			array('required' => $this->lang->s('No Facebook app has been selected! Please select a facebook app.'))
		);

		$this->form_validation->set_rules(
			'run_at', $this->lang->s('Date and time of publishing'),
			'trim|required',
			array('required' => $this->lang->s('Date and time of publishing must be specified.'))
		);

		$this->form_validation->set_rules(
			'post_interval', $this->lang->s('Post interval'),
			'trim|required',
			array('required' => $this->lang->s('Post interval can not be empty'))
		);

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$nextRunTime = dateFromFormat($this->settings['date_format']." H:i",$this->input->post("run_at",TRUE));
		if(!$nextRunTime){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('Schedule post Start date or time is invalid')
			));
			exit;
		}

		$endOn = new Datetime();

		if($this->input->post('repeat_every',TRUE) > 0){

			$endOn = dateFromFormat($this->settings['date_format']." H:i",$this->input->post("end_on",TRUE));
			if(!$endOn){
				display_json(array(
					'status' => 'error',
					'message' => $this->lang->s('End on date or time is invalid')
				));
				exit;
			}
		}

		$this->load->model('FbAccount_Model');

	    $newData = array();
	    $autoPause = array();
		
		$autoPause['pause'] = (int)$this->input->post("pause_after",TRUE);
		$autoPause['pause_after'] = (int)$this->input->post("pause_after",TRUE)-1;
		$autoPause['resume'] = $this->input->post("resume_after",TRUE);

		$newData['auto_pause'] = json_encode($autoPause);

		$newData['next_post_time'] = $nextRunTime->format('Y-m-d H:i');
	    $newData['post_interval'] = abs($this->input->post('post_interval',TRUE));
	    $newData['post_app'] = $this->input->post('post_app',TRUE);
	    $newData['fb_account'] = $this->FbAccount_Model->UserDefaultFbAccount();
	    $newData['repeat_every'] = $this->input->post('repeat_every',TRUE);
	    $newData['repeated_at'] = $nextRunTime->format('Y-m-d H:i');
	    $newData['end_on'] = $endOn->format("Y-m-d H:i");


	    // Get schedule by id
		$this->Schedule_Model->setId((int)$this->input->post('schedule_id', TRUE));
		$this->Schedule_Model->setuserId((int)$this->currentUser['user_id']);

		$schedule = $this->Schedule_Model->getById();

		if(!$schedule->row()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('Schedule not found')
			));
			return;
		}

	    // if the schedule is finished repost
	    if($schedule->row('status') == 1){
	    	$newData['status'] = 0;
	    	$newData['next_target'] = 0;
	    }
		
		if($this->Schedule_Model->update($newData)){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Schedule been updated successfully')
				));
		}else{
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('Nothing has been changed')
				));
		}
	}
	public function details($scheduleID){
		// Get schedule by id
		$this->Schedule_Model->setId((int)$scheduleID);
		$this->Schedule_Model->setUserId((int)$this->currentUser['user_id']);
		$schedule = $this->Schedule_Model->getById();

		if(!$schedule->row()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('Schedule not found')
			));
			return;
		}

		// show schedule details
		$sDetails = array(
			'post_id' 			=> $schedule->row('post_id'),
			'next_post_time' 	=> $schedule->row('next_post_time'),
			'next_target' 		=> $schedule->row('next_target'),
			'post_interval' 	=> $schedule->row('post_interval'),
			'post_app' 			=> $schedule->row('post_app'),
			'auto_pause' 		=> json_decode($schedule->row('auto_pause'),true),
			'repeat_every' 		=> $schedule->row('repeat_every'),
			'start_on' 		=> $schedule->row('repeated_at'),
			'end_on' 		=> $schedule->row('end_on'),
			'total_targets' 	=> $schedule->row('total_targets'),
			'status' 			=> $schedule->row('status'),
			'pause' 			=> $schedule->row('pause'),
		);

		$this->load->model('FbAccount_Model');
		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($schedule->row('fb_account'));
		$appsList = $this->FbAccount_Model->fbAccountApps();

		$apps = array();

		foreach ($appsList as $app) {
			$apps[] = array(
				"id" 		=> $app->id,
				"appid" 	=> $app->appid,
				"app_name" 	=> $app->app_name,
			);
		}

		$sDetails['fb_account']	= array(
			'fb_id' 	=> $schedule->row('fb_account'),
			'fb_apps'	=> $apps
		);

		display_json(array(
			'status' => 'ok',
			'schedule' => $sDetails
		));
	}
}