<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Modules_Manager extends CI_Controller {

	private $settings;
	private $currentUser;

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

		// User must be an admin to access this area
		if(!$this->User_Model->HasPermission('admin')){
			redirect("errors/404");
			exit();
		}

		$this->load->model('Settings_Model');
		$this->load->model('FbAccount_Model');

		$this->settings = $this->Settings_Model->get();
		$this->twig->addGlobal('app_settings', $this->settings);
		
		$this->config->set_item('language', $this->currentUser['lang']);

		$this->lang->load(array("general"));

		$this->twig->addGlobal('fbaccountDetails', $this->FbAccount_Model->getFbAccountById($this->FbAccount_Model->UserDefaultFbAccount()));
		$this->twig->addGlobal('fbaccount', $this->FbAccount_Model);
		$this->twig->addGlobal('user', $this->User_Model);
		$this->twig->addGlobal('userData', $this->currentUser);

		// Set User Timezone
		date_default_timezone_set($this->currentUser['timezone']);

		// Set Date format
		$this->twig->addGlobal('date_format', $this->settings['date_format']);
		$this->load->helper('general_helper');
		$this->twig->addGlobal('date_format_js', php_date_to_js($this->settings['date_format']));
	}

	public function index() {
		$twigData = array();
		$this->twig->display('settings/modules_manager',$twigData);
	}

	public function available_modules(){

		$this->load->helper('file');
		$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file','key_prefix' => 'kp_'));

		if (!$am=$this->cache->get('available_modules')){

			$this->load->library('Curl');
			$json = $this->curl->get("http://pandisoft.com/manager/modules/kingposter");
			$res = json_decode($json,TRUE);

			if(json_last_error() != JSON_ERROR_NONE){
				display_json(array("status"=>"fail","message"=>"Failed connect to the server, please try again"));
				return;
			}	

			if(!isset($res["status"]) || $res["status"] != "ok"){
				display_json(array("status"=>"fail","message"=>"Failed connect to the server, please try again"));
				return;
			}

			$modulesList = (array)$res['modules'];

			$kpModules = array();
			$modules = APPPATH.DIRECTORY_SEPARATOR.MODULES_LOCATION;
			foreach (new DirectoryIterator($modules) as $fileInfo) {
				if($fileInfo->isDot() || !$fileInfo->isDir()) continue;
				$module = $modules.DIRECTORY_SEPARATOR.$fileInfo->getFilename().DIRECTORY_SEPARATOR."index.php";
				if(file_exists($module)){
					require_once $module;
				}
			}

			// Add Other modules
			foreach ($kpModules as $k => $v) {
				if(!isset($modulesList[$k])){
					$nModule = array();
					$nModule["name"] = @$kpModules[$k]["name"];
		            $nModule["title"] = @$kpModules[$k]["title"];
		            $nModule["description"] = @$kpModules[$k]["description"];
		            $nModule["version"] = @$kpModules[$k]["version"];
		            $nModule["dev_version"] = @$kpModules[$k]["dev_version"];
		            $nModule["folder_name"] = @$kpModules[$k]["folder_name"];
		            $nModule["kp_min_version"] = @$kpModules[$k]["kp_min_version"];
		            $nModule["author"] = @$kpModules[$k]["author"];
		            $nModule["price"] = @$kpModules[$k]["price"];
		            $nModule["link"] = @$kpModules[$k]["link"];
					$modulesList[$k] = $nModule;
				}
			}
			
			$installedModules = (array)$this->config->item('kp_modules');

			foreach ($kpModules as $k => $v) {
				if(isset($modulesList[$k])){
					if(isset($installedModules[$k])){
						$modulesList[$k]['status'] = "active";
					}else{
						$modulesList[$k]['status'] = "inactive";
					}
					if($modulesList[$k]['dev_version'] > @$kpModules[$k]['dev_version']){
						$modulesList[$k]['update'] = 1;
					}else{
						$modulesList[$k]['update'] = 0;
					}
				}else{
					if(isset($modulesList[$k])){
						$modulesList[$k]['status'] = "Not installed";
					}
				}

			}

			// Get available modules
			$am = json_encode(array("status"=>"ok","modules" =>$modulesList),JSON_PRETTY_PRINT);
	        $this->cache->save('available_modules', $am, 604800);
		}

		header('Cache-Control: no-cache, must-revalidate');
    	header('Content-type: application/json');
		echo $am;
	}

	public function active()
	{

		$this->load->library('form_validation');

		$this->form_validation->set_rules('module', $this->lang->s('Module'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'fail',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$moduleTOActive = $this->input->post("module",TRUE);

		// Search for the module
		$kpModules = array();
		$modules = APPPATH.DIRECTORY_SEPARATOR.MODULES_LOCATION;
		foreach (new DirectoryIterator($modules) as $fileInfo) {
			if($fileInfo->isDot() || !$fileInfo->isDir()) continue;
			$module = $modules.DIRECTORY_SEPARATOR.$fileInfo->getFilename().DIRECTORY_SEPARATOR."index.php";
			if(file_exists($module)){
				require_once $module;
			}
		}
		
		if(!isset($kpModules[$moduleTOActive])){
			display_json(array("status"=>"fail","message"=> $moduleTOActive." module Not found"));
			return;
		}

		if(isset($kpModules[$moduleTOActive]['kp_min_version']) && $kpModules[$moduleTOActive]['kp_min_version'] > APP_VERSION_DEV){
			display_json(array("status"=>"fail","message"=> $kpModules[$moduleTOActive]['title']." module require Kingposter version >= ".$kpModules[$moduleTOActive]['kp_min_version']));
			return;
		}

		$moduleFile = APPPATH.'config'.DIRECTORY_SEPARATOR.'kp_modules.php';
		$installedModules = (array)$this->config->item('kp_modules');

		// Add module is not exists
		if(isset($installedModules[$moduleTOActive])){
			display_json(array(
				"status"=>"ok",
				"message"=> $kpModules[$moduleTOActive]['title']." module already activated",
				"m_status"=> "active"
			));
			return;
		}

		if(!isset($kpModules[$moduleTOActive]['folder_name'])){
			display_json(array(
				"status"=>"fail",
				"message"=> "Module folder name is missing",
				"m_status"=> "active"
			));
			return;
		}

		$installedModules[$moduleTOActive] = array("folder_name"=>$kpModules[$moduleTOActive]['folder_name']);

		$fp = fopen($moduleFile, 'a+');
		flock($fp, LOCK_EX);
		ftruncate($fp, 0);
		fseek($fp, 0);
		$kpmfc = "<?php \n";

		foreach ($installedModules as $k => $v) {
			$kpmfc .= "\$config['kp_modules']['".$k."'] = array(\n";
			$kpmfc .= "'folder_name'=>'".@$installedModules[$k]['folder_name']."',\n";
			$kpmfc .= ");\n";
		}

		$kpmfc .= "\r\n?>".PHP_EOL;

		fwrite($fp, $kpmfc);
		flock($fp, LOCK_UN);
		fclose($fp);

		$this->load->helper('file');
		$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file','key_prefix' => 'kp_'));
		$this->cache->delete('available_modules');

		display_json(array(
			"status"=>"ok",
			"message"=> $kpModules[$moduleTOActive]['title']." module has been activated",
			"m_status"=> "active"
		));

	}

	public function disable()
	{
		$this->load->library('form_validation');

		$this->form_validation->set_rules('module', $this->lang->s('Module'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'fail',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$moduleToDisable = $this->input->post("module",TRUE);

		$moduleFile = APPPATH.'config'.DIRECTORY_SEPARATOR.'kp_modules.php';
		$installedModules = (array)$this->config->item('kp_modules');

		// Remove module is not exists
		if(!isset($installedModules[$moduleToDisable])){
			display_json(array(
				"status"=>"ok",
				"message"=> str_replace("_", " ", $moduleToDisable)." module not activated",
				"m_status"=> "active"
			));
			return;
		}

		$moduleTitle = $installedModules[$moduleToDisable];
		unset($installedModules[$moduleToDisable]);

		$fp = fopen($moduleFile, 'a+');
		flock($fp, LOCK_EX);
		ftruncate($fp, 0);
		fseek($fp, 0);
		$kpmfc = "<?php \n";

		if(count($installedModules) == 0){
			$kpmfc .= "\$config['kp_modules'] = array();\n";
		}else{
			foreach ($installedModules as $k => $v) {
				$kpmfc .= "\$config['kp_modules']['".$k."'] = array(\n";
				$kpmfc .= "'folder_name'=>'".@$installedModules[$k]['folder_name']."',\n";
				$kpmfc .= ");\n";	
			}
		}

		$kpmfc .= "\r\n?>".PHP_EOL;
		fwrite($fp, $kpmfc);
		flock($fp, LOCK_UN);
		fclose($fp);

		$this->load->helper('file');
		$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file','key_prefix' => 'kp_'));
		$this->cache->delete('available_modules');

		display_json(array(
			"status"=>"ok",
			"message"=> str_replace("_", " ", $moduleToDisable)." module has been disabled",
			"m_status"=> "disabled",
		));
	}
}
?>