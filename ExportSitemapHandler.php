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
    public $content_id = null;

    /**
     * @var null раздел и его подразделы попадут в выгрузку
     */
    public $tree_id = null;

    /**
     * @var null базовый путь сайта
     */
    public $base_url = null;

    /**
     * @var string путь к результирующему файлу
     */
    public $file_path = '';


    /**
     * @var string
     */
    public $shop_name = '';

    /**
     * @var string
     */
    public $shop_company = '';


    /**
     * @var string
     */
    public $vendor = '';

    /**
     * @var string
     */
    public $vendor_code = '';

    public $filter_property = '';
    public $filter_property_value = '';

    public function init()
    {
        $this->name = \Yii::t('skeeks/exportSitemap', 'Sitemap.xml export');

        if (!$this->file_path)
        {
            $rand = \Yii::$app->formatter->asDate(time(), "Y-M-d") . "-" . \Yii::$app->security->generateRandomString(5);
            $this->file_path = "/export/sitemap/content-{$rand}.xml";
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
     * @return null|CmsContent
     */
    public function getCmsContent()
    {
        if (!$this->content_id)
        {
            return null;
        }

        return CmsContent::findOne($this->content_id);
    }



    /**
     * Соответствие полей
     * @var array
     */
    public $matching = [];

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            ['content_id' , 'required'],
            ['content_id' , 'integer'],

            ['tree_id' , 'required'],
            ['tree_id' , 'integer'],

            ['base_url' , 'required'],
            ['base_url' , 'url'],

            ['vendor' , 'string'],
            ['vendor_code' , 'string'],

            ['shop_name' , 'string'],
            ['shop_company' , 'string'],

            ['default_sales_notes' , 'string'],

            ['default_pickup' , 'string'],
            ['default_store' , 'string'],
            ['default_delivery' , 'string'],

            ['filter_property' , 'string'],
            ['filter_property_value' , 'string'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_id'        => \Yii::t('skeeks/exportShopYandexMarket', 'Контент'),
            'tree_id'        => \Yii::t('skeeks/exportShopYandexMarket', 'Выгружаемые категории'),
            'base_url'        => \Yii::t('skeeks/exportShopYandexMarket', 'Базовый url'),
            'vendor'        => \Yii::t('skeeks/exportShopYandexMarket', 'Производитель или бренд'),
            'vendor_code'        => \Yii::t('skeeks/exportShopYandexMarket', 'Артикул производителя'),
            'shop_name'        => \Yii::t('skeeks/exportShopYandexMarket', 'Короткое название магазина'),
            'shop_company'        => \Yii::t('skeeks/exportShopYandexMarket', 'Полное наименование компании, владеющей магазином'),

            'default_sales_notes'        => \Yii::t('skeeks/exportShopYandexMarket', 'вариантах оплаты, описания акций и распродаж '),
            'default_delivery'        => \Yii::t('skeeks/exportShopYandexMarket', 'Возможность курьерской доставки'),
            'default_pickup'        => \Yii::t('skeeks/exportShopYandexMarket', 'Возможность самовывоза из пунктов выдачи'),
            'default_store'        => \Yii::t('skeeks/exportShopYandexMarket', 'Возможность купить товар в розничном магазине'),

            'filter_property'        => \Yii::t('skeeks/exportShopYandexMarket', 'Признак выгрузки'),
            'filter_property_value'        => \Yii::t('skeeks/exportShopYandexMarket', 'Значение'),
        ]);
    }
    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'shop_name'        => \Yii::t('skeeks/exportShopYandexMarket', 'Короткое название магазина, должно содержать не более 20 символов. В названии нельзя использовать слова, не имеющие отношения к наименованию магазина, например «лучший», «дешевый», указывать номер телефона и т. п.
Название магазина должно совпадать с фактическим названием магазина, которое публикуется на сайте. При несоблюдении данного требования наименование может быть изменено Яндекс.Маркетом самостоятельно без уведомления магазина.'),
            'shop_company'        => \Yii::t('skeeks/exportShopYandexMarket', 'Полное наименование компании, владеющей магазином. Не публикуется, используется для внутренней идентификации.'),
            'default_delivery'        => \Yii::t('skeeks/exportShopYandexMarket', 'Для всех товаров магазина, по умолчанию'),
            'default_pickup'        => \Yii::t('skeeks/exportShopYandexMarket', 'Для всех товаров магазина, по умолчанию'),
            'default_store'        => \Yii::t('skeeks/exportShopYandexMarket', 'Для всех товаров магазина, по умолчанию'),
            'default_sales_notes'        => \Yii::t('skeeks/exportShopYandexMarket', 'Элемент используется для отражения информации о:
 минимальной сумме заказа, минимальной партии товара, необходимости предоплаты (указание элемента обязательно);
 вариантах оплаты, описания акций и распродаж (указание элемента необязательно).
Допустимая длина текста в элементе — 50 символов. .'),
        ]);
    }

    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo BlockTitleWidget::widget([
            'content' => 'Общий настройки магазина'
        ]);

        echo $form->field($this, 'base_url');
        echo $form->field($this, 'shop_name');
        echo $form->field($this, 'shop_company');

        echo $form->field($this, 'content_id')->listBox(
            array_merge(['' => ' - '], CmsContent::getDataForSelect(true, function(ActiveQuery $activeQuery)
            {
                $activeQuery->andWhere([
                    'id' => \yii\helpers\ArrayHelper::map(\skeeks\cms\shop\models\ShopContent::find()->all(), 'content_id', 'content_id')
                ]);
            })), [
            'size' => 1,
            'data-form-reload' => 'true'
        ]);

        echo $form->field($this, 'tree_id')->widget(
            SelectTree::className(),
            [
                'mode' => SelectTree::MOD_SINGLE
            ]
        );

        if ($this->content_id)
        {
            echo BlockTitleWidget::widget([
                'content' => 'Настройки данных товаров'
            ]);


            echo $form->field($this, 'default_delivery')->listBox(
                ArrayHelper::merge(['' => ' - '], \Yii::$app->cms->booleanFormat()), [
                'size' => 1,
            ]);

            echo $form->field($this, 'default_pickup')->listBox(
                ArrayHelper::merge(['' => ' - '], \Yii::$app->cms->booleanFormat()), [
                'size' => 1,
            ]);

            echo $form->field($this, 'default_store')->listBox(
                ArrayHelper::merge(['' => ' - '], \Yii::$app->cms->booleanFormat()), [
                'size' => 1,
            ]);

            echo $form->field($this, 'default_sales_notes')->textInput([
                'maxlength' => 50
            ]);

            echo "<hr />";

            echo $form->field($this, 'vendor')->listBox(
                ArrayHelper::merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
            ]);

            echo $form->field($this, 'vendor_code')->listBox(
                ArrayHelper::merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
            ]);



            echo BlockTitleWidget::widget([
                'content' => 'Фильтрация'
            ]);

            echo Alert::widget([
                'options' => [
                    'class' => 'alert-info',
                ],
                'body' => 'В выгрузку попадают только активные товары и предложения. Дополнительно можно ограничить выборку опциями ниже.'
            ]);

            echo $form->field($this, 'filter_property')->listBox(
                ArrayHelper::merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
                'data-form-reload' => 'true'
            ]);

            if ($this->filter_property)
            {
                if ($propertyName = $this->getRelatedPropertyName($this->filter_property))
                {
                    $element = new CmsContentElement([
                        'content_id' => $this->cmsContent->id
                    ]);

                    if ($property = $element->relatedPropertiesModel->getRelatedProperty($propertyName))
                    {
                        if ($property->handler instanceof PropertyTypeList)
                        {
                            echo $form->field($this, 'filter_property_value')->listBox(
                                ArrayHelper::merge(['' => ' - '], ArrayHelper::map($property->enums, 'id', 'value')), [
                                'size' => 1,
                            ]);
                        } else
                        {
                            echo $form->field($this, 'filter_property_value');
                        }
                    } else
                    {
                        echo $form->field($this, 'filter_property_value');
                    }
                }

            }

        }

    }


    public function getAvailableFields()
    {
        if (!$this->cmsContent)
        {
            return [];
        }

        $element = new CmsContentElement([
            'content_id' => $this->cmsContent->id
        ]);

        $fields = [];

        foreach ($element->attributeLabels() as $key => $name)
        {
            $fields['element.' . $key] = $name;
        }

        foreach ($element->relatedPropertiesModel->attributeLabels() as $key => $name)
        {
            $fields['property.' . $key] = $name . " [свойство]";
        }

        $fields['image'] = 'Ссылка на главное изображение';

        return array_merge(['' => ' - '], $fields);
    }

    public function getRelatedPropertyName($fieldName)
    {
        if (strpos("field_" . $fieldName, 'property.'))
        {
            $realName = str_replace("property.", "", $fieldName);
            return $realName;
        }
    }

    public function getElementName($fieldName)
    {
        if (strpos("field_" . $fieldName, 'element.'))
        {
            $realName = str_replace("element.", "", $fieldName);
            return $realName;
        }

        return '';
    }

    public function export()
    {

        //TODO: if console app
        \Yii::$app->urlManager->baseUrl = $this->base_url;
        \Yii::$app->urlManager->scriptUrl = $this->base_url;

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

        $imp = new \DOMImplementation();
		$dtd = $imp->createDocumentType('yml_catalog', '', "shops.dtd");
		$xml               = $imp->createDocument('', '', $dtd);
		$xml->encoding     = 'utf-8';
		//$xml->formatOutput = true;

        $yml_catalog = $xml->appendChild(new \DOMElement('yml_catalog'));
		$yml_catalog->appendChild(new \DOMAttr('date', date('Y-m-d H:i:s')));

            $this->result->stdout("\tДобавление основной информации\n");

        $shop = $yml_catalog->appendChild(new \DOMElement('shop'));

        $shop->appendChild(new \DOMElement('name', $this->shop_name ? htmlspecialchars($this->shop_name) : htmlspecialchars(\Yii::$app->name)));
		$shop->appendChild(new \DOMElement('company', $this->shop_company ? htmlspecialchars($this->shop_company) : htmlspecialchars(\Yii::$app->name)));
		$shop->appendChild(new \DOMElement('email', htmlspecialchars('info@skeeks.com')));
		$shop->appendChild(new \DOMElement('url', htmlspecialchars(
            $this->base_url
        )));
		$shop->appendChild(new \DOMElement('platform', "SkeekS CMS"));


        $this->_appendCurrencies($shop);
        $this->_appendCategories($shop);
        $this->_appendOffers($shop);

        $xml->formatOutput = true;
        $xml->save($this->rootFilePath);

        return $this->result;
    }

    /**
     * @param \DOMElement $shop
     *
     * @return $this
     */
    protected function _appendCurrencies(\DOMElement $shop)
    {
        $this->result->stdout("\tДобавление валют\n");
        /**
         * @var Currency $currency
         */
        $xcurrencies = $shop->appendChild(new \DOMElement('currencies'));
		foreach (Currency::find()->active()->orderBy(['priority' => SORT_ASC])->all() as $currency)
		{
			$xcurr = $xcurrencies->appendChild(new \DOMElement('currency'));
			$xcurr->appendChild(new \DOMAttr('id', $currency->code));
			$xcurr->appendChild(new \DOMAttr('rate', (float) $currency->course));
		}

        return $this;
    }

    /**
     * @param \DOMElement $shop
     *
     * @return $this
     */
    protected function _appendCategories(\DOMElement $shop)
    {
        /**
         * @var CmsTree $rootTree
         * @var CmsTree $tree
         */
        $rootTree = CmsTree::findOne($this->tree_id);

        $this->result->stdout("\tВставка категорий\n");

        if ($rootTree)
        {
            $xcategories = $shop->appendChild(new \DOMElement('categories'));

            $trees = $rootTree->getDescendants()->orderBy(['level' => SORT_ASC])->all();
            $trees = ArrayHelper::merge([$rootTree], $trees);
            foreach ($trees as $tree)
            {
                /*$xcategories->appendChild($this->__xml->importNode($cat->toXML()->documentElement, TRUE));*/

                $xcurr = $xcategories->appendChild(new \DOMElement('category', $tree->name));
                $xcurr->appendChild(new \DOMAttr('id', $tree->id));
                if ($tree->parent && $tree->id != $rootTree->id)
                {
                    $xcurr->appendChild(new \DOMAttr('parentId', $tree->parent->id));
                }
            }
        }

    }

    /**
     * @param \DOMElement $shop
     *
     * @return $this
     */
    protected function _appendOffers(\DOMElement $shop)
    {
        $totalCount = ShopCmsContentElement::find()->where([
            'content_id' => $this->content_id
        ])->count();

        $this->result->stdout("\tВсего товаров: {$totalCount}\n");

        $activeTotalCount = ShopCmsContentElement::find()->active()->andWhere([
            'content_id' => $this->content_id
        ])->count();

        $this->result->stdout("\tАктивных товаров найдено: {$activeTotalCount}\n");


        if ($activeTotalCount)
        {
            $successAdded = 0;
            $xoffers = $shop->appendChild(new \DOMElement('offers'));
            /**
             * @var ShopCmsContentElement $element
             */
            foreach (ShopCmsContentElement::find()->active()->andWhere([
                'content_id' => $this->content_id
            ])->each(10) as $element)
            {
                try
                {
                    if (!$element->shopProduct)
                    {
                        throw new Exception("Нет данных для магазина");
                        continue;
                    }

                    if (!$element->shopProduct->baseProductPrice ||
                        !$element->shopProduct->baseProductPrice->money->getValue()
                    )
                    {
                        throw new Exception("Нет цены");
                        continue;
                    }

                    if ($element->shopProduct->quantity <= 0)
                    {
                        throw new Exception("Нет в наличии");
                        continue;
                    }


                    if ($element->shopProduct->product_type == ShopProduct::TYPE_SIMPLE)
                    {
                        $this->_initOffer($xoffers, $element);
                    } else
                    {
                        $offers = $element->tradeOffers;
                        foreach ($offers as $offer)
                        {
                            /*$xoffer = $xoffers->appendChild(new \DOMElement('offer'));
                            $this->_initOffer($xoffer, $offer);*/
                            $this->_initOffer($xoffers, $offer);
                        }
                    }

                    $successAdded ++;
                } catch (\Exception $e)
                {
                    $this->result->stdout("\t\t{$element->id} — {$e->getMessage()}\n", Console::FG_RED);
                    continue;
                }
            }

            $this->result->stdout("\tДобавлено в файл: {$successAdded}\n");
        }
    }

    protected function _initOffer($xoffers, ShopCmsContentElement $element)
    {

        if (!$element->shopProduct)
        {
            throw new Exception("Нет данных для магазина");
        }

        if ($this->filter_property && $this->filter_property_value)
        {
            $propertyName = $this->getRelatedPropertyName($this->filter_property);

            if (!$attributeValue = $element->relatedPropertiesModel->getAttribute($propertyName))
            {
                throw new Exception("Не найдено свойство для фильтрации");
            }

            if ($attributeValue != $this->filter_property_value)
            {
                throw new Exception("Не найдено свойство для фильтрации");
            }
        }


        $xoffer = $xoffers->appendChild(new \DOMElement('offer'));
        $xoffer->appendChild(new \DOMAttr('id', $element->id));


        if ($element->shopProduct->quantity)
        {
            $xoffer->appendChild(new \DOMAttr('available', 'true'));
        } else
        {
            throw new Exception("Нет в наличии");
            //$xoffer->appendChild(new \DOMAttr('available', 'false'));
        }

        $xoffer->appendChild(new \DOMElement('url', htmlspecialchars($element->url)));
        $xoffer->appendChild(new \DOMElement('name', htmlspecialchars($element->name)));

        if ($element->image)
        {
            $xoffer->appendChild(new \DOMElement('picture', htmlspecialchars($this->base_url . $element->image->src)));
        }

        if ($element->tree_id)
        {
            $xoffer->appendChild(new \DOMElement('categoryId', $element->tree_id));
        }

        if ($element->shopProduct->baseProductPrice)
        {
            $money = $element->shopProduct->baseProductPrice->money;
            $xoffer->appendChild(new \DOMElement('price', $money->getValue()));
            $xoffer->appendChild(new \DOMElement('currencyId', $money->getCurrency()->getCurrencyCode()));
        }


        if ($this->vendor)
        {
            if ($propertyName = $this->getRelatedPropertyName($this->vendor))
            {
                if ($element->relatedPropertiesModel)
                {
                    if ($value = $element->relatedPropertiesModel->getAttribute($propertyName))
                    {
                        $smartName = $element->relatedPropertiesModel->getSmartAttribute($propertyName);
                        $xoffer->appendChild(new \DOMElement('vendor', $smartName));
                    }
                }
            }
        }

        if ($this->vendor_code)
        {
            if ($propertyName = $this->getRelatedPropertyName($this->vendor_code))
            {
                if ($element->relatedPropertiesModel)
                {
                    if ($value = $element->relatedPropertiesModel->getAttribute($propertyName))
                    {
                        $smartName = $element->relatedPropertiesModel->getSmartAttribute($propertyName);
                        $xoffer->appendChild(new \DOMElement('vendorCode', $smartName));
                    }
                }
            }
        }

        if ($this->default_delivery)
        {
            if ($this->default_delivery == 'Y')
            {
                $xoffer->appendChild(new \DOMElement('delivery', 'true'));
            } else if ($this->default_delivery == 'N')
            {
                $xoffer->appendChild(new \DOMElement('delivery', 'false'));
            }
        }

        if ($this->default_store)
        {
            if ($this->default_store == 'Y')
            {
                $xoffer->appendChild(new \DOMElement('store', 'true'));
            } else if ($this->default_store == 'N')
            {
                $xoffer->appendChild(new \DOMElement('store', 'false'));
            }
        }

        if ($this->default_pickup)
        {
            if ($this->default_pickup == 'Y')
            {
                $xoffer->appendChild(new \DOMElement('pickup', 'true'));
            } else if ($this->default_pickup == 'N')
            {
                $xoffer->appendChild(new \DOMElement('pickup', 'false'));
            }
        }

        if ($this->default_sales_notes)
        {
            $xoffer->appendChild(new \DOMElement('sales_notes', $this->default_sales_notes));
        }

        return $this;
    }
}