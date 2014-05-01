<?php

namespace pavlinter\translation;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;
use yii\bootstrap\Modal;
use yii\widgets\ActiveForm;
use yii\helpers\Security;
use yii\caching\DbDependency;

class I18N extends \yii\i18n\I18N
{

    public $dotMode         = true;
    public $dotClass        = 'dot-translation';
    public $dotSymbol       = '&bull;';

    public $langModel       = 'pavlinter\translation\Languages';
    public $langAttribute   = 'code'; //language code ru,en ...
    public $langLabel       = 'name';
    public $langFilter      = ['active' => 1];
    public $langOrder       = 'weight';

    public $enableCaching   = true;
    public $durationCaching = 0;

    public $router          = 'site/dot-translation';
    public $langParam       = 'lang'; // $_GET KEY
    public $access          = 'dots-control';

    private $language           = null;
    private $languageId         = 0;
    private $languages          = []; //list languages
    private $dot                = null;
    private $showDot            = false;
    private $beforeTranslate    = '';
    private $afterTranslate     = '';
    /**
     * Initializes the component by configuring the default message categories.
     */
    public function init()
    {
        parent::init();
        $this->language = Yii::$app->language;

        $this->changeLanguage();

        if ($this->access()) {
            $view = Yii::$app->getView();
            $this->registerAssets($view);
            $view->on(View::EVENT_END_BODY, function ($event) {

                Modal::begin([
                    'header' => '<div id="dots-modal-header" style="padding-right: 10px;"></div>',
                    'toggleButton' => [
                        'class' => 'hide',
                        'id' => 'dots-btn-modal',
                    ],
                ]);

                    $form = ActiveForm::begin([
                        'id' => 'dot-translation-form',
                        'action' => [$this->router],
                    ]);
                        echo Html::hiddenInput('category', '', ['id' => 'dots-inp-category']);
                        echo Html::hiddenInput('message', '', ['id' => 'dots-inp-message']);
                        foreach ($this->languages as $lang) {

                            echo Html::beginTag('div', ['class' => 'form-group']);
                            echo Html::label($lang[$this->langLabel],'dot-translation-' . $lang[$this->langAttribute]);
                            echo Html::textarea('translation[' . $lang[$this->langAttribute] . ']', '', [
                                'class' => 'form-control',
                                'id' => 'dot-translation-' . $lang[$this->langAttribute]]);
                            echo Html::endTag('div');
                        }
                        echo Html::submitButton('Change', ['class' => 'btn btn-success', 'id' => 'dot-btn']);

                    ActiveForm::end();

                Modal::end();

            });
            $this->showDot = true;
        }
    }
    /**
     * Change language through $_GET params.
     */
    public function changeLanguage()
    {
        $model = Yii::createObject($this->langModel);
        $key = self::className().'Languages';
        $this->languages = Yii::$app->cache->get($key);

        if ($this->languages === false) {

            $query = $model::find()->indexBy($this->langAttribute);
            $query->andFilterWhere($this->langFilter);

            $cacheQuery = (new Query());
            $cacheQuery->select('SUM(updated_at)')->from($model->tableName())->andFilterWhere($this->langFilter);

            if ($this->langOrder) {
                $query->orderBy($this->langOrder);
                $cacheQuery->orderBy($this->langOrder);
            }
            $this->languages = $query->asArray()->all();


            if ($this->enableCaching) {

                $command = $cacheQuery->createCommand();
                Yii::$app->cache->set($key,$this->languages,$this->durationCaching,new DbDependency([
                    'sql' => $command->getRawSql(),
                ]));
            }
        }



        $langKey = Yii::$app->request->get($this->langParam);

        if ($this->languages) {
            if ($langKey && isset($this->languages[$langKey])) {
                $language = $this->languages[$langKey];
            } else {
                $language = reset($this->languages);
            }

            Yii::$app->language = $this->language = $language[$this->langAttribute];
            $this->languageId   = $language['id'];
        }

    }
    /**
     * Translates a message to the specified language.
     *
     * After translation the message will be formatted using [[MessageFormatter]] if it contains
     * ICU message format and `$params` are not empty.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the translated and formatted message.
     */
    public function translate($category, $message, $params, $language)
    {

        $messageSource = $this->getMessageSource($category);
        $translation = $messageSource->translate($category, $message, $language);

        $mod = ArrayHelper::remove($params,'dot',$this->dotMode);


        if ($this->showDot) {
            if (!$this->setDot($category,$message,$mod)) {
                return $this->beforeTranslate.$this->afterTranslate;
            }
        }

        if ($translation === false) {
            return $this->beforeTranslate.$this->format($message, $params, $messageSource->sourceLanguage).$this->afterTranslate;
        } else {
            return $this->beforeTranslate.$this->format($translation, $params, $language).$this->afterTranslate;
        }
    }
    /**
 * @return string language code
 */
    public function getLanguage()
    {
        return $this->language;
    }
    /**
     * @return array languages list
     */
    public function getLanguages()
    {
        return $this->languages;
    }
    /**
     * @return int language id from langModel
     */
    public function getId()
    {
        return $this->languageId;
    }
    /**
     * @return string the previous dot target
     */
    public function getDot()
    {
        return $this->dot;
    }
    /**
     * Set dot mode
     */
    public function setDot($category,$message,$mod)
    {

        $htmlOptions = [
            'class' => $this->dotClass,
            'data-category' => urlencode($category),
            'data-message' => urlencode($message),
            'data-header' => Html::encode($message),
            'data-redirect' => 1,
            'data-hash' => md5($category.$message),
        ];
        $this->dot = Html::tag('span', $this->dotSymbol, $htmlOptions);

        if ($mod === true) {
            $this->beforeTranslate  = Html::beginTag('span', ['class' => 'text-' . $this->dotClass]);
            $this->afterTranslate   = $this->dot    = Html::endTag('span') . Html::tag('span', $this->dotSymbol, ArrayHelper::merge($htmlOptions, ['data-redirect' => 0]));
        } elseif ($mod === '.') {
            $this->beforeTranslate  = $this->dot;
            $this->afterTranslate   = '';
            return false;
        } elseif($mod === false) {
            $this->beforeTranslate  = '';
            $this->afterTranslate   = '';
        }
        return true;
    }
    /**
     * User permissions
     * @return boolean
     */
    public function access()
    {
        if (is_string($this->access)) {
            return Yii::$app->getUser()->can($this->access);
        } elseif (is_callable($this->access)) {
            return call_user_func($this->access);
        }
    }
    /**
     * Register client side
     */
    public function registerAssets($view)
    {
        $view->registerJs('
            $("#dot-translation-form button").on("click", function () {

                var form = $(this).closest("form");
                var hash        = form.attr("data-hash");
                var redirect    = form.attr("data-redirect")==1;
                var lang        = "'.$this->getLanguage().'";
                var val         = $("textarea#dot-translation-"+lang,form).val();

                $("#dot-btn",form).button("loading");

                jQuery.ajax({
                    url: form.attr("action"),
                    type: "POST",
                    dataType: "json",
                    data: form.serialize(),
                    success: function(d) {
                        if (redirect) {
                            location.href = "'.Url::to('').'";
                            return false;
                        }
                        if (d.r) {
                            $("[data-hash=\'" + hash + "\']").prev(".text-' . $this->dotClass . '").html(val);

                            var modalID = $("#dots-btn-modal").attr("data-target");
                            $(modalID).modal("hide");
                            $("#dot-btn",form).button("reset");
                        }
                    },
                    error: function(response) {

                    }
                });

                return false;

            });

            $(document).on("click",".'.$this->dotClass.'",function () {
                var form        = $("#dot-translation-form");
                var category    = $(this).attr("data-category");
                var message     = $(this).attr("data-message");
                var header      = $(this).attr("data-header");
                var hash        = $(this).attr("data-hash");
                var redirect    = $(this).attr("data-redirect");
                var textarea    = $("#dot-translation-form textarea").val("Loading...");


                form.attr("data-redirect",redirect);
                form.attr("data-hash",hash);


                $("#dot-translation-form #dots-inp-category").val(category);
                $("#dot-translation-form #dots-inp-message").val(message);

                $("#dots-modal-header").text(header);
                $("#dots-btn-modal").trigger("click");

                jQuery.ajax({
                    url: form.attr("action"),
                    type: "GET",
                    dataType: "json",
                    data: {category: category,message: message},
                    success: function(d) {
                        textarea.val("");
                        for(m in d.fields){
                            $(m).val(d.fields[m]);
                        }
                    },
                    error: function(response) {

                    }
                });
                return false;
            });

        ');

        $view->registerCss('
            .'.$this->dotClass.'{
                cursor: pointer;
                text-decoration: none;
            }
        ');
    }
}
