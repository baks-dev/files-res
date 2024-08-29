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

namespace BaksDev\Files\Resources\Upload\Image;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use BaksDev\Telegram\Bot\Messenger\Notifier\NotifierTelegramBotMessage;
use Doctrine\ORM\Mapping\Table;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ImageUpload implements ImageUploadInterface
{
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        private Filesystem $filesystem,
        private MessageDispatchInterface $messageDispatch,
        LoggerInterface $filesResLogger,
    ) {
        $this->logger = $filesResLogger;
    }


    public function upload(File|UploadedFile $file, UploadEntityInterface $entity): void
    {
        $name = md5_file($file->getPathname());

        if(empty($name))
        {
            throw new InvalidArgumentException(sprintf('Not found image in class %s', get_class($entity)));
        }

        $ref = new ReflectionClass($entity);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $TABLE = $current->getArguments()['name'] ?? 'images';


        /* Создаем директорию Для загрузки файла */
        $uploadDir = implode(DIRECTORY_SEPARATOR, [$this->upload, 'public', 'upload', $TABLE, $name]);
        $this->filesystem->mkdir($uploadDir);

        /* Перемещаем файл в директорию */
        try
        {
            /* Генерируем новое название файла с расширением */
            $newFilename = 'image.'.$file->guessExtension();

            if(!file_exists($uploadDir.DIRECTORY_SEPARATOR.$newFilename))
            {
                /* Перемещаем файл */
                $file = $file->move(
                    $uploadDir,
                    $newFilename
                );
            }

            $newFilePath = $uploadDir.DIRECTORY_SEPARATOR.$newFilename;

            $extension = pathinfo($newFilePath, PATHINFO_EXTENSION);
            $size = filesize($newFilePath);
            $entity->updFile($name, $extension, $size);

            /* Отправляем событие в шину  */
            $this->messageDispatch->dispatch(
                message: new CDNUploadImageMessage($entity->getId(), $entity::class, $name),
                transport: 'files-res'
            );

        }
        catch(FileException $e)
        {
            $this->logger->error($e->getMessage(), [self::class.':'.__LINE__]);
        }
    }
}
