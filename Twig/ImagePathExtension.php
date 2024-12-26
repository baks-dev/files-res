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

namespace BaksDev\Files\Resources\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


final class ImagePathExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(env: 'CDN_HOST')] private readonly string $cdnHost,
    ) {}

    public function getFunctions()
    {
        return [
            new TwigFunction('cdn_image_path', [$this, 'imagePath'], ['is_safe' => ['html']]),
        ];
    }

    public function imagePath(
        ?string $img_name,
        ?string $img_ext,
        bool $img_cdn = false
    ): string
    {
        if($img_name === null || $img_ext === null)
        {
            return '/assets/img/blank.svg';
        }

        $img_host = $img_cdn ? 'https://'.$this->cdnHost : '';

        $img_file = (empty($img_host) ? '/image.' : '/small.').$img_ext;

        return sprintf('%s%s%s', $img_host, $img_name, $img_file);
    }

}