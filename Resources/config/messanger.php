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

return static function(ContainerConfigurator $configurator, FrameworkConfig $framework) {
	$services = $configurator->services()
		->defaults()
		->autowire()
		->autoconfigure()
	;
	
	/* Настройки вторизации CDN */
	$configurator->parameters()->set('cdn.host', '%env(CDN_HOST)%');
	$configurator->parameters()->set('cdn.user', '%env(CDN_USER)%');
	$configurator->parameters()->set('cdn.pass', '%env(CDN_PASS)%');
	
	$namespace = 'BaksDev\Files\Resources';
	
	$services->load($namespace.'\Messanger\\', __DIR__.'/../../Messanger')
		->exclude(__DIR__.'/../../Messanger/**/*Command.php')
	;
	
	/*
        php bin/console messenger:stop-workers
        php bin/console messenger:consume async_files_resources --time-limit=3600 -vv
    */
	
	$framework->messenger()->transport('async_files_resources')
		->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
		->options(['queue_name' => 'files'])
		->retryStrategy()
		->maxRetries(20)
		->delay(1000)
		->maxDelay(0)
		->multiplier(2)
		->service(null)
	;
	
	$framework->messenger()->routing(\BaksDev\Files\Resources\Messanger\Request\Images\Command::class)->senders(
		['async_files_resources']
	);
};
