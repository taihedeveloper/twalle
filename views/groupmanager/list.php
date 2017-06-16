<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('gm', 'list title');
use yii\helpers\Url;
use yii\widgets\LinkPager;
use app\models\User;
?>
<div class="box">
    <div class="box-header">
        <form action="/groupmanager/list" method="POST">
            <input type="hidden" value="<?= \Yii::$app->request->getCsrfToken(); ?>" name="_csrf">
            <div class="col-xs-12 col-sm-8" style="padding-left: 0;margin-bottom: 10px;">
                <div class="input-group">
                    <input type="text" name="kw" class="form-control search-query" placeholder="<?= yii::t('gm', 'list placeholder') ?>">
                    <span class="input-group-btn">
                        <button type="submit"
                                class="btn btn-default btn-sm">
                            Search
                            <i class="icon-search icon-on-right bigger-110"></i>
                        </button>
                    </span>
                </div>
            </div>
        </form>
        <a class="btn btn-default btn-sm" href="<?= Url::to('@web/groupmanager/add') ?>">
            <i class="icon-pencil align-top bigger-125"></i>
            <?=yii::t('gm','addgroup')?>
        </a>
    </div><!-- /.box-header -->
    <div class="box-body table-responsive no-padding clearfix">
        <table class="table table-striped table-bordered table-hover">
            <tbody><tr>
                <th><?= yii::t('gm', 'l_id') ?></th>
                <th><?= yii::t('gm', 'l_groupname') ?></th>
                <th>操作</th>
            </tr>
            <?php foreach ($groupList as $item) { ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><?= $item['group_name'] ?></td>
                    <td>
                        <div class="nav">
                            <li>
                                <a data-toggle="dropdown" class="dropdown-toggle" href="javascript:void();">
                                    <i class="icon-cog"></i>&nbsp;<?= yii::t('gm', 'option') ?>
                                    <i class="icon-caret-down bigger-110 width-auto"></i>
                                </a>
                                <ul class="dropdown-menu data-user"
                                    data-group-id="<?= $item['id']?>"
                                    data-group-name="<?= $item['group_name']?>"
                                    data-delete-url="<?= Url::to('@web/groupmanager/delete') ?>"
                                >
                                    <li><a href="<?= Url::to("@web/groupmanager/edit?gid={$item['id']}") ?>"><i class="icon-pencil"></i> <?=yii::t('gm','l_edit')?></a></li>
                                    <li><a href="###" class="cnt-user-option" data-url-key="delete-url" data-confirm="<?=yii::t('gm','l_confirm')?>"><i class="icon-trash"></i> <?=yii::t('gm','l_delete')?></a></li>
<li><a href="<?= Url::to("@web/groupmanager/permission?gid={$item['id']}") ?>"><i class="icon-key"></i><?=yii::t('gm','perm')?></a></li>
                                </ul>
                            </li>

                        </div>

                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?= LinkPager::widget(['pagination' => $pages]); ?>
        <!-- 模态框（Modal） -->

    </div><!-- /.box-body -->
</div>

<script>
    jQuery(function($) {

        $('[data-rel=tooltip]').tooltip({container:'body'});
        $('[data-rel=popover]').popover({container:'body'});

        $('.cnt-user-option').click(function(e) {
            var uid = $(this).parents('.data-user').data('group-id');
            var urlKey = $(this).data('url-key')
            var url = $(this).parents('.data-user').data(urlKey);
            var confirmLabel = $(this).data('confirm')
            if (confirm(confirmLabel)) {
                $.get(url, {gid: uid}, function(o) {
                    if (!o.code) {
                        location.reload();
                    } else {
                        alert(o.msg);
                    }
                })
            }
        });


    });
</script>
