<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Files\Resources\Messenger\Request;
use Symfony\Config\FrameworkConfig;

return static function(FrameworkConfig $framework) {

    $messenger = $framework->messenger();


    $messenger
        ->transport('files-res')
        ->dsn('redis://%env(REDIS_PASSWORD)%@%env(REDIS_HOST)%:%env(REDIS_PORT)%?dbindex=%env(REDIS_TABLE)%&auto_setup=true')
        ->options(['stream' => 'files-res'])
        ->failureTransport('failed-files-res')
        ->retryStrategy()
        ->maxRetries(3)
        ->delay(1000)
        ->maxDelay(0)
        ->multiplier(3) // увеличиваем задержку перед каждой повторной попыткой
        ->service(null);


    $messenger
        ->transport('files-res-low')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->options(['queue_name' => 'files-res'])
        ->failureTransport('failed-files-res')
        ->retryStrategy()
        ->maxRetries(1)
        ->delay(1000)
        ->maxDelay(1)
        ->multiplier(2)
        ->service(null);



    $messenger
        ->transport('failed-files-res')
        ->dsn('%env(MESSENGER_TRANSPORT_DSN)%')
        ->options(['queue_name' => 'failed-files-res']);
};
