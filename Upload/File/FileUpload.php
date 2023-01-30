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

use BaksDev\Files\Resources\Messanger\Request\Images\Command;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FileUpload implements FileUploadInterface
{
	private LoggerInterface $logger;
	
	private RequestStack $request;
	
	private TranslatorInterface $translator;
	
	private MessageBusInterface $bus;
	
	private ParameterBagInterface $parameter;
	
	
	public function __construct(
		LoggerInterface $logger,
		RequestStack $request,
		TranslatorInterface $translator,
		MessageBusInterface $bus,
		ParameterBagInterface $parameter,
	)
	{
		$this->logger = $logger;
		$this->request = $request;
		$this->translator = $translator;
		$this->bus = $bus;
		$this->parameter = $parameter;
	}
	
	
	public function upload(string $parameterUploadDir, UploadedFile $file, UploadEntityInterface $entity) : void
	{
		$name = uniqid('', false);
		
		//        /* Получаем название директории по классу */
		//        $entityDir = explode('Entity', get_class($entity));
		//        $entityDir = str_replace('\\', '/', strtolower($entityDir[1]));
		
		//$dirId = null;
		
		//        if(method_exists($entity, 'getEvent'))
		//        {
		//            $dirId = $entity->getEvent()->getId();
		//        }
		//        else if(method_exists($entity, 'getId'))
		//        {
		//            $dirId = $entity->getId();
		//        }
		
		$dirId = $entity->getUploadDir();
		
		if(empty($dirId))
		{
			throw new \RuntimeException(sprintf('Not found ID in class %s', get_class($entity)));
		}
		
		/* Перемещаем файл в директорию */
		try
		{
			/* Генерируем новое название файла с расширением */
			$newFilename = $name.'.'.$file->guessExtension();
			
			/* Перемещаем файл */
			$move = $file->move(
				$this->parameter->get($parameterUploadDir).$dirId,
				$newFilename
			);
			
			/**
			 *  Применяем к сущности параметры файла
			 *  $name - название файла без расширения
			 */
			$entity->updFile($name, $move->getExtension(), $move->getSize());
			
			/* Создаем комманду отправки файла CDN */
			$command = new Command($dirId, get_class($entity), $newFilename, $parameterUploadDir);
			$this->bus->dispatch($command);
			
		}
		catch(FileException $e)
		{
			$this->logger->error($e->getMessage());
			$this->request->getSession()->getFlashBag()->add(
				'danger',
				$name.": ".$this->translator->trans(
					'error.product.upload.photo',
					domain: 'product.product'
				)
			);
		}
	}
	
}