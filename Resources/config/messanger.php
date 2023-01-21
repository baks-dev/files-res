<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\FrameworkConfig;

return static function (ContainerConfigurator $configurator, FrameworkConfig $config)
{
    $services = $configurator->services()
      ->defaults()
      ->autowire()
      ->autoconfigure()
    ;
    
    $configurator->parameters()->set('cdn.host', '%env(CDN_HOST)%');
    $configurator->parameters()->set('cdn.user', '%env(CDN_USER)%');
    $configurator->parameters()->set('cdn.pass', '%env(CDN_PASS)%');
    
    $namespace = 'BaksDev\Files\Resources';
	
	
    $services->load($namespace.'\Messanger\\', '../../Messanger')
      ->exclude(__DIR__.'/../../Messanger/**/*Command.php');
	
    $config->messenger()->routing(\BaksDev\Files\Resources\Messanger\Request\Images\Command::class)->senders(['async_files_resources']);
	
};
