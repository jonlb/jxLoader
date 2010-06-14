/*
---

name: Loader

description: This class is used to create managers to control stores, widgets, or other components

license: MIT-style license.

requires:
 - Core/Browser
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
 - Option.Page

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

    },

    require: function (classes, key, prereqs, fn) {

        key = $defined(key) ? key : 'set'+ (this.counter++);
        this.requests.set(key, {
            classes: classes,
            fn: fn,
            prereqs: prereqs,
            deps: [],
            loading: false,
            repos: null
        });
        if (this.dev) {
            this.requestDeps(key);
        } else {
            if ($defined(prereqs) && prereqs.length > 0) {
                fn = this.requestDone.bind(this, key);
            }
            this.requestFiles(classes, null, true, key, fn);
        }

    },

    require_repo: function (repos, fn, key, prereqs) {
        key = $defined(key) ? key : 'set'+ (this.counter++);
        this.requests.set(key,new Hash({
            repos: repos,
            fn: fn,
            prereqs: prereqs,
            deps: [],
            loading: false,
            classes: null
        }));
        if (this.dev) {
            this.requestDeps(key);
        } else {
            if ($defined(prereqs) && prereqs.length > 0) {
                fn = this.requestDone.bind(this, key);
            }
            this.requestFiles(null, repos, true, key, fn);
        }
    },

    requestDeps: function(key) {
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
            req.deps =$A(data.deps);
            this.run(data.key)
        }
    },

    run: function (key) {
        var req = this.requests.get(key);
        if (!$defined(req.prereqs) || req.prereqs.length == 0) {
            if (req.deps.length > 0) {
                var dep = req.deps.shift();
                var deps =   dep.split(':');
                var css = !!(deps[1]);
                req.loading = true;
                this.requestFiles(deps[0], null, css, key, this.fileDone.bind(this,key))
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
                req.fn.run();

            }
        }
    },

    fileDone: function (key) {
        var req = this.requests.get(key);
        req.loading = false;
        this.run(key);
    },

    requestDone: function (key) {
        var req = this.requests.get(key);
        req.loading = false;
        req.fn.run();
        //remove from the hash
        this.requests.erase(key);
        //remove key from all prereqs lists
        this.requests.each(function(request, key2){
            if ($defined(request.prereqs) && request.prereqs.contains(key)) {
                request.prereqs = request.prereqs.erase(key);
                if (request.prereqs.length == 0) {
                    this.requestFiles(request.classes, request.repos, true, key2, this.requestDone.bind(this, key2));
                }
            }
        },this);
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
