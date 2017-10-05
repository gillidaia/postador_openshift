<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('pagination_config') )
{
  function pagination_config()
  {	
  	$config['page_query_string'] = true;
    $config['full_tag_open'] = "<nav aria-label='...'><ul class='pagination'>";
	$config['full_tag_close'] ="</ul></nav>";
	$config['attributes'] = array('class' => 'page-link');
	$config['num_tag_open'] = "<li class='page-item'>";
	$config['num_tag_close'] = '</li>';
	$config['cur_tag_open'] = "<li class='page-item active'><a href='#' class='page-link'>";
	$config['cur_tag_close'] = "<span class='sr-only'>(current)</span></a></li>";
	$config['next_tag_open'] = "<li class='page-item'>";
	$config['next_tag_close'] = "</li>";
	$config['prev_tag_open'] = "<li class='page-item'>";
	$config['prev_tag_close'] = "</li>";
	$config['first_tag_open'] = "<li class='page-item'>";
	$config['first_tag_close'] = "</li>";
	$config['last_tag_open'] = "<li class='page-item'>";
	$config['last_tag_close'] = "</li>";
	$config['first_link'] = "<span aria-hidden='true'>&laquo;</span>";
	$config['last_link'] = "<span aria-hidden='true'>&raquo;</span>";

	return $config;
  }
}

