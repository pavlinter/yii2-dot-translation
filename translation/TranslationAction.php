<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-dot-translation
 * @version 1.0.0
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
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 * @since 1.0
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

        if (Yii::$app->request->isPost) {


            $category           = urldecode(Yii::$app->request->post('category'));
            $message            = urldecode(Yii::$app->request->post('message'));
            $translations       = Yii::$app->request->post('translation');

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

            foreach ($translations as $id_language => $value) {
                if (!isset($languages[$id_language])) {
                    continue;
                }

                if ($this->htmlEncode) {
                    $value = Html::encode($value);
                }

                $query = new Query();
                $res = $query->from($this->messageTable)->where([
                            'id' => $id,
                            'id_language' => $id_language,
                        ])->exists();
                if ($res) {
                    Yii::$app->db->createCommand()->update($this->messageTable, [
                        'translation'  => $value,
                    ], [
                        'id' => $id,
                        'id_language' => $id_language,
                    ])->execute();
                } else {
                    Yii::$app->db->createCommand()->insert($this->messageTable, [
                        'id' => $id,
                        'id_language' => $id_language,
                        'translation'  => $value,
                    ])->execute();
                }
            }
            $json['r'] = 1;
            return $json;
        } else {

            $category   = urldecode(Yii::$app->request->get('category'));
            $message    = urldecode(Yii::$app->request->get('message'));

            $json['fields']     = [];
            $json['adminLink'] = $this->getAdminLink($category, $message);

            foreach ($languages as $id_language => $language) {
                $query = new Query();
                $query->select("m.translation")->from($this->sourceMessageTable.' AS s')
                    ->innerJoin($this->messageTable.' AS m','m.id = s.id')
                    ->where([
                        'm.id_language' => $language['id'],
                        's.category' => $category,
                        's.message'  => $message,
                    ]);
                $translation = $query->scalar();
                if ($translation === false) {
                    $translation = '';
                }

                $json['fields']['#dot-translation-' . $id_language] = $translation;

            }
            return $json;
        }
    }
    public function getAdminLink($category, $message)
    {
        if (is_callable($this->adminLink)) {
            $to = call_user_func($this->adminLink, $category, $message);
        } else{
            $to = $this->adminLink;
        }
        if (is_array($to)) {
            if (!isset($to['category'])) {
                $to['category'] = $category;
            }
            if (!isset($to['message'])) {
                $to['message']  = $message;
            }
        }
        if ($to) {
            return urlencode(Url::to($to));
        }
        return null;
    }

}
