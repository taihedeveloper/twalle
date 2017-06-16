<?php
/* *****************************************************************
 * @Author: wushuiyong
 * @Created Time : 日  8/ 2 10:43:15 2015
 *
 * @File Name: command/Ftp.php
 * @Description:
 * *****************************************************************/
namespace app\components;

use yii\helpers\StringHelper;
use app\models\Project;

class Ftp extends Command {

    private $hostname   = '';
    private $username   = '';
    private $password   = '';
    private $port       = 21;
    private $passive    = TRUE;
    private $debug      = TRUE;
    private $conn_id    = FALSE;


    public function updateRepo($branch = 'trunk', $ftpDir = null, $file_name='') {
        $ftpDir = $ftpDir ?: Project::getDeployFromDir();

        $cmd[] = sprintf('mkdir -p %s ', $ftpDir);
        $cmd[] = sprintf('cd %s ', $ftpDir);

        //wget -r -nH -P/home/fengxin/test/ ftp://172.31.1.1:21/* --ftp-user=ftpuser --ftp-password=ftpuser
        $cmd[] = sprintf('wget -r -nH -P %s ftp://%s:%s@%s .', Project::getDeployFromDirFtp(), $this->getConfig()->repo_username, $this->getConfig()->repo_password,$this->getConfig()->repo_url.trim($file_name," "));

        $command = join(' && ', $cmd);
        return $this->runLocalCommand($command);

    }

public function create_dir_release($task,$dir,$is_dir=true){

    $project = Project::getConf($task->project_id);
    
    //判断是否是文件夹
    if($is_dir)
    {
        $cmd[] = sprintf('mkdir -p %s/%s',Project::getReleaseVersionDir($task->link_id),$dir);//创建文件夹
        $cmd[] = sprintf('test -d %s && cp -rf %s/%s/* %s/%s || echo 1', // 无论如何总得要$?执行成功
                    $project->release_to, $project->release_to,$dir, Project::getReleaseVersionDir($task->link_id),$dir);
    }else{
        $cmd[] = sprintf('mkdir -p %s/%s',Project::getReleaseVersionDir($task->link_id),dirname($dir));//创建文件夹
        $cmd[] = sprintf('test -d %s && cp -rf %s/%s %s/%s || echo 1', // 无论如何总得要$?执行成功
                    $project->release_to, $project->release_to,$dir, Project::getReleaseVersionDir($task->link_id),dirname($dir));
    }
     $command = join(' && ', $cmd);

    return $this->runRemoteCommand($command);
}




    /**
     * 更新到指定commit版本
     *
     * @param string $commit
     * @return bool
     */
    public function updateToVersion($task) {
		if($task->is_tar_gz){
    		$copy  = GlobalHelper::str2arr($task->file_list);
    		$fileAndVersion = [];
    		foreach ($copy as $file) {
    			$fileAndVersion[] = StringHelper::explode($file, " ", true, true);
    		}
    		// 兼容无trunk、无branches、无tags下为空
    		$branch = ($task->branch == 'trunk' || $task->branch == '')
    		? $task->branch
    		: ($this->getConfig()->repo_mode == Project::REPO_BRANCH ? 'branches/' : 'tags/') . $task->branch;
    		//先删除link_id下所有文件    		 
    		$cmd[] = sprintf('rm -fr %s/%s',Project::getDeployWorkspace($task->link_id),'*');
    		// 更新指定文件到指定版本，并复制到同步目录

    		foreach ($fileAndVersion as $assign) {
    			if (in_array($assign[0], ['.', '..'])) continue;
    			// 多层目录需要先新建父目录，否则复制失败


                //获取根目录内文件夹
				 $inner_dir =  trim(strchr($assign[0],'/'),'/');
                 //生成目标机release版本
                
                if($task->is_backup){
        			if (strpos($assign[0], '/') !== false) {
                        //判断是文件夹还是文件
                        if(substr($assign[0], strlen($assign[0])-1,1) == '/')
                        {
                            $file_fir_cmd = trim(strchr($inner_dir,'/'));
                            $this->create_dir_release($task,trim(strchr($inner_dir,'/'),'/'),true); 
                        }else{
                            $file_fir_cmd= dirname(trim(strchr($inner_dir,'/'),'/'));
                            $this->create_dir_release($task,trim(strchr($inner_dir,'/'),'/'),false);
                        }
                        
        				$cmd[] = sprintf('mkdir -p %s/%s',
        						Project::getDeployWorkspace($task->link_id), ltrim($file_fir_cmd,'/'));
        			}
                }else{
                    $file_fir_cmd= dirname(trim(strchr($inner_dir,'/'),'/'));
                	//创建要上传的文件夹
                	if (strpos($assign[0], '/') !== false) {
                		//$cmd[] = sprintf('mkdir -p %s/%s',Project::getDeployWorkspace($task->link_id),  strchr($inner_dir,'/'));
                        $cmd[] = sprintf('mkdir -p %s/%s',Project::getDeployWorkspace($task->link_id),  $file_fir_cmd);
                	}
                }
                
				//宿主机目录
				
    			$my_dir = Project::getDeployFromDir();
    			$cmd[] = sprintf('cp -rf %s %s/%s',
    					$my_dir.'/'.rtrim($assign[0], '/'), Project::getDeployWorkspace($task->link_id), dirname(trim(strchr($inner_dir,'/'),'/')));
    		}
			//将本次解压的文件夹删掉
			$_tmp_dir = explode('/',$assign[0]);
			$remove_dir = $my_dir.'/'.$_tmp_dir[0];
			//$cmd[] =sprintf('rm -fr %s',$remove_dir);
			//$cmd[] =sprintf('rm -fr %s/*',$my_dir);
    		$command = join(' && ', $cmd);
          	
    		return $this->runLocalCommand($command);
    	}else{
    		return true;
    	}
    
    }

    /**
     * 获取分支/tag列表
     * 可能后期要换成 svn ls http://xxx/branches
     *
     * @return array
     */
    public function getBranchList() {
        // 更新
        $this->updateRepo();
        $list = [];
        $branchDir = 'tags';
        // 分支模式
        if ($this->getConfig()->repo_mode == Project::REPO_BRANCH) {
            $branchDir = 'branches';
            $trunkDir  = sprintf("%s/trunk", rtrim(Project::getDeployFromDir(), '/'));

            if (file_exists($trunkDir)) {
                $list[] = [
                    'id' => 'trunk',
                    'message' => 'trunk',
                ];
            } else {
                $list[] = [
                    'id' => '',
                    'message' => \yii::t('w', 'default trunk'),
                ];
            }
        }
        $branchDir = sprintf("%s/%s", rtrim(Project::getDeployFromDir(), '/'), $branchDir);

        // 如果不存在branches目录，则跳过查找其它分支
        if (!file_exists($branchDir)) {
            return $list;
        }

        $branches = new \DirectoryIterator($branchDir);
        foreach ($branches as $branch) {
            $name = $branch->__toString();
            if ($branch->isDot() || $branch->isFile()) continue;
            if ('.svn' == $name) continue;
            $list[] = [
                'id' => $name,
                'message' => $name,
            ];
        }
        // 降序排列分支列表
        rsort($list);
        
        return $list;
    }

    /**
     * 获取提交历史
     *
     * @return array
     */
    public function getCommitList($branch = 'trunk', $count = 30, $file_name='') {
        // 先更新

        $destination = Project::getDeployFromDir();
        $this->updateRepo($branch, $destination,$file_name);

        $dir = static::getBranchDir($branch, $this->getConfig()->repo_mode == Project::REPO_TAG ?: false);
        
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {

                while (($file = readdir($dh)) !== false) {
                    if ($file!="." && $file!="..") {
                        //echo "<a href=file/".$file.">".$file."</a><br>";
                        $list[] = $file;
                    }
                }
                closedir($dh);
            }
        }

        return $list;

        /*

        $cmd[] = sprintf('cd %s ', static::getBranchDir($branch, $this->getConfig()->repo_mode == Project::REPO_TAG ?: false));
        $cmd[] = $this->_getSvnCmd('svn log --xml -l' . $count);
        $command = join(' && ', $cmd);
        $result = $this->runLocalCommand($command);

        if (!$result) {
            throw new \Exception(\yii::t('walle', 'get commit log failed') . $this->getExeLog());
        }

        // 总有一些同学没有团队协作意识，不设置好编码：(
        $log = GlobalHelper::convert2Utf8($this->getExeLog());
        return array_values(static::formatXmlLog($log));
        */
    }

    /**
     * 获取tag记录
     *
     * @return array
     */
    public function getTagList($count = 20) {
        $branchesDir = sprintf("%s/tags", rtrim(Project::getDeployFromDir(), '/'));
        $list[] = [
            'id'      => 'trunk',
            'message' => 'trunk',
        ];
        if (!file_exists($branchesDir) && !$this->u0pdateRepo()) {
            return $list;
        }

        $branches = new \DirectoryIterator($branchesDir);
        foreach ($branches as $branch) {
            $name = $branch->__toString();
            if ($branch->isDot() || $branch->isFile()) continue;
            if ('.svn' == $name) continue;
            $list[] = [
                'id'      => $name,
                'message' => $name,
            ];
        }
        // 降序排列分支列表
        rsort($list);

        return $list;
    }

    /**
     * 获取commit之间的文件
     *
     * @return array
     */
    public function getFileBetweenCommits($branch, $star, $end) {
        // 先更新
        $destination = Project::getDeployFromDir();
        $this->updateRepo($branch, $destination);
        $cmd[] = sprintf('cd %s ', static::getBranchDir($branch, $this->getConfig()->repo_mode == Project::REPO_TAG ?: false));
        $cmd[] = $this->_getSvnCmd(sprintf('svn diff -r %d:%d --summarize', $star, $end));
        $command = join(' && ', $cmd);
        $result = $this->runLocalCommand($command);
        if (!$result) {
            throw new \Exception(\yii::t('walle', 'get commit log failed') . $this->getExeLog());
        }

        $list = [];
        $files = StringHelper::explode($this->getExeLog(), PHP_EOL);
        $files = array_map(function($item) {
            return trim(substr($item, strpos($item, " ")));
        }, $files);
        // 排除点文件
        if (in_array('.', $files)) {
            unset($files[array_search('.', $files)]);
        }
        foreach ($files as $key => $file) {
            // 如果是目录，则目录下的文件则可以不带了
            if (in_array(dirname($file), $files)) continue;
            $list[] = $file;
        }

        return $list;
    }

    /**
     * 格式化svn log xml 2 array
     *
     * @param $xmlString
     * @return array
     */
    public static function formatXmlLog($xmlString) {
        $history = [];
        $xml = simplexml_load_string($xmlString);
        foreach ($xml as $item) {
            $attr = $item->attributes();
            $id   = $attr->__toString();

            $history[$id] = [
                'id' => $id,
                'date' => $item->date->__toString(),
                'author' => $item->author->__toString(),
                'message' => $item->msg->__toString(),
            ];
        }
        return $history;
    }

    public static function getBranchDir($branch, $tag = false) {
        $svnDir = Project::getDeployFromDir();
        // 兼容无trunk、无branches、无tags下为空
        $branchDir = ($branch == '' || $branch == 'trunk') && !$tag
            ? $branch
            : ($tag ? 'tags/'.$branch : 'branches/'.$branch);
        return sprintf('%s/%s', $svnDir, $branchDir);
    }

    private function _getSvnCmd($cmd) {
        return sprintf("/usr/bin/env %s  --username='%s' --password='%s' --non-interactive --trust-server-cert ",
            $cmd, $this->config->repo_username, $this->config->repo_password);
    }

	/**
     * 命令解压缩包
     * 
     */
	public function packTar($type,$path,$to_pathname){
		if(!file_exists($to_pathname))
		{
			mkdir($to_pathname);
		}
		
		if($type == 'zip')
        {
            $cmd[] = sprintf('unzip %s -d %s',$path,$to_pathname.'/');
        }elseif($type == 'bz2'){
            $cmd[] = sprintf('tar -xjf %s -C %s',$path,$to_pathname.'/');
        }else{
            $cmd[] = sprintf('tar -zxvf %s -C %s',$path,$to_pathname.'/');
        }
		
		$command = join(' && ', $cmd);
		return $this->runLocalCommand($command);
        
	}

    /**
     * FTP连接
     *
     * @access  public
     * @return  boolean
     */
    public function connect() {
        $config = array(
            'hostname' => $this->getConfig()->repo_url,
            'username' => $this->getConfig()->repo_username,
            'password' => $this->getConfig()->repo_password,
            'port' => $this->getConfig()->repo_port,
        );
        if(count($config) > 0) {
            foreach($config as $key => $val) {
                if(isset($this->$key)) {
                    $this->$key = $val;
                }
            }
            //特殊字符过滤
            $this->hostname = preg_replace('|.+?://|','',$this->hostname);
        }
        if(FALSE === ($this->conn_id = @ftp_connect($this->hostname,$this->port))) {
            if($this->debug === TRUE) {
                //$this->_error("ftp_unable_to_connect");
                throw new \Exception(\yii::t('walle', 'ftp_unable_to_connect'));
            }
            return FALSE;
        }
        if( ! $this->_login()) {
            if($this->debug === TRUE) {
                //$this->_error("ftp_unable_to_login");
                throw new \Exception(\yii::t('walle', 'ftp_unable_to_connect'));
            }
            return FALSE;
        }
        if($this->passive === TRUE) {
            ftp_pasv($this->conn_id, TRUE);
        }
        return TRUE;
    }

    /**
     * FTP登陆
     *
     * @access  private
     * @return  boolean
     */
    private function _login() {
        return @ftp_login($this->conn_id, $this->username, $this->password);
    }

    /**
     * 关闭FTP
     *
     * @access  public
     * @return  boolean
     */
    public function close() {
        if( ! $this->_isconn()) {
            return FALSE;
        }

        return @ftp_close($this->conn_id);
    }

    /**
     * 判断con_id
     *
     * @access  private
     * @return  boolean
     */
    private function _isconn() {
        if( ! is_resource($this->conn_id)) {
            if($this->debug === TRUE) {
                $this->_error("ftp_no_connection");
            }
            return FALSE;
        }
        return TRUE;
    }

}
