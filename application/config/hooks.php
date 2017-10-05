<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	https://codeigniter.com/user_guide/general/hooks.html
|
*/

$hook['pre_system'] = function()
{
	if(!file_exists(APPPATH . "config/kp_modules.php")){
		$fp = fopen(APPPATH . "config/kp_modules.php", 'a+');
		flock($fp, LOCK_EX);
		ftruncate($fp, 0);
		fseek($fp, 0);
		fwrite($fp, "<?php \n\$config['kp_modules'] = array();\r\n?>".PHP_EOL);
		flock($fp, LOCK_UN);
		fclose($fp);
	}

	$url = INDEX_PAGE ==  "" ? BASE_URL : BASE_URL.INDEX_PAGE."/";

	$install_url = $url.'install';
	$install_url1 = $url.'install/step1';
	$install_url2 = $url.'install/step2';
	$install_url3 = $url.'install/step3';

	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || @$_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	$actual_url = $protocol.@$_SERVER['HTTP_HOST'].@$_SERVER['REQUEST_URI']; 
	
	// Check if the app is installed
	if(!SYS_INSTALLED){
		if($actual_url != $install_url){
			if($actual_url != $install_url1){
				if($actual_url != $install_url2){
					if($actual_url != $install_url3){

						// Delete update file is exists 
						if(file_exists(APPPATH . "cache/update")){
							unlink(APPPATH . "cache/update");
						}

						header('location: '.$install_url);
						exit();	
					}
				}
			}
		}
	}else{

		if($actual_url == $install_url){
			header('location: '.$url);
			exit();
		}
	}
};

$hook['post_controller'] = function()
{
    if(SYS_INSTALLED){
    	$CI =& get_instance();
	    $CI->load->model('Settings_Model');
	    $CI->load->model('User_Model');
	    $settings = $CI->Settings_Model->get();
	    if(isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == 1){
	    	if(!$CI->User_Model->isLoggedIn() || $CI->User_Model->hasPermission('admin') == false){
				$cs = $CI->uri->segment(1);
				if($cs != "login" && $cs != "update"){
					$CI->load->library('twig');
					$CI->twig->display('errors/maintenance_mode',array('user' => $CI->User_Model));	
				}
			}
	    }

	    $updateFile = APPPATH . "cache/update";
	    if(file_exists($updateFile)){

	    	// Turn on mantenance mode
	    	if($settings['maintenance_mode'] == 0){
	    		$CI->Settings_Model->update(array("maintenance_mode"=>1));	
	    	}

	    	if($CI->User_Model->isLoggedIn() && $CI->User_Model->hasPermission('admin') == TRUE ){
				$cs = $CI->uri->segment(1);
				if($cs != "update"){
					$updatePage = BASE_URL . "update";
					unlink($updateFile);
					header('location: '.$updatePage);
					exit();
				
				}
			}
	    }

	    /*if($CI->User_Model->isLoggedIn() && $CI->User_Model->hasPermission('admin') == TRUE ){
	    	$kp_modules = (array)$CI->config->item("kp_modules");
			foreach ($kp_modules as $key => $value) {
				if($value['kp_min_version'] > APP_VERSION_DEV){
					die("<p style='padding:10px;background: #f52;color:white'>Incompatible Module detected: <strong>(".$value['title'].")</strong> Module require Kingposter version >= ".$value['kp_min_version']."</p>");
				}
			}
		}*/
	}
};