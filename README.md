Yii2 Dot Trasnlation
======================

![Screen Shot](https://github.com/pavlinter/yii2-dot-translation/blob/master/screenshot.png?raw=true)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist pavlinter/yii2-dot-translation "master"
```

or add

```
"pavlinter/yii2-dot-translation": "master"
```

to the require section of your `composer.json` file.


Configuration
-------------

* Run migration file
    ```php
    yii migrate --migrationPath=@vendor/pavlinter/yii2-dot-translation/migrations
    ```

* Update config file
```php
'bootstrap' => [
   'i18n',
   ...
],
'components' => [
    'i18n' => [
        'class'=>'pavlinter\translation\I18N',
        //'translations' => [
            //'myCategory' => [
                //'class' => 'pavlinter\translation\DbMessageSource',
                //'forceTranslation' => true,
                //'autoInsert' => true, //if message key doesn't exist in the database, message key will be created automatically
            //],
        //],
        //default settings
        //'access' => 'dots-control',  //user permissions or function(){ return true || false; }
        //'dotCategory' => [ //set global settings for category
            //'app' => true, //show dot after text(default)
            //example:
            //'app*' => true, //In this case we're handling everything that begins with app
            //'app/menu' => false, //disable dot
            //'*' => true, //settings for all categories
        //],

        //'dotClass' => 'dot-translation',
        //'dotSymbol' => '&bull;',
        //'nl2br' => true //nl2br filter text
        //languages table
        //'langTable' => '{{%languages}}', //string || null if table not exist
        //'langColCode' => 'code',
        //'langColLabel' => 'name',
        //'langColUpdatedAt' => 'updated_at', //string || null
        //'langWhere' => ['active' => 1], //$query->where(['active' => 1]);
        //'langOrder' => 'weight', //$query->orderBy('weight');

        //'enableCaching' => true, //for langTable cache
        //'durationCaching' => 0, //langTable cache
        //'router' => 'site/dot-translation', //'site' your controller
        //'langParam' => 'lang', // $_GET KEY
    ],
    ...
],
```
* Update controller
```php
//SiteController.php
public function actions()
{
    return [
        'dot-translation' => [
            'class' => 'pavlinter\translation\TranslationAction',
            //'htmlEncode' => true, //encode new message
            //'access' => null, //default Yii::$app->i18n->access
        ],
        ...
    ];
}

```

Usage
-----

Change language:
```php
/index.php?r=site/index&lang=ru
```

Example:
```php

echo Yii::t('app', 'Hello world.'); used global settings

//individual adjustment
echo Yii::t('app', 'Hi {username}.', ['username' => 'Bob', 'dot' => true]); //enable dot

echo Yii::t('app', 'Hello world.', ['dot' => false , 'nl2br' => false]); //disable dot and disable nl2br filter

echo Html::submitInput(Yii::t('app', 'Submit', ['dot' => false])); //disable dot

echo Yii::$app->i18n->getPrevDot(); // show previous dot

// Or

echo Yii::t('app', 'Submit', ['dot' => '.']); //show only dot

```

```php
Yii::$app->i18n->disableDot(); //force disable all dots

echo Breadcrumbs::widget([
    'links' => $this->params['breadcrumbs'],
]);

Yii::$app->i18n->enableDot(); //enable again
```