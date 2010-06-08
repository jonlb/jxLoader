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
        loadOptionals: true,
        serial: true,
        page: null
    },

    queue: null,

    fn: null,

    counter: 0,

    current: 0,

    initialize: function(options){
        this.setOptions(options);
        this.dev = this.options.dev;
        this.queue = new Hash();
        if (!$defined(this.options.page)) {
            this.requestPageId();
        }
    },

    requestPageId: function () {
        var data = {
            requestPage: true
        };
        var request = new Request.JSON({
            url: this.options.url,
            data: data,
            onSuccess: this.processPageId.bind(this)
        });
        request.send();
    },

    processPageId: function (data) {
        if ($defined(data.page)) {
            this.options.page = data.page;
        }
    },

    require: function (files, fn, serial) {
        this.options.serial =  $defined(serial)? serial : this.options.serial;
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
        if (!$defined(this.options.page)) {
            this.requestDeps.delay(5,this, [files, repos, fn]);
            return;
        }
        var data = {
            file: files,
            repo: repos,
            mode: 'dev',
            depsOnly: true,
            opts: this.options.loadOptionals,
            page: this.options.page
        };
        this.fn.set(this.counter + 1,fn);
        var request = new Request.JSON({
            url: this.options.url,
            data: data,
            onSuccess: this.loadDeps.bind(this)
        });
        request.send();
    },

    loadDeps: function (data) {
        if (this.options.serial) {
            this.counter++;
            this.queue.set(this.counter, data);
        } else {
            this.queue.get(this.counter).push(data);
        }
        if (!this.loading) {
            this.nextFile();
        }
    },

    nextFile: function () {
        if (this.queue.get(this.current).length > 0) {
            var c = this.queue.get(index).shift();
            this.requestFiles(c,null,this.nextFile.bind(this, index));
        } else {
            this.fn.get(this.current).run();
            this.queue.erase(this.current);
            if (this.queue.getLength() > 0) {
                this.current++;
                this.nextFile();
            }
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

        a.push('page='+this.options.page);

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
