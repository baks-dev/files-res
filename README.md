# BaksDev Files Resources

[![Version](https://img.shields.io/badge/version-7.2.9-blue)](https://github.com/baks-dev/files-res/releases)
![php 8.4+](https://img.shields.io/badge/php-min%208.4-red.svg)
[![packagist](https://img.shields.io/badge/packagist-green)](https://packagist.org/packages/baks-dev/files-res)

Модуль файловых ресурсов

## Установка

``` bash
$ composer require baks-dev/files-res
```

## Настройки

Добавить директорию и установить права для загрузки обложек категорий:

``` bash
$ sudo mkdir <path_to_project>/public/upload
$ chown -R unit:unit <path_to_project>/public/upload

``` 

***

Для пережатия и конвертации файлов изображений в формат .webp устанавливаем на отдельный хост CDN-сервер (РЕКОМЕНДУЦИЯ!
на отдельный домен!!!)

``` bash
$ composer create-project symfony/skeleton <project_name>
```

Переходим в папку проекта CDN cервера, устанавливаем и настраиваем
пакет ["Модуль CDN файловых ресурсов"](https://github.com/baks-dev/files-cdn)

***

Для асинхронной обработки запустить очередь из сообщений `resources`

``` bash
$ php bin/console messenger:consume async_files_resources --time-limit=3600
``` 

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

