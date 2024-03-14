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

namespace BaksDev\Files\Resources\Messenger\Request\Images;

final class CDNUploadImageMessage
{
	/**
	 * Идентификатор сущности для обновления CDN (UID)
	 */
	private readonly object $id;
	
	/**
	 * Директория загрузки файла
	 */
    private readonly string $dir;
	
	/**
	 * Класс сущности
	 */
    private readonly string $entity;


	public function __construct(object $id, string $entity, string $dir)
	{
		$this->id = $id;
		$this->dir = $dir;
		$this->entity = $entity;
	}

    /**
     * Id
     */
    public function getId(): object
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

