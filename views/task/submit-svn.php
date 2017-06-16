<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('task', 'submit task title');
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use app\models\Project;

?>
<style>
    .tooltip-inner {
        max-width: none;
        white-space: nowrap;
        text-align:left;
    }
</style>
<div class="box">
    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
    <div class="box-body">
        <?= $form->field($task, 'title')->label(yii::t('task', 'submit title'), ['class' => 'control-label bolder blue']) ?>
        <!-- 无trunk时，不需要查看所有分支-->
        <?php if ($nonTrunk) { ?>
            <input type="hidden" id="branch" class="form-control" name="Task[branch]" value="">
        <?php } else { ?>
            <!-- 分支选取 -->
            <div class="form-group">
                <label class="control-label bolder blue">
                    <?= yii::t('task', 'select branches') ?>
                    <a class="show-tip icon-refresh green" href="javascript:;"></a>
                    <span class="tip"><?= yii::t('task', 'all branches') ?></span>
                    <i class="get-branch icon-spinner icon-spin orange bigger-125" style="display: none"></i>
                </label>
                <select name="Task[branch]" aria-hidden="true" tabindex="-1" id="branch" class="form-control select2 select2-hidden-accessible">
                    <?php if ($conf->repo_mode == Project::REPO_BRANCH) { ?>
                        <option value="trunk">trunk</option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>
        <div class="between-history" style="display: none">
            <div class="form-group col-xs-3" style="padding-left: 0">
                <label class="control-label bolder blue">commit id start</label>
                <i class="getting-history icon-spinner icon-spin orange bigger-125" style=""></i>
                <select name="Task[start_id]" id="start" class="form-control select2 col-xs-3 history-list">
                </select>
            </div>
            <div class="form-group col-xs-3">
                <label class="control-label bolder blue">commit id end</label>
                <i class="getting-history icon-spinner icon-spin orange bigger-125" style=""></i>
                <select name="Task[commit_id]" id="end" class="form-control select2 col-xs-3 history-list">
                </select>
            </div>
        </div>
        <div class="clearfix"></div>

        <!-- 分支选取 end -->

        <?= $form->field($task, 'file_list')
            ->textarea([
                'rows'           => 12,
                'placeholder'    => 'index.php  1234',
                'data-html'      => 'true',
                'data-placement' => 'top',
                'data-rel'       => 'tooltip',
                'data-title'     => yii::t('task', 'file list placeholder'),
                'style'          => 'overflow:scroll;overflow-y:hidden;;overflow-x:hidden',
                'onchange'       => "window.activeobj=this;this.clock=setInterval(function(){activeobj.style.height=activeobj.scrollHeight+'px';},200);",
                'onblur'         => "clearInterval(this.clock);",
            ])
            ->label(yii::t('task', 'file list')
                . '<a class="icon-magic green show-between-history" data-rel="tooltip" data-placement="top" data-title="'
                . yii::t('task', 'diff tip')
                . '" href="javascript:;"></a>'
                . '<i class="getting-change-files icon-spinner icon-spin orange bigger-125" style="display: none"></i>',
                ['class' => 'control-label bolder blue']) ?>


        <div class="form-group" id="file-diff">
        </div>

    </div><!-- /.box-body -->

    <!--文件diff-->
    <div id="diff-box" style="display: none;">
        <div id="mergely-resizer" style="height: 500px;">
            <div id="compare">
            </div>
        </div>
    </div>
    <!--文件diff-->

    <div class="box-footer">
        <input type="submit" class="btn btn-primary" value="<?= yii::t('w', 'submit') ?>">
    </div><br>

    <!-- 错误提示-->
    <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="800px">
                <div class="modal-header">
                    <button type="button" class="close"
                            data-dismiss="modal" aria-hidden="true">
                        &times;
                    </button>
                    <h4 class="modal-title" id="myModalLabel">
                        <?= yii::t('w', 'modal error title') ?>
                    </h4>
                </div>
                <div class="modal-body"></div>
            </div><!-- /.modal-content -->
        </div>
    </div>
    <!-- 错误提示-->



    <?php ActiveForm::end(); ?>
</div>

<script type="text/javascript">
    jQuery(function($) {
        $('[data-rel=tooltip]').tooltip({container:'body'});

        var projectId =  <?= (int)$_GET['projectId'] ?>;
        // 用户上次选择的分支作为转为分支
        var branch_name= 'pre_branch_' + projectId;
        var pre_branch = ace.cookie.get(branch_name);
        if (pre_branch) {
            var option = '<option value="' + pre_branch + '" selected>' + (pre_branch ? pre_branch : 'non-trunk') + '</option>';
            $('#branch').html(option);
            getCommitList();
        }
        // 无trunk时，直接获取commit log
        if (!$('#branch').val()) {
            getCommitList();
        }

        function getBranchList() {
            $('.get-branch').show();
            $('.tip').hide();
            $('.show-tip').hide();
            $.get("<?= Url::to('@web/walle/get-branch?projectId=') ?>" + projectId, function (data) {
                // 获取分支失败
                if (data.code) {
                    showError(data.msg);
                }
                var select = '';
                var nonTrunk = false;
                var count = 0;
                $.each(data.data, function (key, value) {
                    // 默认选中 trunk 主干
                    var checked = value.id == 'trunk' ? 'selected' : '';
                    select += '<option value="' + value.id + '"' + checked + '>' + value.message + '</option>';
                    nonTrunk = ++count == 1 && value.id == '';
                });
                if (nonTrunk) {
                    // 添加cookie记住最近使用的分支名字
                    ace.cookie.set(branch_name, '', 86400*30);
                }
                $('#branch').html(select);
                $('.get-branch').hide();
                $('.show-tip').show();
                getCommitList();
            });
        }
        // 获取commit log
        function getCommitList() {
            $('.getting-history').show();
            $.get("<?= Url::to('@web/walle/get-commit-history?projectId=') ?>" + projectId +"&branch=" + $('#branch').val(), function (data) {
                // 获取commit log失败
                if (data.code) {
                    showError(data.msg);
                }

                var select = '';
                $.each(data.data, function (key, value) {
                    select += '<option value="' + value.id + '">' + value.id + ' - ' + value.message + '</option>';
                });
                $('.history-list').html(select);
                $('.getting-history').hide();
            });
        }

        function getChangeFiles(projectId, branch, start, end) {
            $.get("<?= Url::to('@web/walle/get-commit-file?projectId=') ?>" + projectId +"&branch=" + branch + "&start=" + start + "&end=" + end, function (data) {
                // 获取commit log失败
                if (data.code) {
                    showError(data.msg);
                }

                var files = '';
                $.each(data.data, function (key, value) {
                    files += value + "\n";
                });
                $('#task-file_list').html(files);
                $('.getting-change-files').hide();
                getContent();
            });
        }

        $('#branch').change(function() {
            // 添加cookie记住最近使用的分支名字
            ace.cookie.set(branch_name, $(this).val(), 86400*30);
            getCommitList();
        });

        // 选择两个commit_id之间提交的文件
        $('.history-list').change(function() {
            var startId = $('#start').val();
            var endId   = $('#end').val();
            $('.getting-change-files').show();
            getChangeFiles(projectId, $('#branch').val(), startId, endId);
        });

        $('.show-between-history').click(function() {
            $('.between-history').show();
        });

        // 页面加载完默认拉取trunk
        // getBranchList();
        // 页面加载完默认拉取trunk
        if ($('#branch').val()) {
            // getCommitList();
        }

        // 查看所有分支提示
        $('.show-tip').hover(function() { $('.tip').show()}, function() { $('.tip').hide();}).click(function() {getBranchList();});

        // 错误提示
        function showError($msg) {
            $('.modal-body').html($msg);
            $('#myModal').modal({
                backdrop: true,
                keyboard: true,
                show: true
            });
        }

        // 清除提示框内容
        $("#myModal").on("hidden.bs.modal", function () {
            $(this).removeData("bs.modal");
        });


        $('#task-file_list').blur(function(){

        });

        $('#file-diff').on("click",".diff",function () {
            var filepath;
            $('#diff-box').show();
            filepath = $(this).data('param');
            $.get("<?= Url::to('@web/walle/get-file-content?projectId=') ?>" + projectId + "&filepath=" + filepath, function (data) {
                // 获取文件内容失败
                if (data.code) {
                    showError(data.msg);
                }
                var data = data.data;

                //文件diff
                $('#compare').mergely({
                    width: 'auto',
                    height: 'auto',
                    cmsettings: { readOnly: false },
                });
                $('#compare').mergely('lhs', data.local_file_content);
                $('#compare').mergely('rhs', data.remot_file_content);

            });
        });

        function getContent(){
            var file = $('#task-file_list').val();
            if(file === '*'){
            }else {
                var fileArr = file.split('\n');
                for (var i = 0; i < fileArr.length; i++){
                    if(fileArr[i] === ''){
                        fileArr.splice(i,1);
                        i--;
                    }
                }

                var newFileArr = JSON.stringify(fileArr);

                $.get("<?= Url::to('@web/walle/get-file-md5?projectId=') ?>" + projectId + "&filepath=" + newFileArr, function (data) {
                    // 获取文件内容失败
                    if (data.code) {
                        showError(data.msg);
                    }
                    var data = data.data;

                    var html = '<label class="control-label bolder blue">文件diff</label><br>';
                    html += '<table class="table"><tr><th width="5%">文件</th><th width="10%">md5</th><th width="10%">操作</th></tr>';
                    for (var i = 0; i < data.length; i++){
                        html += '<tr><td>' + data[i].filepath + '</td><td>' + data[i].md5 + '</td><td><a href="javascript:void(0);" class="btn btn-success diff" data-param="' + data[i].filepath + '">diff</a></td></tr>';
                    }
                    html += '</table>';
                    $('#file-diff').html(html);
                });
            }
        }




    });

</script>
