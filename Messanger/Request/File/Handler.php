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

namespace BaksDev\Files\Resources\Messanger\Request\File;

use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Handler
{
    public const PATH_FILE_CDN = '/cdn/upload/file';
    
    private EntityManagerInterface $entityManager;
    //private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $parameter;
    //private KernelInterface $kernel;
    
    public function __construct(
      EntityManagerInterface $entityManager,
      //LoggerInterface $logger,
      HttpClientInterface $httpClient,
      ParameterBagInterface $parameter,
      KernelInterface $kernel,
    )
    {
        $this->entityManager = $entityManager;
        //$this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->parameter = $parameter;
        //$this->kernel = $kernel;
    }
    
    public function handle(Command $command) : bool|string
    {
        
        // dump('$command');
        //dd($command);
        
        /* Абсолютный путь к файлу изображения */
        $uploadFile = $this->parameter->get($command->dir).$command->id.'/'.$command->name;
        
        
        /* Указываем путь и название файла для загрузки CDN */
        $formFields = [
          'dir' => $command->dir,
          'id' => $command->id,
          'image' => DataPart::fromPath($uploadFile),
        ];
        
        $formData = new FormDataPart($formFields);
        
        $request = $this->httpClient->request('POST', $this->parameter->get('cdn.host').self::PATH_FILE_CDN, [
          'headers' => $formData->getPreparedHeaders()->toArray(),
          'body' => $formData->bodyToString(),
        ]);
        
        
        if($request->getStatusCode() !== 200)
        {
            throw new RecoverableMessageHandlingException('Error upload file CDN');
        }
        
        
        /* @var UploadEntityInterface $imgEntity */
        $imgEntity = $this->entityManager->getRepository($command->entity)->find($command->id);
    
        /* Обновляем сущность на CDN файла */
        if($imgEntity)
        {
            $imgEntity->updCdn('webp');
            $this->entityManager->flush();
        }

        return true;
    }
    
}