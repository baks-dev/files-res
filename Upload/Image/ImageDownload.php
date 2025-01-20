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
use Doctrine\ORM\Mapping\Table;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ImageDownload
{

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('filesResLogger')] private LoggerInterface $logger,
        private HttpClientInterface $client,
        private Filesystem $filesystem,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function get(string $url, UploadEntityInterface $entity): void
    {

        $upload = implode(DIRECTORY_SEPARATOR, [$this->upload, 'public', 'upload']);

        $parse_url = parse_url($url);
        $originalFilename = pathinfo($parse_url['path']);
        $name = md5($url);
        $newFilename = $name.'.'.$originalFilename['extension'];

        $dirId = $entity->getUploadDir();

        if(empty($dirId))
        {
            throw new InvalidArgumentException(sprintf('Not found ID in class %s', get_class($entity)));
        }

        /** Определяем название директор из таблицы */
        $ref = new ReflectionClass($entity);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $TABLE = $current->getArguments()['name'] ?? 'images';

        /** Абсолютный путь директории загрузки файла по названию таблицы и дайджеста */
        $uploadDir = implode(DIRECTORY_SEPARATOR, [$upload, $TABLE, $dirId]);

        /** Абсолютный путь к файлу с названием */
        $path = implode(DIRECTORY_SEPARATOR, [$uploadDir, $newFilename]);

        /* Создаем директорию Для загрузки */
        $this->filesystem->mkdir($uploadDir);

        /* Если файла не существует - скачиваем */
        if(!file_exists($path))
        {
            /* Создаем директорию для загрузки */
            if(!file_exists($uploadDir) && !mkdir($uploadDir) && !is_dir($uploadDir))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
            }

            $response = $this->client->request('GET', $url);

            if($response->getStatusCode() !== 200)
            {
                $error = sprintf('%s: Ошибка при загрузке файла', $url);
                $this->logger->error($error);

                return;
            }

            // получить содержимое ответа и сохранить их в файл
            $fileHandler = fopen($path, 'wb');
            foreach($this->client->stream($response) as $chunk)
            {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);

            /* Вычисляем размер файла */
            $fileSize = filesize($path);

            /*
             * Применяем к сущности параметры файла
             * $name - название файла без расширения
             */
            $entity->updFile($name, $originalFilename['extension'], $fileSize);

            /* Отправляем событие в шину  */
            $this->messageDispatch->dispatch(
                message: new CDNUploadImageMessage($entity->getId(), get_class($entity), $newFilename, $dirId),
                transport: 'files-res'
            );
        }
    }
}
