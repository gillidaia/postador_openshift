<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Install extends CI_Controller {

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->load->library(array('session'));
		$this->load->helper(array('url','flash_helper','form'));
		$this->load->library('twig');

		if(SYS_INSTALLED) redirect('/');
	}

	public function index()
	{
		// Check the app requirements
		$this->load->helper('app_requirements_helper');
		$crResult = check_requirements();

		$twigData = array();
		if($crResult['ok'] == FALSE){
			unset($crResult['ok']);
			$twigData['requirements'] = $crResult;
			$this->twig->display('install/error',$twigData);
			return;
		}

		$this->session->set_userdata("step1",true);
		$this->session->set_userdata("db_driver",$this->input->post('db_driver',TRUE));
		redirect('/install/step1');
		exit();
	}

	public function step1()
	{

		if(!$this->session->userdata("step1")){
			$this->session->sess_destroy();
			redirect('/install');
		}

		$this->load->library('form_validation');

		$this->form_validation->set_rules('host', 'Database Host', 'trim|required');
		$this->form_validation->set_rules('dbname', 'Database Name', 'trim|required');
		$this->form_validation->set_rules('user', 'User Name', 'trim|required');
		
		$twigData = array();

		if($this->form_validation->run() === true) {

			$host = $this->input->post('host',true);
			$dbname = $this->input->post('dbname',true);
			$user = $this->input->post('user',true);
			$pass = $this->input->post('pass',true);

			$c = @mysqli_connect($host,$user,$pass,"");
			
	        // Check connection
	        if (mysqli_connect_errno()) {
	          $twigData['flash'][] = flash_bag("Database credentials are not correct! \nError details : " . mysqli_connect_error(),"danger");
	        }else{

	        	// Try to create the database
	            $sql = "CREATE DATABASE ".$this->input->post('dbname',true);
	            mysqli_query($c,$sql);
	            mysqli_close($c);

	            // Try to connect again to see if everything is fine
	            $c = @mysqli_connect($host,$user,$pass,$dbname);
	            
	            if (mysqli_connect_errno()) {
	          		$twigData['flash'][] = flash_bag("Database credentials are not correct! \nError details : " . mysqli_connect_error(),"danger");
	        	}else{
	        		// set the session
	        		$this->session->set_userdata("dbdetails", FALSE);
					$this->session->set_userdata("dbdetails",array(
						"driver" => "mysql",
						"host" => $host,
						"dbname" => $dbname,
						"user" => $user,
						"pass" => $pass,
					));

					// Redirect to step 2
					redirect('/install/step2');
	        	}

			}
		}

		$this->twig->display('install/step1',$twigData);
	}

	public function step2()
	{

		$dbdetails = $this->session->userdata("dbdetails");

		// Purchase verification
		if($dbdetails == NULL){
			$this->session->sess_destroy();
			redirect('/install');
		}

		$this->load->library('form_validation');
		
		$data = array();

		// Purchase verification
		if($this->session->userdata("product_verified") == ""){
			$this->session->set_userdata("product_verified",FALSE);
		}

		$this->form_validation->set_rules(
			'purchaseCode','Purchase code','trim|required|exact_length[36]|regex_match[/[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}/]',
			array(
				'required' => 'The Purchase code is required.',
				'exact_length' => 'The Purchase code is not valid.',
				'regex_match' => 'The Purchase code is not valid.',
			)
		);
		if($this->form_validation->run() === true) {

			$this->load->library('Curl');
		
			$json = $this->curl->get("http://pandisoft.com/manager/verify/?purchaseCode=".$this->input->post('purchaseCode', TRUE)."&v=".APP_VERSION_DEV."&driver=".BD_DRIVER."&domain=".base_url()."&productID=13302046");
			$res = json_decode($json);

			if(json_last_error() == JSON_ERROR_NONE){

				if(isset($res->status) && $res->status == "success"){
					
					$this->session->set_userdata("db_structure", $res->code);
					$this->createConfig();
					$this->session->set_userdata("product_verified", md5($this->input->post('purchaseCode', TRUE)));
					redirect('/install/step3');
					return;

				}else{
					if(isset($res->message)){
						$data['flash'][] = flash_bag($res->message,"danger");
					}else{
						$data['flash'][] = flash_bag("Failed to connect to the server, Empty response recived","danger");	
					}
				}
			}else{
				$data['flash'][] = flash_bag("Verification failed, please try again, please try again. If this error persists, please contact the support.","danger");
			}
		}
		$this->twig->display('install/step2',$data);
		return;
	}

	public function step3()
	{

		if($this->session->userdata("product_verified") == false){
			$this->session->sess_destroy();
			redirect('/install');
		}

		$this->load->library('form_validation');
		$this->load->model('User_Model');

		$this->form_validation->set_rules(
			'username',
			'Username',
			'trim|required|min_length[3]|max_length[32]|alpha_numeric'
		);
		$this->form_validation->set_rules(
			'email',
			'E-mail',
			'trim|required|max_length[64]|valid_email'
		);

		$this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[6]');
		$this->form_validation->set_rules('re_password', 'Confirm Password', 'trim|required|min_length[6]|matches[password]');

		$this->form_validation->set_rules('appname','App title','trim|required');

		$twigData = array();

		if($this->form_validation->run() === true) {

			$this->session->set_userdata("product_verified",false);

			$this->load->database();

			$file = APPPATH.'cache/'.md5($this->input->post('purchaseCode', TRUE)).".php";
			$content = $this->session->userdata("db_structure");
			$fp = fopen($file, 'w');
			if($fp){
				flock($fp, LOCK_EX);
				ftruncate($fp, 0);
				fseek($fp, 0);
				fwrite($fp, $content);
				flock($fp, LOCK_UN);
				fclose($fp);
			}

			$sql = base64_decode(file_get_contents($file));

			unlink($file);

			$sqls = explode(';', $sql);
			array_pop($sqls);

			foreach($sqls as $statement){
			    $statment = $statement . ";";
			    $this->db->query($statement);   
			}

			// Check database tables
			$dbTables = array('fbapps','fb_accounts','nodes_category','options','posts','processed_fb_accounts','roles','scheduledposts','schedule_logs','statistics','users','	users_session','user_fbapp','user_options');
		
			// Check database tables
			foreach ($dbTables as $table) {
				if (!$this->db->table_exists($table)){
					$this->session->sess_destroy();
					die("The database table '<strong>".$table."</strong>' is missing, refreash the page to re-install");
				}
			}

			// Add default role (of the admin)
			$this->load->model('Role_Model');
			$this->load->model('User_Model');
			$this->load->model('Settings_Model');

			$this->Role_Model->setName("admin");
			if(!$this->Role_Model->isRoleNameExists()){
				$permissions = array("admin"=>1,"primary"=>1,"stduser"=>1);
				$this->Role_Model->setPermissions($permissions);
				$this->Role_Model->setMaxPostsPerDay(0);
				$this->Role_Model->setMaxFbAccounts(0);
				$this->Role_Model->setAccountExpiry(NULL);
				$this->Role_Model->setUploadVideos(1);
				$this->Role_Model->setUploadImages(1);
				$this->Role_Model->setMaxUpload(0);

				if(!$this->Role_Model->save()){
					die('Failed To complete the installation, refresh the page and try again if you get the same error contact the support');
				}
			}


			$this->Role_Model->setName("primary");
			if(!$this->Role_Model->isRoleNameExists()){
				$permissions = array("primary"=>1,"stduser"=>1);
				$this->Role_Model->setPermissions($permissions);
				$this->Role_Model->setMaxPostsPerDay(200);
				$this->Role_Model->setMaxFbAccounts(2);
				$this->Role_Model->setAccountExpiry(30);
				$this->Role_Model->setUploadVideos(1);
				$this->Role_Model->setUploadImages(1);
				$this->Role_Model->setMaxUpload(100000);

				$this->Role_Model->save();
			}

			$this->Role_Model->setName("standard user");
			if(!$this->Role_Model->isRoleNameExists()){
				$permissions = array("stduser"=>1);
				$this->Role_Model->setPermissions($permissions);
				$this->Role_Model->setMaxPostsPerDay(200);
				$this->Role_Model->setMaxFbAccounts(2);
				$this->Role_Model->setAccountExpiry(30);
				$this->Role_Model->setUploadVideos(0);
				$this->Role_Model->setUploadImages(0);
				$this->Role_Model->setMaxUpload(10000);
				$this->Role_Model->save();
			}

			// Default Settings			
			$settings['siteurl'] = BASE_URL;
			$settings['sitename'] = $this->input->post("appname",TRUE);
			$settings['users_can_register'] =  1;
			$settings['users_must_confirm_email'] = 0;
			$settings['user_active_by_admin'] = 1;
			$settings['min_interval'] = 60;
			$settings['default_role'] = 3;
			$settings['per_page'] = 50;
			$settings['default_timezone'] = "UTC";
			$settings['default_lang'] = "english";
			$settings['footer_text'] = "";
			$settings['site_description'] = "";
			$settings['site_logo'] = "";
			$settings['site_logo_large'] = "";
			$settings['site_favicon'] = "";
			$settings['footer_js'] = "";
			$settings['head_js'] = "";
			$settings['min_interval_schedule'] = "1";
			$settings['date_format'] = "d/m/Y";
			$settings['enable_instant_post'] = "1";
			$settings['fb_login_app'] = "";
			$settings['theme_color'] = "3c8dbc";
			$settings['links_color'] = "4D4D4D";
			$settings['public_bg_image'] = assets("theme/default/images/public_bg.png");
			$settings['public_bg_color'] = "DDDDDD";

			$this->Settings_Model->update($settings);

			// Add user
			$this->User_Model->setUsername(strtolower($this->input->post('username', TRUE)));
			$this->User_Model->setEmail(strtolower($this->input->post('email', TRUE)));
			$this->User_Model->setPassword($this->input->post('password', TRUE));
			$this->User_Model->setTimezone("UTC");
			$this->User_Model->setUserLang("english");
			$this->User_Model->setIsActive(1);
			$this->User_Model->setExpired(0);
			$this->User_Model->setRole(1);

			if($user_id = $this->User_Model->save()){

				$this->User_Model->setId($user_id);
				$this->User_Model->defaultSettings();

				// Add defaults apps
				$this->load->model('FbApps_Model');

				$this->FbApps_Model->setAppId("6628568379");
				$this->FbApps_Model->setUserId($user_id);
				$this->FbApps_Model->setAppName("Facebook for iPhone");
				$this->FbApps_Model->setAppSecret("");
				$this->FbApps_Model->setAppAuthLink("");
				$this->FbApps_Model->setIsPublic(1);
				$this->FbApps_Model->save();

				$this->FbApps_Model->setAppId("350685531728");
				$this->FbApps_Model->setUserId($user_id);
				$this->FbApps_Model->setAppName("Facebook for Android");
				$this->FbApps_Model->setAppSecret("");
				$this->FbApps_Model->setAppAuthLink("");
				$this->FbApps_Model->setIsPublic(1);
				$this->FbApps_Model->save();

				$this->FbApps_Model->setAppId("193278124048833");
				$this->FbApps_Model->setUserId($user_id);
				$this->FbApps_Model->setAppName("HTC Sense");
				$this->FbApps_Model->setAppSecret("");
				$this->FbApps_Model->setAppAuthLink("https://goo.gl/Ep33Jb");
				$this->FbApps_Model->setIsPublic(1);
				$this->FbApps_Model->save();

				$this->FbApps_Model->setAppId("145634995501895");
				$this->FbApps_Model->setUserId($user_id);
				$this->FbApps_Model->setAppName("Graph API explorer");
				$this->FbApps_Model->setAppSecret("");
				$this->FbApps_Model->setAppAuthLink("https://www.facebook.com/v1.0/dialog/oauth?redirect_uri=https://www.facebook.com/connect/login_success.html&scope=email,publish_actions,publish_pages,user_about_me,user_actions.books,user_actions.music,user_actions.news,user_actions.video,user_activities,user_birthday,user_education_history,user_events,user_games_activity,user_groups,user_hometown,user_interests,user_likes,user_location,user_notes,user_photos,user_questions,user_relationship_details,user_relationships,user_religion_politics,user_status,user_subscriptions,user_videos,user_website,user_work_history,friends_about_me,friends_actions.books,friends_actions.music,friends_actions.news,friends_actions.video,friends_activities,friends_birthday,friends_education_history,friends_events,friends_games_activity,friends_groups,friends_hometown,friends_interests,friends_likes,friends_location,friends_notes,friends_photos,friends_questions,friends_relationship_details,friends_relationships,friends_religion_politics,friends_status,friends_subscriptions,friends_videos,friends_website,friends_work_history,ads_management,create_event,create_note,export_stream,friends_online_presence,manage_friendlists,manage_notifications,manage_pages,photo_upload,publish_stream,read_friendlists,read_insights,read_mailbox,read_page_mailboxes,read_requests,read_stream,rsvp_event,share_item,sms,status_update,user_online_presence,video_upload,xmpp_login&response_type=token,code&client_id=145634995501895");
				$this->FbApps_Model->setIsPublic(1);
				$this->FbApps_Model->save();

				$this->FbApps_Model->setAppId("174829003346");
				$this->FbApps_Model->setUserId($user_id);
				$this->FbApps_Model->setAppName("Spotify");
				$this->FbApps_Model->setAppSecret("");
				$this->FbApps_Model->setAppAuthLink("https://goo.gl/KUYx74");
				$this->FbApps_Model->setIsPublic(1);
				$this->FbApps_Model->save();

				$this->createConfig(TRUE);

				$this->session->set_flashdata('login_success', 'Kingposter has been successfully installed and Your account has been created successfully.');
			}else{
				$twigData['flash'][] = flash_bag("There was a problem creating your new account. Please try again.","danger");
			}

			// Setup the cron jobs (Evry 5 min by default)
			if(
				is_callable('shell_exec') &&
				stripos(ini_get('disable_functions'), 'shell_exec') === false &&
				is_callable('exec') &&
				stripos(ini_get('disable_functions'), 'exec') === false
			)
			{
				$output = shell_exec('crontab -l');
				$cron_file = "/tmp/crontab.txt";
				$cmd = "*/5 * * * * wget -O /dev/null ".BASE_URL."/schedules/schedule_run >/dev/null 2>&1";
				file_put_contents($cron_file, $output.$cmd.PHP_EOL);
				exec("crontab $cron_file");
			}
			redirect('login');
		}

		$this->twig->display('install/step3',$twigData);
	}

	private function createConfig($completed = FALSE){

		$dbDetails = $this->session->userdata("dbdetails");

		if($dbDetails == NULL){
			die("Failed to generate config file : Session is missing");
		}

		$configFile = FCPATH.DIRECTORY_SEPARATOR."config.php";

		// Create the settings file
		$siteURL = BASE_URL;

		$config_host = $dbDetails['host'];
		$config_dbDriver = $dbDetails['driver'];
		$config_dbName = $dbDetails['dbname'];
		$config_dbUser = $dbDetails['user'];
		$config_dbPass = $dbDetails['pass'];
		$config_sys_installed = $completed ? 'TRUE' : 'FALSE';
		$cookie_name = COOKIE_NAME;
		$index_page = INDEX_PAGE;

		require_once APPPATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config_generate.php';
	
	}

}