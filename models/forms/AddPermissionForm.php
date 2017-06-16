<?php

namespace app\models\forms;

use yii;
use yii\base\Model;
use app\models\Permission;

class AddPermissionForm extends Model {

    public $project_ids;
	public $group_id;

    public function attributeLabels()
    {
    }

    public function rules() {
        return [
            ['project_ids', 'required'],

            ['project_ids', 'string'],
        ];
    }

    public function signup() {
        if ($this->validate()) {
            $perm = new Permission();
            $perm->project_ids = $this->project_ids;
			$perm->group_id

            if ($perm->save()) {
                return $perm;
            }

            return null;
        }
    }
}
