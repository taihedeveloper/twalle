<?php

namespace app\controllers;

use yii;
use app\models\forms\AddGroupManagerForm;
use app\models\Permission;
use yii\web\NotFoundHttpException;
use yii\data\Pagination;
use app\components\Controller;
use app\models\GroupManager;
use app\models\Project;
use app\components\GlobalHelper;


class GroupmanagerController extends Controller
{

    /**
     * @param \yii\base\Action $action
     * @return bool
     */
    public function beforeAction($action) {
        parent::beforeAction($action);
        if (!GlobalHelper::isValidAdmin()) {
            throw new \Exception(yii::t('conf', 'you are not active'));
        }
        return true;
    }

    /**
     * 用户组列表
     *
     */
    public function actionIndex() {
        $groups = GroupManager::find();
        $kw = \Yii::$app->request->post('kw');
        if ($kw) {
            $groups->andWhere(['like', "group_name", $kw]);
        }
        $groups = $groups->asArray()->all();
        return $this->render('index', [
            'list' => $groups,
        ]);
    }

 /**
     * 用户管理
     */
    public function actionList($page = 1, $size = 10) {
        $groupList = GroupManager::find()->orderBy('id desc');
        $kw = \Yii::$app->request->post('kw');
        if ($kw) {
            $groupList->andFilterWhere(['like', "group_name", $kw]);
        }
        $pages = new Pagination(['totalCount' => $groupList->count(), 'pageSize' => $size]);
        $groupList = $groupList->offset(($page - 1) * $size)->limit($size)->asArray()->all();

        return $this->render('list', [
            'groupList' => $groupList,
            'pages' => $pages,
        ]);
    }



    /**
     * 创建用户组
     */

    public function actionAdd(){
        $this->validateAdministrator();
        $model = new AddGroupManagerForm();

        if ($model->load(Yii::$app->request->post()) ) {

            if ($user = $model->signup()) {
                return $this->redirect('@web/groupmanager/list');
            } else {
                throw new \Exception(yii::t('gm', 'save fail'));
            }
        }

        return $this->render('add', [
            'group' => $model
        ]);
    }


    /**
     * 编辑用户组
     */
    public function actionEdit($gid = null) {
        $this->validateAdministrator();
        if ($gid) {
            $group = $this->findModel($gid);
        } else {
            $group = new GroupManager();
            $group->loadDefaultValues();
        }

        if (\Yii::$app->request->getIsPost() && $group->load(Yii::$app->request->post())) {
            if ($group->save()) {
                $this->redirect('@web/groupmanager/list');
            }
        }

        return $this->render('add', [
            'group' => $group,
        ]);
    }

    /**
     * 删除用户组
     *
     * @return json
     */
    public function actionDelete($gid) {
        $this->validateAdministrator();
        $group = GroupManager::findOne($gid);

        if($group->id=='1'){
            throw new \Exception(yii::t('gm', 'administrator account cannot be deleted'));
        }

        if ($group) {
            $group->delete();
        }

        $this->renderJson([], self::SUCCESS);
    }


	/**
	 *判断是否是管理员操作
	 */
     protected function validateAdministrator() {
        if (!GlobalHelper::isSuperAdmin()) {
            throw new \Exception(\yii::t('walle', 'you are not the super manager'));
        }
    }
	
	 /**
     * 简化
     *
     * @param integer $id
     * @return Notification the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id) {
        if (($model = GroupManager::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(yii::t('gm', 'group not exists'));
        }
    }
   
	/**
	 *分配用户组权限
	 *
	 */
	public function actionPermission($gid){
		$this->validateAdministrator();
		$perm = new Permission();
		 if (\Yii::$app->request->getIsPost() && $perm->load(Yii::$app->request->post())) {
		$pj_ids = Yii::$app->request->post('Permission')['project_ids'];
//			$perm->project_ids=serialize(Yii::$app->request->post('Permission')['project_ids']);
			if (Permission::addPerm($gid,$pj_ids)) {
                $this->redirect('@web/groupmanager/list');
            }	
		}
		
		//获取组拥有权限
		$ids = Permission::getGroupProjectIds($gid);
		if(!empty($ids))
		$perm->project_ids =unserialize($ids[0]);

		$project = Project::find()->select(array('id','name'))->where(['status' => Project::STATUS_VALID])->asArray()->all();
		return $this->render('plist', [
              'perm' => $perm,
			  'project'=>$project,
			  'gid'=>$gid,
          ]);

	}

}
?>
