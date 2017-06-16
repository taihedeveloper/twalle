# Twalle
## 概述：
太合瓦力——一款基于开源walle定制的项目部署和发布平台，具备配置简单、功能完善、界面流畅、开箱即用！支持svn版本上线，支持各种代码发布，静态的HTML，动态PHP,新增用户组权限管理、代码diff功能、并发发布等功能
## 原有功能：
支持svn版本管理<br>
用户分身份注册、登录（需要接入CAS单点登录）<br>
开发者发起上线任务申请、部署<br>
管理者审核上线任务<br>
支持多项目部署<br>
支持多项目多任务并行<br>
快速回滚<br>
项目的用户权限管理<br>
部署前准备任务pre-deploy（前置检查）<br>
代码检出后处理任务post-deploy（如vendor）<br>
同步后更新软链前置任务pre-release<br>
发布完毕后收尾任务post-release（如重启）<br>
线上文件指纹确认<br>
多机器并发传输文件(Ansible)<br>
## 新增功能：
支持权限组（用户组）以及项目权限分配<br>
用户注册登录改造为CAS登录方式<br>
支持ftp文件上线（支持解压制定文件及文件夹上线）<br>
改造备份策略（可选基于上线文件的备份或全项目备份）<br>
支持串行和并行发布项目<br>
支持查看SVN上线历史记录和查看、下载错误日志<br>
支持svn上线diff功能（本地代码库和线上目录文件diff功能）<br>
拆分日志目录（以不同项目上线单为单位）<br>
## 依赖
Bash(git、ssh)
LNMP/LAMP(php5.4+)
Composer
Ansible(可选)
## 安装
git clone git@github.com:meolu/walle-web.git
cd walle-web
vi config/web.php # 设置mysql连接
composer install  # 如果缺少bower-asset的话， 先安装：composer global require "fxp/composer-asset-plugin:*"
./yii walle/setup # 初始化项目
配置nginx/apache的webroot指向walle-web/web，简单范例详见页面底部常见问题和解决办法。
