<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Facebook extends CI_Controller {

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
		$this->settings = $this->Settings_Model->get();
		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));

		$this->twig->addGlobal('app_settings', $this->settings);
		$this->twig->addGlobal('user', $this->User_Model);
	}
	
	public function app_authentication($type = NULL)
	{
		
		// Get the app 
		if ($type == NULL){
			show_404();
		}

		$app_id = $this->input->get('app_id',TRUE);

		if($app_id == NULL){
			show_404();
		}

		$this->load->model('FbApps_Model');
		
		// Find app or show error
		$this->FbApps_Model->setId((int)$app_id);
		$app = $this->FbApps_Model->getUserAppById();

		if($app->row() == NULL){
			echo $this->lang->s("App not found");
			return;
		}

		switch ($type) {
			case 'own_app':
				$this->own_app_auth($app);
				break;
			case 'third_party':
				$this->third_party_auth($app);
				break;
			default:
				echo "Unknow type ".$type;
				return;
		}

		return;
	}

	private function own_app_auth($app){
		$twigData = array();

		$this->load->helper(array('flash_helper'));
		$this->load->model('Facebook_Model');

		try{
			$notic = $this->Facebook_Model->FbAuth($app);
			
			if($notic != ""){
				$twigData['flash'][] = flash_bag($this->lang->s($notic),"success",TRUE,FALSE,TRUE);
			}

			$twigData['flash'][] = flash_bag($this->lang->s("Successfully authorized")." <a href='#' onclick='window.opener.location.href = window.opener.location.href;window.close();'>Close this window</a>","success",TRUE,FALSE,TRUE);

		}catch(Exception $ex){
			$twigData['flash'][] = flash_bag($ex->GetMessage(),"danger",TRUE,FALSE);
		}

		$this->twig->display('app_authentication/own_app',$twigData);
	}

	private function third_party_auth($app){

		$twigData = array();

		$twigData['app'] = $app;

		$twigData['appWithRealFBId'] = array(
			'41158896424',
			'10754253724',
			'179375112119470',
			'24553799497',
			'200758583311692',
			'201123586595554'
		);

		if($app->row('appid') == "6628568379" || $app->row('appid') == "350685531728"){
			$view = 'app_authentication/third_party_app_using_pw';
		}else{
			$view =	'app_authentication/third_party_app';
		}
		 
		$this->load->helper(array('form','flash_helper'));
		$this->load->library('form_validation','Facebook_API');

		// set validation rules
		$this->form_validation->set_rules('access_token', $this->lang->s('Access token'), 'trim|required',array(
			'required' => $this->lang->s('The access token field is empty!')
		));
	
		if ($this->form_validation->run() == false) {
			// validation not ok, send validation errors to the view
			$this->twig->display($view,$twigData);
			return;
		}

		$this->load->model('FbAccount_Model');

		$currentFbAccount = $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount());

		// Check if a facebook account is available
		if(!$currentFbAccount->row()){
			$twigData['flash'][] = flash_bag($this->lang->s('NO_FB_ACCOUNT_SELECTED'),"danger");
			$this->twig->display($view,$twigData);
			return;
		}

		// Extract Facebook Access token
		$this->load->helper("general_helper");
		$eRes = extractAccessToken($this->input->post("access_token",true));

		if($eRes['status'] == FALSE){
			$twigData['flash'][] = flash_bag($this->lang->s($eRes['message']),"danger");
			$this->twig->display($view,$twigData);
			return;
		}

		// Access token 
		$accessToken = $eRes['access_token'];

		$this->load->model('Facebook_Model');

		//  Set access token
		$this->Facebook_Model->setAccessToken($accessToken);

		// Get token details 
		$token = $this->Facebook_Model->accessTokenDetails();

		// Check if token is valid
		if($token == null){
			$twigData['flash'][] = flash_bag($this->Facebook_Model->getError(),"danger");
			$this->twig->display($view,$twigData);
			return;
		}

		// get facebook User info 
		$userData = $this->Facebook_Model->GetUserFromAccessToken($accessToken);
		
		if(!$userData){
			$twigData['flash'][] = flash_bag($this->lang->s('Unable to get Fb account details') ." : ". $this->Facebook_Model->getError(),"danger");
			$this->twig->display($view,$twigData);
			return;
		}

		if(
			in_array($app->row('appid'),$twigData['appWithRealFBId']) && 
			$userData->id != $currentFbAccount->row('appid') && 
			trim($currentFbAccount->row('firstname')) != trim($userData->first_name) && 
			trim($currentFbAccount->row('lastname')) != trim($userData->last_name)
		){

			$twigData['flash'][] = flash_bag($this->lang->s('The current facebook account is %s and you are logged in on facebook as %s',"<strong>".$currentFbAccount->row('firstname') . " " . $currentFbAccount->row('firstname')."</strong>","<strong>".$userData->first_name." ".$userData->last_name." </strong>"),"danger");
			$twigData['flash'][] = flash_bag($this->lang->s('To avoid inserting the wrong access token make sure to logged into the same facebook account on facebook.'),'warning');
		
			$this->twig->display($view,$twigData);
			return;
		}

		// Save|update access token
		$userID = $this->currentUser['user_id'];
		$fbID = $currentFbAccount->row('fb_id');
		$appID = $app->row('id');

		$this->FbApps_Model->setUserId($userID);
		$this->FbApps_Model->setId($appID);
		
		$at = $this->FbApps_Model->getAccessToken($appID,$fbID,$userID);
		
		$expires_in = isset($token->expires_in) ? $token->expires_in : "never";

		if($at && $at->row()){
			// Update AT
			$resp = $this->FbApps_Model->updateAccessToken($accessToken,$fbID,$expires_in);
		}else{
			// Save AT
			$resp = $this->FbApps_Model->saveAccessToken($accessToken,$fbID,$expires_in);
		}
		
		if($resp === false){
			$twigData['flash'][] = flash_bag($this->lang->s('Nothing has been saved! something went wrong please try again'),"danger");
			$this->twig->display($view,$twigData);
			return;
		}

		if(trim($currentFbAccount->row('firstname')) != trim($userData->first_name) && trim($currentFbAccount->row('lastname')) != trim($userData->last_name)){	
			$twigData['flash'][] = flash_bag($this->lang->s('It seems that you logged into a different facebook account on facebook! To avoid inserting the wrong access token make sure to logged into the same facebook account on facebook.'),'warning');
		}
	
		$twigData['flash'][] = flash_bag($this->lang->s('ACCESS_TOKEN_SAVED_SUCCESS')." <a href='#' onclick='window.opener.location.href = window.opener.location.href;window.close();'>Close this window</a>","success",TRUE,TRUE,TRUE);

		$this->twig->display($view,$twigData);
		
	}

	public function generate_token($appID)
	{
		$this->load->library('form_validation');
		$this->load->helper('json_helper');

		$this->form_validation->set_rules('username', $this->lang->s('Facebook username'), 'trim|required');
		$this->form_validation->set_rules('password', $this->lang->s('Facebook passowrd'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'fail',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$username = $this->input->post('username',TRUE);
		$password = $this->input->post('password',TRUE);

		$apps = array();

		$apps['6628568379'] = array(
			"api_key" => "3e7c78e35a76a9299309885393b02d97",
			"api_secret" => "c1e620fa708a1d5696fb991c1bde5662"
		);

		$apps['350685531728'] = array( 
			"api_key" => "882a8490361da98702bf97a021ddc14d",
			"api_secret" => "62f8ce9f74b12f84c123cc23437a4a32"
		);

		if(!isset($apps[$appID])){
			display_json(array(
				'status' => 'fail',
				'fb_url' => "Invalid Facebook APP ID"
			));
			return;
		}

		$sig = md5("api_key=".$apps[$appID]['api_key']."credentials_type=passwordemail=".trim($username)."format=JSONgenerate_machine_id=1generate_session_cookies=0locale=en_USmethod=auth.loginpassword=".trim($password)."return_ssl_resources=0v=1.0".$apps[$appID]['api_secret']);

		$fb_url = "https://api.facebook.com/restserver.php?api_key=".$apps[$appID]['api_key']."&credentials_type=password&email=".trim($_POST['username'])."&format=JSON&generate_machine_id=1&generate_session_cookies=0&locale=en_US&method=auth.login&password=".urlencode(trim($_POST['password']))."&return_ssl_resources=0&v=1.0&sig=".$sig;

		display_json(array(
			'status' => 'ok',
			'fb_url' => $fb_url
		));
		return;
	}

	public function get_access_token($schedule){
		
		// Get account available access tokens
		$this->load->model('Facebook_Model');
		$this->load->model('Schedule_Model');
		$this->load->model('FbAccount_Model');

		$this->load->helper('json_helper');

		// Get Schedule
		$this->Schedule_Model->setId((int)$schedule);
		$this->Schedule_Model->setUserId($this->currentUser['user_id']);

		$schedule = $this->Schedule_Model->getById();

		if(!$schedule->row()){
			display_json(array(
				'status' => "error",
				'message' => "Schedule with id " . $scheduleId . " not found"
			));
			return;
		}

		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($schedule->row('fb_account'));

		$fbaccount = $this->FbAccount_Model->getFbAccountById($schedule->row('fb_account'),$this->currentUser['user_id']);
		
		$apps = $this->FbAccount_Model->fbAccountApps();

		if(!$apps){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("No valid access token for ('Facebook for iPhone' or 'Facebook for Android' or 'HTC sense') '%s %s' available, Go to Facebook Apps section and authorize an app then try again.",$fbaccount->row('firstname'),$fbaccount->row('lastname'))
			));
			return;
		}

		$validAccesstokenAvailable = false;

		$appsToSearchFor = array('6628568379','350685531728','193278124048833');

		foreach ($apps as $app) {
			if(in_array(trim($app->fbappid),$appsToSearchFor)){
				$this->Facebook_Model->setAccessToken($app->access_token);
				$token = $this->Facebook_Model->accessTokenDetails();
				if($token != null){
					$validAccesstokenAvailable = true;
					break;
				}
			}
		}

		if($validAccesstokenAvailable === false){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("No valid access token for ('Facebook for iPhone' or 'Facebook for Android' or 'HTC sense') '%s %s' available, Go to Facebook Apps section and authorize an app then try again.",$fbaccount->row('firstname'),$fbaccount->row('lastname'))
			));
			return;
		}

		display_json(array(
			'status' => 'success',
			'access_token' => $token->access_token
		));
		return;
	}
}
