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
use skeeks\cms\models\CmsTree;
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
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/**
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
     * @var string путь к результирующему файлу
     */
    public $file_path = '';


    public function init()
    {
        $this->name = \Yii::t('skeeks/exportSitemap', 'Sitemap.xml export');

        if (!$this->file_path)
        {
            $this->file_path = "/sitemap.xml";
        }

        if (!$this->base_url)
        {
            if (!\Yii::$app instanceof Application)
            {
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

            ['content_ids' , 'required'],
            ['content_ids' , 'safe'],

            ['tree_ids' , 'safe'],

            ['base_url' , 'required'],
            ['base_url' , 'url'],

        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_ids'        => \Yii::t('skeeks/exportShopYandexMarket', 'Контент'),
            'tree_ids'        => \Yii::t('skeeks/exportShopYandexMarket', 'Выгружаемые категории'),
            'base_url'        => \Yii::t('skeeks/exportShopYandexMarket', 'Базовый url'),
        ]);
    }
    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'default_delivery'        => \Yii::t('skeeks/exportShopYandexMarket', 'Для всех товаров магазина, по умолчанию'),
        ]);
    }

    /**
     * @param ActiveFormUseTab $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo BlockTitleWidget::widget([
            'content' => 'Общий настройки'
        ]);

        echo $form->field($this, 'base_url');

        echo $form->fieldSelectMulti($this, 'content_ids', array_merge(['' => ' - '], CmsContent::getDataForSelect()));

        echo $form->field($this, 'tree_ids')->widget(
            SelectTree::className(),
            [
                'mode' => SelectTree::MOD_MULTI
            ]
        );

    }

    public function export()
    {
        //TODO: if console app
        \Yii::$app->urlManager->baseUrl     = $this->base_url;
        \Yii::$app->urlManager->scriptUrl   = $this->base_url;

        ini_set("memory_limit","8192M");
        set_time_limit(0);

        //Создание дирректории
        if ($dirName = dirname($this->rootFilePath))
        {
            $this->result->stdout("Создание дирректории\n");

            if (!is_dir($dirName) && !FileHelper::createDirectory($dirName))
            {
                throw new Exception("Не удалось создать директорию для файла");
            }
        }



        $trees = Tree::find()->where(['site_code' => \Yii::$app->cms->site->code])
            ->orderBy(['level' => SORT_ASC, 'priority' => SORT_ASC])
            ->all();

        if ($trees)
        {
            /**
             * @var Tree $tree
             */
            foreach ($trees as $tree)
            {
                if (!$tree->redirect)
                {
                    $result[] =
                    [
                        "loc"           => $tree->url,
                        "lastmod"       => $this->_lastMod($tree),
                    ];
                }
            }
        }


        return $this->result;
    }

}