<?php

require_once 'includes/yaml.php';

class Loader {

    private $config;
    private $repos;
    private $queue = array();
    private $flat;

    public function __construct($config) {

        $this->config = $config;

        $dir = dirname(__FILE__);

        if (file_exists($dir .'/repos.yml' )) {
            $this->repos = YAML::decode_file($dir .'/repos.yml');
            if ($this->verify_repos() && file_exists($dir . '/flat.yml')) {
                $this->flat = YAML::decode_file($dir . '/flat.yml');
            }  else {
                $this->flat = $this->flatten($this->repos);
            }
        } else {
            $this->rebuild();
        }

        //write file back out
        file_put_contents($dir .'/repos.yml',YAML::encode($this->repos));
        file_put_contents($dir.'/flat.yml', YAML::encode($this->flat));

    }

    private function verify_repos() {
        //just check to see if each repo in config is present in yml file
        $result = true;
        foreach ($this->config['repos'] as $name => $c) {
            if (!isset($this->repos[$name])) {
                $this->load_repo($name, $c);
                $result = false;
            }
            //normalize the paths...
            if (isset($this->config['repos'][$name]['paths']['css'])) {
                $p = $this->config['repos'][$name]['paths']['css'];
                if (strpos($p,'{theme}')) {
                    list($p,$rest) = explode('{theme}',$p);
                    $this->config['repos'][$name]['paths']['css'] = $this->find_path($p).DS.'{theme}'.$rest;
                } else {
                    $this->config['repos'][$name]['paths']['css'] = $this->find_path($this->config['repos'][$name]['paths']['css']);
                }

            }

            if (isset($this->config['repos'][$name]['paths']['cssalt'])) {
                $p = $this->config['repos'][$name]['paths']['cssalt'];
                if (strpos($p,'{theme}')) {
                    list($p,$rest) = explode('{theme}',$p);
                    $this->config['repos'][$name]['paths']['cssalt'] = $this->find_path($p).DS.'{theme}'.$rest;
                } else {
                    $this->config['repos'][$name]['paths']['cssalt'] = $this->find_path($this->config['repos'][$name]['paths']['cssalt']);
                }
            }

            if (isset($this->config['repos'][$name]['paths']['images'])) {
                $p = $this->config['repos'][$name]['paths']['images'];
                if (strpos($p,'{theme}')) {
                    list($p,$rest) = explode('{theme}',$p);
                    $this->config['repos'][$name]['paths']['images'] = $this->find_path($p).DS.'{theme}'.$rest;
                } else {
                    $this->config['repos'][$name]['paths']['images'] = $this->find_path($this->config['repos'][$name]['paths']['images']);
                }
            }
        }

        //Jx_Debug::dump($this->config['repos']);
        return $result;
    }

    private function load_repo($name, $config) {
        $path = $this->find_path($config['paths']['js']);

        //grab a recursiveDirectoryIterator and process each file it finds
        $it = new RecursiveDirectoryIterator($path);
        foreach (new RecursiveIteratorIterator($it) as $filename => $file) {

            if ($file->isFile()) {
                $p = $file->getRealPath();
                $source = file_get_contents($p);
                $descriptor = array();

                // get contents of first comment
                preg_match('/\s*\/\*\s*(.*?)\s*\*\//s', $source, $matches);

                if (!empty($matches)){
                    //echo "<br>Got contents of first comment.";
                    // get contents of YAML front matter
                    preg_match('/^-{3}\s*$(.*?)^(?:-{3}|\.{3})\s*$/ms', $matches[1], $matches);

                    if (!empty($matches)) {
                        $descriptor = YAML::decode($matches[1]);
                    }
                }
                 
                // populate / convert to array requires and provides
                $requires = (array)(!empty($descriptor['requires']) ? $descriptor['requires'] : array());
                $provides = (array)(!empty($descriptor['provides']) ? $descriptor['provides'] : array());
                $optional = (array)(!empty($descriptor['optional']) ? $descriptor['optional'] : array());
                $file_name = $file->getFilename();

                // "normalization" for requires. Fills up the default package name from requires, if not present.
                // and removes any version information
                foreach ($requires as $i => $require) {
                    $requires[$i] = implode('/', $this->parse_name($name, $require));

                }
                //do same for any optional ones...
                foreach ($optional as $i => $require) {
                    $optional[$i] = implode('/', $this->parse_name($name, $require));
                }

                $this->repos[$name][$file_name] = array_merge($descriptor, array(
                    'repo' => $name,
                    'requires' => $requires,
                    'provides' => $provides,
                    'optional' => $optional,
                    'path' => $p
                ));
            }
        }


    }

    private function find_path($path) {
        if (!is_dir($path)) {
            $path = $this->config['repoBasePath'] . $path;
        }
        $check = realpath($path);

        if ($check === false) {
            $path = dirname(__FILE__).DS.$path;
            $check = realpath($path);
            if ($check === false) {
                throw new Exception('Unable to locate path '.$this->config['paths']['js']);
            } else {
                return $check;
            }
        } else {
            return $check;
        }
    }

    public function rebuild(){
        $this->repos = null;
        $this->verify_repos();
        $this->flat = $this->flatten($this->repos);
    }


    private function flatten($arr){
        $flat = array();
        foreach ($arr as $repo => $a) {
            //var_dump($a);
            foreach ($a as $key => $a2) {
                foreach ($a2['provides'] as $class) {
                    $flat[strtolower($repo).'/'.strtolower($class)] = $a2;
                }
            }
        }

        return $flat;
    }

    private function parse_name($default, $name){
		$exploded = explode('/', $name);
		$length = count($exploded);
		if ($length == 1) return array($default, $exploded[0]);
		if (empty($exploded[0])) return array($default, $exploded[1]);
        $exploded2 = explode(':',$exploded[0]);
        $length2 = count($exploded2);
        if ($length2 == 1) return array($exploded[0],$exploded[1]);
		return array($exploded2[0], $exploded[1]);
	}

    public function get_repo_array() {
        return $this->repos;
    }

    public function get_flat_array() {
        return $this->flat;
    }

    public function compile_deps($classes, $repos, $type, $opts = true, $exclude = array()) {

        if (!is_array($exclude)) {
            $exclude = array();
        }
        $list = array();
        if (!empty($repos)) {
            foreach ($repos as $repo) {
                $fa = $this->flatten(array($repo => $this->repos[$repo]));
                foreach ($fa as $key => $arr) {
                    $list = $this->includeDependencies($repo, $key, $opts, $exclude, $fa, $list, $type, array($key));
                }
            }
        }
        if (!empty($classes)) {
            foreach($classes as $class) {
                $r = $this->find_repo($class);
                //clear visited references
                foreach ($this->flat as $key => $arr) {
                    $this->flat[$key]['visited'] = false;
                }
                $list = $this->includeDependencies($r, $class, $opts, $exclude, $this->flat, $list, $type);
            }
        }
        //Jx_Debug::dump($list,'list of dependencies');
        return $list;
    }

    public function compile($classes, $repos, $type = 'js', $includeDeps = true, $theme = '', $exclude = array(), $opts = true) {

        $deps = null;
        if ($includeDeps) {
            $deps = $this->compile_deps($classes, $repos, $type, $opts, $exclude);
        } else {
            $deps = $this->convert_classes_to_dep($classes, $type, $exclude);
        }

        if (count($deps) > 0) {
            $included = array();
            $sources = array();
            //grab files, save the name of each file included in $included
            if ($type == 'js') {
                $ret = $this->get_js_files($sources, $included, $deps);
            } else {
                //css
                $ret = $this->get_css_files($sources, $included, $theme, $deps);
            }

            return array('included' => $ret['includes'] , 'source' => implode("\n\n",$ret['sources']));
        } else {
            return false;
        }
    }

    private function includeDependencies($repo, $class, $opts, $exclude, &$flatArray, $list = array(), $type = 'js', &$ml = array()) {
        $k = null;
        if (strpos($class,'/') === false) {
            $k = strtolower($repo).'/'.strtolower($class);
        } else {
            $k = $class;
        }

        if (!array_key_exists($k,$flatArray)) {
            return $list;
        }
        $inf = $flatArray[$k];
        $circ = false;
        if ($inf['visited'] === true && in_array($k,$ml)) {
            //we've been here before.... circular reference!
            $circ = true;
        }

        if (($type=='js' && (in_array($inf['path'],$exclude) || in_array($inf['path'], $list))) ||
            ($type=='css'  && (in_array($k,$exclude) || in_array($k, $list))) ||
            ($type=='jsdeps' && (in_array($k,$list) || in_array($inf['path'],$exclude)))) {
            return $list;
        }
        if (!$circ) {
            $requires = $inf['requires'];
            $flatArray[$k]['visited'] = true;
            if ($opts && array_key_exists('optional', $inf) && count($inf['optional']) > 0) {
                $requires = array_merge($requires, $inf['optional']);
            }
            if (is_array($requires) and count($requires) > 0) {
                foreach ($requires as $req) {
                    list($r, $c) = explode('/',$req);
                    array_push($ml,$k);
                    $list = $this->includeDependencies($r, $c, $opts, $exclude, $flatArray, $list, $type, $ml);
                    array_pop($ml);
                }
            }
            if ($type == 'js') {
                $list[] = $inf['path'];
            } else {
                $list[] = $k;
            }
        }

        return $list;
    }

    private function convert_classes_to_dep($classes, $type, $exclude) {
        $list = array();
        if (!is_array($classes)) {
            $classes = array($classes);
        }
        foreach ($classes as $class) {
            $class = strtolower($class);
            if (strpos($class,'/') !== false) {
                if ($type == 'js' && !in_array($this->flat[strtolower($class)]['path'], $exclude)) {
                    $list[] = $this->flat[strtolower($class)]['path'];
                } elseif ($type == 'css' & !in_array($class,$exclude)) {
                    $list[] = $class;
                }
            } else {
                foreach ($this->flat as $key => $arr) {
                    list($r, $c) = explode('/',$key);
                    if (strtolower($c) === strtolower($class)) {
                        if ($type == 'js' && !in_array($arr['path'], $exclude)) {
                            $list[] = $arr['path'];
                        } elseif ($type == 'css' & !in_array($class,$exclude)) {
                            $list[] = $key;
                        }
                        break;
                    }
                }
            }
        }

        return $list;
    }

    private function find_repo($class) {
        if (strpos($class, '/') !== false) {
            list($r,$c) = explode('/',$class);
            return $r;
        } else {
            foreach ($this->flat as $key => $arr) {
                list($r, $c) = explode('/', $key);
                if (strtolower($c) == strtolower($class)) {
                    return $r;
                }
            }
        }
        return false;
    }

    private function get_js_files($sources, $included, $deps) {
        foreach ($deps as $filename) {
            $sources[] = file_get_contents($filename);
            $included[] = $filename;
        }
        return array('includes' => $included, 'sources' => $sources);
    }

    private function get_css_files($sources, $included, $theme, $deps) {
        foreach ($deps as $dep) {
            //split out repo name
            list($r,$c) = explode('/',$dep);
            $included[] = $dep;
            //get css files
            if (isset($this->config['repos'][$r]['paths']['css'])) {
                $csspath = $this->config['repos'][$r]['paths']['css'];
                $csspath = str_ireplace('{theme}',$theme,$csspath);
                $csspath = realpath($csspath);
                $cssfiles = isset($this->flat[$dep]['css']) ? $this->flat[$dep]['css']:'';


                
                if (!empty($cssfiles)) {
                    //Jx_Debug::dump($cssfiles, "Css files for $dep");
                    //Jx_Debug::dump($this->config['repos'][$r]['paths']['css'], 'base css path');
                    //Jx_Debug::dump($csspath, "Css path for $dep");

                    foreach ($cssfiles as $css) {
                        $fp = $csspath . '/' . $css . '.css';
                        if (file_exists($fp)) {
                            $s = file_get_contents($fp);
                            //replace for image path

                            if ($this->config['rewriteImageUrl'] && isset($this->config['repos'][$r]['imageUrl'])) {
                                $s = str_ireplace($this->config['repos'][$r]['imageUrl'], $this->config['imagePath'],$s);
                            }
                            $sources[] = $s;
                        } else {
                            if (!empty($this->config['repos'][$r]['paths']['cssalt'])) {
                                $csspathalt = $this->config['repos'][$r]['paths']['cssalt'];
                                $csspathalt = str_ireplace('{theme}',$theme,$csspathalt);
                                $csspathalt = realpath($csspathalt);
                                $fp = $csspathalt . '/' . $css . '.css';
                                if (file_exists($fp)) {
                                    $s = file_get_contents($fp);
                                    //replace for image path

                                    if ($this->config['rewriteImageUrl'] && isset($this->config['repos'][$r]['imageUrl'])) {
                                        $s = str_ireplace($this->config['repos'][$r]['imageUrl'], $this->config['imagePath'],$s);
                                    }
                                    $sources[] = $s;
                                }
                            }
                        }
                    }
                }

                if ($this->config['moveImagesRelativeToLoader']) {
                    if (isset($this->flat[$dep]['images'])) {
                        $imageFiles = $this->flat[$dep]['images'];
                        if (!empty($imageFiles)) {
                            //get images and move them
                            $ipath = $this->config['repos'][$r]['paths']['images'];
                            if (strpos($ipath,'{theme}') !== false) {
                                $ipath = str_ireplace('{theme}',$theme,$ipath);
                            }
                            $ipath = realpath($ipath);

                            $destImagePath = dirname(__FILE__) . '/' . $this->config['imageLocation'];

                            //Jx_Debug::dump($destImagePath);
                            //Jx_Debug::dump(realpath($destImagePath));

                            //create dest directory if it's not there
                            if (!file_exists($destImagePath)) {
                                mkdir($destImagePath);
                            }

                            foreach ($imageFiles as $filename) {
                                if (!file_exists($destImagePath . '/' . $filename)) {
                                    copy($ipath . '/' . $filename, $destImagePath . '/' . $filename);
                                }
                            }
                        }
                    }
                }
            }
        }
        return array('includes' => $included, 'sources' => $sources);
    }
}
