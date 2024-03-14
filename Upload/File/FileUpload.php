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

namespace BaksDev\Files\Resources\Upload\File;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messanger\Request\File\CDNUploadFileMessage;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FileUpload implements FileUploadInterface
{
    private LoggerInterface $logger;

    private RequestStack $request;

    private TranslatorInterface $translator;


    private ParameterBagInterface $parameter;

    private Filesystem $filesystem;
    private MessageDispatchInterface $messageDispatch;
    private string $upload;


    public function __construct(
        #[Autowire('%kernel.project_dir%/public/upload/')] string $upload,
        LoggerInterface $logger,
        RequestStack $request,
        TranslatorInterface $translator,
        ParameterBagInterface $parameter,
        Filesystem $filesystem,
        MessageDispatchInterface $messageDispatch
    )
    {
        $this->logger = $logger;
        $this->request = $request;
        $this->translator = $translator;

        $this->parameter = $parameter;
        $this->filesystem = $filesystem;
        $this->messageDispatch = $messageDispatch;
        $this->upload = $upload;
    }


    public function upload(File|UploadedFile $file, UploadEntityInterface $entity): void
    {

        $name = md5_file($file->getPathname());

        if(empty($name))
        {
            throw new InvalidArgumentException(sprintf('Not found file in class %s', get_class($entity)));
        }


        /* Определяем директорию загрузки файла по названию таблицы */
        $uploadDir = $this->upload.$entity::TABLE.'/'.$name;

        /* Создаем директорию Для загрузки */
        $this->filesystem->mkdir($uploadDir);


        /* Перемещаем файл в директорию */
        try
        {
            /* Генерируем новое название файла с расширением */
            $newFilename = 'file.'.$file->guessExtension();

            if(!file_exists($uploadDir.'/'.$newFilename))
            {
                /* Перемещаем файл */
                $file->move(
                    $uploadDir,
                    $newFilename
                );
            }

            $extension = pathinfo($uploadDir.'/'.$newFilename, PATHINFO_EXTENSION);
            $size = filesize($file);
            $entity->updFile($name, $extension, $size);

            /* Отправляем событие в шину  */
            $this->messageDispatch->dispatch(
                message: new CDNUploadFileMessage($entity->getId(), get_class($entity), $name),
                transport: 'files-res'
            );

        }
        catch(FileException $e)
        {
            $this->logger->error($e->getMessage(), [__FILE__.':'.__LINE__]);
        }
    }

}