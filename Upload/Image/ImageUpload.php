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

use BaksDev\Core\Services\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messanger\Request\Images\Command;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImageUpload implements ImageUploadInterface
{
	
	private LoggerInterface $logger;
	
	private RequestStack $request;
	
	private TranslatorInterface $translator;
	
	private ParameterBagInterface $parameter;
	
	private Filesystem $filesystem;
    private MessageDispatchInterface $messageDispatch;


    public function __construct(
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
    }
	
	
	/**
	 * @param string $parameterUploadDir Название параметра в контейнере абсолютного пути директории загрузки файла изображение
	 * @param UploadedFile $file Объект загружаемого файла изображения
	 * @param UploadEntityInterface $entity
	 *
	 * @return void
	 * @throws Exception
	 */
	public function upload(File|UploadedFile $file, UploadEntityInterface $entity) : void
	{
		$name = uniqid('', false);
		$dirId = $entity->getUploadDir();
		
		if(empty($dirId))
		{
			throw new InvalidArgumentException(sprintf('Not found ID in class %s', get_class($entity)));
		}
		
		/* Определяем директорию загрузки файла по названию таблицы */
		$parameterUploadDir = $entity::TABLE;
		$uploadDir = $this->parameter->get($parameterUploadDir).$dirId;
		
		/* Создаем директорию Для загрузки */
		$this->filesystem->mkdir($uploadDir);
		
		/* Перемещаем файл в директорию */
		try
		{
			/* Генерируем новое название файла с расширением */
			$newFilename = $name.'.'.$file->guessExtension();
			
			
			/* Перемещаем файл */
			$move = $file->move(
				$uploadDir,
				$newFilename
			);
			
			/**
			 *  Применяем к сущности параметры файла
			 *  $name - название файла без расширения
			 */
			$entity->updFile($name, $move->getExtension(), $move->getSize());



            /* Отправляем событие в шину  */
            $this->messageDispatch->dispatch(
                message: new Command($entity->getId(), get_class($entity), $newFilename, $dirId, $parameterUploadDir),
                transport: 'resources'
            );

			
		}
		catch(FileException $e)
		{
			$this->logger->error($e->getMessage());
			$this->request->getSession()->getFlashBag()->add(
				'danger',
				$name.": ".$this->translator->trans(
					'error.upload.file',
					domain: 'files.res'
				)
			);
		}
		
	}
	
}