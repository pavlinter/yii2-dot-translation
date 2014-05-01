<?php

namespace pavlinter\translation;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\Query;
use yii\helpers\Url;
use yii\web\Response;
use yii\web\ForbiddenHttpException;


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
     * Initializes the action.
     * @throws InvalidConfigException if the font file does not exist.
     */
    public function init()
    {
        if (!Yii::$app->i18n->access()) {
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

        if (Yii::$app->request->isPost) {

            $category           = urldecode(Yii::$app->request->post('category'));
            $message            = urldecode(Yii::$app->request->post('message'));
            $translations       = Yii::$app->request->post('translation');

            $query = new Query();
            $query->select('id')->from($this->sourceMessageTable)->where([
                'category' => $category,
                'message'  => $message,
            ]);

            $id = $query->createCommand()->queryScalar();
            if ($id === false) {
                Yii::$app->db->createCommand()->insert($this->sourceMessageTable, [
                    'category' => $category,
                    'message'  => $message,
                ])->execute();
                $id = Yii::$app->db->getLastInsertID();
            }

            foreach ($translations as $lang => $value) {
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

            return ['r'=>1];
        } else {

            $category   = urldecode(Yii::$app->request->get('category'));
            $message    = urldecode(Yii::$app->request->get('message'));

          
            $model = Yii::createObject(Yii::$app->i18n->langModel);
            $query = $model::find();
            $models = $query->andFilterWhere(Yii::$app->i18n->langFilter)->all();
            $json['fields'] = [];
            foreach ($models as $lang) {
                $query = new Query();
                $query->select("m.translation")->from($this->sourceMessageTable.' AS s')
                    ->innerJoin($this->messageTable.' AS m','m.id = s.id')
                    ->where([
                        'm.language' => $lang[Yii::$app->i18n->langAttribute],
                        's.category' => $category,
                        's.message'  => $message,
                    ]);
                $translation    = $query->createCommand()->queryScalar();
                if ($translation === false) {
                    $translation = '';
                }

                $json['fields']['#dot-translation-'.$lang[Yii::$app->i18n->langAttribute]] = $translation;

            }
            return $json;
        }
    }

}
