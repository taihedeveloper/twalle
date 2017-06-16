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
class Permission extends \yii\db\ActiveRecord
{
    /**
     * 普通开发者
     */
    const TYPE_USER  = 0;

    /**
     * 管理员
     */
    const TYPE_ADMIN = 1;
//public $project_ids;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'permission';
    }

 public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'group_id' => 'group_id',
            'project_ids' => 'project_ids',
        ];
    }

    
    /**
     * width('perm')
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPerm() {
        return $this->hasOne(Permission::className(), ['id' => 'id']);
    }

    /**
     * 为用户组添加权限
     *
     * @param $gid
     * @param $userId array
     * @return bool
     */
    public static function addPerm($gid, $project_ids) {
        // 是否已在组内
        $exitsGids = Permission::find()
            ->select(['project_ids'])
            ->where(['group_id' => $gid])
            ->column();
		$notExists=array();
 	$perm = new Permission();

	if(!empty($exitsGids)){
		$ret = Permission::updateAll(array('project_ids'=>serialize($project_ids)),array('group_id'=>$gid));
		if($ret)       
			 return true;
	}else{
 		$perm->group_id=$gid;
	 	$perm->project_ids=serialize($project_ids);
		$perm->save();
		return true;
	}
    }





	/**
     * 获取用户组内的项目
     *
     * @param $uid
     * @return array
     */
    public static function getGroupProjectIds($gid) {
        return static::find()
            ->select(['project_ids'])
            ->where(['group_id' => $gid])
            ->column();
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
