<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Files\Resources\Messenger\Request\File;

use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Table;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class CDNUploadFile
{
    /**
     * Конечная точка CDN для загрузки файла
     */
    public const string PATH_FILE_CDN = '/cdn/upload/file';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Autowire(env: 'CDN_HOST')] private string $CDN_HOST,
        #[Autowire(env: 'CDN_USER')] private string $CDN_USER,
        #[Autowire(env: 'CDN_PASS')] private string $CDN_PASS,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
    ) {}

    public function __invoke(CDNUploadFileMessage $command): bool|string
    {
        $this->entityManager->clear();

        /* @var UploadEntityInterface $imgEntity */
        $imgEntity = $this->entityManager->getRepository($command->getEntity())->find($command->getId());

        if($imgEntity === null)
        {
            throw new RecoverableMessageHandlingException('Error get Entity ID:'.$command->getId());
        }


        $ref = new ReflectionClass($imgEntity);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $TABLE = $current->getArguments()['name'] ?? 'images';


        /* Абсолютный путь к директории файлу изображения */
        $uploadDir = implode(DIRECTORY_SEPARATOR, [$this->upload, 'public', 'upload', $TABLE, $command->getDir()]);
        $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'file.'.$imgEntity->getExt();


        if(!file_exists($uploadFile))
        {
            throw new RecoverableMessageHandlingException(sprintf('File Not found: %s', $uploadFile));
        }

        /* Указываем путь и название файла для загрузки CDN */
        $formFields = [
            'dir' => $command->getDir(),
            'file' => DataPart::fromPath($uploadFile),
        ];


        /* Формируем заголовки файла и авторизации CDN */
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: Basic '.base64_encode($this->CDN_USER.':'.$this->CDN_PASS);

        $request = $this->httpClient->request(
            'POST',
            'https://'.$this->CDN_HOST.self::PATH_FILE_CDN,
            [
                'headers' => $headers,
                'body' => $formData->bodyToString(),
            ]
        );

        if($request->getStatusCode() !== 200)
        {
            throw new RecoverableMessageHandlingException(sprintf('Error upload file CDN (%s)', $request->getContent()));
        }

        /* Обновляем сущность на CDN файла */
        $imgEntity->updCdn();
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
        if(!is_readable($dir))
        {
            return null;
        }
        return (count(scandir($dir)) === 2);
    }
}
