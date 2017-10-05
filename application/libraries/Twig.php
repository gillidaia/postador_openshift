<?php
/**
 * Part of CodeIgniter Simple and Secure Twig
 *
 * @author     Kenji Suzuki <https://github.com/kenjis>
 * @license    MIT License
 * @copyright  2015 Kenji Suzuki
 * @link       https://github.com/kenjis/codeigniter-ss-twig
 */

// If you don't use Composer, uncomment below
require_once APPPATH . '../vendor/twig/lib/Twig/Autoloader.php';
Twig_Autoloader::register();

class Twig
{
	public $config = [];
	private $ci;

	private $functions_asis = [
		'base_url', 'site_url', 'assets'
	];
	private $functions_safe = [
		'form_open', 'form_open_multipart','form_close', 'form_error', 'set_value', 'form_input', 'form_hidden','validation_errors','csrf_input'
	];

	/**
	 * @var bool Whether functions are added or not
	 */
	private $functions_added = FALSE;

	/**
	 * @var Twig_Environment
	 */
	private $twig;

	/**
	 * @var Twig_Loader_Filesystem
	 */
	private $loader;

	public function __construct($params = [])
	{
		// default config
		$this->config = [
			'paths' => [VIEWPATH],
			'cache' => APPPATH . 'cache/twig',
		];

		$this->ci =& get_instance();
		
		$this->config = array_merge($this->config, $params);

		if (isset($params['functions']))
		{
			$this->functions_asis = 
				array_unique(
					array_merge($this->functions_asis, $params['functions'])
				);
		}
		if (isset($params['functions_safe']))
		{
			$this->functions_safe = 
				array_unique(
					array_merge($this->functions_safe, $params['functions_safe'])
				);
		}

	}

	protected function resetTwig()
	{
		$this->twig = null;
		$this->createTwig();
	}

	protected function createTwig()
	{
		// $this->twig is singleton
		if ($this->twig !== null)
		{
			return;
		}

		if (ENVIRONMENT === 'production')
		{
			$debug = FALSE;
		}
		else
		{
			$debug = TRUE;
		}

		if ($this->loader === null)
		{
			$this->loader = new \Twig_Loader_Filesystem($this->config['paths']);
		}

		$twig = new \Twig_Environment($this->loader, [
			'cache'      => $this->config['cache'],
			'debug'      => $debug,
			'autoescape' => TRUE,
		]);

		if ($debug)
		{
			$twig->addExtension(new \Twig_Extension_Debug());
		}

		$this->twig = $twig;
		
		// Add Modules namespace
		$kp_modules = (array)$this->ci->config->item('kp_modules');
		foreach ($kp_modules as $module) {
			$folderName = $module['folder_name'];
			$this->loader->addPath(APPPATH.MODULES_LOCATION."/".$folderName."/views",$folderName);
		}

		$this->twig->addGlobal("sidebar_status", $this->ci->input->cookie("sidebar_status",TRUE));

		$this->twig->addGlobal("app_version", APP_VERSION);
		$this->twig->addGlobal("fb_api_version", FB_API_VERSION);
		$this->twig->addGlobal("extended_version", EXTENDED_VERSION);
		$this->twig->addGlobal("enable_sale_post_type", ENABLE_SALE_POST_TYPE);
		$this->twig->addGlobal("enable_link_customize", ENABLE_LINK_CUSTOMIZE);
		$this->twig->addGlobal("enable_ads", ENABLE_ADS);
		$this->twig->addGlobal("assets_version", ASSETS_VERSION);
		$this->twig->addGlobal("kpModules", (array)$this->ci->config->item('kp_modules'));
	}

	protected function setLoader($loader)
	{
		$this->loader = $loader;
	}

	/**
	 * Registers a Global
	 * 
	 * @param string $name  The global name
	 * @param mixed  $value The global value
	 */
	public function addGlobal($name, $value)
	{
		$this->createTwig();
		$this->twig->addGlobal($name, $value);
	}

	/**
	 * Renders Twig Template and Set Output
	 * 
	 * @param string $view   Template filename without `.twig`
	 * @param array  $params Array of parameters to pass to the template
	 */
	public function display($view, $params = [])
	{
		$CI =& get_instance();
		$CI->output->set_output($this->render($view, $params));
	}

	/**
	 * Renders Twig Template and Returns as String
	 * 
	 * @param string $view   Template filename without `.twig`
	 * @param array  $params Array of parameters to pass to the template
	 * @return string
	 */
	public function render($view, $params = [])
	{
		$this->createTwig();
		// We call addFunctions() here, because we must call addFunctions()
		// after loading CodeIgniter functions in a controller.
		$this->addFunctions();

		$view = $view . '.twig';
		return $this->twig->render($view, $params);
	}

	protected function addFunctions()
	{
		// Runs only once
		if ($this->functions_added)
		{
			return;
		}

		// as is functions
		foreach ($this->functions_asis as $function)
		{
			if (function_exists($function))
			{
				$this->twig->addFunction(
					new \Twig_SimpleFunction(
						(string)$function,
						$function
					)
				);
			}
		}

		// safe functions
		foreach ($this->functions_safe as $function)
		{
			if (function_exists($function))
			{
				$this->twig->addFunction(
					new \Twig_SimpleFunction(
						(string)$function,
						$function,
						['is_safe' => ['html']]
					)
				);
			}
		}

		// customized functions
		if (function_exists('anchor'))
		{
			$this->twig->addFunction(
				new \Twig_SimpleFunction(
					'anchor',
					[$this, 'safe_anchor'],
					['is_safe' => ['html']]
				)
			);
		}

		if (function_exists('asset'))
		{
			$this->_twig_env->addFunction(
		    new Twig_SimpleFunction('asset', 'asset', 
		        array('is_safe' => array('html')))
			);
		}

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"json_decode",
				function($string,$array = false){
					return json_decode($string,$array);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"l",
				function($string,$p1 = null,$p2 = null,$p3 = null,$p4 = null,$p5 = null){
					return $this->ci->lang->s($string,$p1,$p2,$p3,$p4,$p5);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"lang",
				function($string,$p1 = null,$p2 = null,$p3 = null,$p4 = null,$p5 = null){
					return $this->ci->lang->s($string,$p1,$p2,$p3,$p4,$p5);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"input_get",
				function($string){
					return $this->ci->input->get($string,TRUE);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"input_post",
				function($string,$filter = TRUE){
					if($filter == FALSE) return $this->ci->input->post($string,$filter);
					return $this->ci->input->post($string,$filter);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"set_input_post",
				function($key,$value){
					$_POST[$key] = $value;
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"config_item",
				function($item){
					return config_item($item);
				}
			)
		);
		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"dump",
				function($object){
					return var_dump($object);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"unescape",
				function($string){
					return html_entity_decode($string);
				}
			)
		);

		$this->twig->addFunction(
			new \Twig_SimpleFunction(
				"strip_tags",
				function($string){
					return strip_tags($string);
				}
			)
		);
		
		$this->functions_added = TRUE;
	}

	/**
	 * @param string $uri
	 * @param string $title
	 * @param array  $attributes [changed] only array is acceptable
	 * @return string
	 */
	public function safe_anchor($uri = '', $title = '', $attributes = [])
	{
		$uri = html_escape($uri);
		$title = html_escape($title);
		
		$new_attr = [];
		foreach ($attributes as $key => $val)
		{
			$new_attr[html_escape($key)] = html_escape($val);
		}

		return anchor($uri, $title, $new_attr);
	}

	/**
	 * @return \Twig_Environment
	 */
	public function getTwig()
	{
		$this->createTwig();
		return $this->twig;
	}
}
