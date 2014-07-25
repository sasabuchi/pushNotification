<?php

/**
 *   CTIServerを監視して、電話がかけられた際にPush通知を行うデーモン
 *
 *   CTIを契約しているOrangeReserveに用いる
 */
require_once 'PEAR/Exception.php';
require_once 'System/Daemon.php';

require_once dirname(__FILE__) . '/../vendor/ApnsPHP-r100/ApnsPHP/Autoload.php';

define('PUSH_EXPIRRY_SECONDS', 30); 
define('PUSH_BADGE', 1);

class RunPushNotificationDaemonWithCtiCommand extends ConsoleCommand
{
    
    public function actionRun($shopId) {
            $app_name = "pushnotification_daemon_with_cti_" . $shopId;
            
            $options = array("appName"        => $app_name,
                             "appDir"         => dirname(__FILE__), 
                             "logLocation"    => Yii::getPathOfAlias('application.runtime') . '/' . $app_name . '.log', 
                             "appPidLocation" => dirname(__FILE__) . '/' . $app_name . '/' . $app_name . '.pid', 
                             "appRunAsUID"    => $shopId,
                             "appRunAsGID"    => $shopId
                             );
            
            System_Daemon::setOptions($options);
            System_Daemon::start();
            while (!System_Daemon::isDying()) {
                    
                    $telno = $this->fetchTelnoFromCTI($shopId);
                    $customerInfo = $this->searchCustoemrWithTelno($telno);
                    $message = $this->pushNotificationToReserve($customerInfo, $telno, $shopId);
       
                    System_Daemon::log(System_Daemon::LOG_INFO, 
                        'shop_id:' . $shopId . 
                        ' telno:' . $telno . 
                        ' date:' . date('Y/m/d H:i:s') . 
                        ' message:' . $message);
                    
                    System_Daemon::iterate(3);
            }
            System_Daemon::log(System_Daemon::LOG_INFO, 'PushNotificationCti Daemon stopped' . ' date:' . date('Y/m/d H:i:s'));
    }

    public function searchCustoemrWithTelno($telno) {
        $customerModel = Customer::model()->find('concat(tel01, tel02, tel03)=:telno', array(':telno'=>$telno));
        return $customerModel;
    }

    /**
    * CTIサーバー側がcomet実装
    */
    public function fetchTelnoFromCTI($shopId)
    {
        $baseInfo = Baseinfo::model()->find('shop_id=:shop_id',
                                        array(':shop_id' => $shopId,
                                            ));
        $ctiEntryPoint = $baseInfo['cti_entry_point'];
        do {
            $currentmodif = file_get_contents($ctiEntryPoint);
            $ctiInfo = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $currentmodif), true);
        } while($ctiInfo[0]['cnts'] != "OnCallList" || empty($ctiInfo[1]['']));
        
        return $ctiInfo[1][''];
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
        
        if(empty($customerInfo) ) {
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

        $deviceToken = $this->fetchDeviceToken($shopId);

        try{
            $push = new ApnsPHP_Push(
                //ApnsPHP_Abstract::ENVIRONMENT_SANDBOX,
                ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,
                //dirname(__FILE__) . '/../vendor/ApnsPHP-r100/certificates/server_certificates_development.pem',
                dirname(__FILE__) . '/../vendor/ApnsPHP-r100/certificates/server_certificates_production.pem'
            );

            $push->setRootCertificationAuthority(dirname(__FILE__) . '/../vendor/ApnsPHP-r100/certificates/entrust_root_certification_authority.pem');
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

