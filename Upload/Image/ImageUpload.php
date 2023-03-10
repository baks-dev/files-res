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

use BaksDev\Files\Resources\Messanger\Request\Images\Command;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImageUpload implements ImageUploadInterface
{
	
	private LoggerInterface $logger;
	
	private RequestStack $request;
	
	private TranslatorInterface $translator;
	
	private MessageBusInterface $bus;
	
	private ParameterBagInterface $parameter;
	
	private Filesystem $filesystem;
	
	
	public function __construct(
		LoggerInterface $logger,
		RequestStack $request,
		TranslatorInterface $translator,
		MessageBusInterface $bus,
		ParameterBagInterface $parameter,
		Filesystem $filesystem,
	)
	{
		$this->logger = $logger;
		$this->request = $request;
		$this->translator = $translator;
		$this->bus = $bus;
		$this->parameter = $parameter;
		$this->filesystem = $filesystem;
	}
	
	
	/**
	 * @param string $parameterUploadDir ???????????????? ?????????????????? ?? ???????????????????? ?????????????????????? ???????? ???????????????????? ???????????????? ?????????? ??????????????????????
	 * @param UploadedFile $file ???????????? ???????????????????????? ?????????? ??????????????????????
	 * @param UploadEntityInterface $entity
	 *
	 * @return void
	 * @throws Exception
	 */
	public function upload(UploadedFile $file, UploadEntityInterface $entity) : void
	{
		$name = uniqid('', false);
		$dirId = $entity->getUploadDir();
		
		//dump($dirId);
		
		if(empty($dirId))
		{
			throw new InvalidArgumentException(sprintf('Not found ID in class %s', get_class($entity)));
		}
		
		/* ???????????????????? ???????????????????? ???????????????? ?????????? ???? ???????????????? ?????????????? */
		$parameterUploadDir = $entity::TABLE;
		$uploadDir = $this->parameter->get($parameterUploadDir).$dirId;
		
		/* ?????????????? ???????????????????? ?????? ???????????????? */
		$this->filesystem->mkdir($uploadDir);
		
		/* ???????????????????? ???????? ?? ???????????????????? */
		try
		{
			
			/* ???????????????????? ?????????? ???????????????? ?????????? ?? ?????????????????????? */
			$newFilename = $name.'.'.$file->guessExtension();
			
			/* ???????????????????? ???????? */
			$move = $file->move(
				$uploadDir,
				$newFilename
			);
			
			/**
			 *  ?????????????????? ?? ???????????????? ?????????????????? ??????????
			 *  $name - ???????????????? ?????????? ?????? ????????????????????
			 */
			$entity->updFile($name, $move->getExtension(), $move->getSize());
			
			/* ?????????????? ???????????????? ???????????????? ?????????? CDN */
			//(object $id, string $entity, string $name, string $dir)
			
			$command = new Command($entity->getId(), get_class($entity), $newFilename, $dirId, $parameterUploadDir);
			$this->bus->dispatch($command);
			
		}
		catch(FileException $e)
		{
			$this->logger->error($e->getMessage());
			$this->request->getSession()->getFlashBag()->add(
				'danger',
				$name.": ".$this->translator->trans(
					'???????????? ?????? ????????????????'
				)
			);
		}
		
	}
	
}