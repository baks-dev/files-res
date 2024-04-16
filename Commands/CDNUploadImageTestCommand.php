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

declare(strict_types=1);

namespace BaksDev\Files\Resources\Commands;


use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'baks:cdn:upload:image:test',
    description: 'Тест отправки изображения на сервер CDN'
)]
class CDNUploadImageTestCommand extends Command
{
    private string $CDN_HOST;
    private string $CDN_USER;
    private string $CDN_PASS;
    private HttpClientInterface $httpClient;
    private string $project;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $project,
        #[Autowire(env: 'CDN_HOST')] string $CDN_HOST,
        #[Autowire(env: 'CDN_USER')] string $CDN_USER,
        #[Autowire(env: 'CDN_PASS')] string $CDN_PASS,
        HttpClientInterface $httpClient,
    )
    {
        $this->project = $project;
        $this->httpClient = $httpClient;
        $this->CDN_HOST = $CDN_HOST;
        $this->CDN_USER = $CDN_USER;
        $this->CDN_PASS = $CDN_PASS;
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        /* Абсолютный путь к файлу изображения */
        $image = $this->project.'/public/assets/img/logo.webp';

        if(!file_exists($image))
        {
            $io->error('Не найден файл изображения');
            return Command::FAILURE;
        }

        /* Указываем путь и название файла для загрузки CDN */
        $formFields = [
            'dir' => 'test_upload/test_dir',
            'image' => DataPart::fromPath($image),
        ];

        /* Формируем заголовки файла и авторизации CDN */
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: Basic '.base64_encode($this->CDN_USER.':'.$this->CDN_PASS);

        /* Отправляем запрос на загрузку файла серверу CDN */
        $request = $this->httpClient->request(
            'POST',
            'https://'.$this->CDN_HOST.CDNUploadImage::PATH_IMAGE_CDN,
            [
                'headers' => $headers,
                'body' => $formData->bodyToString(),
            ]);


        if($request->getStatusCode() !== 200)
        {
            throw new RecoverableMessageHandlingException(sprintf('Error upload file CDN (%s)', $request->getContent()));
        }

        dump($request->getContent());

        return Command::SUCCESS;
    }
}
