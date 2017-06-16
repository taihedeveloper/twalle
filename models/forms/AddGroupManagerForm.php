<?php

namespace app\models\forms;

use yii;
use yii\base\Model;
use app\models\GroupManager;

class AddGroupManagerForm extends Model {

    public $group_name;

    public function attributeLabels()
    {
        return [
            'Group Name' => '邮箱',
            'username' => '用户名',
            'realname' => '真实姓名',
            'role' => '角色',
            //'password' => '密码',
        ];
    }

    public function rules() {
        return [
            ['group_name', 'required','message' => '用户组名不能为空'],

            ['group_name', 'string'],

            //['password', 'string', 'min' => 6, 'max' => 30],

            //['realname', 'string', 'min' => 2],

            //['role', 'in', 'range' => [User::ROLE_DEV, User::ROLE_ADMIN]],
        ];
    }

    public function signup() {
        if ($this->validate()) {
            $group = new GroupManager();
            $group->group_name = $this->group_name;


            if ($group->save()) {
                return $group;
            }

            return null;
        }
    }
}
