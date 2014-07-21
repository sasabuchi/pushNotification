#!/usr/bin/php
<?php
 
require_once 'System/Daemon.php';
 date_default_timezone_set("Asia/Tokyo");

// デーモンの名前
$app_name = "daemon_push______________________";
 
$options = array("appName"        => $app_name, // デーモンの名前
                 "authorEmail"    => "foo@example.com", // 作者のE-Mail
                 "appDir"         => dirname(__FILE__), // アプリを動かすディレクトリ
                 "logLocation"    => dirname(__FILE__) . "/" . $app_name . ".log", // アプリのログのパス(デフォルトだと/var/log/{アプリ名}.log)
                 "appPidLocation" => dirname(__FILE__) . "/" . $app_name . "/" . $app_name . ".pid", // プロセスIDのパス(デフォルトだと/var/run/{アプリ名}/{アプリ名}.pid)
                 "appRunAsUID"    => 500,
                 "appRunAsGID"    => 500);
 
// 上記オプションをまとめてセット
System_Daemon::setOptions($options);
 
// デーモンの起動
System_Daemon::start();
 
// これ以降の処理がデーモンとして動作させたい処理
// デーモンが生きてるかチェックしながらループ
while (!System_Daemon::isDying()) {
// SLEEP処理(5秒待機)
	$url = "https://n.thincacti.com/CTI/T999-0003-001-01-6fe87e56fe557e69/comet.php?mode=check";
	$json = curl_get_contents($url, 100000000);
    //$json = file_get_contents("https://n.thincacti.com/CTI/T999-0003-001-01-6fe87e56fe557e69/comet.php?mode=check");
    System_Daemon::log(System_Daemon::LOG_INFO, date('Y/m/d H:i:s'));
    print_r($json);
     System_Daemon::log(System_Daemon::LOG_INFO, $json);
    push_simple();
    System_Daemon::iterate(5);
}
 
System_Daemon::stop();

function curl_get_contents( $url, $timeout = 60 ){
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
    $result = curl_exec( $ch );
    curl_close( $ch );
    return $result;
}

function push_simple()
{

// Adjust to your timezone
date_default_timezone_set('Asia/Tokyo');

// Report all PHP errors
error_reporting(-1);

// Using Autoload all classes are loaded on-demand
require_once 'ApnsPHP/Autoload.php';

// Instanciate a new ApnsPHP_Push object
$push = new ApnsPHP_Push(
    //ApnsPHP_Abstract::ENVIRONMENT_SANDBOX,
    ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,
    //'/vagrant/asterisk/agi-bin/reserve/ApnsPHP-r100/certificates/server_certificates_development.pem'
    'certificates/server_certificates_production.pem'
);

// Set the Root Certificate Autority to verify the Apple remote peer
$push->setRootCertificationAuthority('certificates/entrust_root_certification_authority.pem');

// Connect to the Apple Push Notification Service
$push->connect();

// Instantiate a new Message with a single recipient
$message = new ApnsPHP_Message('a2f7b676c4e62b58aee82296be8870e1f3bd212ccfcb4a2a3a46e920e9f887b9');
//$message = new ApnsPHP_Message('00f1b81d400ff29751166b0f860505cb6a70cbb2a3da5355cfc4b3e878e98eb6');

// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
// over a ApnsPHP_Message object retrieved with the getErrors() message.
$message->setCustomIdentifier("Message-Badge-3");

// Set badge icon to "3"
$message->setBadge(3);

// Set a simple welcome text
$message->setText('Hello APNs-enabled device!');

// Play the default sound
$message->setSound();

// Set a custom property
$message->setCustomProperty('acme2', array('bang', 'whiz'));

// Set another custom property
$message->setCustomProperty('acme3', array('bing', 'bong'));

// Set the expiry value to 30 seconds
$message->setExpiry(30);

// Add the message to the message queue
$push->add($message);

// Send all messages in the message queue
$push->send();

// Disconnect from the Apple Push Notification Service
$push->disconnect();

// Examine the error message container
$aErrorQueue = $push->getErrors();
if (!empty($aErrorQueue)) {
    var_dump($aErrorQueue);
}

}