<?php
/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-dot-translation
 * @version 1.0.0
 */
namespace pavlinter\translation;

use ReflectionClass;
use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;

/**
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 * @since 1.0
 * @commit c79ab7cf9d77cf107ce060323a0b1fb1ec01c505
 */
class TranslationBehavior extends Behavior
{
    /**
     * @var string the name of the translations relation
     */
    public $relation = 'translations';
    /**
     * @var string the language field used in the related table. Determines the language to query | save.
     */
    public $languageField = 'language_id';
    /**
     * @var string the scenario.
     */
    public $scenario = ActiveRecord::SCENARIO_DEFAULT;
    /**
     * @var array the list of attributes to translate. You can add validation rules on the owner.
     */
    public $translationAttributes = [];

    /**
     * @var ActiveRecord[] the models holding the translations.
     */
    private $_models = [];

    /**
     * @var string the language selected.
     */
    private $_language;

    /**
     * Returns models' language.
     * @return array
     */
    public function getLangModels()
    {
        return $this->_models;
    }
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
        ];
    }

    /**
     * Make [[$translationAttributes]] writeable
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->translationAttributes)) {
            $this->getTranslation()->$name = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Make [[$translationAttributes]] readable
     * @inheritdoc
     */
    public function __get($name)
    {
        if (!in_array($name, $this->translationAttributes) && !isset($this->_models[$name])) {
            return parent::__get($name);
        }
        if (isset($this->_models[$name])) {
            return $this->_models[$name];
        }

        $model = $this->getTranslation();
        return $model->$name;
    }

    /**
     * Expose [[$translationAttributes]] writable
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->translationAttributes) ? true : parent::canSetProperty($name, $checkVars);
    }

    /**
     * Expose [[$translationAttributes]] readable
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->translationAttributes) ? true : parent::canGetProperty($name, $checkVars);
    }

    /**
     * @param Event $event
     */
    public function afterFind($event)
    {
        $this->populateTranslations();
        $this->getTranslation($this->getLanguage());
    }

    /**
     * @param Event $event
     */
    public function afterInsert($event)
    {
        $this->saveTranslation();
    }

    /**
     * @param Event $event
     */
    public function afterUpdate($event)
    {
        $this->saveTranslation();
    }

    /**
     * Sets current model's language
     * @param $id_language
     */
    public function setLanguage($id_language)
    {
        if (!isset($this->_models[$id_language])) {
            $this->_models[$id_language] = $this->loadTranslation($id_language);
        }
        $this->_language = $id_language;
    }

    /**
     * Returns current models' language. If null, will return app's configured language.
     * @return string
     */
    public function getLanguage()
    {
        if ($this->_language === null) {
            $this->_language = Yii::$app->getI18n()->getId();
        }
        return $this->_language;
    }

    /**
     * Saves current translation model
     * @return bool
     */
    public function saveTranslation($id_language = null,$validate = true)
    {
        $model = $this->getTranslation($id_language);
        $dirty = $model->getDirtyAttributes();
        if (empty($dirty)) {
            return true; // we do not need to save anything
        }
        /** @var \yii\db\ActiveQuery $relation */
        $relation = $this->owner->getRelation($this->relation);
        $model->{key($relation->link)} = $this->owner->getPrimaryKey();
        return $model->save($validate);

    }
    public function saveAllTranslation($validate = true)
    {
        $valid = true;
        foreach (Yii::$app->getI18n()->getLanguages() as $id_language => $language) {
            $valid = $this->saveTranslation($id_language, $validate) && $valid;
        }
        return $valid;
    }
    public function validateAll($data, $onlyTranslation = false)
    {
        $valid = true;
        if ($onlyTranslation === false) {
            $valid = $this->owner->load($data) && $valid;
            if($valid === false){
                return $valid;
            }
            $valid = $this->owner->validate() && $valid;
        }
        return $this->validateAllTranslation($data) && $valid;
    }


    public function validateAllTranslation($data)
    {
        $valid = true;
        foreach (Yii::$app->getI18n()->getLanguages() as $id_language => $language) {
            /** @var ActiveRecord $model */
            $model = $this->loadLang($data, $id_language);
            if ($model !== false) {
                $valid = $model->validate() && $valid;
            } else {
                $valid = false;
            }
        }
        return $valid;
    }
    /**
     * Returns translation model
     * @param array $data the data array. This is usually `$_POST` or `$_GET`, but can also be any valid array
     * supplied by end user.
     * @param integer|null $language the language to return. If null, current sys language
     * @return false|ActiveRecord
     */
    public function loadLang($data, $id_language = null)
    {
        $id_language = $id_language === null ? $this->getLanguage() : $id_language;
        /** @var \yii\db\ActiveQuery $relation */
        $relation = $this->owner->getRelation($this->relation);
        /** @var ActiveRecord $class */
        $class = $relation->modelClass;
        $reflector = new ReflectionClass($relation->modelClass);
        $scope  = $reflector->getShortName();
        if (isset($data[$scope][$id_language])) {
            $model = $this->getTranslation($id_language);
            $model->setAttributes($data[$scope][$id_language]);
            return $model;
        } else {
            return false;
        }
    }
    /**
     * Returns a related translation model
     * @param integer|null $language the language to return. If null, current sys language
     * @return ActiveRecord
     */
    public function getTranslation($id_language = null)
    {
        if ($id_language === null) {
            $id_language = $this->getLanguage();
        }

        if (!isset($this->_models[$id_language])) {
            $this->_models[$id_language] = $this->loadTranslation($id_language);
        }

        return $this->_models[$id_language];
    }
    /**
     * Loads a specific translation model
     * @param integer $language the language to return
     * @return null|\yii\db\ActiveQuery|static
     */
    private function loadTranslation($id_language)
    {
        /** @var \yii\db\ActiveQuery $relation */
        $relation = $this->owner->getRelation($this->relation);
        /** @var ActiveRecord $class */
        $class = $relation->modelClass;

        $translation = $class::findOne([$this->languageField => $id_language, key($relation->link) => $this->owner->getPrimarykey()]);
        if ($translation === null) {
            $translation = new $class;
            $translation->{key($relation->link)} = $this->owner->getPrimaryKey();
            $translation->{$this->languageField} = $id_language;
        }
        $translation->setScenario($this->scenario);

        return $translation;
    }
    /**
     * Populates already loaded translations
     */
    private function populateTranslations()
    {
        //translations
        $aRelated = $this->owner->getRelatedRecords();
        if (isset($aRelated[$this->relation]) && $aRelated[$this->relation] != null) {
            if (is_array($aRelated[$this->relation])) {
                foreach ($aRelated[$this->relation] as $model) {
                    $this->_models[$model->getAttribute($this->languageField)] = $model;
                }
            } else {
                $model = $aRelated[$this->relation];
                $this->_models[$model->getAttribute($this->languageField)] = $model;
            }
        }
    }


} 
