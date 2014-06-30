<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-dot-translation
 * @version 1.0.0
 */

namespace pavlinter\translation;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use yii\bootstrap\Modal;
use yii\widgets\ActiveForm;
use yii\caching\DbDependency;
use yii\i18n\MessageSource;

/**
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 * @since 1.0
 */
class I18N extends \yii\i18n\I18N
{

    public $dotCategory         = ['app' => true];
    public $dotClass            = 'dot-translation';
    public $dotSymbol           = '&bull;';

    public $langTable           = '{{%languages}}';
    public $langColCode         = 'code'; //language code ru,en ...
    public $langColLabel        = 'name';
    public $langColUpdatedAt    = 'updated_at';

    public $langWhere           = ['active' => 1];
    public $langOrder           = 'weight';

    public $enableCaching       = true;
    public $durationCaching     = 0;

    public $nl2br               = true;

    public $router              = 'site/dot-translation';
    public $langParam           = 'lang'; // $_GET KEY
    public $access              = 'dots-control';
    public $htmlScope           = false;
    public $htmlScopeClass      = 'bs';

    private $dotMode            = null;
    private $dotCategoryMode    = false;
    private $language           = null;
    private $languageId         = null;
    private $languages          = []; //list languages
    private $dot                = null;
    private $showDot            = false;
    /**
     * @var boolean encode new message.
     */
    public $htmlEncode = true;
    /**
     * Initializes the component by configuring the default message categories.
     */
    public function init()
    {
        if (!isset($this->translations['yii']) && !isset($this->translations['yii*'])) {
            $this->translations['yii'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en',
                'basePath' => '@yii/messages',
            ];
        }
        if (!isset($this->translations['app']) && !isset($this->translations['app*'])) {
            $this->translations['app'] = [
                'class' => 'pavlinter\translation\DbMessageSource',
                'sourceLanguage' => Yii::$app->sourceLanguage,
                'forceTranslation' => true,
            ];
        }

        if (Yii::$app->request->getIsConsoleRequest()) {
            return true;
        }

        $this->language = Yii::$app->language;

        $this->changeLanguage();

        if ($this->access()) {
            $view = Yii::$app->getView();
            $this->registerAssets($view);
            $view->on($view::EVENT_END_BODY, function ($event) {

                if ($this->htmlScope) {
                    echo Html::beginTag('span',['class' => $this->htmlScopeClass]);
                }
                Modal::begin([
                    'header' => '<div id="dots-modal-header" style="padding-right: 10px;"><div id="dots-modal-cat-header"></div><div id="dots-modal-key-header"></div></div>',
                    'toggleButton' => [
                        'class' => 'hide',
                        'id' => 'dots-btn-modal',
                    ],
                ]);

                    ActiveForm::begin([
                        'id' => 'dot-translation-form',
                        'action' => [$this->router],
                    ]);
                        echo Html::hiddenInput('category', '', ['id' => 'dots-inp-category']);
                        echo Html::hiddenInput('message', '', ['id' => 'dots-inp-message']);
                        foreach ($this->languages as $code=>$language) {

                            echo Html::beginTag('div', ['class' => 'form-group']);
                            echo Html::label($language[$this->langColLabel],'dot-translation-' . $code);
                            echo Html::textarea('translation[' . $code . ']', '', [
                                'class' => 'form-control',
                                'id' => 'dot-translation-' . $code]);
                            echo Html::endTag('div');
                        }
                        echo Html::submitButton('Change', ['class' => 'btn btn-success', 'id' => 'dot-btn']);

                    ActiveForm::end();

                Modal::end();

                if ($this->htmlScope) {
                    echo Html::endTag('span');
                }

            });
            $this->showDot = true;
        }
    }
    /**
     * Change language through $_GET params.
     */
    public function changeLanguage()
    {

        $key = self::className().'Languages';
        $this->languages = Yii::$app->cache->get($key);

        if ($this->languages === false) {

            if ($this->langTable) {
                $query = new Query();
                $query->from($this->langTable)
                    ->where($this->langWhere)
                    ->orderBy($this->langOrder);

                $this->languages = $query->indexBy($this->langColCode)->all();
            } else {
                $this->languages = [];
            }

            if ($this->languages===[]) {

                $this->languages[$this->language] = [
                    'id' => 0,
                    $this->langColCode  => $this->language,
                    $this->langColLabel => $this->language,
                ];
            }

            if ($this->enableCaching) {
                if ($this->langTable && $this->langColUpdatedAt) {

                    $query = new Query();
                    $sql = $query->select('COUNT(*),MAX(' . $this->langColUpdatedAt . ')')
                        ->from($this->langTable)
                        ->createCommand()
                        ->getRawSql();
                    Yii::$app->cache->set($key,$this->languages,$this->durationCaching,new DbDependency([
                        'sql' => $sql,
                    ]));
                } else if ($this->durationCaching) {
                    Yii::$app->cache->set($key,$this->languages,$this->durationCaching);
                }
            }

        }

        $langKey = Yii::$app->request->get($this->langParam);

        if ($this->languages) {
            if ($langKey && isset($this->languages[$langKey])) {
                $language = $this->languages[$langKey];
            } else {
                $language = reset($this->languages);
            }

            Yii::$app->language = $this->language = $language[$this->langColCode];
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

        if ($this->dotMode!==null) {
            $this->dotCategoryMode = $this->dotMode;
        }

        $mod = ArrayHelper::remove($params,'dot');
        $nl2br = ArrayHelper::remove($params,'nl2br',$this->nl2br);


        $settings = [
            'before' => '' ,
            'after' => '',
            'return' => false,
        ];

        $settings = ArrayHelper::merge($settings,$this->setDot($category,$message,$params,$mod));
        if ($settings['return']) {
            return $settings['before'].$settings['after'];
        }
        if ($nl2br) {
            $message = nl2br($message);
        }

        if ($translation === false) {
            return $settings['before'].$this->format($message, $params, $messageSource->sourceLanguage).$settings['after'];
        } else {
            return $settings['before'].$this->format($translation, $params, $language).$settings['after'];
        }
    }
    /**
     * Returns the message source for the given category.
     * @param string $category the category name.
     * @return MessageSource the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function getMessageSource($category)
    {
        $this->dotCategoryMode = false;
        if (isset($this->translations[$category])) {
            if (isset($this->dotCategory[$category])) {
                $this->dotCategoryMode = $this->dotCategory[$category];
            } elseif (isset($this->dotCategory['*'])) {
                $this->dotCategoryMode = $this->dotCategory['*'];
            }
            $source = $this->translations[$category];
            if ($source instanceof MessageSource) {
                return $source;
            } else {
                return $this->translations[$category] = Yii::createObject($source);
            }
        } else {
            // try wildcard matching
            foreach ($this->translations as $pattern => $source) {
                if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                    if (isset($this->dotCategory[$category])) {
                        $this->dotCategoryMode = $this->dotCategory[$category];
                    } elseif (isset($this->dotCategory[$pattern])) {
                        $this->dotCategoryMode = $this->dotCategory[$category] = $this->dotCategory[$pattern];
                    } elseif (isset($this->dotCategory['*'])) {
                        $this->dotCategoryMode = $this->dotCategory[$category] = $this->dotCategory['*'];
                    }
                    if ($source instanceof MessageSource) {
                        return $source;
                    } else {
                        return $this->translations[$category] = $this->translations[$pattern] = Yii::createObject($source);
                    }
                }
            }
            // match '*' in the last
            if (isset($this->translations['*'])) {
                $source = $this->translations['*'];
                if (isset($this->dotCategory['*'])) {
                    $this->dotCategoryMode = $this->dotCategory['*'];
                }
                if ($source instanceof MessageSource) {
                    return $source;
                } else {
                    return $this->translations[$category] = $this->translations['*'] = Yii::createObject($source);
                }
            }
        }

        throw new InvalidConfigException("Unable to locate message source for category '$category'.");
    }
    /**
     * Set dot mode
     */
    public function setDot($category,$message,$params,$mod)
    {
        $res = [];
        if (!is_array($mod)) {
            $mod = ['dot' => ($mod === null?$this->dotCategoryMode:$mod)];

        }

        $mod = ArrayHelper::merge([
            'dot' => $this->dotCategoryMode,
            'dotSymbol' => $this->dotSymbol,
        ],$mod);

        $options = [
            'class' => $this->dotClass,
            'data-category' => urlencode($category),
            'data-message' => urlencode($message),
            'data-header' => Html::encode($message),
            'data-redirect' => 1,
            'data-hash' => $this->getHash($category.$message),
            'data-params'=>Json::encode($params),
        ];
        $this->dot = Html::tag('span', $mod['dotSymbol'], $options);

        if (!$this->showDot) {
            if ($mod['dot'] === '.') {
                $res['return'] = true;
            }
        } elseif  ($mod['dot'] === true) {
            $res['before']  = Html::beginTag('span', ['class' => 'text-' . $options['class']]);
            $res['after']   = $this->dot    = Html::endTag('span') . Html::tag('span', $mod['dotSymbol'], ArrayHelper::merge($options, ['data-redirect' => 0]));
        } elseif ($mod['dot'] === '.') {
            $res['before']  = $this->dot;
            $res['return']  = true;
        }
        return $res;
    }
    /**
     * User permissions
     * @param  null|string|function
     * @return boolean
     */
    public function access($access = null)
    {
        if ($access === null) {
            $access = $this->access;
        }
        if (is_string($access) && Yii::$app->getAuthManager()!==null) {
            return Yii::$app->getUser()->can($access);
        } elseif (is_callable($access)) {
            return call_user_func($access);
        }
        return false;
    }
    public function getMessageId($category,$message)
    {
        $messageSource = $this->getMessageSource($category);

        if(method_exists($messageSource,'getId'))
        {
            return $messageSource->getId($message);
        }
        return false;
    }
    /**
     * Generate hash for message;
     */
    public function getHash($data)
    {
        return md5($data);
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
                            var dot = $("[data-hash=\'" + hash + "\']");
                            var params = dot.attr("data-params");
                            if (params) {
                                var o = jQuery.parseJSON(params);
                                for (m in o) {
                                    val = val.replace("{" + m + "}",o[m]);
                                }

                            }
                            dot.prev(".text-' . $this->dotClass . '").html(val);
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
                $("#dots-modal-header #dots-modal-cat-header").text(decodeURIComponent(category));
                $("#dots-modal-header #dots-modal-key-header").html(dotNl2br(header));
                $("#dots-btn-modal").trigger("click");

                jQuery.ajax({
                    url: form.attr("action"),
                    type: "GET",
                    dataType: "json",
                    data: {category: category,message: message},
                    success: function(d) {
                        textarea.val("");
                        for(m in d.fields){
                            val = d.fields[m];
                            if(val == ""){
                                $(m).addClass("emptyField").val(header);
                            }else{
                                $(m).removeClass("emptyField").val(val);
                            }
                        }
                    },
                    error: function(response) {

                    }
                });
                function dotNl2br( str ){
	                return str.replace(/([^>])\n/g, "$1<br/>");
	            }
                return false;
            });
            $("#dot-translation-form textarea").on("focus",function(){
                $(this).removeClass("emptyField");
            });
        ');

        $view->registerCss('
            .'.$this->dotClass.'{
                cursor: pointer;
                text-decoration: none;
            }
            #dots-modal-header #dots-modal-cat-header{
                text-align: center;
                border-bottom: 1px dashed silver;
                width: 80%;
                margin: 0 auto;
                padding: 0px 0px 5px 0px;
            }
            #dots-modal-header #dots-modal-key-header{
                padding: 13px 0px 0px 0px;
            }
            #dot-translation-form .emptyField{
                color: silver;
            }
        ');
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
     * @return int language id from language table
     */
    public function getId()
    {
        return $this->languageId;
    }
    /**
     * @return string the previous dot target
     */
    public function getPrevDot()
    {
        if ($this->access()) {
            return $this->dot;
        }
        return null;
    }
    /**
     * Force disable all dots
     */
    public function disableDot()
    {
        $this->dotMode = false;
    }
    /**
     * Set global previous settings
     */
    public function enableDot()
    {
        $this->dotMode = null;
    }
}
