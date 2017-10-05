<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if ( ! function_exists('directory_size_helper'))
{
    function directory_size_helper($path) {
        $bytestotal = 0;
        $path = realpath($path);
        if($path!==false && $path!='' && file_exists($path)){
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                $bytestotal += $object->getSize();
            }
        }
        return $bytestotal;
    }
}

