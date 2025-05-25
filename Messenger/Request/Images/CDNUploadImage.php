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

namespace BaksDev\Files\Resources\Messenger\Request\Images;

use App\Kernel;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Table;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use ReflectionAttribute;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class CDNUploadImage
{
    /**
     * Конечная точка CDN для загрузки изображений
     */
    public const string PATH_IMAGE_CDN = '/cdn/upload/image';

    private HttpClientInterface $httpClient;

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Autowire(env: 'CDN_HOST')] $CDN_HOST,
        #[Autowire(env: 'CDN_USER')] $CDN_USER,
        #[Autowire(env: 'CDN_PASS')] $CDN_PASS,
        HttpClientInterface $httpClient,
    )
    {
        $options = new HttpOptions()
            ->setBaseUri(sprintf('https://%s', $CDN_HOST))
            ->setAuthBasic($CDN_USER, $CDN_PASS)
            ->toArray();

        $this->httpClient = $httpClient->withOptions($options);
    }


    public function __invoke(CDNUploadImageMessage $command): bool|string
    {

        $this->entityManager->clear();

        if(!class_exists($command->getEntity()))
        {
            throw new InvalidArgumentException(sprintf('Невозможно получить класс сущности %s', $command->getEntity()));
        }

        /* @var UploadEntityInterface $imgEntity */
        $imgEntity = $this->entityManager
            ->getRepository($command->getEntity())
            ->find($command->getId());

        /**
         * Если не найдена сущность, возможно она еще не сохранилась,
         * и её необходимо отпарить позже через комманду repack
         */
        if($imgEntity === null)
        {
            return false;
        }

        /** Выделяем из сущности название таблицы для директории загрузки файла */
        $ref = new ReflectionClass($imgEntity);

        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $TABLE = $current->getArguments()['name'] ?? 'images';


        /* Абсолютный путь к директории и файлу изображения */
        $uploadDir = implode(DIRECTORY_SEPARATOR, [$this->upload, 'public', 'upload', $TABLE, $command->getDir()]);
        $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'image.'.$imgEntity->getExt();

        /**
         * Если файл отсутствует локально - проверяем нет ли его на CDN
         */
        if(!file_exists($uploadFile))
        {
            $request = $this->httpClient->request(
                'GET',
                '/upload/'.$TABLE.'/'.$command->getDir().'/min.webp',
            );

            if($request->getStatusCode() === 200)
            {
                /* Обновляем сущность на CDN файла */
                $imgEntity->updCdn('webp');
                $this->entityManager->flush();

                /**
                 * Если на CDN найден файл - удаляем директорию с оригиналом файла
                 *
                 * @var SplFileInfo $info
                 */

                $directory = new RecursiveDirectoryIterator($uploadDir);

                foreach($directory as $info)
                {
                    if($info->isFile())
                    {
                        unlink($info->getRealPath());
                    }
                }

                rmdir($uploadDir);

                return true;
            }

            throw new RecoverableMessageHandlingException(sprintf('File Not found: %s', $uploadFile));
        }

        /* Указываем путь и название файла для загрузки CDN */
        $formFields = [
            'dir' => $TABLE.'/'.$command->getDir(),
            'image' => DataPart::fromPath($uploadFile),
        ];

        /* Формируем заголовки файла и авторизации CDN */
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();

        /**
         * Отправляем запрос на загрузку файла серверу CDN
         */
        $request = $this->httpClient->request(
            'POST',
            self::PATH_IMAGE_CDN,
            [
                'headers' => $headers,
                'body' => $formData->bodyToString(),
            ],
        );

        if($request->getStatusCode() !== 200)
        {
            throw new RecoverableMessageHandlingException(sprintf('Error upload file CDN (%s)', $request->getContent()));
        }

        /* Обновляем сущность на CDN файла */
        $imgEntity->updCdn('webp');

        $this->entityManager->flush();

        /** Удаляем оригинал и пустую директорию если файл загружен на CDN */

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
