<?php

namespace pavlinter\translation;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

class Lang extends Component
{
    public $langParam = 'lang';
    /**
     * Initializes the component by configuring the default message categories.
     */
    public function init()
    {
        echo __CLASS__.'<br/>';
        parent::init();
        $lang = Yii::$app->request->get($this->langParam);
        
    }

}
