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

namespace BaksDev\Files\Resources\Messanger\Request\Images;

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
	/** Конечная точка CDN для загрузки файла */
	private const PATH_IMAGE_CDN = '/cdn/upload/image';
	
	private EntityManagerInterface $entityManager;
	
	private HttpClientInterface $httpClient;
	
	private ParameterBagInterface $parameter;
	
	
	public function __construct(
		EntityManagerInterface $entityManager,
		HttpClientInterface $httpClient,
		ParameterBagInterface $parameter,
	)
	{
		$this->entityManager = $entityManager;
		$this->httpClient = $httpClient;
		$this->parameter = $parameter;
	}
	
	
	public function handle(Command $command) : bool|string
	{
		/* @var UploadEntityInterface $imgEntity */
		$imgEntity = $this->entityManager->getRepository($command->entity)->find($command->id);
		
		if($imgEntity === null)
		{
			throw new RecoverableMessageHandlingException('Error get Entity ID:'.$command->id);
		}
		
		/* Абсолютный путь к файлу изображения */
		$uploadFile = $this->parameter->get($command->path).$command->dir.'/'.$command->name;
		
		if(!file_exists($uploadFile))
		{
			throw new RecoverableMessageHandlingException(sprintf('File Not found: %s', $uploadFile));
		}
		
		/* Указываем путь и название файла для загрузки CDN */
		$formFields = [
			'path' => $command->path,
			'dir' => (string) $command->dir,
			'image' => DataPart::fromPath($uploadFile),
		];
		
		/* Формируем заголовки файла и авторизации CDN */
		$formData = new FormDataPart($formFields);
		$headers = $formData->getPreparedHeaders()->toArray();
		$headers[] = 'Authorization: Basic '.base64_encode($this->parameter->get('cdn.user'
				).':'.$this->parameter->get('cdn.pass')
			);
		
		$request = $this->httpClient->request('POST', $this->parameter->get('cdn.host').self::PATH_IMAGE_CDN, [
			'headers' => $headers,
			'body' => $formData->bodyToString(),
		]);
		
		if($request->getStatusCode() !== 200)
		{
			throw new RecoverableMessageHandlingException('Error upload file CDN');
		}
		
		/* Обновляем сущность на CDN файла */
		$imgEntity->updCdn('webp');
		$this->entityManager->flush();
		
		return true;
	}
	
}