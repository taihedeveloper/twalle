<?php
/* *****************************************************************
 * @Author: wushuiyong
 * @Created Time : 五  7/31 22:42:32 2015
 *
 * @File Name: command/Command.php
 * @Description:
 * *****************************************************************/
namespace app\components;
use app\models\IpRecord;
set_time_limit(0);

class Command {

    protected static $LOGDIR = '';
    /**
     * Handler to the current Log File.
     * @var mixed
     */
    protected static $logFile = null;

    /**
     * Config
     * @var \walle\config\Config
     */
    protected $config;

    /**
     * 命令运行返回值：0失败，1成功
     * @var int
     */
    protected $status = 1;

    protected $command = '';

    protected $log = null;

    /**
     * 加载配置
     *
     * @param $config
     * @return $this
     * @throws \Exception
     */
    public function __construct($config) {
        if ($config) {
            $this->config = $config;
        } else {
            throw new \Exception(\yii::t('walle', 'unknown config'));
        }
    }

    /**
     * 执行本地宿主机命令
     *
     * @param $command
     * @param $taskId = '' 临时加的
     * @return bool|int true 成功，false 失败
     */
    final public function runLocalCommand($command, $taskId = '') {
        $command = trim($command);
    
        $this->log('---------------------------------', $taskId);
        $this->log('---- Executing: $ ' . $command, $taskId);
        $status = 1;
        $log = '';

        exec($command . ' 2>&1', $log, $status);
        // 执行过的命令
        $this->command = $command;
        // 执行的状态
        $this->status = !$status;
        // 操作日志
        $log = implode(PHP_EOL, $log);
        $this->log = trim($log);
	if($this->status==false){
		$this->status=0;
		$log = $command;
	}

        $this->log($log, $taskId);
        $this->log('---------------------------------', $taskId);

        return $this->status;
    }
/**
*并行上线
*$bid 步ID
*
*/
 final public function runLocalBingCommand($command,$ip='',$taskid=0,$bid=1) {
        $command = trim($command);
        if(empty($taskid))
        {
           return false;
        }
        $this->log('---------------------------------', $taskid);
        $this->log('---- Executing: $ ' . $taskid);
        $status = 1;
        $log = '';
	//首先创建一个命令文件，根据taskid
	// $file_name="/tmp/walle/".$taskid.".log";
	$file_name="/tmp/walle/walle-".$taskid.".log";
	$stream_file = fopen($file_name,'a+');
	fwrite($stream_file,$command);
	fclose($stream_file);
	if($ip != '' && !empty($ip))
	{
	    exec("sh /tmp/walle/".$taskid."_".$ip.".sh ".$bid,$logs,$status);
	}else{
	    exec("sh /tmp/walle/".$taskid.".sh ".$bid,$logs,$status);
	}
        //exec($command . ' && sleep 3 $? > out.log  2>&1 || echo 500 >&1 &', $log, $status);
        // 执行过的命令
        $this->command = $command;
        // 执行的状态
        $this->status = !$status;
        // 操作日志
        $log = '';
        $this->log = trim($log);
        if($this->status==false){
                $this->status=0;
                $log = $command;
        }
	if($log){
	    $err = explode("\n",$log);
	    if(end($err)==500)
	    {
	        //errno 500
	        $this->status=0;
	    }
	    $this->log = implode("",$err);
	}
	if($this->log==500){
	    $this->log = $command;
	}
        //$this->ip_log($ip,$this->status,$this->log,$taskid,$bid);
	//$db_status = IpRecord::saveRecord($this->status,$taskid,$ip,$command,$this->log,$bid);
        $this->log($log, $taskid);
        $this->log('---------------------------------', $taskid);
      // return $this->status;
    }
//并行上线
  
    final public function runRemoteBingCommand($command,$task,$buid,$serverip="") {
        $this->log = '';
        $needTTY = ' -T ';
	if($serverip){
	  /*  $localCommand = 'ssh ' . $needTTY . ' -p ' . $this->getHostPort($serverip)
                . ' -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no '
                . $this->getConfig()->release_user . '@'
                . $this->getHostName($serverip);
	  */
	   
            $remoteCommand = str_replace('"', '\\"', trim($command));
            $localCommand = $remoteCommand;
            $logDir = \Yii::$app->params['log.dir'];
            //static::log('Run remote command ' . $remoteCommand);
            $log = $this->log;
            $this->runLocalBingCommand($localCommand,$this->getHostName($serverip),$task->id,$buid);

            $this->log = $log . (($log ? PHP_EOL : '') . $serverip . ' : ' . $this->log);
	}else{
       
            $remoteCommand = str_replace('"', '\\"', trim($command));
            $localCommand = $remoteCommand;
	    $logDir = \Yii::$app->params['log.dir'];
	    //$iplogFile = realpath($logDir) . '/' .$remoteHost . '.log';
            static::log('Run remote command ' . $remoteCommand, $task->id);
            $log = $this->log;
            //$this->runLocalBingCommand($localCommand,$this->getHostName($remoteHost),$task->id,$buid);
	    $this->runLocalBingCommand($localCommand,$this->getHostName($serverip),$task->id,$buid);
            $this->log = $log . (($log ? PHP_EOL : '') . $serverip . ' : ' . $this->log);
            //if (!$this->status) return false;

	}
        return true;
    }
    /**
     * 执行远程目标机器命令
     *
     * @param $command
     * @return bool
     */
    final public function runRemoteCommand($command, $taskId = '') {
        $this->log = '';
        $needTTY = ' -T ';

        foreach (GlobalHelper::str2arr($this->getConfig()->hosts) as $remoteHost) {
            $localCommand = 'ssh ' . $needTTY . ' -p ' . $this->getHostPort($remoteHost)
                . ' -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no '
                . $this->getConfig()->release_user . '@'
                . $this->getHostName($remoteHost);
            $remoteCommand = str_replace('"', '\\"', trim($command));
            $localCommand .= ' " ' . $remoteCommand . ' "';
            static::log('Run remote command ' . $remoteCommand, $taskId);
            $log = $this->log;
            // $this->runLocalCommand($localCommand,$this->getHostName($remoteHost));
            $this->runLocalCommand($localCommand, $taskId);

            $this->log = $log . (($log ? PHP_EOL : '') . $remoteHost . ' : ' . $this->log);
            if (!$this->status) return false;
        }
        return true;
    }

    /**
     * 加载配置
     *
     * @param $config
     * @return $this
     * @throws \Exception
     */
    public function setConfig($config) {
        if ($config) {
            $this->config = $config;
        } else {
            throw new \Exception(\yii::t('walle', 'unknown config'));
        }
        return $this;
    }
    /**
     *获取iplog日志
     *
    */
    public function getIpLog($ip=array(),$num=1,$taskid,$buid=1){
	  $logDir = \Yii::$app->params['log.dir'];
        if (!file_exists($logDir)) return;
	$ip_attr = array();
	if(empty($ip))
	{
	   foreach (GlobalHelper::str2arr($this->getConfig()->hosts) as $remoteHost) {
		$current_ip = $this->getHostName($remoteHost);
             $iplogFile = realpath($logDir) . '/'.$taskid."_".$current_ip . '.log';
	     $data = file_get_contents($iplogFile);
	     $_jilu_attr = explode(PHP_EOL,$data);
	     $ip_attr[$current_ip] = array_slice($_jilu_attr,-1-$num,$num); 
	     foreach($ip_attr[$current_ip] as $k => $v)
	     {
		$attr_log_param = explode("_N_",$v);
		if(intval($attr_log_param[1]) != intval($buid) || $taskid != $attr_log_param[2]){
		    $ip_attr[$current_ip][$k] = "1_N_".$buid."_N_".$taskid."_N_正在操作中......";
		}
	     }
		
	  }
	}
	return $ip_attr;

    }

    /**
     * 获取配置
     * @return \walle\config\Config
     */
    protected function getConfig() {
        return $this->config;
    }
	
    public function ip_log($ip,$status,$message='',$taskid=0,$buid=1){
if (empty(\Yii::$app->params['log.dir'])) return;
        $logDir = \Yii::$app->params['log.dir'];
        if (!file_exists($logDir)) return;

        $iplogFile = realpath($logDir) . '/' .$ip . '.log';
        $ip_logFile = fopen($iplogFile, 'a');
        $message = $status."#".$buid."#".$taskid."#".$message."#".date('Y-m-d H:i:s');
        fwrite($ip_logFile, $message . PHP_EOL);
	fclose($ip_logFile);
}

    public static function log($message, $taskId = '') {
        if (empty(\Yii::$app->params['log.dir'])) return;

        $logDir = \Yii::$app->params['log.dir'];
        if (!file_exists($logDir)) return;
	// $logFile = realpath($logDir) . '/walle-' . date('Ymd') . '.log';
	$logFile = realpath($logDir) . '/walle-' . $taskId . '.log'; 
	if($taskId == '') {
	   $logFile = realpath($logDir) . '/submit-walle-' . date('Ymd') . '.log';
	}
        if (self::$logFile === null) {
            self::$logFile = fopen($logFile, 'a');
        }

        $message = date('Y-m-d H:i:s -- ') . $message;
        fwrite(self::$logFile, $message . PHP_EOL);
    }

    /**
     * 获取执行command
     *
     * @author wushuiyong
     * @return string
     */
    public function getExeCommand() {
        return $this->command;
    }

    /**
     * 获取执行log
     *
     * @author wushuiyong
     * @return string
     */
    public function getExeLog() {
        return $this->log;
    }

    /**
     * 获取执行log
     *
     * @author wushuiyong
     * @return string
     */
    public function getExeStatus() {
        return $this->status;
    }

    /**
     * 获取耗时毫秒数
     *
     * @return int
     */
    public static function getMs() {
        return intval(microtime(true) * 1000);
    }

    /**
     * 获取目标机器的ip或别名
     *
     * @param $host
     * @return mixed
     */
    protected function getHostName($host) {
        list($hostName,) = explode(':', $host);
        return $hostName;
    }

    /**
     * 获取目标机器的ssh端口
     *
     * @param $host
     * @param int $default
     * @return int
     */
    protected function getHostPort($host, $default = 22) {
        $hostInfo = explode(':', $host);
        return !empty($hostInfo[1]) ? $hostInfo[1] : $default;
    }

    public function createRsyncShell($taskid,$server_ip='',$command,$buid=1)
    {
    if (empty(\Yii::$app->params['log.dir'])) return;
        $logDir = \Yii::$app->params['log.dir'];
        if (!file_exists($logDir)) return;
        if($server_ip){
                $shFile = realpath($logDir) . '/'.$taskid.'_'.$server_ip.'.sh';
        }else{
                $shFile = realpath($logDir) . '/'.$taskid.'.sh';
        }
        $files_c = fopen($shFile, 'w');
        $ip_attr='(';
        if($server_ip){
            $ip_attr .=$server_ip;
        }else{
            foreach(GlobalHelper::str2arr($this->getConfig()->hosts) as $remoteHost){
                $ip_attr .=$remoteHost." ";
            }
        }
        $ip_attr =trim($ip_attr," ").")";
	if($buid=='3.2')
	{
	     $str_repl1 = str_replace('"',"",$command);
	     $str_repl2 = str_replace("'","",$str_repl1);	
	     $str="str='".$str_repl2."'";
	}else{
	     $str="str='".$command."'";
	}
            $content="#!/bin/bash \n"."ipattr=".$ip_attr."; \n ".'length=${#ipattr[*]}'."; \n".' cmd=$(cat '.$taskid.'.log)'."\n".$str." \n for((i=0;i<length;i++)) ;do \n";
	if($buid ==5)
	{
		$content .="touch ".realpath($logDir).'/'.$taskid.'_${ipattr[$i]}.log && echo 1_N_'.$buid.'_N_ >'.realpath($logDir).'/'.$taskid.'_${ipattr[$i]}.log'."\n";
	}
        if($buid==1 || $buid ==5 || $buid=="3.1")
        {
            $content.="nohup ssh -T -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no work@".'${ipattr[$i]} "'.$command.'"';
        }elseif($buid=='3.2'){
	    $content.="nohup ssh -T -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no work@".'${ipattr[$i]} '.$command;
	}elseif($buid==2){
        $content.='nohup '.$command;
        }
        $content.=' && echo $?_N_$1_N_'.$taskid.'_N_success >>'.realpath($logDir).'/'.$taskid.'_${ipattr[$i]}.log || echo $?_N_$1_N_'.$taskid.'_N_$str >>'.realpath($logDir).'/'.$taskid.'_${ipattr[$i]}.log &';
        $content.="\n done";
        fwrite($files_c, $content . PHP_EOL);
        fclose($files_c);
    }
}
