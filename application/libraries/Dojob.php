<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Dojob
{

	private $service;  
    private $params = array();
	private $ci;

    // Setters and getters Auto Generate  
    public function __call($function, $args)
    {
        $functionType = strtolower(substr($function, 0, 3));
        $propName = lcfirst(substr($function, 3));
        switch ($functionType) {
            case 'get':
                if (property_exists($this, $propName)) {
                    return $this->$propName;
                }
                break;
            case 'set':
                if (property_exists($this, $propName)) {
                    $this->$propName = $args[0];
                }
                break;
        }
    }

    public function __construct(){
        $this->ci = & get_instance();
    }

	public function run(){
        
        $params = ""; 
        foreach ($this->params as $param) {
           $params .= $param."/";
        }

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, base_url($this->service."/".$params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        echo curl_exec($ch);
        curl_close($ch);
    }

}