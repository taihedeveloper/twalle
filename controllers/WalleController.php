<?php
/* *****************************************************************
 * @Author: wushuiyong
 * @Created Time : 一  9/21 13:48:30 2015
 *
 * @File Name: WalleController.php
 * @Description:
 * *****************************************************************/

namespace app\controllers;

use app\components\Repo;
use yii;
use yii\data\Pagination;
use app\components\Command;
use app\components\GlobalHelper;
use app\components\Folder;
use app\components\Git;
use app\components\Task as WalleTask;
use app\components\Controller;
use app\models\Task;
use app\models\Record;
use app\models\Project;
use app\models\User;

class WalleController extends Controller {

    /**
     * 项目配置
     */
    protected $conf;

    /**
     * 上线任务配置
     */
    protected $task;

    /**
     * Walle的高级任务
     */
    protected $walleTask;

    /**
     * Walle的文件目录操作
     */
    protected $walleFolder;

    public $enableCsrfValidation = false;


    /**
     * 发起上线
     *
     * @throws \Exception
     */
    public function actionStartDeploy() {
        $taskId = \Yii::$app->request->post('taskId');
        if (!$taskId) {
            $this->renderJson([], -1, yii::t('walle', 'deployment id is empty'));
        }
        $this->task = Task::findOne($taskId);
        if (!$this->task) {
            throw new \Exception(yii::t('walle', 'deployment id not exists'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        // 任务失败或者审核通过时可发起上线
        if (!in_array($this->task->status, [Task::STATUS_PASS, Task::STATUS_FAILED])) {
            throw new \Exception(yii::t('walle', 'deployment only done for once'));
        }
        // 清除历史记录
        Record::deleteAll(['task_id' => $this->task->id]);

        // 项目配置
        $this->conf = Project::getConf($this->task->project_id);
        $this->walleTask   = new WalleTask($this->conf);
        $this->walleFolder = new Folder($this->conf);
        try {
            if ($this->task->action == Task::ACTION_ONLINE) {//上线
                $this->_makeVersion();                             //产生一个上线版本
                $this->_initWorkspace();                             //权限、目录检查
                $this->_preDeploy();                            //部署前置触发任务
                $this->_gitUpdate();                                //更新代码文件
                $this->_postDeploy();                            //部署后置触发任务
                $this->_rsync();                                   //同步文件到服务器
                $this->_updateRemoteServers($this->task->link_id);  //执行远程服务器任务集合
                $this->_cleanRemoteReleaseVersion();                //只保留最大版本数，其余删除过老版本
                $this->_cleanUpLocal($this->task->link_id);       //收尾工作，清除宿主机的临时部署空间
            } else {//回滚
               
                $this->_rollback($this->task->ex_link_id);
            }

            /** 至此已经发布版本到线上了，需要做一些记录工作 */

            // 记录此次上线的版本（软链号）和上线之前的版本
            ///对于回滚的任务不记录线上版本
            if ($this->task->action == Task::ACTION_ONLINE) {
                //$this->task->ex_link_id = $this->conf->version;
                //将软链ID作为回滚ID
                $this->task->ex_link_id = $this->task->link_id;
            }
            
            // 第一次上线的任务不能回滚、回滚的任务不能再回滚
            if ($this->task->action == Task::ACTION_ROLLBACK || $this->task->id == 1) {
                $this->task->enable_rollback = Task::ROLLBACK_FALSE;
            }
            $this->task->status = Task::STATUS_DONE;
            $this->task->save();

            // 可回滚的版本设置
            $this->_enableRollBack();
            
            // 记录当前线上版本（软链）回滚则是回滚的版本，上线为新版本
            $this->conf->version = $this->task->link_id;
            $this->conf->save();
        } catch (\Exception $e) {
            $this->task->status = Task::STATUS_FAILED;
            $this->task->save();
            // 清理本地部署空间
            $this->_cleanUpLocal($this->task->link_id);

            throw $e;
        }
        $this->renderJson([]);
    }
/**
*并行上线入口
*
*/
public function actionBingDeploy(){
	$taskId = \Yii::$app->request->post('taskId');
	$buzhou_id = \Yii::$app->request->post('bId');
	$ip = \Yii::$app->request->post('ip');
	$server_ip = "";
	if(!empty($ip))
	{
	    $server_ip = $ip;
	}
        if (!$taskId) {
            $this->renderJson([], -1, yii::t('walle', 'deployment id is empty'));
        }
        $this->task = Task::findOne($taskId);
        if (!$this->task) {
            throw new \Exception(yii::t('walle', 'deployment id not exists'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        // 任务失败或者审核通过时可发起上线
        if (!in_array($this->task->status, [Task::STATUS_PASS, Task::STATUS_FAILED])) {
            throw new \Exception(yii::t('walle', 'deployment only done for once'));
        }
        // 清除历史记录
        Record::deleteAll(['task_id' => $this->task->id]);
        // 项目配置
        $this->conf = Project::getConf($this->task->project_id);
        $this->walleTask   = new WalleTask($this->conf);
        $this->walleFolder = new Folder($this->conf);

	if ($this->task->action == Task::ACTION_ONLINE) {//上线
	    if($buzhou_id==1){
	        if(!$server_ip){
 	            $this->_makeVersion();
		}                             //产生一个上线版本
 		$this->_initiBingWorkspace($server_ip);
	    }elseif($buzhou_id==2){
 		$this->_preDeploy();
		$this->_gitUpdate();
		$this->_postDeploy();                            //部署后置触发任务
		$this->_rsyncBing($server_ip);                                   //同步文件到服务器
	    }elseif($buzhou_id==3){
		$this->_updateRemoteServersBing($this->task->link_id,$server_ip);
		$this->_cleanBingReleaseVersion($this->task,$buzhou_id,$server_ip);
	    }elseif($buzhou_id==4){
		$this->_cleanUpLocal($this->task->link_id);
	    }
	}else{
	   //回滚
 	    $this->_rollbackbing($this->task->ex_link_id,$server_ip);
	}

if($buzhou_id < 4 && $this->task->action == Task::ACTION_ONLINE){
$this->renderJson("goon");

}

            /** 至此已经发布版本到线上了，需要做一些记录工作 */

            // 记录此次上线的版本（软链号）和上线之前的版本
            ///对于回滚的任务不记录线上版本
            if ($this->task->action == Task::ACTION_ONLINE) {
                //$this->task->ex_link_id = $this->conf->version;
                //将软链ID作为回滚ID
                $this->task->ex_link_id = $this->task->link_id;
            }
            
            // 第一次上线的任务不能回滚、回滚的任务不能再回滚
            if ($this->task->action == Task::ACTION_ROLLBACK || $this->task->id == 1) {
                $this->task->enable_rollback = Task::ROLLBACK_FALSE;
            }
            $this->task->status = Task::STATUS_DONE;
            $this->task->save();

            // 可回滚的版本设置
            $this->_enableRollBack();
            
            // 记录当前线上版本（软链）回滚则是回滚的版本，上线为新版本
            $this->conf->version = $this->task->link_id;
            $this->conf->save();
        $this->renderJson("over");

}
public function createCmdShell($taskid,$conf,$server_ip=''){
	if (empty(\Yii::$app->params['log.dir'])) return;
        $logDir = \Yii::$app->params['log.dir'];
        if (!file_exists($logDir)) return;
	if($server_ip){
		$shFile = realpath($logDir) . '/'.$taskid.'_'.$server_ip.'.sh';
	}else{
        	$shFile = realpath($logDir) . '/'.$taskid.'.sh';
	}
	if(file_exists($shFile)){
	    //return;
	}
        $files_c = fopen($shFile, 'w');
	$ip_attr='(';
	if($server_ip){
	    $ip_attr .=$server_ip;
	}else{
	    foreach(GlobalHelper::str2arr($conf->hosts) as $remoteHost){
	        $ip_attr .=$remoteHost." ";
	    }
	}
	$ip_attr =trim($ip_attr," ").")";
//cmd=$(cat '.$taskid.'.log)'	
//	$content="#!/bin/bash \n"."ipattr=".$ip_attr."; \n ".'length=${#ipattr[*]}'."; \n".' cmd=$(cat '.$taskid.'.log)'."; \n for((i=0;i<length;i++)) ;do \n";
//	$content.="nohup ssh -T -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no ".$conf->release_user."@".'${ipattr[$i]}'.' $cmd ';
//	$content.='&& echo $?_N_$1_N_'.$taskid.'_N_success >>'.realpath($logDir).'/${ipattr[$i]}.log || echo $?_N_$1_N_'.$taskid.'_N_$cmd >>'.realpath($logDir).'/${ipattr[$i]}.log &';
//	$content.="\n done";
//	fwrite($files_c, $content . PHP_EOL);
//	fclose($files_c);
}
public function actionBinglogs($ip='',$num=1,$taskId,$buid)
{	
	$this->task = Task::findOne($taskId);
	if(empty($this->task))
	{
	$this->renderJson([]);
	}
	$this->conf = Project::getConf($this->task->project_id);
	$this->walleFolder = new Folder($this->conf);
	$ip_attr = array();
	if($ip)
	    $ip_attr= array($ip);
	$log_data = $this->walleFolder->initGetIpLog($ip_attr,$num,$taskId,$buid);
	$this->renderJson($log_data);
} 
    /**
     * 提交任务
     *
     * @return string
     */
    public function actionCheck() {
        $projects = Project::find()->asArray()->all();
        return $this->render('check', [
            'projects' => $projects,
        ]);
    }

    /**
     * 项目配置检测，提前发现配置不当之处。
     *
     * @return string
     */
    public function actionDetection($projectId) {
        $project = Project::getConf($projectId);
        $log = [];
        $code = 0;

        // 本地git ssh-key是否加入deploy-keys列表
        $revision = Repo::getRevision($project);
        try {
            $ret = $revision->updateRepo();
            if (!$ret) {
                $code  = -1;
                $error = $project->repo_type == Project::REPO_GIT
                    ? yii::t('walle', 'ssh-key to git')
                    : yii::t('walle', 'correct username passwd');
                $log[] = yii::t('walle', 'hosted server error', [
                    'user'       => getenv("USER"),
                    'path'       => $project->deploy_from,
                    'ssh_passwd' => $error,
                    'error'      => $revision->getExeLog(),
                ]);
            }
        } catch (\Exception $e) {
            $code = -1;
            $log[] = yii::t('walle', 'hosted server sys error', [
                'error' => $e->getMessage()
            ]);
        }

        // 权限与免密码登录检测
        $this->walleTask = new WalleTask($project);
        try {
            $command = sprintf('mkdir -p %s', Project::getReleaseVersionDir('detection'));
            $ret = $this->walleTask->runRemoteTaskCommandPackage([$command]);
            if (!$ret) {
                $code = -1;
                $log[] = yii::t('walle', 'target server error', [
                    'local_user'  => getenv("USER"),
                    'remote_user' => $project->release_user,
                    'path'        => $project->release_to,
                    'error'       => $this->walleTask->getExeLog(),
                ]);
            }
            // 清除
            $command = sprintf('rm -rf %s', Project::getReleaseVersionDir('detection'));
            $this->walleTask->runRemoteTaskCommandPackage([$command]);
        } catch (\Exception $e) {
            $code = -1;
            $log[] = yii::t('walle', 'target server sys error', [
                'error' => $e->getMessage()
            ]);
        }

        // task 检测todo...

        if ($code === 0) {
            $log[] = yii::t('walle', 'project configuration works');
        }
        $this->renderJson(join("<br>", $log), $code);
    }

    /**
     * 获取线上文件md5
     *
     * @param $projectId
     */
    public function actionFileMd5($projectId, $file) {
        // 配置
        $this->conf = Project::getConf($projectId);

        $this->walleFolder = new Folder($this->conf);
        $projectDir = $this->conf->release_to;
        $file = sprintf("%s/%s", rtrim($projectDir, '/'), $file);

        $this->walleFolder->getFileMd5($file);
        $log = $this->walleFolder->getExeLog();

        $this->renderJson(join("<br>", explode(PHP_EOL, $log)));
    }

    /**
     * 获取branch分支列表
     *
     * @param $projectId
     */
    public function actionGetBranch($projectId) {
        $conf = Project::getConf($projectId);

        $version = Repo::getRevision($conf);
        $list = $version->getBranchList();

        $this->renderJson($list);
    }

    /**
     * 获取commit历史
     *
     * @param $projectId
     */
    public function actionGetCommitHistory($projectId, $branch = 'master',$file_name='') {
 		$conf = Project::getConf($projectId);
        $revision = Repo::getRevision($conf);
        if ($conf->repo_mode == Project::REPO_TAG && $conf->repo_type == Project::REPO_GIT) {
            $list = $revision->getTagList();
        } else {
            $list = $revision->getCommitList($branch,20,$file_name);
        }
        $this->renderJson($list);
    }

    /**
     * FTP 目录解压
     *
	 * @param $projectId 
     * @param $pack_name
     */
    public function actionGetTarFile($projectId,$pack_name){
    	//判断压缩包类型
        error_reporting(E_ALL & ~E_NOTICE);
    	//$_tar_type = array('zip','gz','tar');
        $f_list = array();
        $files = array();
    	$type = explode('.',$pack_name);
    	$len = count($type);
    	if($len <=1)
    		throw new \Exception('请检查名字是否是正确的压缩包格式，只支持zip,tar.gz');
    
		$conf = Project::getConf($projectId);
        $revision = Repo::getRevision($conf);
        $local_path_dir = Project::getDeployFromDir();
		$date_dir = date("YmdH",time()).$pack_name;
        $focus_pathname=$local_path_dir.'/'.$date_dir;
        $_tar_type = strtolower($type[$len-1]);    
        $list = $revision->packTar($_tar_type,$local_path_dir.'/'.$pack_name,$focus_pathname);	
       
		if($list)
    	{
    		$files = $this->getFileList($focus_pathname);

           
			//$f_list = $this->arr_foreach($files,$date_dir);

            $f_list = $this->arr_foreach_file_path($files,$date_dir);
        
    	}
		
		$this->renderJson($f_list);
    }

	/**
     * 遍历文件夹
     * 
     * @param dir_name
     */
    public function getFileList($dir_name){
    	if (is_dir($dir_name)) {
    		if ($dh = opendir($dir_name)) {
    			while (($file = readdir($dh)) !== false) {
    				if ($file!="." && $file!="..") {
    					$current_path = $dir_name.'/'.$file;

    					if(is_dir($dir_name.'/'.$file))
    					{
    						//$list[] = $this->getFileList($dir_name.'/'.$file);
                            $list['dir'][$current_path] = $this->getFileList($dir_name.'/'.$file);
    					}else{ 
    						//$list[] = $current_path;
                            $list['file'][] = $current_path;
    					}
    				}
    				}
    			}	
    			closedir($dh);
    		}
           
    		return $list;
    }


    /**
     *获取文件夹内列表
     *
     *@param $multi
     *@param $date_dir
     */
    function arr_foreach_file_path($multi,$date_dir)
    {
        $arr = array();
        foreach ($multi as $key => $val) {
            if( is_array($val) ) {
                if(strchr($key,'/'))
                {
                    //获取前几个目录文件代码，不加level比对，则显示全部的文件夹
                    //$_tmp_keys = explode($date_dir.'/',$key);
                    //$key_date_dir = explode('/',$_tmp_keys[1]);
                   // if(isset($key_date_dir) && !empty($key_date_dir))
                    //    $key_level = count($key_date_dir);
                    //if(isset($key_level) && $key_level < 3)
                        $arr[strchr($key,$date_dir)] = strchr($key,$date_dir).'/';
                }
                
                $arr = array_merge($arr, $this->arr_foreach_file_path($val,$date_dir));
            } else {
               
                $arr[$key][] = strchr($val,$date_dir);
            }
        }
        return $arr;
    }

	
	/**
     * 将多维数组转为一维数组
     * 
     * @param $multi
     * @param $date_dir
     */
    public function arr_foreach($multi,$date_dir)
    {
    	$arr = array();
    	foreach ($multi as $key => $val) {
    		if( is_array($val) ) {
    			$arr = array_merge($arr, $this->arr_foreach($val,$date_dir));
    		} else {
    			$arr[] = strchr($val,$date_dir);
    		}
    	}
    	return $arr;
    }

	/**
     * 获取commit之间的文件
     *
     * @param $projectId
     */
    public function actionGetCommitFile($projectId, $start, $end, $branch = 'trunk') {
        $conf = Project::getConf($projectId);
        $revision = Repo::getRevision($conf);
        $list = $revision->getFileBetweenCommits($branch, $start, $end);

        $this->renderJson($list);
    }

    /**
     * 上线管理
     *
     * @param $taskId
     * @return string
     * @throws \Exception
     */
    public function actionDeploy($taskId) {
        $this->task = Task::find()
            ->where(['id' => $taskId])
            ->with(['project'])
            ->one();
        if (!$this->task) {
            throw new \Exception(yii::t('walle', 'deployment id not exists'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }

        return $this->render('deploy', [
            'task' => $this->task,
        ]);
    }

    /**
     * 获取上线进度
     *
     * @param $taskId
     */
    public function actionGetProcess($taskId) {
        $record = Record::find()
            ->select(['percent' => 'action', 'status', 'memo', 'command'])
            ->where(['task_id' => $taskId,])
            ->orderBy('id desc')
            ->asArray()->one();
        $record['memo'] = stripslashes($record['memo']);
        $record['command'] = stripslashes($record['command']);

        $this->renderJson($record);
    }

    /**
     * 获取上线日志
     *
     * @param $taskId
     */
    public function actionGetLog($taskId){
        $record = Record::find()
            ->select(['command', 'memo', 'created_at', 'status'])
            ->where(['task_id' => $taskId,])
            ->orderBy('action asc')->all();

        $log_list = array();

        /*
        //textarea
        for($i=0;$i<count($record);$i++){
            $log = '';
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ---------------------------------------------------------------------------------------------------\n";
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ------ Executing: $ ".stripslashes($record[$i]['command'])."\n";
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ------ ".stripslashes($record[$i]['memo'])."\n";
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ---------------------------------------------------------------------------------------------------\n\n";

            $log_list[$i] = $log;

        }
        */

        //div
        for($i=0;$i<count($record);$i++){
            $log = '';
            if($record[$i]['status'] == 0){
                $log.= "<span style='color: red'>";
            }
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ---------------------------------------------------------------------------------------------------<br>";
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])."<span style='color:#000000'> ------ Executing: $ ".stripslashes($record[$i]['command'])."</span><br>";
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ------ ".stripslashes($record[$i]['memo'])."<br>";
            $log.= date('Y-m-d H:i:s',$record[$i]['created_at'])." ---------------------------------------------------------------------------------------------------<br>";
            if($record[$i]['status'] == 0){
                $log.= "</span>";
            }

            $log_list[$i] = $log;

        }


        $this->renderJson($log_list);
    }

    /**
     * 产生一个上线版本
     */
    private function _makeVersion() {
        $version = date("Ymd-His", time());
        $this->task->link_id = $version;
        return $this->task->save();
    }

    /**
     * 检查目录和权限，工作空间的准备
     * 每一个版本都单独开辟一个工作空间，防止代码污染
     *
     * @return bool
     * @throws \Exception
     */
    private function _initWorkspace() {
        $sTime = Command::getMs();

        // 本地宿主机工作区初始化
        $this->walleFolder->initLocalWorkspace($this->task->link_id, $this->task->id);

        // 远程目标目录检查,并且生产webroot目录
        $this->walleFolder->initRemotWebroot($this->task->id);

        // 远程目标目录检查,并且生成版本目录
        $ret = $this->walleFolder->initRemoteVersion($this->task->link_id,$this->task);

        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_PERMSSION, $duration);

        if (!$ret) throw new \Exception(yii::t('walle', 'init deployment workspace error'));
        return true;
    }

private function _initiBingWorkspace($serverip='') {
        $sTime = Command::getMs();
	if(empty($serverip)){
        // 本地宿主机工作区初始化
        	$this->walleFolder->initLocalWorkspace($this->task->link_id, $this->task->id);
        // 远程目标目录检查,并且生产webroot目录
        	$this->walleFolder->initRemotWebroot($this->task->id);
	}
        // 远程目标目录检查,并且生成版本目录
        $ret = $this->walleFolder->initRemoteVersion($this->task->link_id,$this->task,1,$serverip);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_PERMSSION, $duration);
 
        if (!$ret) throw new \Exception(yii::t('walle', 'init deployment workspace error'));
        return true;
    }

    /**
     * 更新代码文件
     *
     * @return bool
     * @throws \Exception
     */
    private function _gitUpdate() {
        // 更新代码文件
        $revision = Repo::getRevision($this->conf);
        $sTime = Command::getMs();
       
        $ret = $revision->updateToVersion($this->task, $this->task->id); // 更新到指定版本
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($revision, $this->task->id, Record::ACTION_CLONE, $duration);

        if (!$ret) throw new \Exception(yii::t('walle', 'update code error'));
        return true;
    }

    /**
     * 部署前置触发任务
     * 在部署代码之前的准备工作，如git的一些前置检查、vendor的安装（更新）
     *
     * @return bool
     * @throws \Exception
     */
    private function _preDeploy() {
        $sTime = Command::getMs();
        $ret = $this->walleTask->preDeploy($this->task->link_id, $this->task->id);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_PRE_DEPLOY, $duration);

        if (!$ret) throw new \Exception(yii::t('walle', 'pre deploy task error'));
        return true;
    }

    /**
     * 部署后置触发任务
     * git代码检出之后，可能做一些调整处理，如vendor拷贝，配置环境适配（mv config-test.php config.php）
     *
     * @return bool
     * @throws \Exception
     */
    private function _postDeploy() {
        $sTime = Command::getMs();
        $ret = $this->walleTask->postDeploy($this->task->link_id, $this->task->id);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_POST_DEPLOY, $duration);

        if (!$ret) throw new \Exception(yii::t('walle', 'post deploy task error'));
        return true;
    }

    /**
     * 同步文件到服务器
     *
     * @return bool
     * @throws \Exception
     */
    private function _rsync() {
        // 同步文件
        foreach (Project::getHosts() as $remoteHost) {
            $sTime = Command::getMs();
            $ret = $this->walleFolder->syncFiles($remoteHost, $this->task->link_id, 0, $this->task->id);
            // 记录执行时间
            $duration = Command::getMs() - $sTime;
            Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_SYNC, $duration);
            if (!$ret) throw new \Exception(yii::t('walle', 'rsync error'));
        }
        return true;
    }

    /**
     * 同步文件到服务器
     *
     * @return bool
     * @throws \Exception
     */
    private function _rsyncBing($serverip='') {
        // 同步文件
	if(!empty($serverip)){
	    $ret = $this->walleFolder->syncFiles($serverip, $this->task->link_id,1,$this->task->id);
	}else{

            $sTime = Command::getMs();
            $ret = $this->walleFolder->syncFiles('', $this->task->link_id,1,$this->task->id);
            // 记录执行时间
            $duration = Command::getMs() - $sTime;
            Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_SYNC, $duration);

	}
        return true;
    }

    /**
     * 执行远程服务器任务集合
     * 对于目标机器更多的时候是一台机器完成一组命令，而不是每条命令逐台机器执行
     *
     * @param $version
     * @throws \Exception
     */
    private function _updateRemoteServers($version) {
        $cmd = [];
        // pre-release task
        if (($preRelease = WalleTask::getRemoteTaskCommand($this->conf->pre_release, $version))) {
            $cmd[] = $preRelease;
        }

        // link软链
        //if (($linkCmd = $this->walleFolder->getLinkCommand($version))) {
            //$cmd[] = $linkCmd;
        //}

        // cp
        //if(($cpCmd = $this->walleFolder->cpProjectCommand($version))){
            //$cmd[] = $cpCmd;
        //}

        // post-release task
        if (($postRelease = WalleTask::getRemoteTaskCommand($this->conf->post_release, $version))) {
            $cmd[] = $postRelease;
        }

        $sTime = Command::getMs();
        // run the task package
        $ret = $this->walleTask->runRemoteTaskCommandPackage($cmd, $this->task->id);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);
        if (!$ret) throw new \Exception(yii::t('walle', 'update servers error'));
        return true;
    }

 private function _updateRemoteServersBing($version,$server_ip='') {
        $cmd = [];
        if (($preRelease = WalleTask::getRemoteTaskCommand($this->conf->pre_release, $version))) {
            $cmd[] = $preRelease;
        }
        // post-release task
        if (($postRelease = WalleTask::getRemoteTaskCommand($this->conf->post_release, $version))) {
            $cmd[] = $postRelease;
        }
        $sTime = Command::getMs();
        // run the task package
        $ret = $this->walleTask->runRemoteTaskCommandPackageBing($cmd,$this->task,$server_ip);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);
        if (!$ret) throw new \Exception(yii::t('walle', 'update servers error'));
        return true;
    }

    /**
     * 可回滚的版本设置
     *
     * @return int
     */
    private function _enableRollBack() {
        $where = ' status = :status AND project_id = :project_id ';
        $param = [':status' => Task::STATUS_DONE, ':project_id' => $this->task->project_id];
        $offset = Task::find()
            ->select(['id'])
            ->where($where, $param)
            ->orderBy(['id' => SORT_DESC])
            ->offset($this->conf->keep_version_num)->limit(1)
            ->scalar();
        if (!$offset) return true;

        $where .= ' AND id <= :offset ';
        $param[':offset'] = $offset;
        return Task::updateAll(['enable_rollback' => Task::ROLLBACK_FALSE], $where, $param);
    }

    /**
     * 只保留最大版本数，其余删除过老版本
     */
    private function _cleanRemoteReleaseVersion() {
        return $this->walleTask->cleanUpReleasesVersion($this->task->id);
    }
   
   /**
    *并发执行删除老版本命令及根据某个IP失败后的重置功能
    *
    *
    */
    private function _cleanBingReleaseVersion($task,$buid,$serverip) {
        return $this->walleTask->cleanUpReleasesVersionBing($task,$buid,$serverip);
    }

    /**
     * 执行远程服务器任务集合回滚，只操作pre-release、link、post-release任务
     *
     * @param $version
     * @throws \Exception
     */
    public function _rollback($version) {
        //return $this->_updateRemoteServers($version);

        $sTime = Command::getMs();

        $ret = $this->walleFolder->cpProjectCommand($version, $this->task->id);
        $ret2 = $this->walleFolder->hgProjectCommand($this->task->id);

        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);
        if (!$ret || !$ret2) throw new \Exception(yii::t('walle', 'update servers error'));
        return true;
    }

     public function _rollbackbing($version,$server_ip='') {
        //return $this->_updateRemoteServers($version);

        $sTime = Command::getMs();

        $ret = $this->walleFolder->cpProjectCommandBing($version,$this->task,$server_ip);
        $ret2 = $this->walleFolder->hgProjectCommandBing($this->task,$server_ip);

        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);
        if (!$ret || !$ret2) throw new \Exception(yii::t('walle', 'update servers error'));
        return true;
    }
    /**
     * 收尾工作，清除宿主机的临时部署空间
     */
    private function _cleanUpLocal($version = null) {
        // 创建链接指向
        $this->walleFolder->cleanUpLocal($version, $this->task->id);
        return true;
    }

    /**
     * 获取diff的文件
     *
     * @param $projectId
     * @param $filepath
     */
    public function actionGetFileContent($projectId, $filepath){
        $project = Project::getConf($projectId);
        $walleFolder = new Folder($project);

        //本地文件目录
        $localPath = Project::getDeployFromDir();

        $filepath_arr = explode('/',$filepath);
        $newfile = array_pop($filepath_arr);

        $path = dirname(dirname(__FILE__)).'/runtime/tmp/'.uniqid() .strtotime(date('Y-m-d H:i:s')).'/';

        if($filepath_arr){
            $newFilepath = implode('/',$filepath_arr);
            $newPath = $path.$newFilepath.'/';
        }else{
            $newPath = $path;
        }

        $remoteHost = Project::getHosts();
        //同步diff文件
        $rs = $walleFolder->rsyncFiles($remoteHost[0], $filepath, $newPath);

        $fileContent = array();
        $fileContent['local_file_content'] = file_get_contents($localPath.'/'.$filepath);
        $fileContent['remot_file_content'] = file_get_contents($newPath.$newfile);

        //删除diff文件
        $walleFolder->rmRsyncFiles($path);
        $this->renderJson($fileContent);
    }

    /**
     * 文件md5
     *
     * @param $filepath
     */
    public function actionGetFileMd5($projectId, $filepath){

        $filepath_arr = json_decode($filepath);

        $project = Project::getConf($projectId);
        $walleFolder = new Folder($project);

        $localPath = Project::getDeployFromDir();

        $newFilepath = array();

        for($i=0;$i<count($filepath_arr);$i++){
            $walleFolder->getLocalFileMd5($localPath.'/'.$filepath_arr[$i]);
            $newFilepath[$i]['filepath'] = $filepath_arr[$i];
            $newFilepath[$i]['md5'] = $walleFolder->getExeLog();
        }

        $this->renderJson($newFilepath);
    }



}
