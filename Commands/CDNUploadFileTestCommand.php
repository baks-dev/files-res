<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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


use BaksDev\Files\Resources\Messenger\Request\File\CDNUploadFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'baks:cdn:upload:file:test',
    description: 'Тест отправки файла на сервер CDN'
)]
class CDNUploadFileTestCommand extends Command
{
    private HttpClientInterface $httpClient;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $project,
        #[Autowire(env: 'CDN_HOST')] string $CDN_HOST,
        #[Autowire(env: 'CDN_USER')] string $CDN_USER,
        #[Autowire(env: 'CDN_PASS')] string $CDN_PASS,
        #[Autowire(env: 'APP_VERSION')] private readonly string $version,
        HttpClientInterface $httpClient,
    )
    {
        $options = new HttpOptions()
            ->setBaseUri(sprintf('https://%s', $CDN_HOST))
            ->setAuthBasic($CDN_USER, $CDN_PASS)
            ->toArray();

        $this->httpClient = $httpClient->withOptions($options);

        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        /* Абсолютный путь к файлу изображения */
        $image = $this->project.'/public/assets/'.$this->version.'/img/logo.webp';

        if(!file_exists($image))
        {
            $io->error('Не найден файл изображения');
            return Command::FAILURE;
        }

        /* Указываем путь и название файла для загрузки CDN */
        $formFields = [
            'dir' => 'test_upload/test_dir',
            'file' => DataPart::fromPath($image),
        ];

        /* Формируем заголовки файла и авторизации CDN */
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();

        /* Отправляем запрос на загрузку файла серверу CDN */
        $request = $this->httpClient->request(
            'POST',
            CDNUploadFile::PATH_FILE_CDN,
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
