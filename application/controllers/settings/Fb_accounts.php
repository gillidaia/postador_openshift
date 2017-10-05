<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Fb_accounts extends CI_Controller {

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
		
		$this->userOptions = $this->User_Model->options($this->currentUser['user_id']);

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

	public function index() {
		
		$twigData = array();

		$this->load->helper('form');
		$this->load->library('form_validation');

		$this->form_validation->set_rules('limitImportGroups',$this->lang->s('limit Import Groups'),'trim|integer');
		$this->form_validation->set_rules('limitImportPages',$this->lang->s('limit Import pages'),'trim|integer');

		if ($this->form_validation->run() === true) {
			
			$newData = array();

			$loadGroups = $this->input->post("loadGroups") == "on" ? 1 : 0;
			$loadPages = $this->input->post("loadPages") == "on" ? 1 : 0;
			$loadOwnPages = $this->input->post("loadOwnPages") == "on" ? 1 : 0;

			$lig = (int)$this->input->post("limitImportGroups",TRUE);
			$lip = (int)$this->input->post("limitImportPages",TRUE);

			$lig = $lig > FB_IMPORT_MAX_GROUPS ? FB_IMPORT_MAX_GROUPS : $lig;
			$lip = $lip > FB_IMPORT_MAX_PAGES ? FB_IMPORT_MAX_PAGES : $lip;

			$newData['limitImportGroups'] = $lig;
			$newData['limitImportPages'] = $lip;
			$newData['load_groups']	= $loadGroups;
			$newData['load_pages']	= $loadPages;
			$newData['load_own_pages']	= $loadOwnPages;

			$this->User_Model->setId($this->currentUser['user_id']);

			if($this->User_Model->UpdateOptions($newData)){
				$twigData['flash'][] = flash_bag($this->lang->s('Your details has been update'),"success");
			}else{
				$twigData['flash'][] = flash_bag($this->lang->s('Nothing has been changed'),"info");
			}

		}else{
			foreach ($this->form_validation->error_array() as $key) {
				$twigData['flash'][] = flash_bag($key,"danger");
			}
		}
		
		$twigData['userOptions'] = $this->userOptions;

		$this->twig->display('settings/fb_accounts',$twigData);
	}

	public function add(){

		// Add new facebook account using access token
		$this->load->library('form_validation');

		$this->form_validation->set_rules('fb_accesstoken', $this->lang->s('Facebook access token'), 'trim|required');

		// Fields validation
		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		// Extract Facebook Access token
		$this->load->helper("general_helper");
		$eRes = extractAccessToken($this->input->post("fb_accesstoken",true));

		if($eRes['status'] == FALSE){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s($eRes['message'])
			));
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
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s($this->Facebook_Model->getError())
			));
			return;
		}

		// get facebook User info 
		$userData = $this->Facebook_Model->GetUserFromAccessToken($accessToken);
		
		if($userData == null){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s($this->Facebook_Model->getError())
			));
			return;
		}

		if($this->input->post('fb_account',TRUE) && is_numeric($this->input->post('fb_account',TRUE)) && $this->FbAccount_Model->exists($this->input->post('fb_account',TRUE))){
			$fbAccountID = $this->input->post('fb_account',TRUE);
		}else{
			$fbAccountID = $userData->id;
		}

		// Check if this facebook account is already exists
		$this->User_Model->setId($this->currentUser['user_id']);
		if(!$this->User_Model->canAddIGAccount($fbAccountID)){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('You reached the max facebook accounts allowed')
			));
			return;
		}

		$userOptions = $this->User_Model->options();

		// Get user groups
		if($userOptions->row('load_groups') == 1){
			$groupsLimit = $userOptions->row('limitImportGroups');
			$fbgroups = $this->Facebook_Model->LoadFbGroups($accessToken,$groupsLimit);

			if(is_array($fbgroups)){
				$this->FbAccount_Model->setGroups(json_encode($fbgroups));
			}	
		}

		// Get user liked pages
		$loadPages = $userOptions->row('load_pages') == 1 ? true : false;
		$loadOwnPages = $userOptions->row('load_own_pages') == 1 ? true : false;

		if($loadPages || $loadOwnPages){
			$pagesLimit = $userOptions->row('limitImportPages');
			$fbpages = $this->Facebook_Model->LoadFbPages($accessToken,$pagesLimit,$loadPages,$loadOwnPages);
			if(is_array($fbpages)){
				$this->FbAccount_Model->setPages(json_encode($fbpages));
			}
		}
		
		// Set current app as default if no app is set 
		$this->Facebook_Model->setAccessToken($accessToken);
		$App = $this->Facebook_Model->AppDetailsFromAt($accessToken);


		if($App && isset($App->id)){

			// Check if app exists & save access token
			$this->load->model('FbApps_Model');
			$this->FbApps_Model->setUserId($this->currentUser['user_id']);
			$this->FbApps_Model->setAppId($App->id);
			$fbApp = $this->FbApps_Model->getUserAppByFbAppId();

			if($fbApp->row()){

				$this->FbApps_Model->setId($fbApp->row('id'));

				$appAT = $this->FbApps_Model->getAccessToken($fbApp->row('id'),$fbAccountID,$this->currentUser['user_id']);

				$expires_in = isset($token->expires_in) ? $token->expires_in : "never";
				
				date_default_timezone_set($this->currentUser['timezone']);
				if($appAT && $appAT->row()){
					$this->FbApps_Model->updateAccessToken($accessToken,$fbAccountID,$expires_in);
				}else{
					$this->FbApps_Model->saveAccessToken($accessToken,$fbAccountID,$expires_in);
				}
			}
		}


		// Save new facebook account
		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($fbAccountID);
		$this->FbAccount_Model->setLastname($userData->last_name);
		$this->FbAccount_Model->setfirstname($userData->first_name);
		$this->FbAccount_Model->setName($userData->name);

		// Check if this facebook account is already exists;
		if($this->FbAccount_Model->exists($fbAccountID)){
			if(!$this->FbAccount_Model->UserFbAccountDefaultApp($fbAccountID)->row() && $fbApp->row()){
				$this->FbAccount_Model->setDefaultApp($fbApp->row('id'));
			}
			$this->FbAccount_Model->Update();
			$successMsg = "Your account has been updated successfully";
		}else{
			if($App && isset($App->id) && $fbApp->row()){
				$this->FbAccount_Model->setDefaultApp($fbApp->row('id'));
			}
			$this->FbAccount_Model->Save();
			$successMsg = "Your account has been added successfully";
		}

		// Set the current account as the default fb account if there is no default account
		if(!$this->FbAccount_Model->UserDefaultFbAccount()){
			$this->User_Model->setId($this->currentUser['user_id']);
			$this->User_Model->UpdateOptions(array('default_Fb_Account' => $fbAccountID));
		}

		display_json(array(
			'status' => 'success',
			'message' => $this->lang->s($successMsg)
		));
		return;
	}

	public function delete()
	{

		$this->load->library('form_validation');
		$this->form_validation->set_rules('id', $this->lang->s('Facebook account id'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
		}else{
			$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
			$this->FbAccount_Model->setFbId($this->input->post('id', TRUE));

			if($this->FbAccount_Model->delete()){
				display_json(array(
					'status' => 'success',
					'message' => $this->lang->s('Facebook account has been deleted')
				));
			}else{
				display_json(array(
					'status' => 'error',
					'message' => 'Nothing has been deleted'
				));
			}

		}
	}
	public function switch_fb_account($fbid = false) {
		$this->load->library('user_agent');
		if($fbid){
			if($this->FbAccount_Model->exists($fbid)){
				$newData['default_Fb_Account'] = $fbid;
				$this->User_Model->UpdateOptions($newData);
				$this->load->library('user_agent');
				if ($this->agent->referrer() != ""){
				    Redirect($this->agent->referrer());
				}		
			}
		}
		Redirect('settings/fb_accounts');
	}
	public function fbAccountsApps($fbaccount)
	{	
		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($fbaccount);
		$appsList = $this->FbAccount_Model->fbAccountApps();

		$apps = array();

		foreach ($appsList as $app) {
			$apps[] = array(
				"id" 		=> $app->id,
				"appid" 	=> $app->appid,
				"app_name" 	=> $app->app_name,
			);
		}

		display_json(array(
			'status' => 'ok',
			'apps' => $apps
		));
	}
	public function update(){

		// Update new facebook account using access token
		$this->load->library('form_validation');

		$this->form_validation->set_rules('fbaccount_id', $this->lang->s('Facebook account id'), 'trim|required');

		// Fields validation
		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		// Get account available access tokens
		$this->load->model('Facebook_Model');

		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($this->input->post("fbaccount_id",TRUE));
		
		$apps = $this->FbAccount_Model->fbAccountApps();

		if(!$apps){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("No valid access token for this Facebook Account available, update your facebook account via new Access token.")
			));
			return;
		}

		$validAccesstokenAvailable = false;
		$appsWithUGP = array('309481925768757','6628568379','350685531728','124024574287414','193278124048833');

		// Search for 'Facebook for iPhone' app
		foreach ($apps as $app) {
			if(in_array(trim($app->fbappid),$appsWithUGP)){
				$this->Facebook_Model->setAccessToken($app->access_token);
				$token = $this->Facebook_Model->accessTokenDetails();
				if($token != null){
					$validAccesstokenAvailable = true;
					break;
				}
			}
		}
		
		$status = "success"; // If the access token of an app on the list appsWithUGP is not found the access token may not load groups

		if($validAccesstokenAvailable === false){
			$status = "warning";
			foreach ($apps as $app) {
				if(in_array(trim($app->fbappid),$appsWithUGP)){
					continue;
				}
				// Test access token
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
				'message' => $this->lang->s("No valid access token for this Facebook Account available, update your facebook account via new Access token.")
			));
			return;
		}

		$accessToken = $this->Facebook_Model->getAccessToken();

		// Get token details 
		$token = $this->Facebook_Model->accessTokenDetails();
		
		// get facebook User info 
		$userData = $this->Facebook_Model->GetUserFromAccessToken($accessToken);
		
		if($userData == null){
			display_json(array(
				'status' => 'error',
				'message' =>  $this->Facebook_Model->getError()
			));
			return;
		}

		// Check if this facebook account is already exists
		$this->User_Model->setId($this->currentUser['user_id']);
		if(!$this->User_Model->canAddIGAccount($this->input->post("fbaccount_id",TRUE))){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('You reached the max facebook accounts allowed')
			));
			return;
		}

		$userOptions = $this->User_Model->options();

		// Get user groups
		if($userOptions->row('load_groups') == 1){
			$groupsLimit = $userOptions->row('limitImportGroups');
			$fbgroups = $this->Facebook_Model->LoadFbGroups($accessToken,$groupsLimit);

			if(is_array($fbgroups) && count($fbgroups) > 0){
				$this->FbAccount_Model->setGroups(json_encode($fbgroups));
			}	
		}

		// Get user liked pages
		$loadPages = $userOptions->row('load_pages') == 1 ? true : false;
		$loadOwnPages = $userOptions->row('load_own_pages') == 1 ? true : false;

		if($loadPages || $loadOwnPages){
			$pagesLimit = $userOptions->row('limitImportPages');
			$fbpages = $this->Facebook_Model->LoadFbPages($accessToken,$pagesLimit,$loadPages,$loadOwnPages);
			if(is_array($fbpages) && count($fbpages) > 0){
				$this->FbAccount_Model->setPages(json_encode($fbpages));
			}
		}
		
		// Set current app as default if no app is set 
		$this->Facebook_Model->setAccessToken($accessToken);
		$App = $this->Facebook_Model->AppDetailsFromAt($accessToken);

		// Save new facebook account
		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($this->input->post("fbaccount_id",TRUE));
		$this->FbAccount_Model->setLastname($userData->last_name);
		$this->FbAccount_Model->setfirstname($userData->first_name);
		$this->FbAccount_Model->setName($userData->name);

		// Check if this facebook account is already exists;
		if($this->FbAccount_Model->exists($this->input->post("fbaccount_id",TRUE))){
			if(!$this->FbAccount_Model->UserFbAccountDefaultApp($this->input->post("fbaccount_id",TRUE))->row() && $fbApp && $fbApp->row()){
				$this->FbAccount_Model->setDefaultApp($fbApp->row('id'));
			}
			$this->FbAccount_Model->Update();
			$successMsg = "Your account has been updated successfully";
		}else{
			if($App && isset($App->id) && $fbApp && $fbApp->row()){
				$this->FbAccount_Model->setDefaultApp($fbApp->row('id'));
			}
			$this->FbAccount_Model->Save();
			$successMsg = "Your account has been updated successfully";
		}

		// Set the current account as the default fb account if there is no default account
		if(!$this->FbAccount_Model->UserDefaultFbAccount()){
			$this->User_Model->setId($this->currentUser['user_id']);
			$this->User_Model->UpdateOptions(array('default_Fb_Account' => $this->input->post("fbaccount_id",TRUE)));
		}

		if($status == "warning"){
			$json = array(
				'status' => "warning",
				'message' => $this->lang->s($successMsg) ." ".$this->lang->s("Your groups has not been updated, Use a new access token to update your facebook account or load your groups Via HTML page"),
			);
		}else{
			$json = array(
				'status' => "success",
				'message' => $this->lang->s($successMsg)
			);
		}

		display_json($json);
		return;
	}
	public function import_groups(){

		$this->load->library('form_validation');

		$this->form_validation->set_rules('fbaccount_id', $this->lang->s('Facebook account id'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		if($_FILES['htmlpage']['type'] !== "text/html" ){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('The file must be html file type')
			));
			return;	
		}

		$file_html = @file_get_contents($_FILES['htmlpage']['tmp_name']);
		$file_html = mb_convert_encoding($file_html, 'HTML-ENTITIES', "UTF-8");
		preg_match_all(
		    HTML_GROUPS_PATTERN,
		    $file_html,
		    $matches,
		    PREG_PATTERN_ORDER
		);

		$dom = new DomDocument();

		$groups = array();
		$countGroups = 0;
		foreach ($matches[0] as $url) {
			$dom->loadHTML($url);
			foreach ($dom->getElementsByTagName('a') as $item) {
				$segments = explode('/', parse_url($item->getAttribute('href'), PHP_URL_PATH));
			    if(isset($segments[2]) && $segments[2] != "" && is_numeric($segments[2])){
			    	$groups[] = array(
				      "id" => $segments[2],
				      "name" => $item->nodeValue,
				      "privacy" => "-",
				    );
			    }
			    $countGroups++;
			    if($countGroups >= FB_IMPORT_MAX_GROUPS){
			    	continue;
			    }
			}
			if($countGroups >= FB_IMPORT_MAX_GROUPS){
		    	continue;
		    }
		}

		if(count($groups) == 0){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('No groups found on the submitted file')
			));
			return;	
		}

		$this->load->model('Facebook_Model');
		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($this->input->post("fbaccount_id",TRUE));
		$this->FbAccount_Model->setGroups(json_encode($groups));
		
		if($this->FbAccount_Model->update()){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('The total of %s groups has been saved into the selected facebook account',count($groups))
			));
			return;
		}

		display_json(array(
			'status' => 'success',
			'message' => $this->lang->s('The total of %s groups has been saved into the selected facebook account',count($groups))
		));
		return;
	}
	public function hide_nodes(){

		// Check required fields
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('nodes', $this->lang->s('nodes'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$fbaccount = $this->FbAccount_Model->UserDefaultFbAccount();

		if(!$fbaccount){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('NO_FB_ACCOUNT_SELECTED')
			));
			return;
		}

		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($fbaccount);
		
		$hg = (array)$this->FbAccount_Model->hideGroups(json_decode($this->input->post('nodes',TRUE),true));
		$hp = (array)$this->FbAccount_Model->hidePages(json_decode($this->input->post('nodes',TRUE),true));
		
		display_json(array('response' 	=> 'ok'));
		return;
	}
	public function unhide_nodes(){
		
		// Check required fields
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('nodes', $this->lang->s('nodes'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$fbaccount = $this->FbAccount_Model->UserDefaultFbAccount();

		if(!$fbaccount){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('NO_FB_ACCOUNT_SELECTED')
			));
			return;
		}

		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$this->FbAccount_Model->setFbId($fbaccount);
		
		$hg = (array)$this->FbAccount_Model->unhideGroups(json_decode($this->input->post('nodes',TRUE),true));
		$hp = (array)$this->FbAccount_Model->unhidePages(json_decode($this->input->post('nodes',TRUE),true));
		
		display_json(array('response' 	=> 'ok'));
		return;
	}
	public function fb_accounts()
	{	
		$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
		$fbas = $this->FbAccount_Model->getAll();

		$fbAccounts = array();

		foreach ($fbas as $fbAccount) {
			$fbAccounts[] = array(
				"id" 		=> $fbAccount->id,
				"fb_id" 		=> $fbAccount->fb_id,
				"name" 	=> $fbAccount->name,
			);
		}

		display_json(array(
			'status' => 'ok',
			'fb_accounts' => $fbAccounts
		));
	}
}
?>