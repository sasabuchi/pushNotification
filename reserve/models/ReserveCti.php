<?php

/**
 * This model is used by CtiController.
 *
 * The followings operate RunPushNotificationDaemonWithCtiCommand.
 */
class ReserveCti extends CModel {

    public $shop_id;
    public $operation;
    public $ctiPid;

    public function attributeNames()
    {
	return array_keys($this->getMetaData()->columns);
    }
    
    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'shop_id'   => '店舗ID',
            'operation' => 'CTI稼働',
        );
    }                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         
    
    public function ctiProcessCommand()
    {
        return 'RunPushNotificationDaemonWithCtiCommand run --shopId=' . $this->shop_id;
    }
    
    public function startCti()
    {
       exec(Yii::app()->basePath . '/yiic ' . $this->ctiProcessCommand() . ' > /dev/null &');  
    }
    
    public function killCti()
    {
        exec('kill ' . $this->ctiPid);
    }

    public function updateCtiOperation()
    {
        if ($this->confirmCtiOperation() == 0 && $this->operation == 1) {
            $this->startCti();
        } else if ($this->confirmCtiOperation() == 1 && $this->operation == 0){
            $this->killCti();
        }
    }
    
    public function pickPidAndProcessName($process, &$pid, &$args)
    {
        $process   = ltrim($process);
        $pos    = strpos($process, " ");
        $pid    = intval(substr($process, 0, $pos));
        $args   = substr($process, $pos+1);
    }
    
    public function checkProcessExists($checkedProcessName)
    {
        $exist = false;
        $command = "/bin/ps -e -o pid,args";
        $processList = popen($command, "r");
        while (($process = fgets($processList)) != false) {
                $this->pickPidandProcessName($process, $pid, $args);
                if ($pid <= 0) {
                    continue;
                }
                if (strstr($args, $checkedProcessName)) {
                        $exist = true;
                        $this->ctiPid = $pid;
                        break;
                }
        }
        fclose($processList);
        return $exist;
    }
     
    public function confirmCtiOperation()
    {
        $confirm = $this->checkProcessExists($this->ctiProcessCommand());
        return $confirm; 
    }
    
}