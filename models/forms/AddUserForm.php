<?php

namespace app\models\forms;

use yii;
use yii\base\Model;
use app\models\User;
use app\models\queries\UserQuery;

class AddUserForm extends Model {

    public $email;
    public $realname;
    public $role;
    public $username;
    public $group_id;

    public function attributeLabels()
    {
        return [
            'email' => '邮箱',
            'username' => '用户名',
            'realname' => '真实姓名',
            'role' => '角色',
            'group_id' => '用户组',
        ];
    }

    public function rules() {
        return [
            [['email', 'role', 'realname', 'username','group_id'], 'required'],

            ['email', 'email'],

            //['password', 'string', 'min' => 6, 'max' => 30],

            ['realname', 'string', 'min' => 2],

            ['role', 'in', 'range' => [User::ROLE_DEV, User::ROLE_ADMIN]],
        ];
    }

    public function signup() {
        if ($this->validate()) {
            $user = new User();
            $user->username = $this->username;
            $user->email = $this->email;
            $user->role = $this->role;
            $user->realname = $this->realname;
            $user->setpassword('123456');
            //给默认头像
            $user->status = User::STATUS_ACTIVE;
            $user->avatar = 'default.jpg';
            $user->is_email_verified = 1;
			$user->group_id = $this->group_id;
			if ($user->save()) {
                return $user;
            }

            return null;
        }
    }
}