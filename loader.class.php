<?php

require_once 'helpers/yaml.php';

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
        }
        return $result;
    }

    private function load_repo($name, $config) {
        $path = $config['paths']['js'];
        if (!is_dir($path)) {
            $path = $this->config['repoBasePath'] . $path;
        }
        //echo "loading from path: $path";
        //grab a recursiveDirectoryIterator and process each file it finds
        $it = new RecursiveDirectoryIterator($path);
        foreach (new RecursiveIteratorIterator($it) as $filename => $file) {

            if ($file->isFile()) {
                 //echo "<br>checking $filename";
                $p = $file->getRealPath();
                $source = file_get_contents($p);
                //echo "<pre>$source</pre>";
                $descriptor = array();

                // get contents of first comment
                preg_match('/\s*\/\*\s*(.*?)\s*\*\//s', $source, $matches);

                if (!empty($matches)){
                    //echo "<br>Got contents of first comment.";
                    // get contents of YAML front matter
                    preg_match('/^-{3}\s*$(.*?)^(?:-{3}|\.{3})\s*$/ms', $matches[1], $matches);

                    if (!empty($matches)) {
                        //echo "<br>decoding: <pre>". $matches[1] . "</pre>";
                        $descriptor = YAML::decode($matches[1]);
                    }
                }

                // echo "descriptor decoded:"; var_dump($descriptor);
                 
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
            //die();
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
        if ($length == 1) return array($exploded[0],$exploded[1]);
		return array($exploded2[0], $exploded[1]);
	}

    public function get_repo_array() {
        return $this->repos;
    }

    public function get_flat_array() {
        return $this->flat;
    }

    public function compile_deps($classes, $repos, $type, $opts = true, $exclude = array()) {
        //echo "compiling deps...";

        if (!is_array($exclude)) {
            $exclude = array();
        }
        //echo "<br>Checking exclude in compile_deps: "; var_dump($exclude);
        $list = array();
        //var_dump($repos);
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
                    $arr['visited'] = false;
                }
                //echo "<br>Class: $class ; Repo from class: $r";
                $list = $this->includeDependencies($r, $class, $opts, $exclude, $this->flat, $list, $type);
            }
        }
        return $list;
    }

    public function compile($classes, $repos, $type = 'js', $includeDeps = true, $theme = '', $exclude = array(), $opts = true) {

        $deps;
        //echo "getting deps...";
        if ($includeDeps) {
            $deps = $this->compile_deps($classes, $repos, $type, $opts, $exclude);
        } else {
            $deps = $this->convert_classes_to_dep($classes, $type, $exclude);
        }

        //echo "<br>deps returned:<pre>";
        //var_dump($deps);
        //echo "</pre>";

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

            //echo "<br>returned from GET method: <br><pre>";
            //var_dump($ret);
            //echo "</pre>";
            //return
            return array('included' => $ret['includes'] , 'source' => implode("\n\n",$ret['sources']));
        } else {
            return false;
        }
    }

    private function includeDependencies($repo, $class, $opts, $exclude, &$flatArray, $list = array(), $type = 'js', &$ml = array()) {
        $k;
        if (strpos($class,'/') === false) {
            $k = strtolower($repo).'/'.strtolower($class);
        } else {
            $k = $class;
        }
        //echo "<br>including dependencies for $k";

        if (!array_key_exists($k,$flatArray)) {
            //echo "<br>key doesn't exist...";
            return $list;
        }
        $inf = $flatArray[$k];
        $circ = false;
        if ($inf['visited'] === true && in_array($k,$ml)) {
            //we've been here before.... circular reference!
            //echo "<br>WE KNOW THIS IS A CIRCULAR REFERENCE!!!<br>";
            $circ = true;
        }

        //echo "<br>type: $type";
        //echo "<br>path: ".$inf['path'];
        //echo "<br>exclude: "; var_dump($exclude);
        //echo "<br>list: ";var_dump($list);
        if (($type=='js' && (in_array($inf['path'],$exclude) || in_array($inf['path'], $list))) ||
            ($type=='css'  && (in_array($k,$exclude) || in_array($k, $list))) ||
            ($type=='jsdeps' && (in_array($k,$list) || in_array($inf['path'],$exclude)))) {
            //echo "<br>$k excluded or already included...";
            return $list;
        }
        if (!$circ) {
            $requires = $inf['requires'];
            $flatArray[$k]['visited'] = true;
            //echo "<br>Requires:<br><pre>"; var_dump($requires); echo "</pre>";
            if ($opts && array_key_exists('optional', $inf) && count($inf['optional']) > 0) {
                $requires = array_merge($requires, $inf['optional']);
            }
            if (is_array($requires) and count($requires) > 0) {
                foreach ($requires as $req) {
                    list($r, $c) = explode('/',$req);
                    array_push($ml,$k);
                    $list = $this->includeDependencies($r, $c, $opts, $exclude, $flatArray, $list, $type, $ml);
                    //echo "<br>Coming back to $k level";
                    array_pop($ml);
                }
            }
            //echo "<br>adding file... $k";
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
            //echo "<br>Class: $class";
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
            //echo "<br>Including $filename";
            $sources[] = file_get_contents($filename);
            $included[] = $filename;
        }
        return array('includes' => $included, 'sources' => $sources);
    }

    private function get_css_files($sources, $included, $theme, $deps) {
       // echo "Deps passed in:<br><pre>";
        //var_dump($deps);
        //echo "</pre>";
        foreach ($deps as $dep) {
            //split out repo name
            list($r,$c) = explode('/',$dep);
            $included[] = $dep;
            //get css files
            $csspath = $this->config['repos'][$r]['paths']['css'];
            $csspath = str_ireplace('{theme}',$theme,$csspath);
            $csspath = realpath($this->config['repoBasePath'] . $csspath);
            $cssfiles = $this->flat[$dep]['css'];

            //var_dump($this->flat[$dep]);


            if (!empty($cssfiles)) {
                //echo "<br>processing CSS files...<br><pre>";
                //var_dump($cssfiles);
                //echo "</pre>";
                foreach ($cssfiles as $css) {
                    $fp = $csspath . '/' . $css . '.css';
                    //echo "<br>file path: $fp";

                    if (file_exists($fp)) {
                        $s = file_get_contents($fp);
                        //replace for image path

                        if ($this->config['rewriteImageUrl']) {
                            $s = str_ireplace($this->config['repos'][$r]['imageUrl'], $this->config['imagePath'],$s); 
                        }
                        //echo "file: <br><pre>" .$s ."</pre>";
                        $sources[] = $s;
                    } else {
                        if (!empty($this->config['repos'][$r]['paths']['cssalt'])) {
                            $csspathalt = $this->config['repos'][$r]['paths']['cssalt'];
                            //echo "<br>CssPathAlt: $csspathalt";
                            $csspathalt = str_ireplace('{theme}',$theme,$csspathalt);
                            //echo "<br>CssPathAlt: $csspathalt";
                            $csspathalt = realpath($this->config['repoBasePath'] . $csspathalt);
                            //echo "<br>CssPathAlt: $csspathalt";
                            $fp = $csspathalt . '/' . $css . '.css';
                            //echo "<br>checking alternate file path: $fp";
                            if (file_exists($fp)) {
                                $s = file_get_contents($fp);
                                //replace for image path

                                if ($this->config['rewriteImageUrl']) {
                                    $s = str_ireplace('images/',$this->config['repos'][$r]['imageUrl'], $s);
                                }
                                //echo "file: <br><pre>" .$s ."</pre>";
                                $sources[] = $s;
                            }
                        }
                    }
                }
            }

            if ($this->config['moveImagesRelativeToLoader']) {
                $imageFiles = $this->flat[$dep]['images'];
                if (!empty($imageFiles)) {
                    //get images and move them
                    $ipath = $this->config['repos'][$r]['paths']['images'];
                    //echo "<br>orginating image path:" . $ipath;
                    if (strpos($ipath,'{theme}') !== false) {
                        $ipath = str_ireplace('{theme}',$theme,$ipath);
                    }
                    //echo "<br>orginating image path:" . $ipath;
                    $ipath = realpath($this->config['repoBasePath'] . $ipath);
                    //echo "<br>image path: $ipath";

                    $destImagePath = $this->config['imagePath'];
                    //$destImagePath = realpath($destImagePath);
                    //echo "<br>image destination path: $destImagePath";

                    //create dest directory if it's not there
                    if (!file_exists($destImagePath)) {
                        mkdir($destImagePath);
                    }

                    //echo "<br>images:<br><pre>"; var_dump($imageFiles); echo "</pre>";
                    foreach ($imageFiles as $filename) {
                        if (!file_exists($destImagePath . '/' . $filename)) {
                            //echo "<br>original: " . $ipath . '/' . $filename;
                            //echo "<br>destination: " . $destImagePath . '/' . $filename;
                            copy($ipath . '/' . $filename, $destImagePath . '/' . $filename);
                        }
                    } 
                }
            }




        }
        return array('includes' => $included, 'sources' => $sources);
    }
}
