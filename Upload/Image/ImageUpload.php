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

namespace BaksDev\Files\Resources\Upload\Image;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use BaksDev\Telegram\Bot\Messenger\Notifier\NotifierTelegramBotMessage;
use Doctrine\ORM\Mapping\Table;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImageUpload implements ImageUploadInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('filesResLogger')] private LoggerInterface $logger,
        private Filesystem $filesystem,
        private MessageDispatchInterface $messageDispatch,
    ) {}


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
