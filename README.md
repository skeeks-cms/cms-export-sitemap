SkeekS CMS cms-export-sitemap
===================================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist skeeks/cms-export-sitemap "*"
```

or add

```
"skeeks/cms-export-sitemap": "*"
```

Configuration app
----------

```php

'components' =>
[
    'cmsExport' => [
        'handlers'     =>
        [
            'skeeks\cms\exportSitemap\ExportSitemapHandler' =>
            [
                'class' => 'skeeks\cms\exportSitemap\ExportSitemapHandler'
            ]
        ]
    ],

    'i18n' => [
        'translations' =>
        [
            'skeeks/exportSitemap' => [
                'class'             => 'yii\i18n\PhpMessageSource',
                'basePath'          => '@skeeks/cms/exportSitemap/messages',
                'fileMap' => [
                    'skeeks/exportSitemap' => 'main.php',
                ],
            ]
        ]
    ]
]

```

##Links
* [Web site](http://en.cms.skeeks.com)
* [Web site (rus)](http://cms.skeeks.com)
* [Author](http://skeeks.com)
* [ChangeLog](https://github.com/skeeks-cms/cms-export-shop-yandex-market/blob/master/CHANGELOG.md)


___

> [![skeeks!](https://gravatar.com/userimage/74431132/13d04d83218593564422770b616e5622.jpg)](http://skeeks.com)  
<i>SkeekS CMS (Yii2) — quickly, easily and effectively!</i>  
[skeeks.com](http://skeeks.com) | [en.cms.skeeks.com](http://en.cms.skeeks.com) | [cms.skeeks.com](http://cms.skeeks.com) | [marketplace.cms.skeeks.com](http://marketplace.cms.skeeks.com)


