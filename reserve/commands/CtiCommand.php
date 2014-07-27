<?php

/**
 *   CTIServerを監視して、電話がかけられた際にPush通知を行うデーモン
 *
 *   CTIを契約しているOrangeReserveに用いる
 */
require_once 'PEAR/Exception.php';
require_once 'System/Daemon.php';
require_once 'System/Daemon/Options.php';
require_once 'System/Daemon/Exception.php';

require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Autoload.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Abstract.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Push.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Exception.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Message.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Message/Custom.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Log/interface.php';
require_once dirname(__FILE__) . '/ApnsPHP-r100/ApnsPHP/Log/Embedded.php';


define('PUSH_EXPIRRY_SECONDS', 30); 
define('PUSH_BADGE', 1);
date_default_timezone_set("Asia/Tokyo");

class CtiCommand extends CConsoleCommand
{

    public $lastDialedTime;
    
    public function actionRun($shopId) {
            $app_name = "pushnotification_daemon_with_cti_" . $shopId;
            //$app_name = "cti_" . $shopId;
            $options = array("appName"        => $app_name,
                             "appDir"         => dirname(__FILE__), 
                             "logLocation"    => Yii::getPathOfAlias('application.runtime') . '/' . $app_name . '.log', 
                             "appPidLocation" => dirname(__FILE__) . '/' . $app_name . '/' . $app_name . '.pid', 
                             "appRunAsUID"    => $shopId,
                             "appRunAsGID"    => $shopId
                             );
            $this->lastDialedTime  = '';
            System_Daemon::setOptions($options);
            System_Daemon::start();
            while (!System_Daemon::isDying()) {
                    
                    $ctiInfo = $this->setCtiInfo($shopId);
                    $ctiStatus = $this->getCtiInfoStatus($ctiInfo);
                    $telno = $this->getTelnoFromCtiInfo($ctiInfo);
                    $dialedTime = $this->getDialedTimeFromCtiInfo($ctiInfo);
                    if ($ctiStatus == "OnCallList" && $this->lastDialedTime != $dialedTime) {
                        $this->lastDialedTime  = $dialedTime;
                        $customerInfo = $this->searchCustoemrWithTelno($telno);
                        $message = $this->pushNotificationToReserve($customerInfo, $telno, $shopId);
       
                        System_Daemon::log(System_Daemon::LOG_INFO, 
                            'shop_id:' . $shopId . 
                            ' telno:' . $telno . 
                            ' date:' . date('Y/m/d H:i:s') . 
                            ' message:' . $message);
                    }
                    System_Daemon::log(System_Daemon::LOG_INFO, 'PushNotificationCti Daemon cotinue' . ' date:' . date('Y/m/d H:i:s'));
                    System_Daemon::iterate(1);
            }
            System_Daemon::log(System_Daemon::LOG_INFO, 'PushNotificationCti Daemon stopped' . ' date:' . date('Y/m/d H:i:s'));
    }

    public function searchCustoemrWithTelno($telno) {
        //$customerModel = Customer::model()->find('concat(tel01, tel02, tel03)=:telno', array(':telno'=>$telno));
        //return $customerModel;
        return 0;
    }

    public function getCtiInfoStatus($ctiInfo)
    {
        return $ctiInfo['0']['cnts'];
    }

    public function getTelnoFromCtiInfo($ctiInfo)
    {
        return $ctiInfo['1'][''];
    }

    public function getDialedTimeFromCtiInfo($ctiInfo)
    {
        return $ctiInfo['23'][''];
    }

    /**
    * CTIサーバー側がcomet実装
    */
    public function setCtiInfo($shopId)
    {
        /*
        $baseInfo = Baseinfo::model()->find('shop_id=:shop_id',
                                        array(':shop_id' => $shopId,
                                            ));
        $ctiEntryPoint = $baseInfo['cti_entry_point'];
        */
        $ctiEntryPoint = "https://n.thincacti.com/CTI/T999-0003-001-01-6fe87e56fe557e69/comet.php?mode=check";
 
        $currentCtiInfoText = $this->curl_get_contents($ctiEntryPoint, 1000);
        $ctiInfo = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $currentCtiInfoText), true);
        //$ctiInfo = json_decode($currentmodif, true);
        
        return $ctiInfo;

    }

    public function curl_get_contents( $url, $timeout = 500 )
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
        $result = curl_exec( $ch );
        curl_close( $ch );
        return $result;
    }

    function fetchDeviceToken($shopId) {
        $posUdid = PosUdid::model()->find('shop_id=:shop_id',
                                        array(':shop_id' => $shopId,
                                            ));
        return $posUdid['device_token'];
    }

    /**
    * Push通知 
    *
    * SSL証明書: entrust_root_certification_authority.pem
    * OrangeReserve Distribution用 Push通知証明書: server_certificates_production.pem
    *
    *  総メッセージ上限: 256byte
    */
    public function pushNotificationToReserve($customerInfo, $telno, $shopId)
    {
        /*
        if (empty($customerInfo) ) {
            $customer = new Customer();
            $customer->setTelno($telno);
             $pushCustomerInfo = array(
            'telno' => $customer->getTelno(),
            );
             $pushMessage = "新規のお客様からのお電話です";
        } else {
            $customer = $customerInfo->getAttributes();
            $pushCustomerInfo = array(
            'telno' => $customerInfo->getTelno(),
            );
            $pushMessage = $customer['name01'] . '様からお電話です';
        } 
        */
        $pushCustomerInfo = array(
            'telno' => $telno,
            );
        $pushMessage = '様からお電話です';
        //$deviceToken = $this->fetchDeviceToken($shopId);
        $deviceToken = 'a2f7b676c4e62b58aee82296be8870e1f3bd212ccfcb4a2a3a46e920e9f887b9';
        try {
            $push = new ApnsPHP_Push(
                //ApnsPHP_Abstract::ENVIRONMENT_SANDBOX,
                ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,
                //dirname(__FILE__) . '/../vendor/ApnsPHP-r100/certificates/server_certificates_development.pem',
                dirname(__FILE__) . '/ApnsPHP-r100/certificates/server_certificates_production.pem'
            );

            $push->setRootCertificationAuthority(dirname(__FILE__) . '/ApnsPHP-r100/certificates/entrust_root_certification_authority.pem');
            $push->connect();
            $message = new ApnsPHP_Message_Custom($deviceToken);
            $message->setCustomIdentifier("Message-" . $deviceToken);
            $message->setBadge(PUSH_BADGE);
            $message->setSound();
            $message->setCustomProperty('customerInfo', $pushCustomerInfo);
            $message->setExpiry(PUSH_EXPIRRY_SECONDS);
            $message->setActionLocKey('予約');
            
            $message->setLocKey($pushMessage );
            $message->setLaunchImage('DefaultAlert.png');
    
            $push->add($message);
            $push->send();
            $push->disconnect();

        } catch(ApnsPHP_Message_Exception $e){
           return $e->getMessage();
        }

        $aErrorQueue = $push->getErrors();
        if (!empty($aErrorQueue)) {
            return $aErrorQueue[1]['ERRORS'][0]['statusMessage'];
        }
        return 'Push Notification success';
    }

}

