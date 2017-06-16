<?php

namespace app\controllers;

use Yii;
use app\components\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\User;
use app\models\forms\LoginForm;
use app\models\forms\PasswordResetRequestForm;
use app\models\forms\ResetPasswordForm;
use yii\web\HttpException;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;


class SiteController extends Controller
{
    public $layout = 'site';

    /**
     * Render the homepage
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * User login
     */
    public function actionLogin() {
        require_once(dirname(dirname(__FILE__)).'/extensions/CAS/CAS.php');
       \phpCAS::client(CAS_VERSION_2_0,"xxxcas服务端地址",80,"cas",true);
        \phpCAS::setNoCasServerValidation();
        \phpCAS::handleLogoutRequests();

        if(\phpCAS::checkAuthentication() == true){
			 $model = new LoginForm();		
			$model->username = \phpCAS::getUser();			
			if($model->login())
			{
            	Yii::$app->response->redirect('/conf');
            	Yii::$app->end();
			}else{
				 throw new \Exception("您没有权限访问系统，请联系管理员");
			}

        }else{
            \phpCAS::forceAuthentication();
        }
        

/*
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
*/
    }

    /**
     * User logout
     */
    public function actionLogout()
    {
		require_once(dirname(dirname(__FILE__)).'/extensions/CAS/CAS.php');
        \phpCAS::client(CAS_VERSION_2_0,"cas.taihenw.com",80,"cas",true);
		\phpCAS::setNoCasServerValidation(true);
		// 单点登录配置，如果不需要单点登出功能请删除此行		
		\phpCAS::handleLogoutRequests(false);
		$param=array("service"=>"http://walle.taihenw.com/site/caslog");
		\phpCAS::logout($param);
    }

	/**
	 *退出walle系统
	 *
	 */
	public function actionCaslog(){
		Yii::$app->user->logout(true);
		return $this->goHome();
	}

	/**
	 * admin登陆入口
	 */
    public function actionAdminlogin()
    {
        if (!\Yii::$app->user->isGuest) {
    		return $this->goHome();
    	}
     	$model = new LoginForm();
    	if ($model->load(Yii::$app->request->post()) && $model->login()) {
       		return $this->goBack();
       	} else {
    		return $this->render('login', ['model' => $model,]);
        	}
    }

    /**
     * admin用户退出系统
     * 
     */
    public function actionAdminlogout(){
    	Yii::$app->user->logout();
    	return $this->goHome();
    }
        

    /**
     * User signup
     */
    public function actionSignup() {
        $user = new User(['scenario' => 'signup']);
        if ($user->load(Yii::$app->request->post())) {
            // 项目管理员需要审核
            if ($user->role == User::ROLE_ADMIN) {
                $user->status = User::STATUS_INACTIVE;
            }
            if ($user->save()) {
                Yii::$app->mail->compose('confirmEmail', ['user' => $user])
                    ->setFrom(Yii::$app->mail->messageConfig['from'])
                    ->setTo($user->email)
                    ->setSubject('瓦力平台 - ' . $user->realname)
                    ->send();
                Yii::$app->session->setFlash('user-signed-up');
                return $this->refresh();
            }
        }

        if (Yii::$app->session->hasFlash('user-signed-up')) {
            return $this->render('signedUp');
        }

        return $this->render('signup', [
            'model' => $user,
        ]);
    }

    /**
     * Confirm email
     */
    public function actionConfirmEmail($token)
    {
        $user = User::find()->emailConfirmationToken($token)->one();
        if ($user!==null && $user->removeEmailConfirmationToken(true)) {
            Yii::$app->getUser()->login($user);
            return $this->goHome();
        }

        return $this->render('emailConfirmationFailed');
    }

    /**
     * Request password reset
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->getSession()->setFlash('success', 'Check your email for further instructions.');
                return $this->goHome();
            } else {
                Yii::$app->getSession()->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Reset password
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }


    public function actionSearch() {

    }

    public function actionError() {
        if (($exception = Yii::$app->getErrorHandler()->exception) === null) {
            return '';
        }
        if ($exception instanceof HttpException) {
            $code = $exception->statusCode;
        } else {
            $code = $exception->getCode();
        }
        if ($exception instanceof Exception) {
            $name = $exception->getName();
        } else {
            $name = Yii::t('yii', 'Error');
        }
        if ($code) {
            $name .= " (#$code)";
        }

        if ($exception instanceof \Exception) {
            $message = $exception->getMessage();
        } else {
            $message = Yii::t('yii', 'An internal server error occurred.');
        }

        if (Yii::$app->getRequest()->getIsAjax()) {
            static::renderJson([], $code ?: -1, $message);
        } else {
            return $this->render('error', [
                'name' => $name,
                'message' => $message,
                'exception' => $exception,
            ]);
        }
    }
}
