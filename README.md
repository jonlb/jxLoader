jxLoader
========

jxLoader is both a server and javascript component designed to ease the loading of both javascript and css.
It is specifically designed to work with [mootools] and the [JxLib] UI framework and id distributed under an MIT-style
license.

[mootools]: http://mootools.net/
[jxlib]: http://jxlib.org/

**PLEASE NOTE**: This work is currently beta code. It has actually been shown to crash my browser in a couple of cases
(I'm still working on why) and could change at any moment. You're free to use this work under the terms of the MIT
license but be forewarned that it's a work in progress.

Setup
-----

In order to setup the loader you will need to do the following:

1. Download the source from this repository.

2. place the source files into a web-accessible directory, preferably in their own directory.

3. Create a repository directory to contain all of the repositories you will need to pull from.
   if you're going to use the loader.js file you will need, at minimum, one for mootools-core
   (core) and one for your application (app) as the loader.js file requires files in mootools-core
   to work properly.

4. Populate the repository directory.

5. Update the config.php file with the correct options and paths for your repositories.

6. If you're using the loader.js file, update the options.js file with the appropriate options for
   your application.

7. Place the loader.js file into one of the repositories, but not the options.js file.

8. The options.js file should be web-accessible in a location of your choosing. It is the only file you will not
   load with the loader.php or loader.js file (i wanted it separate but it would be possible to make it a dependency
   of loader.js. I also use it for setting some base Jx configurations).

Note: It's possible that this may eventually be included with JxLib at which time loader.js will be a part of that
repository.


Using the Loader
----------------

There are two ways you can use this script. It is callable simply by script and link tags to get your code down. Or you
can use the loader.js file in conjunction with a script tag for the initial load.

###Calling the loader via script and link tags

In order to load everything using script and link tags simply place the tags as normal in your page. The only thing you
need to do is to point them to the loader.php script and pass the appropriate parameters.

For example, to load the entire library of JxLib, mootools-core and -more do the following:

    <link rel="stylesheet" href="/loader/loader.php?repo[]=jxlib&type=css&theme=crispin" type="text/css" media="screen" charset="utf-8">
    <script type="text/javascript" src="/loader/loader.php?repo[]=core&repo[]=more&repo[]=jxlib"></script>


###Calling the loader via javascript (loader.js)

Config.php options
------------------


Loader.php options
------------------


Loader.js options
-----------------


Example
-------

###Setting up the Example
