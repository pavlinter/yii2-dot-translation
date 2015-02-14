<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2015
 * @package yii2-dot-translation
 * @version 1.1.1
 */

namespace pavlinter\translation;

use Yii;
use yii\db\Query;

/**
 * @author Pavels Radajevs <pavlinter@gmail.com>
 */
class DbMessageSource extends \yii\i18n\DbMessageSource
{
    public $autoInsert = false;
    public $dotMode;
    private $messagesId = [];
    /**
     * Initializes the DbMessageSource component.
     */
    public function init()
    {
        parent::init();
        if ($this->autoInsert) {
            $this->on(self::EVENT_MISSING_TRANSLATION,function($event){
                if (!isset($this->messagesId[$event->message])) {
                    Yii::$app->db->createCommand()->insert($this->sourceMessageTable,[
                        'category' => $event->category,
                        'message'  => $event->message,
                    ])->execute();
                    $this->messagesId[$event->message] = Yii::$app->db->lastInsertID;
                }
                $event->translatedMessage = $event->message;
            });
        }
    }

    /**
     * Loads the message translation for the specified language and category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return array the loaded messages. The keys are original messages, and the values
     * are translated messages.
     */
    public function loadMessages($category, $language)
    {
        $I18n = Yii::$app->getI18n();
        $languages = $I18n->getLanguages();
        $newLanguage = null;

        if (is_numeric($language)) {
            if (isset($languages[$language])) {
                $newLanguage = $languages[$language]['id'];
            }
        } else if (is_string($language)) {
            if ($language !== Yii::$app->language) {
                foreach ($languages as $id => $lang) {
                    if (isset($lang[$I18n->langColCode])) {
                        $newLanguage = $id;
                        break;
                    }
                }
            } else {
                $newLanguage = null;
            }
        }

        if ($newLanguage === null) {
            $language = $I18n->getId();
        } else {
            $language = $newLanguage;
        }

        if ($this->enableCaching && false) {
            $key = [
                __CLASS__,
                $category,
                $language,
            ];
            $messages = $this->cache->get($key);
            if ($messages === false) {
                $messages = $this->loadMessagesFromDb($category, $language);
                $this->cache->set($key, $messages, $this->cachingDuration);
            }

            return $messages;
        } else {
            return $this->loadMessagesFromDb($category, $language);
        }
    }

    /**
     * Loads the messages from database.
     * You may override this method to customize the message storage in the database.
     * @param string $category the message category.
     * @param string $language the target language.
     * @return array the messages loaded from database.
     */
    protected function loadMessagesFromDb($category, $language)
    {
        $mainQuery = new Query();
        $mainQuery->select(['t1.id', 't1.message message', 't2.translation translation'])
            ->from([$this->sourceMessageTable . ' t1'])
            ->leftJoin($this->messageTable . ' t2','t1.id = t2.id AND t2.language_id = :language')
            ->where('t1.category = :category')
            ->params([':category' => $category, ':language' => $language]);

        $messages = $mainQuery->createCommand($this->db)->queryAll();

        $result = [];
        foreach ($messages as $message) {
            if ($message['translation'] !== null) {
                $result[$message['message']] = $message['translation'];
            }
            $this->messagesId[$message['message']] = $message['id'];
        }
        return $result;
    }

    /**
     * @param $message
     * @return bool
     */
    public function getId($message)
    {
        if(isset($this->messagesId[$message]))
        {
            return $this->messagesId[$message];
        }
        return false;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messagesId;
    }
}
