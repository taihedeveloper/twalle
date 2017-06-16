<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('gm', 'perm group');
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Permission;
use yii\helpers\ArrayHelper;
?>

<div class="box col-xs-8">
    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
    <div class="box-body">
<!-- $perm->project_ids=array(1,2)-->
 <?= $form->field($perm, 'project_ids')->label('项目列表')->checkboxList(ArrayHelper::map($project,'id','name')) ?>
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

