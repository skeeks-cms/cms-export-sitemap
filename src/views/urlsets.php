<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 08.11.2016
 */
echo <<<HTML
<?xml version="1.0" encoding="UTF-8"?>\n
HTML;
?>
<!--	Created by <?= \Yii::$app->cms->descriptor->name; ?>    -->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<? foreach($data as $item) : ?>
<url>
    <loc><?= $item['loc']; ?></loc>
    <? if (isset($item['lastmod'])) : ?><lastmod><?= $item['lastmod']; ?></lastmod><? endif; ?>
</url>
<? endforeach; ?>
</urlset>
