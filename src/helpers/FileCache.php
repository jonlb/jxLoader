<?php


class FileCache {

    private $config;
    private $cache_path;

    public function __construct($config) {
        $this->config = $config;
        $this->cache_path = dirname(__FILE__) . $this->config['path'];
        if (!file_exists($this->cache_path)) {
            mkdir($this->cache_path);
        }
    }

    public function get($key) {
        if (file_exists($this->cache_path . "$key.cache")) {
            return file_get_contents($this->cache_path . "$key.cache");
        } else {
            return false;
        }
    }

    public function save($key, $string) {
        return file_put_contents($this->cache_path . "$key.cache", $string);
    }

    public function clearCache() {
        $it = new RecursiveDirectoryIterator($this->cache_path);
        foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST) as $filename => $file) {
            if ($file->isFile()) {
                 //echo "<br>checking $filename";
                unlink($file->getRealPath());
            } else {
                rmdir($file->getRealPath());
            }
        }
    }
}
