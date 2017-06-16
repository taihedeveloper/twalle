<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('walle', 'deploying');
use \app\models\Task;
use yii\helpers\Url;
?>
<style>
    .status > span {
        float: left;
        font-size: 12px;
        width: 14%;
        text-align: right;
    }
    .btn-deploy {
        margin-left: 30px;
    }
    .btn-return {
        /*float: right;*/
        margin-left: 30px;
    }

    /*输出日志*/
    .output-log{
        /*height: 500px;*/
        height: auto;

    }

    .step-log-span{
        float: left;
        width: 1000px;
    }
    .bg{
	background-color: #428bca;
	width: 200px;
    height: 50px;
    line-height:50px;
        text-align: center;
    color: white;
	}
    .no_select{
	background-color:#cacdd0;
	   width: 200px;
    height: 50px;
    color: white;
	}
    #msgbox{
        width: 100%;
        height: 500px;
    }


</style>
<div class="box" style="height: 100%">
    <h4 class="box-title header smaller red">
            <i class="icon-map-marker"></i><?= \Yii::t('w', 'conf_level_' . $task->project['level']) ?>
            -
            <?= $task->project->name ?>
            ：
            <?= $task->title ?>
            （<?= $task->project->repo_mode . ':' . $task->branch ?> <?= yii::t('walle', 'version') ?><?= $task->commit_id ?>）
<label>是否选择并行上线</label>
            <input name="is_bing" value="0"  type="radio" checked class="bing_n">否
    		<input name="is_bing" value="1"  type="radio"  class="bing_y">是
		<?php if (in_array($task->status, [Task::STATUS_PASS, Task::STATUS_FAILED])) { ?>
                <button type="submit" class="btn btn-primary btn-deploy" data-id="<?= $task->id ?>"><?= yii::t('walle', 'deploy') ?></button>
            <?php } ?>
            <a class="btn btn-success btn-return" href="<?= Url::to('@web/task/index') ?>"><?= yii::t('walle', 'return') ?></a>
    </h4>
    <div class="status">
        <span><i class="fa fa-circle-o text-yellow step-1"></i><?= yii::t('walle', 'process_detect') ?></span>
        <span><i class="fa fa-circle-o text-yellow step-2"></i><?= yii::t('walle', 'process_pre-deploy') ?></span>
        <span><i class="fa fa-circle-o text-yellow step-3"></i><?= yii::t('walle', 'process_checkout') ?></span>
        <span><i class="fa fa-circle-o text-yellow step-4"></i><?= yii::t('walle', 'process_post-deploy') ?></span>
        <span><i class="fa fa-circle-o text-yellow step-5"></i><?= yii::t('walle', 'process_rsync') ?></span>
        <span style="width: 28%"><i class="fa fa-circle-o text-yellow step-6"></i><?= yii::t('walle', 'process_update') ?></span>
    </div>
    <div style="clear:both"></div>
    <div class="progress progress-small progress-striped active">
        <div class="progress-bar progress-status progress-bar-success" style="width: <?= $task->status == Task::STATUS_DONE ? 100 : 0 ?>%;"></div>
    </div>

<!--    <div class="output-log">-->
<!--        <textarea id="msgbox" readonly> </textarea>-->
<!--    </div>-->
<div class='start_up' style="display:none">
<?php if($task->action == Task::ACTION_ONLINE){ ?>
<span class='start_one bg' id='s_1' style="display:block">1.部署初始化</span>
<span class='start_two' id='s_2' style="display:none">2.同步文件</span>
<span class='start_three' id='s_3' style="display:none">3.执行脚本并删除备份</span>
<span class='start_four' id='s_4' style="display:none">4.清理宿主机临时文件</span>
<?php }else{ ?>
<span class='start_five bg' id='s_5' style="display:block">回滚代码</span>
<?php }?>
<span style="float:right"><button id='submit_bing'>开始</button><?php if($task->action == Task::ACTION_ONLINE){ ?><button id="bing_next">下一步</button><?php }?><button id="stop">停止刷新</button><button id="restart">刷新日志</button></span>
</div>
<input type="hidden" value="<?=$task->action == Task::ACTION_ONLINE ? 1 : 5 ?>" name="buzhou" id="buzhou" />
<input type="hidden" value="" name="current_ip" id="current_ip" />
    <div class="output-log alert">
        <div class="step-log"></div>
    </div>

    <div class="alert alert-block alert-success result-success" style="<?= $task->status != Task::STATUS_DONE ? 'display: none' : '' ?>">
        <h4><i class="icon-thumbs-up"></i><?= yii::t('walle', 'done') ?></h4>
        <p><?= yii::t('walle', 'done praise') ?></p>

    </div>

    <div class="alert alert-block alert-danger result-failed" style="display: none">
        <h4><i class="icon-bell-alt"></i><?= yii::t('walle', 'error title') ?></h4>
        <span class="error-msg">
        </span>
        <br><br>
        <i class="icon-bullhorn"></i><span><?= yii::t('walle', 'error todo') ?></span>
    </div>

</div>

<script type="text/javascript">
    $(function() {
	$('.bing_y').click(function(){
                $('.status').css('display','none');
		$('.btn-deploy').css('display','none');
                $('.progress').css('display','none');
		$('.start_up').css('display','block');
		
            }); 
            $('.bing_n').click(function(){
                $('.status').css('display','block');
		$('.btn-deploy').removeAttr('style');
                $('.progress').css('display','block');
		$('.start_up').css('display','none');
            });
	var showIPlogTimer;
	$("#submit_bing").click(function(){
	var _bId = $("#buzhou").attr('value');
	var _taskid = $('.btn-deploy').data('id');
	$.post("<?= Url::to('@web/walle/bing-deploy') ?>",{bId:_bId,taskId:_taskid},function(o){
	if(o.code==0){
	    if(o.data=='over'){
		$('.result-success').css('display','block');
	    }
	}
});
	if(_bId!=4){
	    showIPlogTimer = setInterval(showIpLog, 3000);
        }
	});
function showIpLog(){
		$('.step-log').html("");
               var _ip = $('#current_ip').val();
	       var buid = $('.bg').attr('id').split("_");
	       var _taskid = $('.btn-deploy').data('id');
                $.get("<?= Url::to('@web/walle/binglogs?ip=') ?>" + _ip+"&num=1&taskId="+_taskid+"&buid="+parseInt(buid[1]), function (o) {
                    data = o.data;
			var _data = "";
if(o.code==0){
        for(key in o.data){
        var _status='<font color="green">成功!</font>';
        var _chongshi='';
	    var _err_msg = "";
        for(k in o.data[key]){

        var _tmp_attr = o.data[key][k].split('_N_');
        if(parseInt(_tmp_attr[0]) !=0){
           _status = "<font color='red'>失败</font>";
           _chongshi="<button class='reset' data-id="+"'"+key+"'"+">重试</button>";
	       _err_msg = "<p>"+_tmp_attr[3]+"</p>";
        }

        }
           _data += "<span>Server:"+key+"  "+_status+" </span> "+_err_msg+_chongshi+" <br/>";
        }
    	if(_data != ''){
            $('.step-log').html(_data);}
        }
        });
            }
	$("#bing_next").click(function(){
		clearInterval(showIPlogTimer);
		var _id_buzhou = $('.bg').attr('id').split("_");
		var current_num=parseInt(_id_buzhou[1]);
		if(current_num >= 4)
			return false;
		var _id_num = current_num+1;
		$("#buzhou").attr('value',_id_num);
		$("#s_"+current_num).removeClass('bg');
		$("#s_"+_id_num).addClass('bg');
		$("#s_"+current_num).css('display','none');
		$("#s_"+_id_num).css('display','block');
		$('.step-log').html('');
	});
//重试
	$(document.body).delegate('.reset','click',function(){
	var _ip = $(this).data('id');
	var buid = $('.bg').attr('id').split("_");
        var _taskid = $('.btn-deploy').data('id');
	 $.post("<?= Url::to('@web/walle/bing-deploy') ?>",{bId:parseInt(buid[1]),taskId:_taskid,ip:_ip}, function (o) {
 	if(o.code==0){
        }

	});
	});
//停止刷新
$("#stop").click(function(){clearInterval(showIPlogTimer);});
//刷新日志
$("#restart").click(function(){showIPlogTimer = setInterval(showIpLog, 3000);});
        $('.btn-deploy').click(function() {
            $this = $(this);
            $this.addClass('disabled');
            var task_id = $(this).data('id');
            var action = '';
            var detail = '';
            var timer;
            var showLogTimer;
            var msgbox = document.getElementById('msgbox');

            $.post("<?= Url::to('@web/walle/start-deploy') ?>", {taskId: task_id}, function(o) {
                action = o.code ? o.msg + ':' : '';
                if (o.code != 0) {
                    clearInterval(timer);
                    clearInterval(showLogTimer);
                    $('.progress-status').removeClass('progress-bar-success').addClass('progress-bar-danger');
                    $('.error-msg').text(action + detail);
                    $('.result-failed').show();
                    $this.removeClass('disabled');
                }
            });
            $('.progress-status').attr('aria-valuenow', 10).width('10%');
            $('.result-failed').hide();

            function getProcess() {
                $.get("<?= Url::to('@web/walle/get-process?taskId=') ?>" + task_id, function (o) {
                    data = o.data;
                    // 执行失败
                    if (0 == data.status) {
                        clearInterval(timer);
                        clearInterval(showLogTimer);
                        $('.step-' + data.step).removeClass('text-yellow').addClass('text-red');
                        $('.progress-status').removeClass('progress-bar-success').addClass('progress-bar-danger');
                        detail = o.msg + ':' + data.memo + '<br>' + data.command;
                        $('.error-msg').html(action + detail);
                        $('.result-failed').show();
                        $this.removeClass('disabled');
                        return;
                    } else {
                        $('.progress-status').removeClass('progress-bar-danger progress-bar-striped').addClass('progress-bar-success');
                    }
                    if (0 != data.percent) {
                        $('.progress-status').attr('aria-valuenow', data.percent).width(data.percent + '%');

                    }
                    if (100 == data.percent) {
                        $('.progress-status').removeClass('progress-bar-striped').addClass('progress-bar-success');
                        $('.progress-status').parent().removeClass('progress-striped');
                        $('.result-success').show();
                        clearInterval(timer);
                        clearInterval(showLogTimer);
                    }
                    for (var i = 1; i <= data.step; i++) {
                        $('.step-' + i).removeClass('text-yellow text-red').addClass('text-green progress-bar-striped');
                    }
                });
            }

            function showLog(){
                $.get("<?= Url::to('@web/walle/get-log?taskId=') ?>" + task_id, function (o) {
                    data = o.data;
                    //$('.step-log').html(data);
                    //msgbox.value = '';
                    var msg = '';
                    for (var i = 0; i<data.length;i++){
                        //console.log(data[i]);
                        //msgbox.value += data[i];
                        msg += data[i];
                        $('.step-log').html(msg);
                    }
                });
            }

            timer = setInterval(getProcess, 600);
            showLogTimer = setInterval(showLog, 600);

        });

    })

</script>
