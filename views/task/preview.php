<?php
/**
 * @var yii\web\View $this
 */
//$this->title = $conf->name . yii::t('conf', 'edit');

use yii\widgets\ActiveForm;
?>

<div class="profile-user-info">
    <div class="profile-info-row">
        <div class="profile-info-name"> <?= yii::t('task', '路径') ?> </div>

        <div class="profile-info-value">
            <span><?php 
			foreach($conf['file_list'] as $k => $v) {
			    echo $v;
			    echo "<br>";
			}
			echo "<br>";
                  ?>
            </span>
        </div>
    </div>

    <div class="profile-info-row">
        <div class="profile-info-name"> <?= yii::t('task', '版本(Start):  ') ?> </div>

        <div class="profile-info-value">
            <span><?php 
			if(!$conf['start_id']){
			    echo '未知';	
			}else{
			    echo $conf['start_id'];
			    echo("  " . $startInfo);
			}
                  ?>
            </span>
        </div>
    </div>

    <div class="profile-info-row">
        <div class="profile-info-name"> <?= yii::t('task', '版本(End):  ') ?> </div>

        <div class="profile-info-value">
            <span><?php 
			echo $conf['commit_id'];
			echo("  " . $endInfo);
                  ?>
            </span>
        </div>
    </div>
</div>
