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

final readonly class CDNUploadImageMessage
{
    /**
     * Идентификатор сущности для обновления CDN (UID)
     */
    private object|string $id;

    /**
     * Директория загрузки файла
     */
    private string $dir;

    /**
     * Класс сущности
     */
    private string $entity;


    public function __construct(object|string $id, string $entity, string $dir)
    {
        $this->id = $id;
        $this->dir = $dir;
        $this->entity = $entity;
    }

    /**
     * Id
     */
    public function getId(): object|string
    {
        return $this->id;
    }

    /**
     * Dir
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * Entity
     */
    public function getEntity(): string
    {
        return $this->entity;
    }
}
