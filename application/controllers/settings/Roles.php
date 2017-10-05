<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Roles extends CI_Controller {

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

		// User must be an admin to access this area
		if(!$this->User_Model->HasPermission('admin')){
			show_404();
			exit();
		}

		$this->currentUser = $this->User_Model->currentuser();

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
		$this->load->model('Role_Model');
		$twigData['roles'] = $this->Role_Model;
		$this->twig->display('settings/roles',$twigData);
	}

	public function add()
	{
		$this->load->library('form_validation');

		$this->form_validation->set_rules('name', $this->lang->s('Name'), 'trim|required|max_length[16]|max_length[16]|regex_match[/^[\p{L}\p{M}\p{Nd} ]{2,}$/u]', array(	
			'regex_match' => $this->lang->s('Role name must contain lowercase letters and numbers only.')
		));

		$this->form_validation->set_rules('max_upload', $this->lang->s('Max upload'), 'trim|integer|greater_than[-1]');

		$this->form_validation->set_rules('maxPosts', $this->lang->s('Max posts per day'), 'trim|integer');
		$this->form_validation->set_rules('maxFbAccount', $this->lang->s('Max facebook accounts'), 'trim|integer');
		$this->form_validation->set_rules('accountExpiry', $this->lang->s('Account expiry'), 'trim|integer');
		
		// Fields validation
		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		// Role name must be unique
		$this->load->model('Role_Model');

		$this->Role_Model->setName($this->input->post("name",TRUE));
		
		if($this->Role_Model->isRoleNameExists()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('The role name %s is already exists, Please choose a different',$this->input->post("name",TRUE))
			));
			return;
		}

		// Permissions
		$permissions = array();

		$this->Role_Model->setName($this->input->post("name",TRUE));
		$this->Role_Model->setPermissions($permissions);
		$this->Role_Model->setMaxPostsPerDay($this->input->post("maxPosts",TRUE));
		$this->Role_Model->setMaxFbAccounts($this->input->post("maxFbAccount",TRUE));
		$this->Role_Model->setAccountExpiry($this->input->post("accountExpiry",TRUE));
		$this->Role_Model->setUploadVideos((int)$this->input->post("upload_videos",TRUE));
		$this->Role_Model->setUploadImages((int)$this->input->post("upload_images",TRUE));
		$this->Role_Model->setMaxUpload((int)$this->input->post("max_upload",TRUE)*1000);

		$this->Role_Model->setMaxComments((int)$this->input->post("max_comments",TRUE));
		$this->Role_Model->setMaxLikes((int)$this->input->post("max_likes",TRUE));

		if($this->Role_Model->save()){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Role app has been Added successfully')
			));
			return;
		}
		
		display_json(array(
			'status' => 'error',
			'message' => 'Nothing has been saved'
		));
	}

	public function delete()
	{

		$this->load->library('form_validation');
		$this->form_validation->set_rules('id', $this->lang->s('Role id'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$this->load->model('Role_Model');
		$this->Role_Model->setId($this->input->post('id',TRUE));

		if($this->Role_Model->delete()){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Role has been deleted')
			));
			return;
		}

		if($this->Role_Model->getError()){
			display_json(array(
				'status' => 'error',
				'message' => $this->Role_Model->getError()
			));
			return;
		}

		display_json(array(
			'status' => 'error',
			'message' => 'Nothing has been deleted'
		));
		
	}

	public function details(){

		$this->load->library('form_validation');

		$this->form_validation->set_rules('role_id', 'role id', 'trim|required|integer|greater_than[1]');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			exit;
		}

		$this->load->model('Role_Model');
		$this->Role_Model->setId($this->input->post('role_id',true));
		$role = $this->Role_Model->getById();

		if(!$role){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("Role not found!")
			));
			exit;
		}		

		$roleDetials = array();

		$roleDetials['name'] = ucfirst($role->row('name'));
		$roleDetials['max_posts'] = $role->row('max_posts');
		$roleDetials['max_fbaccount'] = $role->row('max_fbaccount');
		$roleDetials['account_expiry'] = $role->row('account_expiry');
		$roleDetials['upload_videos'] = $role->row('upload_videos');
		$roleDetials['upload_images'] = $role->row('upload_images');
		$roleDetials['max_upload'] = $role->row('max_upload')/1000;
		$roleDetials['max_comments'] = $role->row('max_comments');
		$roleDetials['max_likes'] = $role->row('max_likes');

		display_json(array(
			'status' => 'ok',
			'role' => $roleDetials
		));
		exit;
    }

    public function update()
	{
		$this->load->library('form_validation');

		$this->form_validation->set_rules('role_id', $this->lang->s('Role id'), 'trim|required|integer');

		$this->form_validation->set_rules('name', $this->lang->s('Name'), 'trim|required|min_length[2]|max_length[16]|regex_match[/^[\p{L}\p{M}\p{Nd} ]{2,}$/u]', array(	
			'regex_match' => $this->lang->s('Role name must contain lowercase letters and numbers only.')
		));

		$this->form_validation->set_rules('maxPosts', $this->lang->s('Max posts per day'), 'trim|integer');

		$this->form_validation->set_rules('max_upload', $this->lang->s('Max upload'), 'trim|integer|greater_than[-1]');

		$this->form_validation->set_rules('maxFbAccount', $this->lang->s('Max facebook accounts'), 'trim|integer');
		$this->form_validation->set_rules('accountExpiry', $this->lang->s('Account expiry'), 'trim|integer');

		// Fields validation
		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		// Role name must be unique
		$this->load->model('Role_Model');

		$this->Role_Model->setId($this->input->post('role_id',true));
		$role = $this->Role_Model->getById();

		if(!$role){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s("Role not found!")
			));
			exit;
		}

		$newRoleName = strtolower($this->input->post("name",TRUE));

		if($newRoleName !=  strtolower($role->row('name'))){
			$this->Role_Model->setName($newRoleName);
			if($this->Role_Model->isRoleNameExists()){
				display_json(array(
					'status' => 'error',
					'message' => $this->lang->s('The role name %s is already exists, Please choose a different',$newRoleName)
				));
				return;
			}
		}

		$newData = array();
		$newData['name'] = $newRoleName;
		$newData['max_posts'] = $this->input->post("maxPosts",TRUE);
		$newData['max_fbaccount'] = $this->input->post("maxFbAccount",TRUE);
		$newData['account_expiry'] = $this->input->post("accountExpiry",TRUE);
		$newData['upload_videos'] = (int)$this->input->post("upload_videos",TRUE);
		$newData['upload_images'] = (int)$this->input->post("upload_images",TRUE);
		$newData['max_upload'] = (int)$this->input->post("max_upload",TRUE)*1000;

		$newData['max_comments'] = (int)$this->input->post("max_comments",TRUE);
		$newData['max_likes'] = (int)$this->input->post("max_likes",TRUE);

		if($this->Role_Model->update($newData)){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('Role app has been updated successfully')
			));
			return;
		}
		
		display_json(array(
			'status' => 'error',
			'message' => 'Nothing has been saved'
		));
	}
}
?>