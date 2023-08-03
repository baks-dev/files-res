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

namespace BaksDev\Files\Resources\Messanger\Request\File;

final class CDNUploadFileMessage
{
	/**
	 * Идентификатор сущности для обновления CDN (UID)
	 */
	public readonly object $id;
	
	/**
	 * Идентификатор сущности ля обновления CDN (UID)
	 */
	public readonly object $dir;
	
	/**
	 * Класс сущности
	 */
	public readonly string $entity;
	
	/**
     * Название файла
	 */
	public readonly string $name;

	public function __construct(object $id, string $entity, string $name, object $dir)
	{
		$this->id = $id;
		$this->name = $name;
		$this->dir = $dir;
		$this->entity = $entity;
	}
}

