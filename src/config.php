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
        'jxlibext' => array(
            'imageUrl' => 'images/',
            'paths' => array(
                'js' => 'jxlibext/Source',
                'css' => 'jxlibext/css',
                'images' => 'jxlib/css/images'
            )
        )
    )
);

$cconfig = array(
    'cacheClass' => 'FileCache',
    'path' => '/../cache/'
);