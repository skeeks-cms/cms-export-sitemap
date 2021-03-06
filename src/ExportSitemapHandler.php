<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.08.2016
 */

namespace skeeks\cms\exportSitemap;

use skeeks\cms\cmsWidgets\treeMenu\TreeMenuCmsWidget;
use skeeks\cms\export\ExportHandler;
use skeeks\cms\export\ExportHandlerFilePath;
use skeeks\cms\importCsv\handlers\CsvHandler;
use skeeks\cms\importCsv\helpers\CsvImportRowResult;
use skeeks\cms\importCsv\ImportCsvHandler;
use skeeks\cms\importCsvContent\widgets\MatchingInput;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentPropertyEnum;
use skeeks\cms\models\CmsSite;
use skeeks\cms\models\CmsTree;
use skeeks\cms\models\Tree;
use skeeks\cms\modules\admin\widgets\BlockTitleWidget;
use skeeks\cms\modules\admin\widgets\form\ActiveFormUseTab;
use skeeks\cms\relatedProperties\PropertyType;
use skeeks\cms\relatedProperties\propertyTypes\PropertyTypeElement;
use skeeks\cms\relatedProperties\propertyTypes\PropertyTypeList;
use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\widgets\formInputs\selectTree\SelectTree;
use skeeks\modules\cms\money\models\Currency;
use yii\base\Exception;
use yii\bootstrap\Alert;
use yii\console\Application;
use yii\data\Pagination;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\UrlNormalizer;
use yii\widgets\ActiveForm;

/**
 * @property string $rootSitemapsDir
 *
 * Class ExportSitemapHandler
 * @package skeeks\cms\exportSitemap
 */
class ExportSitemapHandler extends ExportHandler
{
    /**
     * @var null выгружаемый контент
     */
    public $content_ids = [];

    /**
     * @var null раздел и его подразделы попадут в выгрузку
     */
    public $tree_ids = [];

    /**
     * @var null базовый путь сайта
     */
    public $base_url = null;

    /**
     * @var null
     */
    public $sitemaps_dir = null;

    /**
     * @var string путь к результирующему файлу
     */
    public $file_path = '';

    /**
     * @var string
     */
    public $site_id = '';

    /**
     * @var int
     */
    public $max_urlsets = 2000;

    /**
     * @var int
     */
    public $min_date;

    /**
     * @var bool
     */
    public $is_only_active_elements = true;
    /**
     * @var bool
     */
    public $is_only_active_sections = true;


    public function init()
    {
        $this->name = \Yii::t('skeeks/exportSitemap', 'Sitemap.xml export');

        if (!$this->file_path) {
            $this->file_path = "/sitemap.xml";
        }

        if (!$this->sitemaps_dir) {
            $this->sitemaps_dir = "/export/sitemaps/";
        }

        if (!$this->base_url) {
            if (!\Yii::$app instanceof Application) {
                $this->base_url = Url::base(true);
            }
        }

        parent::init();
    }

    /**
     * Соответствие полей
     * @var array
     */
    public $matching = [];

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            ['content_ids', 'required'],
            ['content_ids', 'safe'],

            ['tree_ids', 'safe'],

            ['base_url', 'required'],
            ['base_url', 'url'],

            ['site_id', 'string'],
            ['sitemaps_dir', 'string'],

            ['max_urlsets', 'integer'],
            ['min_date', 'integer'],
            ['is_only_active_elements', 'boolean'],
            ['is_only_active_sections', 'boolean'],

        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_ids'             => \Yii::t('skeeks/exportShopYandexMarket', 'Контент'),
            'tree_ids'                => \Yii::t('skeeks/exportShopYandexMarket', 'Выгружаемые категории'),
            'base_url'                => \Yii::t('skeeks/exportShopYandexMarket', 'Базовый url'),
            'sitemaps_dir'            => \Yii::t('skeeks/exportShopYandexMarket', 'Папка частей sitemap'),
            'site_id'                 => \Yii::t('skeeks/exportShopYandexMarket', 'Сайт'),
            'max_urlsets'             => \Yii::t('skeeks/exportShopYandexMarket', 'Максимальное количество urlsets в одном файле'),
            'min_date'                => \Yii::t('skeeks/exportShopYandexMarket', 'Минимальная дата обновления ссылки'),
            'is_only_active_elements' => \Yii::t('skeeks/exportShopYandexMarket', 'Добавлять в карту только активные элементы'),
            'is_only_active_sections' => \Yii::t('skeeks/exportShopYandexMarket', 'Добавлять в карту только активные разделы'),
        ]);
    }
    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'default_delivery' => \Yii::t('skeeks/exportShopYandexMarket', 'Для всех товаров магазина, по умолчанию'),
            'sitemaps_dir'     => \Yii::t('skeeks/exportShopYandexMarket', 'В случае большого sitemap.xml файла, он будет разделен на кусочки и эти кусочки будут лежать в этой папке'),
            'min_date'         => \Yii::t('skeeks/exportShopYandexMarket',
                'Если будет задан этот параметр, то ни в одной ссылке не будет указано даты обновления меньше этой. Используется для переиндексации всех страниц.'),
        ]);
    }

    /**
     * @param ActiveFormUseTab $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo BlockTitleWidget::widget([
            'content' => 'Общий настройки',
        ]);

        echo $form->field($this, 'sitemaps_dir');
        echo $form->field($this, 'base_url');

        echo $form->field($this, 'site_id')->listBox(ArrayHelper::merge([null => ' - '],
            ArrayHelper::map(CmsSite::find()->all(), 'code', 'name')
        ), ['size' => 1]);

        echo $form->fieldSelectMulti($this, 'content_ids', array_merge(['' => ' - '], CmsContent::getDataForSelect()));

        echo $form->field($this, 'tree_ids')->widget(
            SelectTree::className(),
            [
                'mode' => SelectTree::MOD_MULTI,
            ]
        );

        echo $form->field($this, 'max_urlsets');
        echo $form->field($this, 'is_only_active_elements')->checkbox();
        echo $form->field($this, 'is_only_active_sections')->checkbox();

        echo $form->field($this, 'min_date')->widget(\kartik\datecontrol\DateControl::classname(), [
            'type' => \kartik\datecontrol\DateControl::FORMAT_DATETIME,
        ]);
    }

    protected $_indexFiles = [];

    public function export()
    {
        //TODO: if console app
        \Yii::$app->urlManager->baseUrl = $this->base_url;
        \Yii::$app->urlManager->scriptUrl = $this->base_url;

        ini_set("memory_limit", "8192M");
        set_time_limit(0);

        //Создание дирректории
        if ($dirName = dirname($this->rootFilePath)) {
            $this->result->stdout("Корневая директория: {$dirName}\n");

            if (!is_dir($dirName) && !FileHelper::createDirectory($dirName)) {
                throw new Exception("Не удалось создать директорию для файла");
            }
        }

        $query = Tree::find()
            ->orderBy(['level' => SORT_ASC, 'priority' => SORT_ASC]);

        if ($this->is_only_active_sections) {
            $query->active();
        }

        if ($this->site_id) {
            $query->where(['cms_site_id' => $this->site_id]);
        }
        $trees = $query->all();

        $result = [];

        $this->result->stdout("\tСоздание файла siemap для разделов\n");

        $this->_indexFiles = [];

        if ($trees) {
            /**
             * @var Tree $tree
             */
            foreach ($trees as $tree) {
                if (!$tree->redirect && !$tree->redirect_tree_id) {
                    $result[] = [
                        "loc"     => $tree->url,
                        "lastmod" => $this->_lastMod($tree),
                    ];
                }
            }

            $publicUrl = $this->generateSitemapFile('tree.xml', $result);
            $this->result->stdout("\tФайл успешно сгенерирован: {$publicUrl}\n");

            $this->_indexFiles[] = $publicUrl;
        }

        if ($this->content_ids) {
            $this->result->stdout("\tЭкспорт контента\n");

            foreach ($this->content_ids as $contentId) {
                $content = CmsContent::findOne($contentId);
                $files = $this->_exportContent($content);
            }
        }

        $this->_exportAdditional();

        if ($this->_indexFiles) {
            $this->result->stdout("\tГенерация sitemap\n");

            $data = [];
            foreach ($this->_indexFiles as $file) {
                $data[] = [
                    "loc"     => $file,
                    "lastmod" => $this->_lastMod(new Tree(['updated_at' => time()])),
                ];
            }

            $sitemapContent = \Yii::$app->view->render('@skeeks/cms/exportSitemap/views/sitemapindex', [
                'data' => $data,
            ]);

            $fp = fopen($this->rootFilePath, 'w');
            // записываем в файл текст
            fwrite($fp, $sitemapContent);
            // закрываем
            fclose($fp);

            if (!file_exists($this->rootFilePath)) {
                throw new Exception("\t\tНе удалось создать файл");
            }
        }

        return $this->result;
    }

    protected function _exportAdditional()
    {
        return $this;
    }


    /**
     * @param ActiveQuery $query
     * @param string      $name
     * @param null        $eachCallback
     * @return $this
     */
    protected function _exportByQuery(ActiveQuery $query, $name = 'auto', $eachCallback = null)
    {
        $countQuery = clone $query;
        $total = $countQuery->count();

        $pages = new Pagination([
            'totalCount'      => $total,
            'defaultPageSize' => $this->max_urlsets,
            'pageSizeLimit'   => [1, $this->max_urlsets],
        ]);

        $this->result->stdout("\t\t\t Elements = {$total}\n");
        $this->result->stdout("\t\t\t Max Urlsets = {$this->max_urlsets}\n");
        $this->result->stdout("\t\t\t PageCount = {$pages->pageCount}\n");

        $i = 0;
        for ($i >= 0; $i < $pages->pageCount; $i++) {
            $pages->setPage($i);

            $this->result->stdout("\t\t\t\t Page = {$i}\n");
            $this->result->stdout("\t\t\t\t Offset = {$pages->offset}\n");
            $this->result->stdout("\t\t\t\t limit = {$pages->limit}\n");

            $result = [];
            foreach ($query->offset($pages->offset)->limit($pages->limit)->each(200) as $element) {
                if ($eachCallback && is_callable($eachCallback)) {
                    $result[] = $eachCallback($element);
                } else {
                    $result[] = [
                        "loc"     => $element->absoluteUrl,
                        "lastmod" => $this->_lastMod($element),
                    ];
                }

            }

            $publicUrl = $this->generateSitemapFile("{$name}_page{$i}.xml", $result);
            $this->result->stdout("\tФайл успешно сгенерирован: {$publicUrl}\n");
            $this->_indexFiles[] = $publicUrl;
        }

        return $this;
    }

    /**
     * @param CmsContent $cmsContent
     * @return ExportSitemapHandler
     */
    protected function _exportContent(CmsContent $cmsContent)
    {
        $this->result->stdout("\t\t {$cmsContent->name}\n");

        $query = CmsContentElement::find()
            ->where(['content_id' => $cmsContent->id])
            ->orderBy(['published_at' => SORT_DESC]);

        if ($this->is_only_active_elements) {
            $query->active();
        }

        return $this->_exportByQuery($query, 'content_'.$cmsContent->id);
    }

    /**
     * @param $sitemapFileName
     * @param $data
     * @return bool|string
     * @throws Exception
     */
    protected function generateSitemapFile($sitemapFileName, $data)
    {
        $rootFilePath = $this->rootSitemapsDir."/".$sitemapFileName;
        $rootFilePath = FileHelper::normalizePath($rootFilePath);

        //Создание дирректории
        if ($dirName = dirname($rootFilePath)) {
            $this->result->stdout("\t\tПапка: {$dirName}\n");

            if (!is_dir($dirName) && !FileHelper::createDirectory($dirName)) {
                throw new Exception("Не удалось создать директорию для файла");
            }
        }

        $this->result->stdout("\t\tГенерация файла: {$rootFilePath}\n");

        $treeSitemapContent = \Yii::$app->view->render('@skeeks/cms/exportSitemap/views/urlsets', [
            'data' => $data,
        ]);

        $fp = fopen($rootFilePath, 'w');
        // записываем в файл текст
        fwrite($fp, $treeSitemapContent);
        // закрываем
        fclose($fp);

        if (!file_exists($rootFilePath)) {
            throw new Exception("\t\tНе удалось создать файл");
        }

        return $this->base_url.FileHelper::normalizePath($this->sitemaps_dir."/".$sitemapFileName);
    }

    /**
     * @return bool|string
     */
    public function getRootSitemapsDir()
    {
        return \Yii::getAlias($this->alias.$this->sitemaps_dir);
    }
    /**
     * @param $model
     * @return false|string
     */
    protected function _lastMod($model)
    {
        $string = date("c", $model->updated_at);

        if ($this->min_date && $this->min_date > $model->updated_at) {
            $string = date("c", $this->min_date);
        }

        return $string;
    }

}
