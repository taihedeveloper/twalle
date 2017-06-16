<?php
/* *****************************************************************
 * @Author: wushuiyong
 * @Created Time : 五  7/31 22:21:23 2015
 *
 * @File Name: command/Folder.php
 * @Description:
 * *****************************************************************/
namespace app\components;

use app\models\Project;

class Folder extends Command {


    private $taskId;
    /**
     * 初始化宿主机部署工作空间
     *
     * @return bool
     */
    public function initLocalWorkspace($version, $taskId = '') {
	$this->taskId = $taskId;
        // svn
        if ($this->config->repo_type == Project::REPO_SVN) {
            $cmd[] = 'mkdir -p ' . Project::getDeployWorkspace($version);
            $cmd[] = sprintf('mkdir -p %s-svn', rtrim(Project::getDeployWorkspace($version), '/'));
        }
        // git 直接把项目代码拷贝过来，然后更新，取代之前原项目检出，提速
        else {
            $cmd[] = sprintf('cp -rf %s %s ', Project::getDeployFromDir(), Project::getDeployWorkspace($version));
        }
        $command = join(' && ', $cmd);
        return $this->runLocalCommand($command, $taskId);
    }

    /**
     * 目标机器webroot目录初始化
     *
     */
    public function initRemotWebroot($taskId = ''){
        $cmd[] = sprintf('mkdir -p %s', Project::getTargetWorkspace());
        $command = join(' && ', $cmd);

        return $this->runRemoteCommand($command, $taskId);
    }
   
    public function initGetIpLog($ip=array(),$num,$taskId,$buid){
	return $this->getIpLog($ip,$num,$taskId,$buid);
    
    }
   
    /**
     * 目标机器的版本库初始化
     * 这里会有点特殊化：
     * 1.（git只需要生成版本目录即可）new：好吧，现在跟2一样了，毕竟本地的copy要比rsync要快，到时只需要rsync做增量更新即可
     * 2.svn还需要把线上版本复制到1生成的版本目录中，做增量发布
     *
     * @author wushuiyong
     * @param $log
     * @return bool
     */
    public function initRemoteVersion($version,$task,$is_bing=0,$serverip="") {
        $cmd[] = sprintf('mkdir -p %s', Project::getReleaseVersionDir($version));
        if ($this->config->repo_type == Project::REPO_SVN) {
            if($task->file_list)
            {
                $copy  = GlobalHelper::str2arr($task->file_list);
            }else{
                $copy = array();
            }
            
            if($this->config->release_excludes){
                $release_excludes = GlobalHelper::str2arr($this->config->release_excludes);
                $exclude = '';
                for($i=0;$i<count($release_excludes);$i++){
                    $exclude.= " --exclude ".$release_excludes[$i]." ";
                }
                if(!empty($copy))
                {
                    
                    $cmd[] = sprintf('test -d %s && cd %s', // 无论如何总得要$?执行成功
                    $this->config->release_to,
                    $this->config->release_to
                   );
                    foreach ($copy as $key => $value) {
                        $version_dir = Project::getReleaseVersionDir($version);
                        $file_dir = '';
                        if(strpos($value, '/') !== false) {
                            $file_dir = substr($value,0,strrpos($value,'/')).'/';
                            $cmd[] = sprintf('mkdir -p %s/%s', $version_dir, $file_dir);
                            $cmd[] = sprintf("if [ -e %s ];then rsync -avq %s %s %s/%s; fi",$value,$exclude,$value,$version_dir,$file_dir);
                        }else{
                            $cmd[] = sprintf('if [ -f %s ];then rsync -avq %s %s %s/%s; fi',$value,$exclude,$value,$version_dir,$file_dir);
                        }
                        
                    }
                   // $cmd[] = sprintf('echo 1');
                }else{
                    $cmd[] = sprintf('test -d %s && cd %s && rsync -avq %s * %s/ || echo 1', // 无论如何总得要$?执行成功
                    $this->config->release_to,
                    $this->config->release_to,
                    $exclude,
                    Project::getReleaseVersionDir($version));
                }
               

            }else{
               
                if(!empty($copy))
                {
                    $cmd[] = sprintf('test -d %s && cd %s', // 无论如何总得要$?执行成功
                    $this->config->release_to,
                    $this->config->release_to
                   );
                    foreach ($copy as $key => $value) {
                        $version_dir = Project::getReleaseVersionDir($version);
                        $file_dir = '';
                        if(strpos($value, '/') !== false) {
                            $file_dir = substr($value,0,strrpos($value,'/')).'/';
                            $cmd[] = sprintf('mkdir -p %s/%s', $version_dir, $file_dir);
                            $cmd[] = sprintf("if [ -e %s ];then rsync -avq %s %s/%s; fi",$value,$value,$version_dir,$file_dir);
                        }
                        //$cmd[] = sprintf('test -e %s && rsync -avq %s %s/%s',$value,$value,$version_dir,$file_dir);
                        $cmd[] = sprintf("if [ -f %s ];then rsync -avq %s %s/%s; fi",$value,$value,$version_dir,$file_dir);
                    }
                    //$cmd[] = sprintf('echo 1');
                }else{
                    $cmd[] = sprintf('test -d %s && cp -rf %s/* %s/ || echo 1', // 无论如何总得要$?执行成功
                    $this->config->release_to, $this->config->release_to, Project::getReleaseVersionDir($version));
                }
                
            }
        }elseif($this->config->repo_type == Project::REPO_FTP){
            if($this->config->release_excludes){
                $release_excludes = GlobalHelper::str2arr($this->config->release_excludes);

                $exclude = '';
                for($i=0;$i<count($release_excludes);$i++){
                    $exclude.= " --exclude ".$release_excludes[$i]." ";
                }

                $cmd[] = sprintf('test -d %s && cd %s && rsync -avq %s * %s/ || echo 1', // 无论如何总得要$?执行成功
                    $this->config->release_to,
                    $this->config->release_to,
                    $exclude,
                    Project::getReleaseVersionDir($version));

            }else{
                //$cmd[] = sprintf('test -d %s && cp -rf %s/* %s/ || echo 1', // 无论如何总得要$?执行成功
                 //   $this->config->release_to, $this->config->release_to, Project::getReleaseVersionDir($version));
                //$cmd[] = sprintf('echo 1');// 无论如何总得要$?执行成功
            }
        }
        $command = join(' && ', $cmd); 
	if($is_bing){
	   //1为第几步操i作
	$this->createRsyncShell($task->id,$serverip,$command);
	   return $this->runRemoteBingCommand($command,$task,1,$serverip);
	}else{
	   return $this->runRemoteCommand($command, $task->id);
	}
    }

    /**
     * rsync 同步文件
     *
     * @param $remoteHost 远程host，格式：host 、host:port
     * @return bool
     */
    public function syncFiles($remoteHost, $version,$is_bing=0,$taskid=0) {
        $excludes = GlobalHelper::str2arr($this->getConfig()->excludes);

        //$command = sprintf('rsync -avzq --rsh="ssh -p %s" %s %s %s%s:%s',
            //$this->getHostPort($remoteHost),
            //$this->excludes($excludes),
            //rtrim(Project::getDeployWorkspace($version), '/') . '/',
            //$this->getConfig()->release_user . '@',
            //$this->getHostName($remoteHost),
            //Project::getReleaseVersionDir($version));

        $command = sprintf('rsync -avzq --rsh="ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p %s" %s %s %s%s:%s',
            $this->getHostPort($remoteHost),
            $this->excludes($excludes),
            rtrim(Project::getDeployWorkspace($version), '/') . '/',
            $this->getConfig()->release_user . '@',
            $this->getHostName($remoteHost),
            Project::getTargetWorkspace());
	if($is_bing){
	   //2 为第2步操作
           $command = sprintf('rsync -avzq --rsh="ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p %s" %s %s %s%s:%s',
            $this->getHostPort($remoteHost),
            $this->excludes($excludes),
            rtrim(Project::getDeployWorkspace($version), '/') . '/',
            $this->getConfig()->release_user . '@',
            '${ipattr[$i]}',
            Project::getTargetWorkspace());
	   $this->createRsyncShell($taskid,$remoteHost,$command,2);
	   return $this->runLocalBingCommand($command,$remoteHost,$taskid,2);
	}else{
	   return $this->runLocalCommand($command, $taskid);
	}
    }

    public function rsyncFiles($remoteHost, $filepath, $path){
        $cmd[] = 'mkdir -p '.$path;
        $cmd[] = sprintf('rsync -avzq --rsh="ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p %s" %s%s:%s %s',
            $this->getHostPort($remoteHost),
            $this->getConfig()->release_user . '@',
            $this->getHostName($remoteHost),
            Project::getTargetWorkspace().'/'.$filepath,
            $path);

        $command = join(' && ', $cmd);

        return $this->runLocalCommand($command, $this->taskId);
    }

    public function rmRsyncFiles($filepath){
        $command = "rm -rf ".$filepath;
        return $this->runLocalCommand($command, $this->taskId);
    }

    /**
     * 打软链
     *
     * @param null $version
     * @return bool
     */
    public function getLinkCommand($version) {
        $user = $this->config->release_user;
        $project = Project::getGitProjectName($this->getConfig()->repo_url);
        $currentTmp = sprintf('%s/%s/current-%s.tmp', rtrim($this->getConfig()->release_library, '/'), $project, $project);
        // 遇到回滚，则使用回滚的版本version
        $linkFrom = Project::getReleaseVersionDir($version);
        $cmd[] = sprintf('ln -sfn %s %s', $linkFrom, $currentTmp);
        $cmd[] = sprintf('chown -h %s %s', $user, $currentTmp);
        $cmd[] = sprintf('mv -fT %s %s', $currentTmp, $this->getConfig()->release_to);

        return join(' && ', $cmd);
    }

    /**
     * 项目备份,发布
     *
     * @param null $version
     * @return bool
     */
    public function cpProjectCommand($version, $taskId = ''){
        $webrootFrom = Project::getTargetWorkspace();

        // 遇到回滚,则使用回滚的版本version
        $releaseFrom = Project::getReleaseVersionDir($version);

        //$cmd[] = sprintf('rm -rf %s',$webrootFrom);
        $cmd[] = sprintf('cp -rf %s/* %s',$releaseFrom,$webrootFrom);

        $command = join(' && ',$cmd);
        
        return $this->runRemoteCommand($command, $taskId);
    }
    /**
     *回滚代码，并行
     *
     */
     public function cpProjectCommandBing($version,$task,$server_ip=''){
        $webrootFrom = Project::getTargetWorkspace();

        // 遇到回滚,则使用回滚的版本version
        $releaseFrom = Project::getReleaseVersionDir($version);
        //$cmd[] = sprintf('rm -rf %s',$webrootFrom);
        $cmd[] = sprintf('cp -rf %s/* %s',$releaseFrom,$webrootFrom);
	$cmd[] =  Project::getProjectCommand();
        $command = join(' && ',$cmd);
        $this->createRsyncShell($task->id,$server_ip,$command,5);
        return $this->runRemoteBingCommand($command,$task,5,$server_ip);
    }

    public function hgProjectCommand($taskId = ''){
        $hg_deploy = Project::getProjectCommand();
        return $this->runRemoteCommand($hg_deploy, $taskId);
    }

    public function hgProjectCommandBing($task,$serverip=""){
        $hg_deploy = '';//Project::getProjectCommand();
//	$this->createRsyncShell($task->id,$serverip,$hg_deploy,5);
        return $this->runRemoteBingCommand($hg_deploy,$task,5,$serverip);
    }


    /**
     * 获取文件的MD5
     *
     * @param $file
     * @return bool
     */
    public function getFileMd5($file) {
        $cmd[] = "test -f /usr/bin/md5sum && md5sum {$file}";
        $command = join(' && ', $cmd);

        return $this->runRemoteCommand($command);
    }

    /**
     * 获取文件的MD5
     *
     * @param $file
     * @return bool
     */
    public function getLocalFileMd5($file) {
        $cmd[] = "test -f /usr/bin/md5sum && md5sum {$file}";
        $command = join(' && ', $cmd);

        return $this->runLocalCommand($command, $this->taskId);
    }


    /**
     * rsync时，要排除的文件
     *
     * @param array $excludes
     * @return string
     */
    protected function excludes($excludes) {
        $excludesRsync = '';
        foreach ($excludes as $exclude) {
            $excludesRsync .= sprintf(" --exclude=%s ", escapeshellarg(trim($exclude)));
        }


        return trim($excludesRsync);
    }

    /**
     * 收尾做处理工作，如清理本地的部署空间
     *
     * @param $version
     * @return bool|int
     */
    public function cleanUpLocal($version, $taskId = '') {
        $cmd[] = "rm -rf " . Project::getDeployWorkspace($version);
        if ($this->config->repo_type == Project::REPO_SVN) {
            $cmd[] = sprintf('rm -rf %s-svn', rtrim(Project::getDeployWorkspace($version), '/'));
        }
        if($this->config->repo_type == Project::REPO_FTP){
            $my_dir = Project::getDeployFromDir();
            $cmd[] =sprintf('rm -fr %s/*',$my_dir);
        }
        $command = join(' && ', $cmd);
        return $this->runLocalCommand($command, $taskId);
    }

    /**
     * 删除本地项目空间
     *
     * @param $projectDir
     * @return bool|int
     */
    public function removeLocalProjectWorkspace($projectDir) {
        $cmd[] = "rm -rf " . $projectDir;
        $command = join(' && ', $cmd);
        return $this->runLocalCommand($command);
    }
}

