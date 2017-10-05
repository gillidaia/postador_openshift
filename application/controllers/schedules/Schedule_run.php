<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Schedule_run extends CI_Controller {

	private $currentUser;
	private $settings;
	private $randomChooseService;

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->model('User_Model');
		$this->load->model('FbAccount_Model');
		$this->load->helper('json_helper');
	}

	public function index(){
		$this->load->library('Cron_lib');
		if($this->cron_lib->lock()) return false;
		// Clear processed fb account
		$this->FbAccount_Model->clearProcessedFbAccounts();
		// Run services
		$this->auto_post();
		$this->cron_lib->unlock();
	}
	/*
	|----------------------------------------------------
	| Auto post
	|----------------------------------------------------
	*/
	private function auto_post(){

		$this->load->model('Schedule_Model');

		// Get schedule
		$schedules = $this->Schedule_Model->getPending();

		foreach ($schedules as $schedule) {

			// Check if facebook is already proccessed 
			$this->FbAccount_Model->setFbId($schedule->fb_account);
			if($this->FbAccount_Model->isFbAccountProcessed()){
				continue;
			}
			
			// Set the user timezone
			if(isset($schedule->timezone) && $schedule->timezone != null && in_array($schedule->timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL))){
				date_default_timezone_set($schedule->timezone);
			}
						
			$currentDateTime = new DateTime();
			$post_run_time = new DateTime($schedule->next_post_time);

			if(strtotime($currentDateTime->format("Y-m-d H:i")) < strtotime($post_run_time->format("Y-m-d H:i"))){
				continue;
			}

			if(USE_MULTI_THREAD){
				$this->load->library('dojob');
				$this->dojob->setService('schedules/schedule_run/send_post');
		        $this->dojob->setParams(array($schedule->userid,$schedule->id,APP_TOKEN));
		        $this->dojob->run();
			}else{
	        	$this->send_post($schedule->userid,$schedule->id,APP_TOKEN);
			}

		}
	}

	public function send_post($userId,$scheduleId,$token){

		if(!$userId || !$scheduleId || !$token){
			display_json(array(
				'status' => "error",
				'message' => "A required param is missing"
			));
			return;
		}

		if($token != APP_TOKEN){
			display_json(array(
				'status' => "error",
				'message' => "Action not allowed"
			));
			return;
		}

		ini_set('max_execution_time', 60000);
		set_time_limit(60000);

		// Get the schedule by id
		$this->load->model('Schedule_Model');
		$this->Schedule_Model->setId((int)$scheduleId);
		$this->Schedule_Model->setUserId((int)$userId);
		$schedule = $this->Schedule_Model->getById();

		if(!$schedule->row()){
			display_json(array(
				'status' => "error",
				'message' => "Schedule with id " . $scheduleId . " not found"
			));
			return;
		}

		$this->load->model('ScheduleLogs_Model');
		$this->ScheduleLogs_Model->setUserId($schedule->row('userid'));
        $this->ScheduleLogs_Model->setScheduleId($schedule->row('id'));

		$this->load->model('User_Model');
		$this->User_Model->setId($schedule->row('userid'));
		if(!$this->User_Model->canDoToday("post")){
			date_default_timezone_set('UTC');
			$currentDateTime = new DateTime();
			$currentDateTime->modify('+1 day');
			$this->Schedule_Model->update(array('next_post_time' => $currentDateTime->format('Y-m-d')));
			
			// Set Timezone
			if(in_array($schedule->row('timezone'), DateTimeZone::listIdentifiers(DateTimeZone::ALL))){
				date_default_timezone_set($schedule->row('timezone'));
			}
			
			// stop the schedule
			$this->ScheduleLogs_Model->setFbPost(null);
			$this->ScheduleLogs_Model->setContent('You reached the maximum posts allowed per day on your account');
			$this->ScheduleLogs_Model->save();
			return;
		}

		// Set Timezone
		if(in_array($schedule->row('timezone'), DateTimeZone::listIdentifiers(DateTimeZone::ALL))){
			date_default_timezone_set($schedule->row('timezone'));
		}
		
		// Get the post by id
		$this->load->model('Post_Model');
		$this->Post_Model->setId((int)$schedule->row('post_id'));
		$this->Post_Model->setUserId((int)$schedule->row('userid'));
		$post = $this->Post_Model->getById();

		if(!$post->row()){
			display_json(array(
				'status' => "error",
				'message' => "Post with id " . $schedule->row('post_id') . " not found"
			));
			// stop the schedule
			$this->Schedule_Model->update(array('pause'=>1));
			return;
		}

		// Get facebok account access token
		$this->load->model('FbAccount_Model');
		$this->FbAccount_Model->setUserId($schedule->row('userid'));
		$this->FbAccount_Model->setFbId($schedule->row('fb_account'));

		// Get fbaccount access token
		$accessToken = $this->FbAccount_Model->defaultAccessToken((int)$schedule->row('post_app'));

		if(!$accessToken->row()){
			// stop the schedule
			$this->ScheduleLogs_Model->setFbPost(null);
			$this->ScheduleLogs_Model->setContent("Access token missing : Reset access token or change app");
			$this->ScheduleLogs_Model->save();
			$this->Schedule_Model->update(array('pause'=>1));
			return;
		}

		/* --------------- Repeat schedule ------------- */

		// Update the schedule
		$sData = array();

		// Check if the current target is the last one
		if((int)$schedule->row('next_target')+1 >= (int)$schedule->row('total_targets')){
			// This was the last target
			// Repeat schedule
			if((int)$schedule->row('repeat_every') > 0 ){
				$now = new DateTime();
				$endOn = new DateTime($schedule->row('end_on'));
				if(strtotime($now->format("Y-m-d H:i")) >= strtotime($endOn->format("Y-m-d H:i"))){
					$sData['status'] = 1;
				}else{
					$next_post_time = new DateTime();
					$next_post_time->modify('+'.(int)$schedule->row('repeat_every').' day');

					$lastRepeated = new DateTime($schedule->row('repeated_at'));
					$newlastRepeated = new DateTime();
					$newlastRepeated->setTime($lastRepeated->format("H"), $lastRepeated->format("i"));
					$next_post_time->setTime($lastRepeated->format("H"), $lastRepeated->format("i"));

					$sData['repeated_at'] = $newlastRepeated->format('Y-m-d H:i');
					$sData['next_post_time'] = $next_post_time->format('Y-m-d H:i');
					$sData['next_target'] = 0;
				}
			}else{
				$sData['status'] = 1;	
			}
		}else{
			$currentDateTime = new Datetime();
			// Update the scheduled
			$currentDateTime->modify("+".$schedule->row('post_interval')." minutes");
			$next_post_time = $this->Schedule_Model->autoPause($schedule);

			if(!$next_post_time){
				$next_post_time = $currentDateTime->format('Y-m-d H:i');
			}

			$sData['next_target'] = (int)$schedule->row('next_target')+1;
			$sData['next_post_time'] = $next_post_time;
		}
		
		$this->Schedule_Model->update($sData);
		
		$targets = (array)json_decode($schedule->row('targets'),TRUE);

		$accessToken = $accessToken->row('access_token');

		$currentTargetIndex = (int)$schedule->row('next_target');
		
		// If target is null exit
		if(!isset($targets[$currentTargetIndex])){
			$this->ScheduleLogs_Model->setFbPost(null);
			$this->ScheduleLogs_Model->setContent("Null target");
			$this->ScheduleLogs_Model->save();
			return;
		}

		$currentTarget = $targets[$currentTargetIndex];

		// If target is null exit
		if(!isset($currentTarget['id']) || trim($currentTarget['id']) == "" || $currentTarget['id'] === 0 ){
			$this->ScheduleLogs_Model->setFbPost(null);
			$this->ScheduleLogs_Model->setContent("Null target");
			$this->ScheduleLogs_Model->save();
			return;
		}

		$nodeId = $currentTarget['id'] == "me" ? $currentTarget['id'] : $schedule->row('fb_account');
		$this->ScheduleLogs_Model->setNodeId($currentTarget['id']);
        $this->ScheduleLogs_Model->setNodeName($currentTarget['name']);
        $this->ScheduleLogs_Model->setNodeType($currentTarget['type']);

        // Check access token
		$this->load->model('Facebook_Model');
		$this->Facebook_Model->setAccessToken($accessToken);

		$this->load->library('spintax');

		// send
		$node = $currentTarget['id'];
		
		// Prepare post
		$postType = $post->row('type');
		$pf = (array)json_decode($post->row('content'),TRUE);
		$params = array();

		// Get user option
		$userOptions = $this->User_Model->options($schedule->row('userid'));

		if($userOptions->row() && $userOptions->row('uniquePost') == 1){
				
			$uniquePost = "";
			
			if(UNIQUE_POST_FORMAT == 1){
				$uniquePost = uniqid();
			} elseif (UNIQUE_POST_FORMAT == 2){
				$now = new DateTime();
				$uniquePost = $now->format('Y-m-d H:i');
			} elseif (UNIQUE_POST_FORMAT == 3) {
				$now = new DateTime();
				$uniquePost = uniqid()." - ".$now->format('Y-m-d H:i');
			}

			if(FB_PDP_POSITTION == 'bottom'){
				$params['message'] = urlencode($this->spintax->get(@$pf['message'])."\n\n". $uniquePost);
			}else{
				$params['message'] = urlencode($uniquePost."\n\n".$this->spintax->get(@$pf['message']));
			}
		}else{
			$params['message'] = urlencode($this->spintax->get(@$pf['message']));
		}

		// Add price and product name
		if(isset($pf['itemprice']) && isset($pf['itemname'])){
			$productDetails = $pf['itemname']." for sale \n";
			$productDetails .= "Price : ".$pf['itemprice']."\n";
			$params['message'] = urlencode($productDetails).$params['message'];
		}

		if($postType == "link"){	

			$link = $this->spintax->get($pf['link']);

			// If is unique post link enabled
			if($userOptions->row() && $userOptions->row('uniqueLink') == 1){
				if (strpos($link, '?') !== false) {
					$link = rtrim($link, "/")."&fb_node=".$node;
				}else{
					$link = rtrim($link, "/")."/?fb_node=".$node;
				}
			}

			$link = $link;
			$picture = $this->spintax->get($pf['picture']);
			$name = $this->spintax->get($pf['name']);
			$caption = $this->spintax->get($pf['caption']);
			$description = $this->spintax->get($pf['description']);

			$params['link'] = urlencode($link);
			
			$this->load->model("FbApps_Model");
			$fbapp = $this->FbApps_Model->getById($schedule->row('post_app'));

			// check facebook app
			// HTC sense still can post 
			if($fbapp->row("appid") != "193278124048833"){
				if(trim($picture) != "" || trim($name) != "" || trim($caption) != "" || trim($description) != ""){
					$params['link'] = base_url("/page/index/".$schedule->row('userid')."/".$post->row('id'));
				}
			}else{
				$params['picture'] = urlencode($picture);
				$params['name'] = urlencode($name);
				$params['caption'] = urlencode($caption);
				$params['description'] = urlencode($description);
			}
		}
		
		if($postType == "video"){
			$params['file_url'] = urlencode($this->spintax->get($pf['video']));
			$params['description'] = urlencode($this->spintax->get(@$pf['message']));
		}

		if($postType == "image"){

			$image_from = "url";
			if(FB_SEND_IMAGE_AS_MP){
				$image_from = "source";
			}

			$images = (array)$pf['image'];
			if(count($images) > 1){
				// Send all images
				$attached_media = array();
				foreach ($images as $image) {

					$currentIMG = $this->spintax->get($image);

					if($image_from == "source" && strpos($currentIMG, base_url()) == FALSE && strpos($currentIMG, "localhost") == FALSE){
						$params['url'] = urlencode($currentIMG);
					}else{
						$params[$image_from] = urlencode($currentIMG);
					}

					$params['published'] = 'false';
					$id = $this->Facebook_Model->post($node,$params,$postType,$accessToken);
					if($id){
						$attached_media[] = '{"media_fbid":"'.$id.'"}';
					}else{
						log_message("error","Failed to send image ".$params['url']." Facebook API response : ".$this->Facebook_Model->getError());
					}
					sleep(5);
				}
				
				if(count($attached_media) == 0){
					$this->ScheduleLogs_Model->setFbPost(NULL);
					$this->ScheduleLogs_Model->setContent("Error : ".$this->Facebook_Model->getError());
					$this->ScheduleLogs_Model->save();
					return;
				}

				unset($params[$image_from]);
				$params['published'] = 'true';
				$params['attached_media'] = '['.implode(',',$attached_media).']';
				$result = $this->Facebook_Model->post($node,$params,"message",$accessToken);

			}else{
				$currentIMG = $this->spintax->get($images[0]);

				if($image_from == "source" && strpos($currentIMG, base_url()) == FALSE && strpos($currentIMG, "localhost") == FALSE){
					$params['url'] = urlencode($currentIMG);
				}else{
					$params[$image_from] = urlencode($currentIMG);
				}
				
				$result = $this->Facebook_Model->post($node,$params,$postType,$accessToken);
			}
			
		}else{
			$result = $this->Facebook_Model->post($node,$params,$postType,$accessToken);
		}

		$this->load->model('Statistic_Model');
		$this->Statistic_Model->setUserId($schedule->row('userid'));

		if($result === false){
			$this->ScheduleLogs_Model->setFbPost(NULL);
			$this->ScheduleLogs_Model->setContent("Error : ".$this->Facebook_Model->getError());
			$this->Statistic_Model->update("posts_fail");

			if(strpos($this->Facebook_Model->getError(),"You're temporarily restricted from joining and posting to groups")){
				$this->Schedule_Model->update(array("pause"=>"1"));
			}elseif(strpos($this->Facebook_Model->getError(),"Error validating access token")){
				$this->Schedule_Model->update(array("pause"=>"1"));
			}

		}else{
			$this->ScheduleLogs_Model->setFbPost($result);
			$this->ScheduleLogs_Model->setContent(null);
			$this->FbAccount_Model->addProcessedFbAccount();
			$this->Statistic_Model->update("posts");
		}

		// Set Timezone
		if(in_array($schedule->row('timezone'), DateTimeZone::listIdentifiers(DateTimeZone::ALL))){
			date_default_timezone_set($schedule->row('timezone'));
		}

		$this->ScheduleLogs_Model->save();

	}
	
}