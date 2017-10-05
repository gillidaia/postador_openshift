<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * settings_modal class.
 * 
 * @extends CI_Model
 */
class Facebook_model extends MY_Model { 

	private $_groups = null;
	private $appId;
	private $_app_secret = null;
	private $error;
	private $accessToken = null;
	private $rawResponse = null;
	private $pageAfter = null;

	// Setters and getters Auto Generate  
    public function __call($function, $args)
    {
        $functionType = strtolower(substr($function, 0, 3));
        $propName = lcfirst(substr($function, 3));
        switch ($functionType) {
            case 'get':
                if (property_exists($this, $propName)) {
                    return $this->$propName;
                }
                break;
            case 'set':
                if (property_exists($this, $propName)) {
                    $this->$propName = $args[0];
                }
                break;
        }
    }

	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->library(array('session','Facebook_API'));
	}

	public function GetUserFromAccessToken(){
		$this->facebook_api->setNode("me");
		$this->facebook_api->setMethod("get");
		$this->facebook_api->setAccessToken($this->accessToken);
		$params =  array('fields'=>'id,name,email,first_name,last_name');
		$this->rawResponse = $this->facebook_api->request($params);
		$res = json_decode($this->rawResponse->getBody());
		
		if(isset($res->error)){
			$this->error = $res->error->message;
			return false;
		}

		return $res;
	}
	
	private function getFbJsonResponse($rawResponse){
		if($rawResponse === FALSE){
			$this->error = $this->facebook_api->getError();
			return FALSE;
		}

		$res = json_decode($rawResponse->getBody());
		
		if(isset($res->error)){
			$this->error = $res->error->message;
			if(isset($res->error->error_user_title)){
				$this->error .= "\nError Details : ".$res->error->error_user_title;
			}
			if(isset($res->error->error_user_msg)){
				$this->error .= " : ".$res->error->error_user_msg;
			}
			return false; 
		}

		return $res;
	}

	/*
	|---------------------------------------------------------
	| Set the accessToken Check if the current acces token is valid
	|---------------------------------------------------------
	|
	*/
	public function IsATValid(){
		$this->facebook_api->setEndPoint("oauth/access_token_info");
		$this->facebook_api->setMethod("get");
		$this->facebook_api->setAccessToken($this->accessToken);
		$this->rawResponse = $this->facebook_api->request();

		if(!$this->rawResponse) return FALSE;

		$response = json_decode($this->rawResponse->getBody());

		if(!isset($response->access_token) || $response->access_token == ""){
			return FALSE;
		}

		return TRUE;
	}
	
	public function accessTokenDetails(){
		$this->facebook_api->setEndPoint("oauth/access_token_info");
		$this->facebook_api->setMethod("get");
		$this->facebook_api->setAccessToken($this->accessToken);

		if($res = $this->getFbJsonResponse($this->facebook_api->request())){
			return $res;
		}
		return FALSE;
	}


	public function FbUserIdFromAt(){

		$this->facebook_api->setNode("me");
		$this->facebook_api->setAccessToken($this->accessToken);

		$this->rawResponse = $this->facebook_api->request();
		$res = json_decode($this->rawResponse->getBody());
		
		return isset($res->id) ? $res->id : false;
	}
	
	public function FbAppUserRole($FbUserId,$app_id,$app_secret){
		$this->facebook_api->setNode($app_id);
		$this->facebook_api->setEndPoint('roles');
		$this->facebook_api->setMethod("get");
		$this->facebook_api->setAccessToken($app_id."|".$app_secret);

		$params = array('fields'=>'user,role');

		$this->rawResponse = $this->facebook_api->request($params);
		$res = json_decode($this->rawResponse->getBody());

		if($res == null) return false;

		foreach($res->data as $user){
			if($user->user == $FbUserId){
				return $user->role;
			}
		}
		return false;
	}

	/*
	|--------------------------------------------------------------------------
	| Get the list of groups of the current user
	|--------------------------------------------------------------------------
	|
	*/ 
	public function LoadFbGroups($accessToken, $limit = 1000){

		$limit = $limit > FB_IMPORT_MAX_GROUPS ? FB_IMPORT_MAX_GROUPS : $limit;

		$this->facebook_api->setApiVersion('v2.3');
		$this->facebook_api->setNode('me');
		$this->facebook_api->setEndPoint('groups');
		$this->facebook_api->setAccessToken($this->accessToken);

		$params = array(
			'fields'=> 'id,name,privacy,members.summary(total_count).limit(0)',
			'limit'	=> $limit,
		);

		$this->rawResponse = $this->facebook_api->request($params);

		$res = json_decode($this->rawResponse->getBody()); 

		if(isset($res->error)){
			$this->error = $res->error->message;
			return false; 
		}

		return $res->data;
	}

	/*
	|--------------------------------------------------------------------------
	| Get the list of pages of the current user
	|--------------------------------------------------------------------------
	|
	*/ 
	public function LoadFbPages($accessToken, $limit = 500,$loadPages,$loadOwnPages){
		
		$limit = $limit > FB_IMPORT_MAX_PAGES ? FB_IMPORT_MAX_PAGES : $limit;

		$p = $limit > 99 ? $limit / 100 : 1;
		
		$limit = $limit > 100 ? 100 : $limit;

		$pages = array();

		$params = array(
			'fields'=> 'id,name,likes,access_token',
			'limit'	=> $limit,
		);

		if($loadPages){
			for ($i=0; $i<$p ; $i++) {
				$this->facebook_api->setApiVersion('v2.3');
				$this->facebook_api->setNode('me');
				$this->facebook_api->setEndPoint('likes');
				$this->facebook_api->setAccessToken($this->accessToken);

				$this->rawResponse = $this->facebook_api->request($params);
				$res = json_decode($this->rawResponse->getBody());
				
				if(isset($res->data)){
					if(!empty($res->data)){
						$pages = array_merge($pages,$res->data);
						if(isset($res->paging->cursors->after)){
							$params['after'] = $res->paging->cursors->after;
							continue;
						}
					}
				}

				break;
			}
		}

		// Remove cursor
		unset($params['after']);

		if($loadOwnPages){
			for ($i=0; $i<$p ; $i++) {
				$this->facebook_api->setApiVersion('v2.3');
				$this->facebook_api->setNode('me');
				$this->facebook_api->setEndPoint('accounts');
				$this->facebook_api->setAccessToken($this->accessToken);

				$this->rawResponse = $this->facebook_api->request($params);
				$res = json_decode($this->rawResponse->getBody());
				
				if(isset($res->data)){
					if(!empty($res->data)){
						$pages = array_merge($pages,$res->data);
						if(isset($res->paging->cursors->after)){
							$params['after'] = $res->paging->cursors->after;
							continue;
						}
					}
				}

				break;
			}
		}

		$this->load->helper("unique_multidim_array");
		$pages = unique_multidim_array($pages,"id");

		return $pages;
	}

	/*
	|--------------------------------------------------------------------------
	| Post to facebook group and return result
	| @return type array
	|--------------------------------------------------------------------------
	|
	*/ 
	public function Post($node,$params,$postType,$accessToken = null){

		$this->load->model('FbAccount_Model');

		$fbNodes = $this->FbAccount_Model->GetPages();

		$nodeHasAccessToken = false;

		$nodeType = "group";

		// Check if the Node is a page and has access token
		for($i = 0; $i<count($fbNodes); $i++) {
			if(isset($fbNodes[$i]) && $fbNodes[$i]['id'] == $node){
				$nodeType = "page";
				if(isset($fbNodes[$i]['access_token'])){
					$this->facebook_api->setAccessToken($fbNodes[$i]['access_token']);
					$nodeHasAccessToken = true;
					break;
				}
			}
		}

		// Unpublished post must post behalf the page name
		if($nodeType == "page" && $postType == "image" && isset($params['published']) && $params['published'] == 'false' && $nodeHasAccessToken == false){
			$this->error = "You can post multi images on managed pages only";
			return false;
		}

		if(!$nodeHasAccessToken){
			if($accessToken == null){
				$this->facebook_api->setAccessToken($this->accessToken);
			}else{
				$this->facebook_api->setAccessToken($accessToken);
			}
		}

		$this->facebook_api->setNode($node);
		$this->facebook_api->setMethod("POST");

		switch ($postType) {
			case 'image':
				$this->facebook_api->setEndPoint('photos');
				break;
			case 'video':
				$this->facebook_api->setEndPoint('videos');
				$this->facebook_api->setHost('https://graph-video.facebook.com/');
				break;
			default:
				$this->facebook_api->setEndPoint('feed');
				break;
		}

		// unset empty post params
		if(isset($params['message']) && trim($params['message']) == ""){
			unset($params['message']);
		}
		if(isset($params['name']) && trim($params['name']) == ""){
			unset($params['name']);
		}
		if(isset($params['picture']) && trim($params['picture']) == ""){
			unset($params['picture']);
		}
		if(isset($params['caption']) && trim($params['caption']) == ""){
			unset($params['caption']);
		}
		if(isset($params['description']) && trim($params['description']) == ""){
			unset($params['description']);
		}

		$res = $this->getFbJsonResponse($this->facebook_api->request($params));

		if(isset($res->error)){
			$this->error = $res->error->message;
			if(isset($res->error->error_user_msg)){
				$this->error .= "\nError Details : " . $res->error->error_user_msg;
			}
			return false; 
		}

		if(!$res){
			$this->error = $this->lang->s($this->error);
			return false; 
		}

		// Get post ID
		if($postType == 'image' || $postType == 'video'){
			return $res->id;
		}else{
			return substr(strrchr($res->id, '_'), 1);
		}
	}
	
	public function comment($node,$params){

		$this->load->model('FbAccount_Model');

		$this->facebook_api->setAccessToken($this->accessToken);
		$this->facebook_api->setNode($node);
		$this->facebook_api->setMethod("POST");
		$this->facebook_api->setEndPoint('comments');

		$res = $this->getFbJsonResponse($this->facebook_api->request($params));

		if(isset($res->error)){
			$this->error = $res->error->message;
			if(isset($res->error->error_user_msg)){
				$this->error .= "\nError Details : " . $res->error->error_user_msg;
			}
			return false; 
		}

		if(!$res){
			$this->error = $this->lang->s($this->error);
			return false; 
		}
		
		return $res->id;
	}


	public function AppDetails(){
		$this->facebook_api->setEndPoint($this->appId);
		$this->facebook_api->setApiVersion("");
		$this->rawResponse = $this->facebook_api->request(NULL,"GET",FALSE);
		$resposne = json_decode($this->rawResponse->getBody());
		return $resposne;
	}
	
	public function AppDetailsFromAt($accessToken){
		
		$this->facebook_api->setEndPoint("app");
		$this->facebook_api->setMethod("get");
		$this->facebook_api->setAccessToken($this->accessToken);
		$this->rawResponse = $this->facebook_api->request();

		if(!$this->rawResponse) return false;

		return json_decode($this->rawResponse->getBody());
	}
	
	public static function App($app_id){
		$this->db->from('fbapps');
		$this->db->where('appid',$app_id);
		return $this->db->get()->row();
	}
	
	private function FbAppAuth($app_id,$app_secret,$redirect){
		$fb = new Facebook\Facebook([
				'app_id' => $app_id,
				'app_secret' => $app_secret,
				'default_graph_version' => 'v2.4',
			]);

		$helper = $fb->getRedirectLoginHelper();
		
		try {
			$accessToken = $helper->getAccessToken();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			// When Graph returns an error
			throw new Exception($e->getMessage());
			return false;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			// When validation fails or other local issues
			throw new Exception($e->getMessage());
			return false;
		}

		if($this->input->get('state',TRUE) && $this->input->get('code',TRUE)){
			return $accessToken;
		}else if($this->input->get('error_message',TRUE)){
			throw new Exception($this->input->get('error_message',TRUE));
		}else{
			
			$perms = array();
			$perms[] = "publish_actions";
			$perms[] = "public_profile";
			Redirect($helper->getLoginUrl($redirect,$perms));
		}
	}
	
	public function FbAuth($app){
		
		$redirect = base_url('Facebook/app_authentication/own_app/?app_id='.$app->row('id'));
		
		// Get admin access token
		$this->db->select('admin_access_token');
		$this->db->from('fbapps');
		$this->db->where('appid',$app->row('appid'));
		$adminAccessToken = $this->db->get()->row('admin_access_token');

		// Get app access token
		$accessToken = $this->FbAppAuth($app->row('appid'),$app->row('app_secret'),$redirect);

		$this->setAccessToken($accessToken);

		$this->load->model("User_Model");
		$this->load->model("FbAccount_Model");
		
		$userID = $this->User_Model->currentUser()['user_id'];
		$fbID = $this->FbAccount_Model->UserDefaultFbAccount();
		$appID = $app->row('id');

		$this->FbApps_Model->setUserId($userID);
		$this->FbApps_Model->setId($appID);
		
		$at = $this->FbApps_Model->getAccessToken($appID,$fbID,$userID);

		// Get token details 
		$token = $this->Facebook_Model->accessTokenDetails();

		$expires_in = isset($token->expires_in) ? $token->expires_in : "never";

		// Check if the access token is valid
		if($adminAccessToken != null && $this->IsATValid($adminAccessToken)){

			// Store user app info
			if($at->row()){
			// Update AT
				$resp = $this->FbApps_Model->updateAccessToken($accessToken,$fbID,$expires_in);
			}else{
				// Save AT
				$resp = $this->FbApps_Model->saveAccessToken($accessToken,$fbID,$expires_in);
			}
			
			// Check if the user is an admin of the facebook app otherwise add him as a tester
			if($this->FbAppUserRole($this->FbUserIdFromAt(),$app->row('appid'),$app->row('app_secret')) != "administrators"){
				if(!$this->Invite($app->row('appid'),$this->FbUserIdFromAt(),$adminAccessToken)){
					throw new Exception($this->lang->s("Unable to add your facebook account as a tester."));
				}else{
					return $this->lang->s("You will recive a tester requests, before you can post you MUST confirm the request. %s ","<a href='https://developers.facebook.com/requests/''>https://developers.facebook.com/requests/</a>");
				}
			}
			
		} else {

			$role = $this->FbAppUserRole($this->FbUserIdFromAt(),$app->row('appid'),$app->row('app_secret'));

			// Check if the user is an admin of the facebook app
			if( $role === "administrators"){

				// Store user app info
				if($at->row()){
				// Update AT
					$resp = $this->FbApps_Model->updateAccessToken($accessToken,$fbID,$expires_in);
				}else{
					// Save AT
					$resp = $this->FbApps_Model->saveAccessToken($accessToken,$fbID,$expires_in);
				}
				
				// Store the app admin access token
				$this->db->set('admin_access_token',$accessToken);
				$this->db->where('appid',$app->row('appid'));
				$this->db->Update("fbapps");

			}else{
				throw new Exception($this->lang->s("The admin must authorized this application first!"));
			}
		}

		return "";
	}
	
	public function Invite($app_id,$fbUserId,$accessToken){

		$this->facebook_api->setEndPoint("roles");
		$this->facebook_api->setNode($app_id);
		$this->facebook_api->setMethod("POST");
		$this->facebook_api->setAccessToken($accessToken);
		$params = array('user'	=> $fbUserId,'role' 	=> 'testers');
		$this->rawResponse = $this->facebook_api->request($params);
		$res = json_decode($this->rawResponse->getBody());

		if(isset($res->error)){
			$this->error = $res->error->message;
			return false;
		}

		if(isset($res->success) && $res->success  == true){
			return true;
		}

		return false;

	}

	public function search($params = array()){
		
		$this->pageAfter = null;
		$this->facebook_api->setApiVersion('v2.3');
		$this->facebook_api->setNode('');
		$this->facebook_api->setEndPoint('search');
		$this->facebook_api->setAccessToken($this->accessToken);
		if(!$res = $this->getFbJsonResponse($this->facebook_api->request($params))){
			return FALSE;
		}

		if(!isset($res->data)){
			$this->error = "No data available";
			return false; 
		}
		if(isset($res->paging->cursors->after)){
			$this->pageAfter = $res->paging->cursors->after;
		}
		return $res;
	}
	
}
?>