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

            foreach ($translations as $lang => $value) {
                if (!isset($languages[$lang])) {
                    continue;
                }

                if ($this->htmlEncode) {
                    $value = Html::encode($value);
                }

                $query = new Query();
                $res = $query->from($this->messageTable)->where([
                            'id' => $id,
                            'language' => $lang,
                        ])->exists();
                if ($res) {
                    Yii::$app->db->createCommand()->update($this->messageTable, [
                        'translation'  => $value,
                    ], [
                        'id' => $id,
                        'language' => $lang,
                    ])->execute();
                } else {
                    Yii::$app->db->createCommand()->insert($this->messageTable, [
                        'id' => $id,
                        'language' => $lang,
                        'translation'  => $value,
                    ])->execute();
                }
            }

            return ['r' => 1];
        } else {

            $category   = urldecode(Yii::$app->request->get('category'));
            $message    = urldecode(Yii::$app->request->get('message'));

            $json['fields'] = [];
            foreach ($languages as $code=>$language) {
                $query = new Query();
                $query->select("m.translation")->from($this->sourceMessageTable.' AS s')
                    ->innerJoin($this->messageTable.' AS m','m.id = s.id')
                    ->where([
                        'm.language' => $code,
                        's.category' => $category,
                        's.message'  => $message,
                    ]);
                $translation = $query->scalar();
                if ($translation === false) {
                    $translation = '';
                }

                $json['fields']['#dot-translation-' . $code] = $translation;

            }
            return $json;
        }
    }

}
