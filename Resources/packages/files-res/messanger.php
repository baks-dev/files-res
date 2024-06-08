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

use BaksDev\Files\Resources\Messenger\Request;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework) {

    $messenger = $framework->messenger();


    $messenger
        ->transport('files-res')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->options(['queue_name' => 'files-res'])
        ->failureTransport('failed-files-res')
        ->retryStrategy()
        ->maxRetries(3)
        ->delay(1000)
        ->maxDelay(0)
        ->multiplier(3) // увеличиваем задержку перед каждой повторной попыткой
        ->service(null)
    ;

    $messenger
        ->transport('failed-files-res')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->options(['queue_name' => 'failed-files-res'])
    ;
};
