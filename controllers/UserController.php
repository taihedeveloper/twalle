<?php

namespace app\controllers;

use app\models\forms\AddUserForm;
use yii;
use yii\web\NotFoundHttpException;
use app\components\Controller;
use app\components\GlobalHelper;
use app\models\User;
use app\models\GroupManager;
use app\models\forms\UserResetPasswordForm;
use yii\base\InvalidParamException;
use yii\data\Pagination;


class UserController extends Controller {

    // 头像大小限制200k
    const AVATAR_SIZE = 200000;

    public function actionIndex() {
        $user = User::findOne($this->uid);
        return $this->render('index', [
            'user' => $user,
        ]);
    }


    public function actionAvatar() {
        $fileParts = pathinfo($_FILES['avatar']['name']);
        if ($_FILES['avatar']['error']) {
            $this->renderJson([], -1, yii::t('user', 'upload failed'));
        }
        if ($_FILES['avatar']['size'] > static::AVATAR_SIZE) {
            $this->renderJson([], -1, yii::t('user', 'attached\'s size too large'));
        }
        if (!in_array(strtolower($fileParts['extension']), \Yii::$app->params['user.avatar.extension'])) {
            $this->renderJson([], -1, yii::t('user', 'type not allow', [
                'types' => join(', ', \Yii::$app->params['user.avatar.extension'])
            ]));
        }
        $tempFile   = $_FILES['avatar']['tmp_name'];
        $baseName   = sprintf('%s-%d.%s', date("YmdHis", time()), rand(10, 99), $fileParts['extension']);
        $newFile    = User::AVATAR_ROOT . $baseName;
        $urlFile    = GlobalHelper::formatAvatar($baseName);
        $targetFile = sprintf("%s/web/%s", rtrim(\Yii::$app->basePath, '/'),  ltrim($newFile, '/'));
        $ret = move_uploaded_file($tempFile, $targetFile);
        if ($ret) {
            $user = User::findOne($this->uid);
            $user->avatar = $baseName;
            $ret = $user->save();
        }

        $this->renderJson(['url' => $urlFile], !$ret, $ret ?: yii::t('user', 'update avatar failed'));
    }

    public function actionAudit() {
        $this->validateAdmin();

        $apply = User::getInactiveAdminList();

        return $this->render('audit', [
            'apply' => $apply,
        ]);
    }


    /**
     * 用户管理
     */
    public function actionList($page = 1, $size = 10) {
		$this->validateAdmin();
        $userList = User::find()->orderBy('id desc');
        $kw = \Yii::$app->request->post('kw');
        if ($kw) {
            $userList->andFilterWhere(['like', "username", $kw])
                     ->orFilterWhere(['like', "realname", $kw])
                     ->orFilterWhere(['like', "email", $kw])
                     ->orFilterWhere(['like', "id", $kw]);
        }
        $pages = new Pagination(['totalCount' => $userList->count(), 'pageSize' => $size]);
        $userList = $userList->offset(($page - 1) * $size)->limit($size)->asArray()->all();

        return $this->render('list', [
            'userList' => $userList,
            'pages' => $pages,
        ]);
    }

    /**
     * 新增用户
     */
    public function actionAdd(){
        $this->validateAdmin();
        $model = new AddUserForm();
		$user_group = $this->getUserGroup();
        if ($model->load(Yii::$app->request->post()) ) {
		$model->group_id = serialize(Yii::$app->request->post('AddUserForm')['group_id']);
            if ($user = $model->signup()) {
                return $this->redirect('@web/user/list');
            } else {
                throw new \Exception(yii::t('user', 'username exists'));
            }
        }

		$tmp_group = array();
		$manager = unserialize(Yii::$app->user->identity->group_id);   
        if(Yii::$app->user->identity->role!=11)
        {   
            foreach($user_group as $k => $v) 
            {   
                if(in_array($v['id'],$manager))
                    $tmp_group[] = $v; 
            }   
            $user_group = !empty($tmp_group) ? $tmp_group : $user_group;
        }   
        return $this->render('add', [
            'user' => $model,
			'user_group'=>$user_group
        ]);
    }

	/** 
     * 获取用户组
     *
     * @return array
     */
	public function getUserGroup(){
		$user_group = GroupManager::find()->orderBy('id desc')->asArray()->all();
		return $user_group;
	
	}


    /**
     * 设置为管理员
     *
     * @return json
     */
    public function actionToAdmin($uid) {
        $this->validateAdmin();
        if ($uid) {
            User::updateAll(['role' => User::ROLE_ADMIN], ['id' => $uid]);
        }

        $this->renderJson([], self::SUCCESS);
    }

    /**
     * 设置为普通用户
     *
     * @return  json
     */
    public function actionToDev($uid) {
        $this->validateAdmin();
        if ($uid) {
            User::updateAll(['role' => User::ROLE_DEV], ['id' => $uid]);
        }

        $this->renderJson([], self::SUCCESS);
    }

    /**
     * 删除帐号
     *
     * @return json
     */
    public function actionDelete($uid) {
        $this->validateAdmin();
        $user = User::findOne($uid);

        if($user->role == '11' || $user->role == '1'){
            throw new \Exception(yii::t('user', 'administrator account cannot be deleted'));
        }

        if ($user) {
            $user->delete();
        }

        $this->renderJson([], self::SUCCESS);
    }

    /**
     * 编辑用户
     */
    public function actionEdit($userId = null) {

        if ($userId) {
            $user = $this->findModel($userId);
        } else {
            $user = new User();
            $user->loadDefaultValues();
        }
        if (\Yii::$app->request->getIsPost() && $user->load(Yii::$app->request->post())) {
				if($user->id ==1)
					$user->role =11;
				$user->group_id = serialize(Yii::$app->request->post('User')['group_id']);
			if ($user->save()) {
                $this->redirect('@web/user/list');
            }
        }
		if(strlen($user->group_id)<=1)
		{
			$user->group_id = array($user->group_id);
		}else{
			//$user->group_id = unserialize(Yii::$app->user->identity->group_id);
			$user->group_id =  unserialize($user->group_id);
		}
		$user_group = $this->getUserGroup();
		$tmp_group = array();		
		if(Yii::$app->user->identity->role ==1)
		{
			$current_group_ids = unserialize(Yii::$app->user->identity->group_id);
			foreach($user_group as $k => $v)
			{
				if(in_array($v['id'],$current_group_ids))
					$tmp_group[] = $v;
			}
			$user_group = !empty($tmp_group) ? $tmp_group : $user_group;
		}
        return $this->render('add', [
            'user' => $user,
			'user_group'=>$user_group
        ]);
    }

    /**
     * 删除项目管理员
     *
     * @return string
     * @throws \Exception
     */
    public function actionDeleteAdmin($id) {
        $this->validateAdmin();
        $user = $this->findModel($id);

        if ($user->role != User::ROLE_ADMIN || $user->role != User::SUPER_ADMIN || $user->is_email_verified != 1
            || $user->status != User::STATUS_INACTIVE) {
            throw new \Exception(yii::t('user', 'cant\'t remove active manager'));
        }

        if (!$user->delete()) throw new \Exception(yii::t('w', 'delete failed'));
        $this->renderJson([]);
    }

    /**
     * 项目审核管理员审核通过
     *
     * @return string
     * @throws \Exception
     */
    public function actionActiveAdmin($id) {
        $this->validateAdmin();
        $user = $this->findModel($id);

        if ($user->role != User::ROLE_ADMIN || $user->role != User::SUPER_ADMIN || $user->is_email_verified != 1
            || $user->status != User::STATUS_INACTIVE) {
            throw new \Exception(yii::t('user', 'only pass inactive manager'));
        }
        $user->status = User::STATUS_ACTIVE;
        if (!$user->update()) throw new \Exception(yii::t('w', 'update failed'));
        $this->renderJson([]);
    }

    /**
     * 用户重置密码
     */
    public function actionResetPassword()
    {
        $user = new UserResetPasswordForm($this->uid);

        if ($user->load(Yii::$app->request->post()) && $user->validate() && $user->resetPassword()) {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');
            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $user,
        ]);
    }

    /**
     * 列表重置密码
     */
    public function actionChangePassword($password, $uid){

        //验证管理员
        $this->validateAdmin();

        if ($password && $uid) {
            $pwd = Yii::$app->security->generatePasswordHash($password);
            $res = User::updateAll(['password_hash' => $pwd], ['id' => $uid]);
            $this->renderJson([], $res ? self::SUCCESS : self::FAIL, $res ? '' : Yii::t('w', 'update failed'));
        }
        $this->renderJson([], self::FAIL, Yii::t('w', 'update failed'));
    }

    /**
     * 简化
     *
     * @param integer $id
     * @return Notification the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id) {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(yii::t('user', 'user not exists'));
        }
    }

}
