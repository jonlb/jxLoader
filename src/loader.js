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
 * It will provide the capability to lazy load js, css, and images (sort of) as they are needed.
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

    requests: null,

    fn: null,

    counter: 0,

    current: 0,

    initialize: function(options){
        this.setOptions(options);
        this.dev = this.options.dev;
        this.requests = new Hash();
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

    require: function (classes, fn, key, prereqs) {
        if (this.dev) {
            key = $defined(key) ? key : 'set'+ (this.counter++);
            this.requests.set(key, {
                classes: classes,
                fn: fn,
                prereqs: prereqs,
                deps: [],
                loading: false
            });
            this.requestDeps(key);
        } else {
            this.requestFiles(files, null, true, null, fn);
        }

    },

    require_repo: function (repos, fn, key, prereqs) {
        if (this.dev) {
            key = $defined(key) ? key : 'set'+ (this.counter++);
            this.requests.set(key, {
                repos: repos,
                fn: fn,
                prereqs: prereqs,
                deps: [],
                loading: false
            });
            this.requestDeps(key);
        } else {
            this.requestFiles(null, repos, true, null, fn);
        }
    },

    requestDeps: function(key) {
        if (!$defined(this.options.page)) {
            this.requestDeps.delay(5,this, key);
            return;
        }
        var req = this.requests.get(key);
        var data = {
            file: req.classes,
            repo: req.repos,
            mode: 'dev',
            depsOnly: true,
            opts: this.options.loadOptionals,
            page: this.options.page,
            key: key
        };
        var request = new Request.JSON({
            url: this.options.url,
            data: data,
            onSuccess: this.loadDeps.bind(this)
        });
        request.send();
    },

    loadDeps: function (data) {
        if ($defined(data)) {
            var req = this.requests.get(data.key);
            req.set('deps',data.deps);
            this.run(data.key)
        }
    },

    run: function (key) {
        var req = this.requests.get(key);
        if (req.prereqs.length == 0) {
            var keys = req.deps.getKeys();
            if (keys.length > 0) {
                var dep = keys[0];
                var css = req.deps.get(key[0]);
                req.deps.shift();
                req.loading = true;
                this.requestFiles(dep, null, css, key, this.fileDone.bind(this,key))
            } else {
                req.fn.run();
                //remove from the hash
                this.requests.erase(key);
                //remove key from all prereqs lists
                this.requests.each(function(request, key2){
                    if (request.prereqs.contains(key)) {
                        request.prereqs = request.prereqs.erase(key);
                        if (request.prereqs.length == 0) {
                            this.run(key2);
                        }
                    }
                },this);


            }
        }
    },

    fileDone: function (key) {
        var req = this.requests.get(key);
        req.loading = false;
        this.run(key);
    },

    requestFiles: function(files, repos, css, key, fn){

        var qs1, qs2;
        var a = [];
        if ($defined(files)) {
            if ($type(files) != 'array') {
                files = [files];
            }
            files.each(function(file){
                a.push('file[]='+file);
            },this);
        }

        if ($defined(repos)) {
            if ($type(repos) != 'array') {
                repos = [repos];
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
        if ($defined(key)) {
            a.push('key='+key);
        }

        qs1 = a.join('&');
        var jsurl = this.options.url + '?' + qs1;
        if (css) {
            a.push('theme='+this.options.theme);
            a.push('type=css');
            qs2 = a.join('&');
            var cssurl = this.options.url + '?' + qs2;
            var c = new Asset.css(cssurl);
        }
        var script = new Asset.javascript(jsurl,{
                onload: fn
        });
    }

}))(options || {});

var $uses = Loader.require.bind(Loader);
