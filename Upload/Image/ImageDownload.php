<?php
/*
 * Copyright (c) 2023.  Baks.dev <admin@baks.dev>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BaksDev\Files\Resources\Upload\Image;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Doctrine\ORM\Mapping\Table;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ImageDownload
{
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        private HttpClientInterface $client,
        private Filesystem $filesystem,
        private MessageDispatchInterface $messageDispatch,
        LoggerInterface $filesResLogger,
    ) {
        $this->logger = $filesResLogger;
    }

    public function get(string $url, UploadEntityInterface $entity): void
    {

        $upload = implode(DIRECTORY_SEPARATOR, [$this->upload, 'public', 'upload']);

        $parse_url = parse_url($url);
        $originalFilename = pathinfo($parse_url['path']);
        $name = md5($url);
        $newFilename = $name.'.'.$originalFilename['extension'];

        $dirId = $entity->getUploadDir();

        if(empty($dirId))
        {
            throw new InvalidArgumentException(sprintf('Not found ID in class %s', get_class($entity)));
        }

        /** Определяем название директор из таблицы */
        $ref = new ReflectionClass($entity);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $TABLE = $current->getArguments()['name'] ?? 'images';

        /** Абсолютный путь директории загрузки файла по названию таблицы и дайджеста */
        $uploadDir = implode(DIRECTORY_SEPARATOR, [$upload, $TABLE, $dirId]);

        /** Абсолютный путь к файлу с названием */
        $path = implode(DIRECTORY_SEPARATOR, [$uploadDir, $newFilename]);

        /* Создаем директорию Для загрузки */
        $this->filesystem->mkdir($uploadDir);

        /* Если файла не существует - скачиваем */
        if(!file_exists($path))
        {
            /* Создаем директорию для загрузки */
            if(!file_exists($uploadDir) && !mkdir($uploadDir) && !is_dir($uploadDir))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
            }

            $response = $this->client->request('GET', $url);

            if($response->getStatusCode() !== 200)
            {
                $error = sprintf('%s: Ошибка при загрузке файла', $url);
                $this->logger->error($error);

                return;
            }

            // получить содержимое ответа и сохранить их в файл
            $fileHandler = fopen($path, 'wb');
            foreach($this->client->stream($response) as $chunk)
            {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);

            /* Вычисляем размер файла */
            $fileSize = filesize($path);

            /*
             * Применяем к сущности параметры файла
             * $name - название файла без расширения
             */
            $entity->updFile($name, $originalFilename['extension'], $fileSize);

            /* Отправляем событие в шину  */
            $this->messageDispatch->dispatch(
                message: new CDNUploadImageMessage($entity->getId(), get_class($entity), $newFilename, $dirId),
                transport: 'files-res'
            );
        }
    }
}
