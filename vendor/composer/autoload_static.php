<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7458a65bda638d519300ecdd4080a554k
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
            'Psr\\Log\\' => 8,
            'Psr\\Cache\\' => 10,
        ),
        'L' => 
        array (
            'League\\Flysystem\\' => 17,
        ),
        'C' => 
        array (
            'Cache\\TagInterop\\' => 17,
            'Cache\\Adapter\\Filesystem\\' => 25,
            'Cache\\Adapter\\Common\\' => 21,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/cache/src',
        ),
        'League\\Flysystem\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/flysystem/src',
        ),
        'Cache\\TagInterop\\' => 
        array (
            0 => __DIR__ . '/..' . '/cache/tag-interop',
        ),
        'Cache\\Adapter\\Filesystem\\' => 
        array (
            0 => __DIR__ . '/..' . '/cache/filesystem-adapter',
        ),
        'Cache\\Adapter\\Common\\' => 
        array (
            0 => __DIR__ . '/..' . '/cache/adapter-common',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7458a65bda638d519300ecdd4080a554k::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7458a65bda638d519300ecdd4080a554k::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
