<?php

// config/packages/doctrine.php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\Framework\TranslatorConfig;
use Symfony\Config\FrameworkConfig;
use Symfony\Config\TwigConfig;

return static function (TwigConfig $config) {

    $config->global('CDN_HOST')->value('https://%env(CDN_HOST)%');
};
