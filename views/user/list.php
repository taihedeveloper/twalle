<?php
/**
 * @var yii\web\View $this
 */
$this->title = yii::t('user', 'list title');
use yii\helpers\Url;
use yii\widgets\LinkPager;
use app\models\User;
?>
<div class="box">
    <div class="box-header">
        <form action="/user/list" method="POST">
            <input type="hidden" value="<?= \Yii::$app->request->getCsrfToken(); ?>" name="_csrf">
            <div class="col-xs-12 col-sm-8" style="padding-left: 0;margin-bottom: 10px;">
                <div class="input-group">
                    <input type="text" name="kw" class="form-control search-query" placeholder="<?= yii::t('user', 'list placeholder') ?>">
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
        <a class="btn btn-default btn-sm" href="<?= Url::to('@web/user/add') ?>">
            <i class="icon-pencil align-top bigger-125"></i>
            <?= yii::t('user', 'create user') ?>
        </a>
    </div><!-- /.box-header -->
    <div class="box-body table-responsive no-padding clearfix">
        <table class="table table-striped table-bordered table-hover">
            <tbody><tr>
                <th><?= yii::t('user', 'l_id') ?></th>
                <th><?= yii::t('user', 'l_username') ?></th>
                <th><?= yii::t('user', 'l_realname') ?></th>
                <th><?= yii::t('user', 'l_email') ?></th>
                <th><?= yii::t('user', 'l_role') ?></th>
                <th><?= yii::t('user', 'l_opera') ?></th>
            </tr>
            <?php foreach ($userList as $item) { ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><?= $item['username'] ?></td>
                    <td><?= $item['realname'] ?></td>
                    <td><?= $item['email'] ?></td>
                    <td>

                        <?php if ($item['role'] == User::ROLE_ADMIN) { ?>
                            <i class="icon icon-user-md green" data-placement="top" data-rel="tooltip" data-title="<?= yii::t('w', 'user_role_' . User::ROLE_ADMIN) ?>"></i>
                            管理员
                        <?php } elseif($item['role'] == User::ROLE_DEV) { ?>
                            <i class="icon icon-user" data-placement="top" data-rel="tooltip" data-title="<?= yii::t('w', 'user_role_' . User::ROLE_DEV) ?>"></i>
                            开发者
                        <?php }elseif($item['role'] == User::SUPER_ADMIN){ ?>
							<i class="icon icon-user-md green" data-placement="top" data-rel="tooltip" data-title="<?= yii::t('w', 'user_role_' . User::SUPER_ADMIN)     ?>"></i>   超级管理员
						<?php }?>

                    </td>
                    <td>

                        <div class="nav">
                            <li>
                                <a data-toggle="dropdown" class="dropdown-toggle" href="javascript:void();">
                                    <i class="icon-cog"></i>&nbsp;<?= yii::t('w', 'option') ?>
                                    <i class="icon-caret-down bigger-110 width-auto"></i>
                                </a>
                                <ul class="dropdown-menu data-user"
                                    data-user-id="<?= $item['id']?>"
                                    data-user-realname="<?= $item['realname']?>"
                                    data-user-email="<?= $item['email']?>"
                                    data-modal-title="<?= yii::t('user', 'change password') ?>"
                                    data-change-password-url="<?= Url::to('@web/user/change-password') ?>"
                                    data-role-url="<?= $item['role'] == User::ROLE_ADMIN || $item['role'] == User::SUPER_ADMIN ? Url::to('@web/user/to-dev') : Url::to('@web/user/to-admin') ?>"
                                    data-delete-url="<?= Url::to('@web/user/delete') ?>"
                                >
                                    <li><a href="<?= Url::to("@web/user/edit?userId={$item['id']}") ?>"><i class="icon-pencil"></i> <?= yii::t('w', 'edit') ?></a></li>
                                    <li><a href="###" class="cnt-user-option" data-url-key="delete-url" data-confirm="<?= yii::t('user', 'js delete user') ?>"><i class="icon-trash"></i> <?= yii::t('w', 'delete') ?></a></li>
                                    <li><a href="###" data-toggle="modal" data-target="#change-password"><i class="icon-key"></i> <?= yii::t('user', 'change password') ?></a></li>
                                    <li class="divider"></li>
									<?php if($item['role']!=User::SUPER_ADMIN){ ?>
                                    <li><a href="###" class="cnt-user-option" data-url-key="role-url" data-confirm="<?= yii::t('user', 'label role to opposite ' . $item['role']) ?>"><i class="i"></i> <?= yii::t('user', 'role to opposite ' . $item['role']) ?></a></li>
									<?php }?>
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
        <div class="modal fade" id="change-password" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="password" class="control-label"><?= yii::t('user', 'label password') ?>:</label>
                            <input type="password" class="form-control" id="password">
                            <input name="_csrf" type="hidden" id="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?= yii::t('user', 'cancel') ?></button>
                        <button type="button" class="btn btn-primary btn-submit"><?= yii::t('user', 'save') ?></button>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.box-body -->
</div>

<script>
    jQuery(function($) {

        $('[data-rel=tooltip]').tooltip({container:'body'});
        $('[data-rel=popover]').popover({container:'body'});

        $('.cnt-user-option').click(function(e) {
            var uid = $(this).parents('.data-user').data('user-id');
            var urlKey = $(this).data('url-key')
            var url = $(this).parents('.data-user').data(urlKey);
            var confirmLabel = $(this).data('confirm')
            if (confirm(confirmLabel)) {
                $.get(url, {uid: uid}, function(o) {
                    if (!o.code) {
                        location.reload();
                    } else {
                        alert(o.msg);
                    }
                })
            }
        });

        $('#change-password').on('show.bs.modal', function (e) {
            var me = $(this),
                srcTar = $(e.relatedTarget).parents('.data-user'),
                modalTit = me.find('.modal-title'),
                uid = srcTar.attr('data-user-id'),
                email = srcTar.attr('data-user-email'),
                realname = srcTar.attr('data-user-realname'),
                title = srcTar.attr('data-modal-title'),
                url = srcTar.attr('data-change-password-url'),
                subBtn = me.find('.btn-submit'),
                password = me.find('#password'),
                csrfToken = me.find('#_csrf');

            modalTit.html('');
            modalTit.html(title + '：' + realname);

            subBtn.click(function () {

                if(password.val() == ''){
                    alert('密码不能为空!');
                    return false;
                }

                $.get(url, {uid: uid, password: password.val(), _csrf:csrfToken.val()}, function(o) {
                    if (!o.code) {
                        alert('修改成功!');
                        location.reload();
                    } else {
                        alert(o.msg);
                    }
                });



            });


        });






    });
</script>