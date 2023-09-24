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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class CDNUploadFile
{
    /**
     * Конечная точка CDN для загрузки файла
     */
    public const PATH_FILE_CDN = '/cdn/upload/file';

    private EntityManagerInterface $entityManager;

    private HttpClientInterface $httpClient;

    /**
     * Абсолютный путь директории загрузки
     */
    private string $upload;

    /**
     * Данные для подключения к серверу CDN
     */
    private string $CDN_HOST;
    private string $CDN_USER;
    private string $CDN_PASS;


    public function __construct(
        #[Autowire('%kernel.project_dir%/public/upload/')] string $upload,
        #[Autowire(env: 'CDN_HOST')] string $CDN_HOST,
        #[Autowire(env: 'CDN_USER')] string $CDN_USER,
        #[Autowire(env: 'CDN_PASS')] string $CDN_PASS,
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
    )
    {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;

        $this->upload = $upload;
        $this->CDN_HOST = $CDN_HOST;
        $this->CDN_USER = $CDN_USER;
        $this->CDN_PASS = $CDN_PASS;
    }


    public function __invoke(CDNUploadFileMessage $command): bool|string
    {
        $this->entityManager->clear();
        
        /* @var UploadEntityInterface $imgEntity */
        $imgEntity = $this->entityManager->getRepository($command->entity)->find($command->id);

        if($imgEntity === null)
        {
            throw new RecoverableMessageHandlingException('Error get Entity ID:'.$command->id);
        }

        /* Абсолютный путь к файлу изображения */
        $uploadDir = $this->upload.$imgEntity::TABLE.'/'.$command->dir;
        $uploadFile = $uploadDir.'/'.$command->name;

        if(!file_exists($uploadFile))
        {
            throw new RecoverableMessageHandlingException(sprintf('File Not found: %s', $uploadFile));
        }

        /* Указываем путь и название файла для загрузки CDN */
        $formFields = [
            'dir' => (string) $command->dir,
            'file' => DataPart::fromPath($uploadFile),
        ];

        /* Формируем заголовки файла и авторизации CDN */
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: Basic '.base64_encode($this->CDN_USER.':'.$this->CDN_PASS);

        $request = $this->httpClient->request(
            'POST',
            'https://'.$this->CDN_HOST.self::PATH_FILE_CDN.'/'.$imgEntity::TABLE,
            [
                'headers' => $headers,
                'body' => $formData->bodyToString(),
            ]);

        if($request->getStatusCode() !== 200)
        {
            throw new RecoverableMessageHandlingException(sprintf('Error upload file CDN (%s)', $request->getContent()));
        }

        /* Обновляем сущность на CDN файла */
        $imgEntity->updCdn('webp');
        $this->entityManager->flush();

        /* Удаляем оригинал если файл загружен на CDN */
        unlink($uploadFile);

        if($this->is_dir_empty($uploadDir))
        {
            rmdir($uploadDir);
        }

        return true;
    }


    private function is_dir_empty($dir)
    {
        if(!is_readable($dir))
            return null;
        return (count(scandir($dir)) === 2);
    }
}

