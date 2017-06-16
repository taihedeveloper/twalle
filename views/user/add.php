<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('user', 'add user');
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\user;
use yii\helpers\ArrayHelper;
?>

<div class="box col-xs-8">
    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
    <div class="box-body">

        <?= $form->field($user, 'username')
            ->textInput(['class' => 'col-xs-5',])
            ->label(Yii::t('user', 'username'), ['class' => 'text-right bolder blue col-xs-2']) ?>
        <div class="clearfix"></div>

        <?= $form->field($user, 'realname')
            ->textInput(['class' => 'col-xs-5',])
            ->label(Yii::t('user', 'realname'), ['class' => 'text-right bolder blue col-xs-2']) ?>
        <div class="clearfix"></div>


        <?= $form->field($user, 'email')
            ->textInput(['class' => 'col-xs-5',])
            ->label(Yii::t('user', 'email'), ['class' => 'text-right bolder blue col-xs-2']) ?>
        <div class="clearfix"></div>
        
<?= $form->field($user, 'role')->label(Yii::t('user', 'role'), ['class' => 'text-right bolder blue col-xs-2'])
            ->dropDownList([
            User::ROLE_DEV => \Yii::t('w', 'user_role_' . User::ROLE_DEV),
            User::ROLE_ADMIN => \Yii::t('w', 'user_role_' . User::ROLE_ADMIN),
        ], ['class' => 'col-xs-5',]) ?>
 
<div class="clearfix"></div>
		<?= $form->field($user, 'group_id')->label(Yii::t('user', 'user_group'), ['class' => 'text-right bolder blue col-xs-2'])
            ->checkboxList(ArrayHelper::map($user_group,'id','group_name'), ['class' => 'col-xs-5',]) ?>

        <div class="clearfix"></div>



        <div class="box-footer">
        <div class="col-xs-2"></div>
        <div class="form-group" style="margin-top:40px">
            <?= Html::submitButton(yii::t('user','save'), ['class' => 'btn btn-primary', 'name' => 'submit-button']) ?>
        </div>
            </div>
    </div><!-- /.box-body -->
    <?php ActiveForm::end(); ?>
</div>
