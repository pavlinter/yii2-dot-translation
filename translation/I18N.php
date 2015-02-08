<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-dot-translation
 * @version 1.1.0
 */

namespace pavlinter\translation;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use yii\web\Application;
use yii\web\HttpException;
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
    const DIALOG_BS = 'bs';
    const DIALOG_JQ = 'jq';

    public $dotClass            = 'dot-translation';
    public $dotSymbol           = '&bull;';

    public $langTable           = '{{%language}}';
    public $langColCode         = 'code'; //language code ru,en ...
    public $langColLabel        = 'name';
    public $langColUpdatedAt    = 'updated_at';

    public $langWhere           = ['active' => 1];
    public $langOrder           = 'weight';

    public $enableCaching       = true;
    public $durationCaching     = 0;

    public $nl2br               = true;

    public $router              = '/site/dot-translation';
    public $langParam           = 'lang'; // $_GET KEY
    public $access              = 'dots-control';
    public $htmlScope           = false;
    public $htmlScopeClass      = 'bs';
    public $dialog              = I18N::DIALOG_BS; // bs or jq

    private $dotMode            = null;
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

        $this->initLanguage();

        if ($this->access() && !$this->isPjax()) {
            $view = Yii::$app->getView();
            $this->register($view);

        }
    }

    /**
     * @param $view
     */
    public function register($view)
    {
        $view->on($view::EVENT_END_BODY, function ($event) {

            $this->registerAssets($event->sender);
            if ($this->htmlScope) {
                echo Html::beginTag('span',['class' => $this->htmlScopeClass]);
            }

            if ($this->dialog == I18N::DIALOG_BS) {
                \yii\bootstrap\Modal::begin([
                    'header' => '<div id="dots-modal-header" style="padding-right: 10px;"><div id="dots-modal-cat-header"></div><div id="dots-modal-key-header"></div></div>',
                    'toggleButton' => [
                        'class' => 'hide',
                        'id' => 'dots-btn-modal',
                    ],
                ]);

                $this->bodyDialog();

                \yii\bootstrap\Modal::end();
            } else if ($this->dialog == I18N::DIALOG_JQ) {
                \yii\jui\Dialog::begin([
                    'options' => [
                        'id' => 'dots-btn-modal',
                    ],
                    'clientOptions' => [
                        'autoOpen' => false,
                        'width' => '50%',
                    ],
                ]);

                $this->bodyDialog();

                \yii\jui\Dialog::end();
            }



            if ($this->htmlScope) {
                echo Html::endTag('span');
            }

        });
        $this->showDot = true;
    }

    /**
     * @param $lang
     * @return bool
     */
    public function changeLanguage($lang)
    {
        if (is_numeric($lang)) {
            if (isset($this->languages[$lang])) {
                Yii::$app->language = $this->languages[$lang][$this->langColCode];
                $this->language     = $this->languages[$lang];
                $this->languageId   = $this->languages[$lang]['id'];
                return true;
            }
        } else if(is_string($lang)) {
            foreach ($this->languages as $language) {
                if ($language[$this->langColCode] === $lang) {
                    Yii::$app->language = $language[$this->langColCode];
                    $this->language     = $language;
                    $this->languageId   = $language['id'];
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Change language through $_GET params.
     */
    public function initLanguage()
    {
        $this->language = Yii::$app->language;
        $key = self::className().'Languages';
        $this->languages = Yii::$app->cache->get($key);

        if ($this->languages === false) {

            if ($this->langTable) {
                $query = new Query();
                $query->from($this->langTable)
                    ->where($this->langWhere)
                    ->orderBy($this->langOrder);

                $this->languages = $query->indexBy('id')->all();
            } else {
                $this->languages = [];
            }

            if ($this->languages === []) {

                $this->languages['0'] = [
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
            $language = null;
            if ($langKey) {
                foreach ($this->languages as $l) {
                    if ($l['code'] == $langKey) {
                        $language = $l;
                        break;
                    }
                }
                if ($language === null) {
                    Yii::$app->on(Application::EVENT_AFTER_REQUEST, function () {
                        throw new HttpException(404, 'Page not exists');
                    });
                }
            }
            if($language === null) {
                $language = reset($this->languages);
            }
            Yii::$app->language = $language[$this->langColCode];
            $this->language     = $language;
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

        $mod = ArrayHelper::remove($params, 'dot');

        if (isset($params['br'])) {
            $nl2br = ArrayHelper::remove($params, 'br');
        } else {
            $nl2br = ArrayHelper::remove($params, 'nl2br' , $this->nl2br);
        }

        $settings = [
            'before' => '' ,
            'after' => '',
            'return' => false,
        ];

        $settings = ArrayHelper::merge($settings,$this->setDot($messageSource, $category, $message, $params, $mod));
        if ($settings['return']) {
            return $settings['before'].$settings['after'];
        }

        if ($translation === false) {
            if ($nl2br) {
                $message = nl2br($message);
            }
            return $settings['before'].$this->format($message, $params, $messageSource->sourceLanguage).$settings['after'];
        } else {
            if ($nl2br) {
                $translation = nl2br($translation);
            }
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
        if (isset($this->translations[$category])) {
            $source = $this->translations[$category];
            if (!($source instanceof MessageSource)) {
                $source = $this->translations[$category] = Yii::createObject($source);
            }
            return $source;
        } else {
            // try wildcard matching
            foreach ($this->translations as $pattern => $source) {
                if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                    if (!($source instanceof MessageSource)) {
                        $source = $this->translations[$category] = $this->translations[$pattern] = Yii::createObject($source);
                    }
                    return $source;
                }
            }
            // match '*' in the last
            if (isset($this->translations['*'])) {
                $source = $this->translations['*'];
                if (!($source instanceof MessageSource)) {
                    $source = $this->translations[$category] = $this->translations['*'] = Yii::createObject($source);
                }
                return $source;
            }
        }

        throw new InvalidConfigException("Unable to locate message source for category '$category'.");
    }

    /**
     * @param $category
     * @param array $options
     * @return bool|string
     * @throws InvalidConfigException
     */
    public function getOnlyDots($category, $options = [])
    {
        /* @var $source \pavlinter\translation\DbMessageSource */
        $source = $this->getMessageSource($category);
        if ($source instanceof DbMessageSource) {

            $loop = ArrayHelper::remove($options, 'loop', '&nbsp;');
            $dot = ArrayHelper::merge([
                'dot' => '.',
                'dotRedirect' => 0,
            ], ArrayHelper::remove($options, 'dot', []));

            $messages = $source->getMessages();
            $res = '';
            foreach ($messages as $m => $id) {
                $res .= Yii::t($category, $m, $dot) . $loop;
            }
            return $res;
        }
        return false;
    }

    /**
     * @param $messageSource
     * @param $category
     * @param $message
     * @param $params
     * @param $mod
     * @return array
     */
    public function setDot($messageSource, $category, $message, &$params, $mod)
    {
        $redirect = ArrayHelper::remove($params, 'dotRedirect', 1);
        $dotHash = ArrayHelper::remove($params, 'dotHash', $this->getHash($category . $message));
        $dotTo = ArrayHelper::remove($params, 'dotTo', '');
        $var = ArrayHelper::remove($params, 'var', []);



        if ($mod === null) {
            if ($this->dotMode !== null) {
                $mod = $this->dotMode;
            } else {
                if ($messageSource instanceof DbMessageSource) {
                    $mod = $messageSource->dotMode;
                }
            }
        }

        $options = [
            'class' => $this->dotClass,
            'data-keys' => Json::encode(['category' => $category, 'message' => $message]),
            'data-redirect' => $redirect,
            'data-hash' => $dotHash,
            'data-var' => Json::encode($var),
            'data-params'=> Json::encode($params),
        ];

        if ($dotTo) {
            $options['data-to'] = $dotTo;
        }

        $this->dot = Html::tag('span', $this->dotSymbol, $options);
        $res = [];
        if (!$this->showDot) {
            if ($mod === '.') {
                $res['return'] = true;
            }
        } elseif ($mod === '.' && $this->dotMode === false) {
            $res['return'] = true;
        } elseif ($mod === true) {
            $res['before']  = Html::beginTag('span', ['class' => 'text-' . $options['class']]);
            $res['after']   = $this->dot = Html::endTag('span') . Html::tag('span', $this->dotSymbol, ArrayHelper::merge($options, ['data-redirect' => 0]));
        } elseif ($mod === '.') {
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

    /**
     * @param $category
     * @param $message
     * @return bool
     * @throws InvalidConfigException
     */
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
     * @param $data
     * @return string
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
        $script = '';
        if ($this->dialog == I18N::DIALOG_JQ) {
            DialogAsset::register(Yii::$app->getView());
            $script = '
                if($("#dots-modal-header").size() == 0){
                    $("#dots-btn-modal").closest(".ui-dialog").find(".ui-dialog-title").html("<div id=\"dots-modal-header\"><div id=\"dots-modal-cat-header\"></div><div id=\"dots-modal-key-header\"></div></div>");
                }
            ';
        }

        $request = Yii::$app->getRequest();

        $view->registerJs('
            if ($.fn.button && $.fn.button.noConflict){
                $.fn.dotBtn = $.fn.button.noConflict();
            } else if($.fn.btn && $.fn.btn.noConflict) {
                $.fn.dotBtn = $.fn.btn;
            }
            $("#dot-translation-form button").on("click", function () {

                var form        = $(this).closest("form");
                var hash        = form.attr("data-hash");
                var dotTo       = form.attr("data-to");
                var redirect    = form.attr("data-redirect")==1;

                $("#dot-btn",form).' .($this->dialog == I18N::DIALOG_JQ?'text("Loading...")':'dotBtn("loading")') . ';

                $.ajax({
                    url: form.attr("action"),
                    type: "POST",
                    dataType: "json",
                    data: form.serialize(),
                }).done(function(d) {
                    if (!dotTo && redirect) {
                        location.href = "'.Url::to('').'";
                        return false;
                    }

                    if (d.r) {
                        var val = d.message;
                        var $dot = $("[data-hash=\'" + hash + "\']").not(form);
                        var $dotTo = $("[data-hash=\'" + dotTo + "\']");
                        var params = $dot.attr("data-params");
                        if (params) {
                            var o = jQuery.parseJSON(params);
                            for (m in o) {
                                val = val.replace("{" + m + "}",o[m]);
                            }
                        }
                        $dot.prev(".text-' . $this->dotClass . '").html(val);
                        $dotTo.html(val);
                        ' .($this->dialog == I18N::DIALOG_JQ?'$("#dots-btn-modal").dialog("close");$("#dot-btn",form).text("Change");':'var modalID = $("#dots-btn-modal").attr("data-target");$(modalID).modal("hide");$("#dot-btn",form).dotBtn("reset");') . '
                    }
                });

                return false;

            });

            $(document).on("click",".'.$this->dotClass.'",function () {
                '.$script.'
                var $form       = $("#dot-translation-form");
                var $varCont    = $("#dots-variables");
                var $el         = $(this);
                var k           = $.parseJSON($el.attr("data-keys"));
                var variables   = $.parseJSON($el.attr("data-var"));
                var hash        = $el.attr("data-hash");
                var redirect    = $el.attr("data-redirect");
                var dotTo       = $el.attr("data-to");
                var $textarea   = $("#dot-translation-form textarea").val("Loading...");
                var $key        = $("#dots-modal-header #dots-modal-key-header")
                var viewMsg     = k.message.replace(/<br\s*[\/]?>/gi, "\n");
                $form.attr("data-redirect",redirect);
                $form.attr("data-hash", hash);
                $form.attr("data-to", dotTo);

                $varCont.hide();

                k.message = encodeURIComponent(k.message);

                $("#dot-translation-form #dots-inp-category").val(k.category);
                $("#dot-translation-form #dots-inp-message").val(k.message);

                if(variables){
                    for(v in variables){
                        if($.isNumeric(v)){
                            $varCont.append("<div class=\"dots-var\">{" + variables[v] +"}</div>");
                        } else {
                            $varCont.append("<div class=\"dots-var\">{" + v + "} - " + variables[v] + "</div>");
                        }

                    }
                    $varCont.show();
                }


                $key.text(viewMsg);
                $key.html($key.html().replace(/\n/g,"<br/>"));

                $("#dots-btn-modal").' .($this->dialog == I18N::DIALOG_JQ?'dialog("open")':'trigger("click")') . ';

                $.ajax({
                    url: $form.attr("action"),
                    type: "POST",
                    dataType: "json",
                    data: k,
                }).done(function(d) {
                    $textarea.val("");
                    var $dotHeader = $("#dots-modal-header #dots-modal-cat-header")

                    if (d.adminLink){
                        var $adminLinkForm = $("<form method=\"post\" action=\"" + d.adminLink + "\" target=\"_blank\"><input type=\"hidden\" name=\"category\" value=\"" + k.category + "\" /><input type=\"hidden\" name=\"message\" value=\"" + k.message + "\" /><input type=\"hidden\" name=\"'.$request->csrfParam.'\" value=\"'.$request->getCsrfToken().'\" /><a href=\"javascript:void(0);\">" + k.category + "</a></form>");
                        $adminLinkForm.find("a").on("click", function(){
                            $(this).closest("form").submit();
                            return false;
                        });
                        $dotHeader.html($adminLinkForm);
                    } else {
                        $dotHeader.text(k.category);
                    }

                    for(m in d.fields){
                        val = d.fields[m];
                        if(val == ""){
                            $(m).addClass("emptyField").val(val);
                        }else{
                            $(m).removeClass("emptyField").val(val);
                        }
                    }
                });
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
            #dots-variables{
                font-size: 11px;
                padding-bottom: 5px;
                margin-bottom: 10px;
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
            #dot-translation-form #dots-filter{
                display: none;
            }
        ');
    }

    /**
     *
     */
    private function bodyDialog()
    {
        ActiveForm::begin([
            'id' => 'dot-translation-form',
            'action' => [$this->router],
        ]);
        echo Html::tag('div',null,['id' => 'dots-filter']);
        echo Html::hiddenInput('category', '', ['id' => 'dots-inp-category']);
        echo Html::hiddenInput('message', '', ['id' => 'dots-inp-message']);
        echo Html::tag('div', null ,['id' => 'dots-variables']);
        foreach ($this->languages as $id_language => $language) {

            echo Html::beginTag('div', ['class' => 'form-group']);
            echo Html::label($language[$this->langColLabel],'dot-translation-' . $id_language);
            echo Html::textarea('translation[' . $id_language . ']', '', [
                'class' => 'form-control',
                'id' => 'dot-translation-' . $id_language]);
            echo Html::endTag('div');
        }
        echo Html::submitButton(Yii::t("app/i18n-dot", "Change", ['dot' => false]), ['class' => 'btn btn-primary', 'id' => 'dot-btn']);

        ActiveForm::end();
    }

    /**
     * @return array|string
     */
    public function isPjax()
    {
        $headers = Yii::$app->getRequest()->getHeaders();
        return $headers->get('X-Pjax');
    }
    /**
     * @param $id
     * @param $data
     */
    public function setLanguage($id, $data)
    {
        $this->languages[$id] = $data;
    }

    /**
     * @param null $id
     * @return array|string fields|field from language table
     */
    public function getLanguage($id = null)
    {
        if ($id !== null && isset($this->languages[$id])) {
            return $this->languages[$id];
        }
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
     * Force enable all dots
     */
    public function enableDot()
    {
        $this->dotMode = true;
    }
    /**
     * Set global previous settings
     */
    public function resetDot()
    {
        $this->dotMode = null;
    }
}
