/*
---

name: Loader

description: This class is used to create managers to control stores, widgets, or other components

license: MIT-style license.

requires:
 - Core/Core
 - Core/Browser
 - Core/Array
 - Core/Function
 - Core/Number
 - Core/String
 - Core/Hash
 - Core/Event
 - Core/Class
 - Core/Class.Extras
 - Core/Element
 - Core/Element.Event
 - Core/JSON
 - Core/Request
 - Core/Request.JSON
 - More/Assets
 - Core/DomReady

provides: [Loader]

...
 */
/**
 * Class: Loader
 * It will provide the capability to lazy load js, css, and images as they are needed.
 *
 *
 */
var Loader = new (new Class({

    Implements: [Options, Events],

    options: {
        theme: 'crispin',
        url: '/loader/loader.php',
        dev: false,
        compress: 'jsmin',
        loadOptionals: true
    },

    queue: null,

    fn: null,

    initialize: function(options){
        this.setOptions(options);
        this.dev = this.options.dev;
    },

    require: function (files, fn) {
        if (this.dev) {
            this.requestDeps(files, null, fn);
        } else {
            this.requestFiles(files, null, fn);
        }

    },

    require_repo: function (repos, fn) {
        if (this.dev) {
            this.requestDeps(null, repos, fn);
        } else {
            this.requestFiles(null, repos, fn);
        }
    },

    requestDeps: function(files, repos, fn) {
        var data = {
            file: files,
            repo: repos,
            mode: 'dev',
            depsOnly: true,
            opts: this.options.loadOptionals
        };
        this.fn = fn;
        var request = new Request.JSON({
            url: this.options.url,
            data: data,
            onSuccess: this.loadDeps.bind(this)
        });
        request.send();
    },

    loadDeps: function (data) {
        this.queue = data;
        this.nextFile();
    },

    nextFile: function () {
        if (this.queue.length > 0) {
            var c = this.queue.shift();
            this.requestFiles(c,null,this.nextFile.bind(this));
        } else {
            this.fn.run();
        }
    },

    requestFiles: function(files, repos, fn){

        var qs1, qs2;
        var a = [];
        if ($defined(files)) {
            if ($type(files) != 'array') {
                files = $A([files]);
            }
            files.each(function(file){
                a.push('file[]='+file);
            },this);
        }

        if ($defined(repos)) {
            if ($type(repos) != 'array') {
                repos = $A([repos]);
            }
            repos.each(function(repo){
                a.push('repo[]='+repo);
            },this);
        }

        if (this.dev) {
            a.push('mode=dev');
        }
        if (!$defined(this.options.compress)) {
            a.push('compress=false');
        } else {
            a.push('alg='+this.options.compress);
        }
        if (this.options.loadOptionals) {
            a.push('opts=true');
        } else {
            a.push('opts=false');
        }

        qs1 = a.join('&');
        var jsurl = this.options.url + '?' + qs1;
        a.push('theme='+this.options.theme);
        a.push('type=css');
        qs2 = a.join('&');
        var cssurl = this.options.url + '?' + qs2;
        var c = new Asset.css(cssurl);
        var script = new Asset.javascript(jsurl,{
                onload: fn
        });
    },

    jsSuccess: function(name, key){
        this.loaded.push(name);
        this.nextFile(key)
    }

}))(options || {});

var $uses = Loader.require.bind(Loader);
