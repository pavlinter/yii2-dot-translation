Yii2 Dot Trasnlation
======================

![Screen Shot](https://github.com/pavlinter/yii2-dot-translation/blob/master/screenshot.png?raw=true)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist pavlinter/yii2-dot-translation "dev-master"
```

or add

```
"pavlinter/yii2-dot-translation": "dev-master"
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
        //default settings
        //'access' => 'dots-control',  //user permissions or function(){ return true || false; }
        //'dotMode' => true, //show dot after text
        // * false - disable dot
        // * '.' - show only dot

        //'dotClass' => 'dot-translation',
        //'dotSymbol' => '&bull;',

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

echo Yii::t('app', 'Hello world.'); //dotMode = true

echo Yii::t('app', 'Hi {username}.', ['username' => 'Bob', 'dot' => true]); //change dotMode

echo Yii::t('app', 'Hello world.', ['dot' => false]); //disable dot

echo Html::submitInput(Yii::t('app', 'Submit', ['dot' => false])); //disable dot

echo Yii::$app->i18n->getDot(); // show previous dot

// Or

echo Yii::t('app', 'Submit', ['dot' => '.']);

```

```php
Yii::$app->i18n->dotMode = false; //disable if you use widget

echo Breadcrumbs::widget([
    'links' => $this->params['breadcrumbs'],
]);

Yii::$app->i18n->dotMode = true; //enable again
```