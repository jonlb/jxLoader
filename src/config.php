<?php

$lconfig = array(
    'repoBasePath' => dirname(__FILE__) . '/../repos/',
    'moveImagesRelativeToLoader' => true,
    'imagePath' => 'images',
    'rewriteImageUrl' => true,
    'repos' => array(
        'core' => array(
            'imageUrl' => 'images/',
            'paths' => array(
                'js' => 'core/Source'
            )
        ),
        'more' => array(
            'imageUrl' => 'images/',
            'paths' => array(
                'js'=>'more/Source'
            )
        ),
        'jxlib' => array(
            'imageUrl' => 'images/',
            'paths' => array(
                'js' => 'jxlib/Source',
                'css' => 'jxlib/themes/{theme}/css',
                'cssalt' => 'jxlib/themes/{theme}',
                'images' => 'jxlib/themes/{theme}/images'
            )
        ),
        'app' => array(
            'imageUrl' => 'images/',
            'paths' => array(
                'js' => 'app/Source',
                'css' => 'app/css',
                'images' => 'app/css/images'
            )
        )
    )
);

$cconfig = array(
    'cacheClass' => 'FileCache',
    'path' => '/../cache/'
);