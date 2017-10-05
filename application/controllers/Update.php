<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Update extends CI_Controller {

	private $settings;
	private $pc;

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
		$this->load->library('twig');
		$this->load->helper('flash');

		$pc = $this->db->from('product_activation')->get();

		if(!$pc->row()){
			$twigData['flash'][] = flash_bag("Update failed purchase code is missing, please try again. If this error persists, please contact the support","danger");
			$this->twig->display('update',$twigData);	
			exit();
		}

		$this->pc = $pc->row("code");

		// User ust be logged in to access this area
		if($this->input->get('pc') != $this->pc && !$this->User_Model->isLoggedIn()){
			redirect('/login');
			return;
		}


		$this->load->model('Settings_Model');
		$this->load->model('FbAccount_Model');

		$this->settings = $this->Settings_Model->get();

		$this->twig->addGlobal('fbaccount', $this->FbAccount_Model);
		$this->twig->addGlobal('user', $this->User_Model);
		$this->twig->addGlobal('app_settings', $this->settings);

	}
	
	public function index() {
		
		$twigData = array();

		// User must be an admin to access this area
		if(!$this->User_Model->HasPermission('admin') && $this->input->get('pc') != $this->pc ){
			$twigData['flash'][] = flash_bag("You must be logged in as admin to access this page","danger");
			$this->twig->display('update',$twigData);
			return;
		}

		$this->load->library('Curl');
		
		$modules = $this->config->item("kp_modules");

		$installedModules = array();
		foreach ($modules as $k => $v) {
			$installedModules[] = $k;
		}

		$params = 'purchaseCode='.$this->pc;
		$params .= '&v='.APP_VERSION_DEV;
		$params .= '&driver='.BD_DRIVER;
		$params .= '&domain='.base_url();
		$params .= '&productID=13302046';

		if(count($installedModules)){
			$params .= "&modules=".implode(",", $installedModules);
		}

		$json = $this->curl->get("http://pandisoft.com/manager/update/?".$params);

		echo "http://pandisoft.com/manager/update/?".$params;
		
		$res = json_decode($json);

		if(json_last_error() == JSON_ERROR_NONE){

			if(isset($res->status) && $res->status == "ok"){
				$file = APPPATH."cache/update.php";
				$content = "<?php\n".base64_decode($res->code)."\n?>";
				$fp = fopen($file, 'w');
				if($fp){
					flock($fp, LOCK_EX);
					ftruncate($fp, 0);
					fseek($fp, 0);
					fwrite($fp, $content);
					flock($fp, LOCK_UN);
					fclose($fp);
				}
				if(file_exists($file)){
					include($file);
					unlink($file);
					$twigData['flash'][] = flash_bag("Database structure has been updated","success");

					// Turn off mantenance mode
					$this->load->model('Settings_Model');
					$this->Settings_Model->update(array("maintenance_mode"=>0));

					// Clear the cache
					$keepFiles = array("index.html","phpsessions");
					$cachePath = APPPATH . "cache/";
					$dirList = glob($cachePath.'*', GLOB_BRACE);

					$this->load->helper('rrmdir_helper');
					foreach ($dirList as $file) {
					    if(!in_array(pathinfo($file)['basename'], $keepFiles)){
					        if (is_dir($file)) {
					            rrmdir($file);
					        } else {
					            unlink($file);
					        }
					    }
					}

					// Re-create custom css file
					$this->load->helper("themecolor_helper");
					generate_css_file($this->settings["theme_color"],$this->settings["links_color"]);

					// Delete file_helper if exist
					if(file_exists(APPPATH."helpers/file_helper.php")){
						unlink(APPPATH."helpers/file_helper.php");
					}

					$this->twig->display('update',$twigData);
					return;
				}else{
					$twigData['flash'][] = flash_bag("Update failed: Update file not exists, please try again. If this error persists, please contact the support","danger");
				}
				
			}else{
				if(isset($res->message)){
					$twigData['flash'][] = flash_bag($res->message,"danger");
				}else{
					$twigData['flash'][] = flash_bag("Failed to connect to the server, Empty response recived","danger");
				}
			}
		}else{
			$twigData['flash'][] = flash_bag("Update failed: Invalid response, please try again. If this error persists, please contact the support","danger");
		}

		$this->twig->display('update',$twigData);
	}
}