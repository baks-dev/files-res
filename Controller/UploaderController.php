<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Files\Resources\Controller;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Product\Repository\AllProducts\AllProductsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsController]
#[RoleSecurity('ROLE_USER')]
final class UploaderController // extends AbstractController
{
    /**
     * Контроллер загрузки файлов изображений
     */
    #[Route('/file/upload/image', name: 'file.uploader', methods: [
        'GET',
        'POST',
    ])]
    public function index(
        #[Autowire(env: 'CDN_HOST')] string $CDN_HOST,
        #[Autowire(env: 'CDN_USER')] string $CDN_USER,
        #[Autowire(env: 'CDN_PASS')] string $CDN_PASS,
        Request $request,
        HttpClientInterface $httpClient
    ): Response {

        /** @var UploadedFile $uploadFile */
        $uploadFile = $request->files->get('file');

        $uploadPathDir = 'files-res/'.md5_file($uploadFile->getPathname());

        $formFields = [
            'dir' => $uploadPathDir, // директория, в которую загружается файл
            'image' => DataPart::fromPath($uploadFile->getPathname()),
        ];

        /* Формируем заголовки файла и авторизации CDN */
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: Basic '.base64_encode($CDN_USER.':'.$CDN_PASS);


        /* Отправляем запрос на загрузку файла серверу CDN */
        $response = $httpClient->request(
            'POST',
            'https://'.$CDN_HOST.'/cdn/upload/image',
            [
                'headers' => $headers,
                'body' => $formData->bodyToString(),
            ]
        );

        if($response->getStatusCode() !== 200)
        {
            throw new RecoverableMessageHandlingException(sprintf('Error upload file CDN (%s)', $response->getContent()));
        }

        return new JsonResponse(['url' => 'https://'.$CDN_HOST.'/upload/'.$uploadPathDir.'/original.webp']);
    }
}
