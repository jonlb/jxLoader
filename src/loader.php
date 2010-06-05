<?php

//start session so that we can save what files have been sent to
//the client already this session.
session_start();

//get config information
require_once 'config.php';

$cclass = $cconfig['cacheClass'];
require_once 'helpers/'.$cclass.'.php';
$cache = new $cclass($cconfig);

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

//get variables
$mode = strtoupper(get_by_key('mode','PROD'));
$files = get_by_key('file',array());
$repos = get_by_key('repo', array());
$type = strtolower(get_by_key('type','js'));
$compress = (bool)get_by_key('compress',true);
$algorithm = get_by_key('alg','jsmin');
$depsOnly = (bool)get_by_key('depsOnly',false);
$rebuild = (bool)get_by_key('rebuild',false);
$opts = (bool)get_by_key('opts',false);
//$clearCache = (bool)get_by_key('clearCache',false);
$theme = get_by_key('theme','');
$allDeps = (bool)get_by_key('allDeps', false);
$clearSession = (bool)get_by_key('clearSession', false);

if (count($files) == 1 && strtolower($files[0]) == 'loader') {
    $mode = 'PROD';
}

if ($mode == 'DEV') {
    //load the main loader class
    require_once 'loader.class.php';

    $loader = new Loader($lconfig);

    if ($rebuild) {
        $loader->rebuild();
    }

    //unset session
    if ($clearSession && isset($_SESSION['included'])) {
        unset($_SESSION['included']);
    }
    //get exclude list...
    $exclude = isset($_SESSION['included']) ? $_SESSION['included'] : array();


    //in development mode
    if ($depsOnly) {
        $deps = $loader->compile_deps($files, $repos, 'jsdeps', $opts, $exclude);
        //send back as json... this would have been called to get deps by loader.js
        header('Content-type:application/json');
        echo json_encode($deps);
    } else {
        //var_dump($exclude);
        $ret = $loader->compile($files, $repos, $type, false, $theme, $exclude, $opts);
        //var_dump($ret);
        if ($ret) {
            $source = $ret['source'];
            $included = array_merge($exclude,$ret['included']);
            $_SESSION['included'] = $included;
            //send back with no compression...
            if ($type == 'js') { $type = 'javascript';}
            header('Content-type:text/'.$type);
            echo $source;
        }
    }
} else {
    //in production mode
    //echo "<br>In production code...";

    //load the main loader class
    require_once 'loader.class.php';

    $loader = new Loader($lconfig);
    //echo "<br>load class...";
    if ($rebuild) {
        $loader->rebuild();
        //echo "<br>rebuild class...";
    }


    //unset session
    if ($clearSession && isset($_SESSION['included'])) {
        unset($_SESSION['included']);
    }
    //get exclude list...
    $exclude;
    if (!$allDeps) {
        $exclude = isset($_SESSION['included']) ? $_SESSION['included'] : array();
    } else {
        $exclude = array();
    }
    //echo "<br>exclude = <pre>";var_dump($exclude); echo "</pre>";
    $ret = $loader->compile($files, $repos, $type, true, $theme, $exclude, $opts);
    $source = $ret['source'];
    //echo "<br>included = <pre>";var_dump($ret['included']); echo "</pre>";
    if (is_null($ret['included'])) {
        $ret['included'] = array();
    }
    $_SESSION['included'] = array_merge($exclude,$ret['included']);

    if (empty($source)) {
        $source = "return;";
    }
    if ($compress) {
        //echo "<br>Compressing....";
        switch ($algorithm){
            case 'jsmin':
                require_once 'helpers/jsmin-1.1.1.php';
                $source = JSMin::minify($source);
                break;
            case 'packer':
                require_once 'helpers/class.JavaScriptPacker.php';
                $packer = new JavaScriptPacker($source, $encoding, $fast_decode, $special_char);
                $source = $packer->pack();
                break;
        }

    }

    //send the file
    if ($type == 'js') { $type = 'javascript';}
    header('Content-type:text/'.$type);
    echo $source;
}


//TODO: need to figure out how to get ie-specific css files.