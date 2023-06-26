# BaksDev Files Resources

![Version](https://img.shields.io/badge/version-6.3.0-blue) ![php 8.1+](https://img.shields.io/badge/php-min%208.1-red.svg)

Модуль файловых ресурсов

## Установка

``` bash
$ composer require baks-dev/files-res
```

## Настройки


Добавить диреткорию и установить права для загрзуки обложек категорий:

``` bash
$ sudo mkdir <path_to_project>/public/upload
$ sudo sudo chmod 773 <path_to_project>/public/upload
``` 

***

Для пережатия и конвертации файлов изображений в формат .webp устанавливаем на отдельный хост CDN-сервер (РЕКОМЕНДУЦИЯ! на отдельный домен!!!)


``` bash
$ composer create-project symfony/skeleton <project_name>
```

переходим в папку проекта CDN-ервера, устанавливаем и настроаиваем пакет ["Модуль CDN файловых ресурсов"](https://github.com/baks-dev/files-cdn)

***

После настройки запустить очередь из сообщений `async_files_resources`

``` bash
$ php bin/console messenger:consume async_files_resources --time-limit=3600
``` 


## Журнал изменений ![Changelog](https://img.shields.io/badge/changelog-yellow)

О том, что изменилось за последнее время, обратитесь к [CHANGELOG](CHANGELOG.md) за дополнительной информацией.

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.


