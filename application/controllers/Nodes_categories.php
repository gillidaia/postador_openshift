<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nodes_categories extends CI_Controller {

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

		$this->load->model('Facebook_Model');
		$this->load->model('FbApps_Model');
		$this->load->model('NodesCategory_Model');
		$this->load->model('FbAccount_Model');

		$this->currentUser = $this->User_Model->currentUser();

		// If the user account has expired show expiry page
		if($this->currentUser['expired'] == 1){
			redirect('account_expiry');
			exit();
		}

		$this->settings = $this->Settings_Model->get();
		
		$this->config->set_item('language', $this->currentUser['lang']);
		$this->lang->load(array("general"));
	}

	public function index()
	{	
		$twigData = array();

		$this->load->helper(array('flash_helper'));

		if(isset($_POST['submit'])){
			$accessToken = trim($_POST['accessToken']);
			if(empty($accessToken)){
				$twigData['flash'][]  = flash_bag(lang('ENTER_ACCESS_TOKEN'),'danger');
			}else{

				$fb = new Facebook();
				$fb_account = new FbAccount();
				$app_id = "145634995501895";

				try{

					if($fb_account->UserDefaultFbAccount()){
						if($fb->IsATValid($accessToken)){

							if($fb->GetAccessToken($app_id)){
								$fb->UpdateAccessToken($this->currentUser['id'],$app_id,$fb_account->UserDefaultFbAccount(),$accessToken);
							}else{
								$fb->SaveAccessToken($this->currentUser['id'],$app_id,$fb_account->UserDefaultFbAccount(),$accessToken);
							}

							$twigData['flash'][]  = flash_bag(lang('Access token has been updated succussfully'),'success');

						}else{
							throw new Exception(lang('INVALID_ACCESS_TOKEN'));
						}

					}else{
						throw new Exception(lang('NO_FB_ACCOUNT_SELECTED'));
					}
				}catch(Exception $e){
					$twigData['flash'][]  = flash_bag($e->getMessage(),'danger');
				}
			}
		}
		
		$this->load->model('Facebook_Model');
		$twigData = array();
		$twigData['user'] = $this->User_Model;
		$this->lang->load(array("general"));
		$this->twig->display('reset_access_token',$twigData);
	}

	public function add_category(){

		// Check required fields
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('categoryname', $this->lang->s('Category name'), 'trim|required|min_length[2]|max_length[32]|regex_match[/^[\p{L}\p{M}\p{Nd} ]{2,}$/u]', array(
			'regex_match' => $this->lang->s('Category name must contain alphanumeric characters underscore and space only.'),	
		));

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		// A category must be associated with a facebook account
		if(!$this->FbAccount_Model->UserDefaultFbAccount()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('NO_FB_ACCOUNT_SELECTED')
			));
			return;
		}

		// add the new category
		// Check if a category with the same name is already exists
		if($this->NodesCategory_Model->isCatNameExists($this->input->post('categoryname',TRUE),$this->currentUser['user_id'],$this->FbAccount_Model->UserDefaultFbAccount())){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('A category with the same name is already exists')
			));
			return;
		}

		$this->NodesCategory_Model->setUserId($this->currentUser['user_id']);
		$this->NodesCategory_Model->setFbId($this->FbAccount_Model->UserDefaultFbAccount());
		$this->NodesCategory_Model->setCategoryName($this->input->post('categoryname',TRUE));

		if($this->NodesCategory_Model->save()){
			display_json(array(
				'status' => 'success',
				'message' => $this->lang->s('The new category has been saved succussfully.')
			));
			return;
		}


		display_json(array(
			'status' => 'success',
			'message' => $this->lang->s('Failed to save the category please try again.')
		));
		return;

	}

	public function delete_category(){

		// Check required fields
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('categoryID', $this->lang->s('Category ID'), 'trim|required|integer');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$this->NodesCategory_Model->setId($this->input->post('categoryID',TRUE));
		$this->NodesCategory_Model->setUserId($this->currentUser['user_id']);

		$category = $this->NodesCategory_Model->getbyId();

		if(!$category->row()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('Category not found')
			));
			return;
		}

		// if the category is the facebook account default category remove the default category
		$currentFbAccount = $this->FbAccount_Model->UserDefaultFbAccount();

		if($category->row('fb_id') == $currentFbAccount){
			$this->FbAccount_Model->setUserId($this->currentUser['user_id']);
			$this->FbAccount_Model->setFbId($currentFbAccount);
			$this->FbAccount_Model->setDefaultNodesCategory("-1");
			$this->FbAccount_Model->update();
		}

		if($this->NodesCategory_Model->delete()){
			display_json(array(
				'status' 	=> 'success',
				'message' 	=> $this->lang->s('Category has been deleted succussfully')
			));
			return;
		}

		display_json(array(
			'status' 	=> 'error',
			'message' 	=> $this->lang->s('Failed to delete the requested category, Please try again')
		));
		return;

	}

	public function remove_nodes()
	{
		// Check required fields
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('category_id', $this->lang->s('Category ID'), 'trim|required|integer');
		$this->form_validation->set_rules('nodes', $this->lang->s('nodes'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		$this->NodesCategory_Model->setId($this->input->post('category_id',TRUE));
		$this->NodesCategory_Model->setUserId($this->currentUser['user_id']);
		$this->NodesCategory_Model->setFbId($this->FbAccount_Model->UserDefaultFbAccount());
		
		$res = $this->NodesCategory_Model->removeNodes(json_decode($this->input->post('nodes',TRUE),true));
		
		if($res){
			display_json(array(
				'status' 	=> 'success',
				'message' 	=> $this->lang->s('Select nodes has been removed from the current category')
			));
			return;
		}

		display_json(array(
			'status' 	=> 'error',
			'message' 	=> $this->lang->s('Nothing has been deleted!')
		));
		return;

	}
	
	public function add_nodes($value='')
	{
		// Check required fields
		$this->load->library('form_validation');
		$this->load->helper(array('json_helper'));

		$this->form_validation->set_rules('category_id', $this->lang->s('Category ID'), 'trim|required|integer');
		$this->form_validation->set_rules('nodes', $this->lang->s('nodes'), 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'error',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		if(!$this->FbAccount_Model->UserDefaultFbAccount()){
			display_json(array(
				'status' => 'error',
				'message' => $this->lang->s('NO_FB_ACCOUNT_SELECTED')
			));
			return;
		}

		$this->NodesCategory_Model->setId($this->input->post('category_id',TRUE));
		$this->NodesCategory_Model->setUserId($this->currentUser['user_id']);
		$this->NodesCategory_Model->setFbId($this->FbAccount_Model->UserDefaultFbAccount());
		
		$res = $this->NodesCategory_Model->addNodes(json_decode($this->input->post('nodes',TRUE),true));
		
		if($res){
			display_json(array(
				'status' 	=> 'success',
				'message' 	=> $this->lang->s('Select nodes has been added to the category')
			));
			return;
		}

		display_json(array(
			'status' 	=> 'error',
			'message' 	=> $this->lang->s('Nothing has been added!')
		));
		return;
	}

}
