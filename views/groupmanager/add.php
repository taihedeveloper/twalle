<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('gm', 'add group');
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\GroupManager;
?>

<div class="box col-xs-8">
    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
    <div class="box-body">

        <?= $form->field($group, 'group_name')
            ->textInput(['class' => 'col-xs-5',])
            ->label(Yii::t('gm', 'group_name'), ['class' => 'text-right bolder blue col-xs-2']) ?>
        <div class="clearfix"></div>



        <div class="box-footer">
        <div class="col-xs-2"></div>
        <div class="form-group" style="margin-top:40px">
            <?= Html::submitButton(yii::t('gm','save'), ['class' => 'btn btn-primary', 'name' => 'submit-button']) ?>
        </div>
            </div>
    </div><!-- /.box-body -->
    <?php ActiveForm::end(); ?>
</div>
