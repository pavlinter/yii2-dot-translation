<?php

namespace pavlinter\translation;

use Yii;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\caching\Cache;
use yii\db\Connection;
use yii\db\Query;

class DbMessageSource extends \yii\i18n\DbMessageSource
{
    private $messagesId = [];
    /**
     * Initializes the DbMessageSource component.
     */
    public function init()
    {
        parent::init();
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
    protected function loadMessages($category, $language)
    {
        if ($this->enableCaching) {
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
            ->leftJoin($this->messageTable . ' t2','t1.id = t2.id AND t2.language = :language')
            ->where('t1.category = :category')
            ->params([':category' => $category, ':language' => $language]);

        $fallbackLanguage = substr($language, 0, 2);
        if ($fallbackLanguage != $language) {
            $fallbackQuery = new Query();
            $fallbackQuery->select(['t1.id', 't1.message message', 't2.translation translation'])
                ->from([$this->sourceMessageTable . ' t1'])
                ->leftJoin($this->messageTable . ' t2','t1.id = t2.id AND t2.language = :fallbackLanguage')
                ->where('t1.category = :category')
                ->andWhere('t2.id NOT IN (SELECT id FROM '.$this->messageTable.' WHERE language = :language)')
                ->params([':category' => $category, ':language' => $language, ':fallbackLanguage' => $fallbackLanguage]);

            $mainQuery->union($fallbackQuery, true);
        }

        $messages = $mainQuery->createCommand($this->db)->queryAll();

        $result = [];
        foreach ($messages as $message) {
            if ($message['translation']!==null) {
                $result[$message['message']] = $message['translation'];
            }
            $this->messagesId[$message['message']] = $message['id'];
        }
        return $result;
    }

    public function getId($message)
    {
        if(isset($this->messagesId[$message]))
        {
            return $this->messagesId[$message];
        }
        return false;
    }
}
