<?php
return [

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
];