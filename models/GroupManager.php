<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "groupmanager".
 *
 * @property integer $id
 * @property integer $group_name
 * 
 */
class GroupManager extends \yii\db\ActiveRecord
{
    /**
     * 普通开发者
     */
    const TYPE_USER  = 0;

    /**
     * 管理员
     */
    const TYPE_ADMIN = 1;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'groupmanager';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['group_name', 'required'],
       //     ['group_name', 'string'],
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'group_name' => '用户组名',
        ];
    }
    
    /**
     * width('user')
     *
     * @return \yii\db\ActiveQuery
     */
    public function getGroup() {
        return $this->hasOne(GroupManager::className(), ['id' => 'id']);
    }

    /**
     * 项目添加用户组
     *
     * @param $projectId
     * @param $userId array
     * @return bool
     */
    public static function addGroup($projectId, $userIds, $type = Group::TYPE_USER) {
        // 是否已在组内
        $exitsUids = Group::find()
            ->select(['user_id'])
            ->where(['project_id' => $projectId, 'user_id' => $userIds])
            ->column();
        $notExists = array_diff($userIds, $exitsUids);
        if (empty($notExists)) return true;

        $group = new Group();
        foreach ($notExists as $uid) {
            $relation = clone $group;
            $relation->attributes = [
                'project_id' => $projectId,
                'user_id'    => $uid,
                'type'       => $type,
            ];
            $relation->save();
        }
        return true;
    }

    /**
     * 是否为该项目的审核管理员
     *
     * @param $projectId
     * @param $uid
     * @return int|string
     */
    public static function isAuditAdmin($uid, $projectId = null) {
        $isAuditAdmin = static::find()
            ->where(['user_id' => $uid, 'type' => Group::TYPE_ADMIN]);
        if ($projectId) {
            $isAuditAdmin->andWhere(['project_id' => $projectId, ]);
        }
        return $isAuditAdmin->count();
    }

    /**
     * 获取用户可以审核的项目
     *
     * @param $uid
     * @return array
     */
    public static function getAuditProjectIds($uid) {
        return static::find()
            ->select(['project_id'])
            ->where(['user_id' => $uid, 'type' => Group::TYPE_ADMIN])
            ->column();
    }

}

?>
