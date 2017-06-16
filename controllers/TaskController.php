<?php

namespace app\controllers;

use yii;
use yii\data\Pagination;
use app\components\Controller;
use app\models\Task;
use app\models\Project;
use app\models\User;
use app\models\Group;
use app\models\Permission;
use app\components\Repo;

class TaskController extends Controller {
    
    protected $task;

    public function actionIndex($page = 1, $size = 20) {
        $size = $this->getParam('per-page') ?: $size;
        $list = Task::find()
            ->with('user')
            ->with('project')
            ->where(['user_id' => $this->uid]);
        // 有审核权限的任务
        $auditProjects = Group::getAuditProjectIds($this->uid);
        if ($auditProjects) {
            $list->orWhere(['project_id' => $auditProjects]);
        }

        $kw = \Yii::$app->request->post('kw');
        if ($kw) {
            $list->andWhere(['or', "commit_id like '%" . $kw . "%'", "title like '%" . $kw . "%'"]);
        }
        $tasks = $list->orderBy('id desc');
        $list = $tasks->offset(($page - 1) * $size)->limit(20)
            ->asArray()->all();

        $pages = new Pagination(['totalCount' => $tasks->count(), 'pageSize' => 20]);
        $shenhe_user_ids = array();
        $project_ids=array();
        $project_user_id=array();
if(!empty($list)){
	foreach($list as $k => $v){
	    if(!in_array($v['project_id'],$project_ids)){
	    array_push($project_ids,$v['project_id']);
	    }
	}
	$shenhe_user_data = Group::getShenHeUser($project_ids);
	foreach($shenhe_user_data as $key => $val){
	   if(!in_array($val['user_id'],$shenhe_user_ids))
	    {
		array_push($shenhe_user_ids,$val['user_id']);
	    }
		$project_user_id[$val['project_id']][] =$val['user_id'];
	}
	$user_name = array();
	$user_array = User::getUsername($shenhe_user_ids);
	foreach($user_array as $u_k => $u_v)
        {
	    $user_name[$u_v['id']] = $u_v['username'];
	}
	$project_uid_name = array();
	foreach($project_user_id as $pk => $pval){
	foreach($pval as $u => $ul){
	    $project_user_id[$pk][$u]=$user_name[$ul];
	}
	}
}
        return $this->render('list', [
            'list'  => $list,
            'pages' => $pages,
            'audit' => $auditProjects,
	    "manager" => $project_user_id
        ]);
    }

    /**
     * 提交任务
     *
     * @param $projectId
     * @return string
     */
    public function actionSubmit($projectId = null) {
        $task = new Task();
        if ($projectId) {
            // svn下无trunk
            $nonTrunk = false;
            $conf = Project::find()
                ->where(['id' => $projectId, 'status' => Project::STATUS_VALID])
                ->one();
            $conf = Project::getConf($projectId);
            // 第一次可能会因为更新而耗时，但一般不会，第一次初始化会是在检测里
            if ($conf->repo_type == Project::REPO_SVN && !file_exists(Project::getDeployFromDir())) {
                $version = Repo::getRevision($conf);
                $version->updateRepo();
            }
            // 为了简化svn无trunk, branches时，不需要做查看分支，直接就是主干
            $svnTrunk = sprintf('%s/trunk', Project::getDeployFromDir());
            // svn下无trunk目录
            if (!file_exists($svnTrunk)) {
                $nonTrunk = true;
            }
        }
        if (\Yii::$app->request->getIsPost()) {
            if (!$conf) throw new \Exception(yii::t('task', 'unknown project'));
            $group = Group::find()
                ->where(['user_id' => $this->uid, 'project_id' => $projectId])
                ->count();
            if (!$group) throw new \Exception(yii::t('task', 'you are not the member of project'));

            if ($task->load(\Yii::$app->request->post())) {
                // 是否需要审核
                $status = $conf->audit == Project::AUDIT_YES ? Task::STATUS_SUBMIT : Task::STATUS_PASS;
                $task->user_id = $this->uid;
                $task->project_id = $projectId;
                $task->status = $status;
                if ($task->save()) {
                    return $this->redirect('@web/task/');
                }
            }
        }
        if ($projectId) {
            //$tpl = $conf->repo_type == Project::REPO_GIT ? 'submit-git' : 'submit-svn';
            $tpl = '';
            if($conf->repo_type == Project::REPO_GIT){
                $tpl = 'submit-git';
            }elseif($conf->repo_type == Project::REPO_SVN){
                $tpl = 'submit-svn';
            }elseif($conf->repo_type == Project::REPO_FTP){
                $tpl = 'submit-ftp';
            }
            return $this->render($tpl, [
                'task' => $task,
                'conf' => $conf,
                'nonTrunk' => $nonTrunk,
            ]);
        }
        // 成员所属项目
        $project = Project::find()->asArray()->all();
		$group_prj = $this->getProjectIds();
        $focus_project = array();
        if(\Yii::$app->user->identity->role==11)
        {
            $focus_project = $project;
        }else{
        if(!empty($group_prj))
        {
            if(!empty($project)){
                foreach($project as $key => $val){
                    if(in_array($val['id'],$group_prj) || \Yii::$app->user->identity->id==$val['user_id'])
                    {
                        if($val['status'] == 0)
                        {
                           // if(\Yii::$app->user->identity->role==1){
                           //     $focus_project[] = $val;
                           // }
                        }else{
                            $focus_project[] = $val;
                        }
                    }
                }
            }
        }
	}

        return $this->render('select-project', [
            'projects' => $focus_project,
        ]);
    }
	
	/**
     *获取用户组内的项目IDS
     *
     */
     public function getProjectIds(){
        $group_ids = array();
        if(strlen(\Yii::$app->user->identity->group_id) > 1)
            $group_ids = unserialize(\Yii::$app->user->identity->group_id);
        $pids_data = array();
        $project_ids = array();
        if(empty($group_ids)) return false;
        foreach($group_ids as $k => $v)
        {
            $pids_data = unserialize(Permission::getGroupProjectIds($v)[0]);
            foreach($pids_data as $key => $val){
                $project_ids[] = $val;
            }
        }
        $project_ids = array_unique($project_ids);
        return $project_ids;
    }

    /**
     * 任务删除
     *
     * @return string
     * @throws \Exception
     */
    public function actionDelete($taskId) {
        $task = Task::findOne($taskId);
        if (!$task) {
            throw new \Exception(yii::t('task', 'unknown deployment bill'));
        }
        if ($task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        if ($task->status == Task::STATUS_DONE) {
            throw new \Exception(yii::t('task', 'can\'t delele the job which is done'));
        }
        if (!$task->delete()) throw new \Exception(yii::t('w', 'delete failed'));
        $this->renderJson([]);

    }

    /**
     * 生成回滚任务
     *
     * @return string
     * @throws \Exception
     */
    public function actionRollback($taskId) {
        $this->task = Task::findOne($taskId);
        if (!$this->task) {
            throw new \Exception(yii::t('task', 'unknown deployment bill'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        //if ($this->task->ex_link_id == $this->task->link_id) {
        if ($this->task->action == 1) {
            throw new \Exception(yii::t('task', 'no rollback twice'));
        }
        $conf = Project::find()
            ->where(['id' => $this->task->project_id, 'status' => Project::STATUS_VALID])
            ->one();
        if (!$conf) {
            throw new \Exception(yii::t('task', 'can\'t rollback the closed project\'s job'));
        }

        // 是否需要审核
        $status = $conf->audit == Project::AUDIT_YES ? Task::STATUS_SUBMIT : Task::STATUS_PASS;

        $rollbackTask = new Task();
        $rollbackTask->attributes = $this->task->attributes;
        $rollbackTask->status = $status;
        $rollbackTask->action = Task::ACTION_ROLLBACK;
        $rollbackTask->link_id = $this->task->ex_link_id;
        $rollbackTask->title = $this->task->title . ' - ' . yii::t('task', 'rollback');
        if ($rollbackTask->save()) {
            $url = $conf->audit == Project::AUDIT_YES
                ? '/task/'
                : '/walle/deploy?taskId=' . $rollbackTask->id;
            $this->renderJson([
                'url' => $url,
            ]);
        } else {
            $this->renderJson([], -1, yii::t('task', 'create a rollback job failed'));
        }
    }

    /**
     * 任务审核
     *
     * @param $id
     * @param $operation
     */
    public function actionTaskOperation($id, $operation) {
        $task = Task::findOne($id);
        if (!$task) {
            static::renderJson([], -1, yii::t('task', 'unknown deployment bill'));
        }
        // 是否为该项目的审核管理员（超级管理员可以不用审核，如果想审核就得设置为审核管理员，要不只能维护配置）
        if (!Group::isAuditAdmin($this->uid, $task->project_id)) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }

        $task->status = $operation ? Task::STATUS_PASS : Task::STATUS_REFUSE;
        $task->save();
        static::renderJson(['status' => \Yii::t('w', 'task_status_' . $task->status)]);
    }

    /**
     * 根据id查找部署位置以及commit id
     *
     *
     * @param $projectID
     */
    public function actionGetFileList($projectID) {
	$this->layout = 'modal';
	$project = "";
	if($projectID < 0) {
	    return $this->render(
	        'preview',
	        ['conf' => $project]
	    );
	}
	$task = Task::findOne($projectID);
        if (!$task) {
	    return $this->render(
	        'preview',
		['conf' => $project]
            );
	}
	
	$project = $task->toArray();

	$project['file_list'] = explode("\n", $project['file_list']);
	
	array_pop($project['file_list']);

	$projectId = $project['project_id'];
	$startId = $project['start_id'];
	$commitId = $project['commit_id'];	
        $startInfo = '未知';
	$endInfo = '未知';

	if($startId) {
	    $startInfo = $this->GetCommitName($startId, $projectId);
        }	
	if($commitId) {
	    $endInfo = $this->GetCommitName($commitId, $projectId);
	}
	return $this->render('preview',[
	         'conf' => $project, 
		 'startInfo' => $startInfo, 
		 'endInfo' => $endInfo
		]
	);
   }

    // 根据传入的projectId 获取提交历史
    public function GetCommitHistory($projectId, $branch = 'master',$file_name='') {
        $conf = Project::getConf($projectId);
        $revision = Repo::getRevision($conf);
        if ($conf->repo_mode == Project::REPO_TAG && $conf->repo_type == Project::REPO_GIT) {            $list = $revision->getTagList();
        } else {
            $list = $revision->getCommitList($branch,20,$file_name);
        }       
	return $list;
    }    

    // 根据commitId 获取该次提交的详细信息
    public function GetCommitName($commitId, $projectId) {	
	$CommitArr = $this->GetCommitHistory($projectId, '');	
	foreach($CommitArr as $k => $v) {
	    // 找到，返回
	    if($v['id'] == $commitId) {
		$retStr = 'Author:' . $v['author'] . '  Message:' . $v['message'];
		return $retStr;
	    }
	}
	// 没有找到
	return '无详细信息';
    }   


    // 在当前controller层判断文件是否存在，若存在，直接传到model层进行下载
    public function actionDownloadLog($projectID) {
	$this->layout = 'modal';
	$file_name = "walle-$projectID.log";
        $file_path = '/tmp/walle/' . $file_name;

        if(!file_exists($file_path)) {
	    return $this->render('download');;
        }
	$this->layout = 'modal';
	$ret = Task::downloadLog($file_name, $file_path);
    }
}
