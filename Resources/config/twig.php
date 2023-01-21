<?php

// config/packages/doctrine.php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\Framework\TranslatorConfig;
use Symfony\Config\FrameworkConfig;
use Symfony\Config\TwigConfig;

return static function (TwigConfig $config) {
    
    $config->global('cdn_host')->value('%env(CDN_HOST)%');
};
