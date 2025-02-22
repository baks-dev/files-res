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

namespace BaksDev\Files\Resources\Twig;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


final class ImagePathExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(env: 'CDN_HOST')] private readonly string $cdnHost,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cdn_image_path', [$this, 'imagePath'], ['is_safe' => ['html']]),
        ];
    }

    public function imagePath(
        ?string $name,
        ?string $ext,
        ?bool $cdn = false,
        ?string $size = 'small'
    ): string
    {
        if($name === null || $ext === null)
        {
            return '/assets/img/blank.svg';
        }

        if(false === $cdn)
        {
            $size = 'image';
        }

        if(true === $cdn)
        {
            $ext = 'webp';
        }

        if(false === in_array($size, ['image', 'original', 'large', 'medium', 'small', 'min'], true))
        {
            throw new InvalidArgumentException(sprintf('Invalid Argument size %s', $size));
        }


        $img_host = $cdn ? 'https://'.$this->cdnHost : '';
        $img_file = sprintf('/%s.%s', $size, $ext); //  (empty($img_host); //   ? '/image.' : '/small.').$ext;

        return sprintf('%s%s%s', $img_host, $name, $img_file);
    }

}