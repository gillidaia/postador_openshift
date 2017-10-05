<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2016, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2016, British Columbia Institute of Technology (http://bcit.ca/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	http://codeigniter.com
 * @since	Version 1.0.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Language Class extension.
 * 
 * Adds language fallback handling.
 * 
 * When loading a language file, CodeIgniter will load first the english version, 
 * if appropriate, and then the one appropriate to the language you specify. 
 * This lets you define only the language settings that you wish to over-ride 
 * in your idiom-specific files.
 * 
 * This has the added benefit of the language facility not breaking if a new 
 * language setting is added to the built-in ones (english), but not yet 
 * provided for in one of the translations.
 * 
 * To use this capability, transparently, copy this file (MY_Lang.php)
 * into your application/core folder.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Language
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/language.html
 */
class MY_Lang extends CI_Lang {

	/**
	 * Refactor: base language provided inside system/language
	 * 
	 * @var string
	 */
	public $base_language = 'english';

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Load a language file, with fallback to english.
	 *
	 * @param	mixed	$langfile	Language file name
	 * @param	string	$idiom		Language name (english, etc.)
	 * @param	bool	$return		Whether to return the loaded array of translations
	 * @param 	bool	$add_suffix	Whether to add suffix to $langfile
	 * @param 	string	$alt_path	Alternative path to look for the language file
	 *
	 * @return	void|string[]	Array containing translations, if $return is set to TRUE
	 */

	public function s($string,$p1 = null,$p2 = null,$p3 = null,$p4 = null,$p5 = null){
		$line = strtolower($this->clean($string));

		$trans = isset($this->language[$line]) && trim($this->language[$line]) != "" ? $this->language[$line] : FALSE;

		if($trans){
			return html_entity_decode(sprintf($trans,$p1,$p2,$p3,$p4,$p5),ENT_COMPAT, 'UTF-8');
		} else {
			// Uncomment those lines to generate the missing lang strings
			/*$CI =& get_instance();
			$CI->lang->load(array("general"));
			if($CI->config->item('language') == 'english' && ENVIRONMENT == "development"){
				$fp = fopen(APPPATH . 'language/'.$CI->config->item('language').'/general_lang.php', 'a+');
				flock($fp, LOCK_EX);
				ftruncate($fp, 0);
				fseek($fp, 0);
				$langContent = "<?php \n";
				foreach($this->language as $key => $value){
					$langContent .= "\$lang['".trim(preg_replace('/\s+/', ' ', strtolower($key)))."'] = \"".str_replace('"', ' ', trim(preg_replace('/\s+/', ' ', $value)))."\";\n";
				}
				$langContent .= "\$lang['".$line."'] = \"".str_replace('"', ' ', $string)."\";\n ?>".PHP_EOL;
				fwrite($fp, $langContent);
				flock($fp, LOCK_UN);
				fclose($fp);
			}*/

			//$this->language[$line] = "";

			return html_entity_decode(sprintf(str_replace('_',' ',$string),$p1,$p2,$p3,$p4,$p5));
		}
	}
	
	private function clean($string) {
	   $string = str_replace(' ', '_', $string); // Replaces all spaces with hyphens.
	   $string = preg_replace('/[^A-Za-z0-9\_"]/', '', $string); // Removes special chars.
	   return preg_replace('/_+/', '_', $string); // Replaces multiple hyphens with single one.
	}

	public function availableLanguages(){
		$folder = APPPATH."/language";
		$folders = array();
		foreach (new DirectoryIterator($folder) as $fileInfo) {
			if($fileInfo->isDot() || !$fileInfo->isDir()) continue;
			$folders[] = $fileInfo->getFilename();
		}
		return $folders;
	}
}