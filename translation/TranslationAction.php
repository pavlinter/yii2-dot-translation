<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2015
 * @package yii2-dot-translation
 * @version 2.1.0
 */

namespace pavlinter\translation;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\Query;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * @author Pavels Radajevs <pavlinter@gmail.com>
 */
class TranslationAction extends Action
{
    /**
     * @var string the name of the source message table.
     */
    public $sourceMessageTable = '{{%source_message}}';
    /**
     * @var string the name of the translated message table.
     */
    public $messageTable = '{{%message}}';
    /**
     * @var boolean encode new message.
     */
    public $htmlEncode = true;
    /**
     * @var null|string|function.
     */
    public $access = null;
    /**
     * @var null|string|function.
     */
    public $adminLink = null;
    /**
     * Initializes the action.
     * @throws InvalidConfigException if the font file does not exist.
     */
    public function init()
    {
        if (!Yii::$app->i18n->access($this->access)) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
    }
    /**
     * Runs the action.
     */
    public function run()
    {

        if (!Yii::$app->request->isAjax) {
            return;
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        $languages = Yii::$app->i18n->getLanguages();

        $category       = Yii::$app->request->post('category');
        $message        = rawurldecode(Yii::$app->request->post('message'));
        $translations   = Yii::$app->request->post('translation');
        $is_empty       = Yii::$app->request->post('is_empty');


        if ($translations) {


            $query = new Query();
            $query->select('id')->from($this->sourceMessageTable)->where([
                'category' => $category,
                'message'  => $message,
            ]);

            $id = $query->scalar();
            if ($id === false) {
                Yii::$app->db->createCommand()->insert($this->sourceMessageTable, [
                    'category' => $category,
                    'message'  => $message,
                ])->execute();
                $id = Yii::$app->db->getLastInsertID();
            }

            $json['message'] = false;

            foreach ($translations as $id_language => $value) {
                if (!isset($languages[$id_language])) {
                    continue;
                }

                if ($is_empty[$id_language]) {
                    $value = null;
                    $json['message'] = null;
                } else {
                    if ($this->htmlEncode) {
                        $value = Html::encode($value);
                    }
                    if (Yii::$app->i18n->getId() == $id_language) {
                        if (Yii::$app->i18n->nl2br) {
                            $json['message'] = nl2br($value);
                        } else {
                            $json['message'] = $value;
                        }
                    }
                }


                $query = new Query();
                $res = $query->from($this->messageTable)->where([
                            'id' => $id,
                            'language_id' => $id_language,
                        ])->exists();
                if ($res) {
                    Yii::$app->db->createCommand()->update($this->messageTable, [
                        'translation'  => $value,
                    ], [
                        'id' => $id,
                        'language_id' => $id_language,
                    ])->execute();
                } else {
                    Yii::$app->db->createCommand()->insert($this->messageTable, [
                        'id' => $id,
                        'language_id' => $id_language,
                        'translation'  => $value,
                    ])->execute();
                }
            }
            $json['r'] = 1;
            return $json;
        } else {

            $json['fields']     = [];
            $json['adminLink']  = $this->getAdminLink();

            foreach ($languages as $id_language => $language) {
                $query = new Query();
                $query->select("m.translation")->from($this->sourceMessageTable.' AS s')
                    ->innerJoin($this->messageTable.' AS m','m.id = s.id')
                    ->where([
                        'm.language_id' => $language['id'],
                        's.category' => $category,
                        's.message'  => $message,
                    ]);

                $value = $query->scalar();

                if ($value === null) {
                    $translation = $value;
                } else {
                    $translation = Html::decode($value);
                }
                if ($translation === false) {
                    $translation = '';
                }
                $json['fields']['#dot-translation-' . $id_language] = $translation;

            }
            return $json;
        }
    }
    public function getAdminLink()
    {
        if (is_callable($this->adminLink)) {
            $to = call_user_func($this->adminLink);
        } else{
            $to = $this->adminLink;
        }
        if ($to) {
            return Url::to($to);
        }
        return null;
    }

}
