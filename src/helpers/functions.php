<?php

function guid(){
	$g = '';
    if (function_exists('com_create_guid')){
        $g = com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
        $g = $uuid;
    }

    $g = str_replace('{','',$g);
    $g = str_replace('}','',$g);
    $g = str_replace('-','',$g);
    return $g;
}

function get_by_key($key, $default) {

    $ret = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    if (is_bool($default) && is_string($ret)) {
        if ($ret === 'true') {
            $ret = true;
        } else {
            $ret = false;
        }
    }
    //echo "<br>$key = "; var_dump($ret);
    return $ret;
}