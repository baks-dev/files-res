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

use BaksDev\Core\Services\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messanger\Request\Images\Command;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImageDownload
{
    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameter;

    private HttpClientInterface $client;

    private Filesystem $filesystem;

    private TranslatorInterface $translator;

    private LoggerInterface $logger;

    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        ParameterBagInterface $parameter,
        HttpClientInterface $client,
        Filesystem $filesystem,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        MessageDispatchInterface $messageDispatch
    ) {
        $this->parameter = $parameter;
        $this->client = $client;
        $this->filesystem = $filesystem;

        $this->translator = $translator;
        $this->logger = $logger;
        $this->messageDispatch = $messageDispatch;
    }

    public function get(string $url, UploadEntityInterface $entity): void
    {
        $parse_url = parse_url($url);
        $originalFilename = pathinfo($parse_url['path']);
        $name = md5($url);
        $newFilename = $name.'.'.$originalFilename['extension'];

        /* преобразуем ссылку в UID */
        /*$stringDir = substr($name, 0, 8).'-'.
            substr($name, 8, 4).'-'.
            substr($name, 12, 4).'-'.
            substr($name, 16, 4).'-'.
            substr($name, 20);*/

        $dirId = $entity->getUploadDir();

        if (empty($dirId))
        {
            throw new InvalidArgumentException(sprintf('Not found ID in class %s', get_class($entity)));
        }

        /* Определяем директорию загрузки файла по названию таблицы */
        $parameterUploadDir = $entity::TABLE;
        $uploadDir = $this->parameter->get($parameterUploadDir).$dirId;

        /* Создаем директорию Для загрузки */
        $this->filesystem->mkdir($uploadDir);

        /* Полный путь к файлу с названием */
        $path = $uploadDir.'/'.$newFilename;

        /* Если файла не существует - скачиваем */
        if (!file_exists($path))
        {
            /* Создаем директорию для загрузки */
            if (!file_exists($uploadDir) && !mkdir($uploadDir) && !is_dir($uploadDir))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
            }

            $response = $this->client->request('GET', $url);

            if ($response->getStatusCode() !== 200)
            {
                $error = sprintf(
                    '%s : %s',
                    $url,
                    $this->translator->trans(
                        'error.upload.file',
                        domain: 'files.res'
                    )
                );

                $this->logger->error($error);

                return;
            }

            // получить содержимое ответа и сохранить их в файл
            $fileHandler = fopen($path, 'wb');
            foreach ($this->client->stream($response) as $chunk)
            {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);

            /* Вычисляем размер файла */
            $fileSize = filesize($path);

            /*
             *  Применяем к сущности параметры файла
             *  $name - название файла без расширения
             */
            $entity->updFile($name, $originalFilename['extension'], $fileSize);

            /* Отправляем событие в шину  */
            $this->messageDispatch->dispatch(
                message: new Command($entity->getId(), get_class($entity), $newFilename, $dirId, $parameterUploadDir),
                transport: 'resources'
            );
        }
    }
}
