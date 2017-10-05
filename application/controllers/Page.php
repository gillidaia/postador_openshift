<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Page extends CI_Controller {

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
	}
	
	public function index($uid = false,$postid = false) {
		$twigData = array();

		if(!$uid || !$postid){
			show_404();
			return;
		}

		// Get the post or show 404
		$this->load->model("Post_Model");

		$this->Post_Model->setId((int)$postid);
		$this->Post_Model->setUserId((int)$uid);

		$post = $this->Post_Model->getById();

		if(!$post->row()){
			show_404();
			return;
		}

		if($post->row('type') != "link"){
			show_404();
			return;
		}

		$this->load->library('twig');
		$this->load->library('spintax');
		$this->load->helper('general_helper');

		$pc = (array)json_decode($post->row('content'),TRUE);

		$link = $this->spintax->get($pc['link']);

		$video = getVideoID($link);
		$twigData['is_video'] = false;
		$twigData['type'] = "website";

		if($video != FALSE){
			$twigData['is_video'] = TRUE;
			$twigData['type'] = "video";
			$twigData['video_url'] = "https://www.youtube.com/embed/".$video[1]."?autoplay=1";
		}

		$twigData['app_id'] = '';

		$twigData['url'] = $link;
		$twigData['title'] = $this->spintax->get($pc['name']);
		$twigData['author'] = $this->spintax->get($pc['caption']);
		$twigData['image'] = $this->spintax->get($pc['picture']);
		$twigData['description'] = $this->spintax->get($pc['description']);

		$twigData['domain'] = getDomainFromURL($link);

		// If not facebook crawler redirect to the page
		if (!preg_match('/facebookexternalhit|Facebot/i', $_SERVER['HTTP_USER_AGENT'])) {
    		header("location: ".$link);
		}

		$twigData['redirect'] = true;

		$this->twig->display('custom_page',$twigData);
	}

	public function generate(){
		$this->load->library('twig');
		$this->load->library('spintax');
		$this->load->helper('general_helper');
		$twigData = array();

		$link = $this->input->get("link",TRUE);
		$picture = $this->input->get("picture",TRUE);
		$name = $this->input->get("name",TRUE);
		$caption = $this->input->get("caption",TRUE);
		$description = $this->input->get("description",TRUE);

		$video = getVideoID($link);
		$twigData['is_video'] = false;
		$twigData['type'] = "article";

		if($video != FALSE){
			$twigData['is_video'] = TRUE;
			$twigData['type'] = "video";
			$twigData['video_url'] = "http://www.youtube.com/v/".$video[1]."?version=3";
		}

		$twigData['app_id'] = '';

		$twigData['url'] = $link;
		$twigData['title'] = $name;
		$twigData['author'] = $caption;
		$twigData['image'] = $picture;
		$twigData['description'] = $description;

		$twigData['domain'] = getDomainFromURL($link);

		// If not facebook crawler redirect to the page
		if (!preg_match('/facebookexternalhit|Facebot/i', $_SERVER['HTTP_USER_AGENT'])) {
    		header("location: ".$link);
		}

		$twigData['redirect'] = true;

		$this->twig->display('custom_page',$twigData);
	}
}