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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Handler
{
	public const PATH_FILE_CDN = '/cdn/upload/file';
	
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
		$uploadDir = $this->parameter->get($command->path).$command->dir;
		$uploadFile = $uploadDir.'/'.$command->name;
		
		if(!file_exists($uploadFile))
		{
			throw new RecoverableMessageHandlingException(sprintf('File Not found: %s', $uploadFile));
		}
		
		/* Указываем путь и название файла для загрузки CDN */
		$formFields = [
			'path' => $command->path,
			'dir' => (string) $command->dir,
			'file' => DataPart::fromPath($uploadFile),
		];
		
		/* Формируем заголовки файла и авторизации CDN */
		$formData = new FormDataPart($formFields);
		$headers = $formData->getPreparedHeaders()->toArray();
		$headers[] = 'Authorization: Basic '.base64_encode($this->parameter->get('cdn.user'
				).':'.$this->parameter->get('cdn.pass')
			);
		
		$request = $this->httpClient->request('POST', $this->parameter->get('cdn.host').self::PATH_FILE_CDN, [
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
		if (!is_readable($dir)) return null;
		return (count(scandir($dir)) === 2);
	}
	
}