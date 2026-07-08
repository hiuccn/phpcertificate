<?php

namespace app\api\controller;

use think\Request;
use think\Db;
use app\common\controller\Api;

require_once "../vendor/appstore-connect-api/vendor/autoload.php";

use MingYuanYun\AppStore\Client;

class Addcer extends Api
{
    protected $noNeedLogin = ["*"];
    protected $noNeedRight = ["*"];


    public function test()
    {
        // Get the issuer id from the post request
        $issuerId = $this->request->post("iss");
        // Get the key id from the post request
        $keyId = $this->request->post("kid");
        // Get the p8 file name from the post request
        $p8 = $this->request->post("p8");
        // Create the client configuration array
        $clientConfig = array("iss" => $issuerId, "kid" => $keyId, "secret" => $p8);
        // Create a new App Store Connect API client
        $appStoreClient = new Client($clientConfig);
        // Get the JWT token
        $jwtToken = $appStoreClient->getToken();
        // Create the authorization header
        $authHeader = array("Authorization" => "Bearer " . $jwtToken);
        // Set the headers on the client
        $appStoreClient->setHeaders($authHeader);
        // Fetch all certificates
        $certificates = $appStoreClient->api("certificates")->all();
        // Check if there were any errors
        $errors = isset($certificates["errors"]) ? $certificates["errors"] : 0;
        // If there were errors, return an error response
        if ($errors != 0) {
            $this->error(__("错误：" . $certificates["errors"][0]["detail"], ""));
        } else {
            // Otherwise, return the certificates
            return $certificates;
        }
    }

    public function del()
    {
        if ($this->request->isPost()) {
            // Get the certificate id from the post request
            $certificateId = $this->request->post("id");
            // Get the issuer id from the post request
            $issuerId = $this->request->post("iss");
            // Get the key id from the post request
            $keyId = $this->request->post("kid");
            // Get the p8 file name from the post request
            $p8FileName = $this->request->post("p8");
            // Construct the path to the p8 file
            $appStoreClient = $this->getAppStoreClient($p8FileName, $issuerId, $keyId);
            // Delete the certificate
            $deleteResponse = $appStoreClient->api("certificates")->del($certificateId);
            // Check if there were any errors
            $errors = isset($deleteResponse["errors"]) ? $deleteResponse["errors"] : 0;
            // If there were errors, return an error response
            if ($errors != 0) {
                $this->error(__("错误：" . $deleteResponse["errors"][0]["detail"], ""));
            } else {
                // Otherwise, return a success response
                $this->success();
            }
        }
    }

    public function check()
    {
        if ($this->request->isPost()) {
            // Get the user id from the post request
            $userId = $this->request->post("uid");
            // Get the issuer id from the post request
            $issuerId = $this->request->post("iss");
            // Get the key id from the post request
            $keyId = $this->request->post("kid");
            // Get the p8 file name from the post request
            $p8FileName = $this->request->post("p8");
            // Get the remark from the post request
            $remark = $this->request->post("beizhu");
            // Construct the path to the p8 file
            $p8FilePath = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $p8FileName;
            // Fetch user details from database
            $userDetails = Db::table("fa_user")->where("id", $userId)->order("id desc")->select();
            // Get the hosting fee from the database
            $hostingFee = Db::table("fa_config")->where("name", "dlcfy")->value("value");
            // Get the user's money
            $userMoney = $userDetails[0]["money"];
            // Check if the user has enough money
            if ($userMoney < $hostingFee) {
                $this->error("添加独立池需要" . $hostingFee . "元托管费，请充值余额！");
            }
            // Create the client configuration array
            $clientConfig = array("iss" => $issuerId, "kid" => $keyId, "secret" => $p8FilePath);
            // Create a new App Store Connect API client
            $appStoreClient = new Client($clientConfig);
            // Get the JWT token
            $jwtToken = $appStoreClient->getToken();
            // Create the authorization header
            $authHeader = array("Authorization" => "Bearer " . $jwtToken);
            // Set the headers on the client
            $appStoreClient->setHeaders($authHeader);
            // Fetch all certificates
            $certificates = $appStoreClient->api("certificates")->all();
            // Check if there were any errors
            $errors = isset($certificates["errors"]) ? $certificates["errors"] : 0;
            // If there were errors, return an error response
            if ($errors != 0) {
                $this->error(__("错误：" . $certificates["errors"][0]["detail"], ""));
            } else {
                // Otherwise, return the certificates
                return $certificates;
            }
        }
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $userId = $this->request->post("uid"); // 用户ID
            $issuerId = $this->request->post("iss"); // 发行人ID
            $keyId = $this->request->post("kid"); // 密钥ID
            $p8FileName = $this->request->post("p8"); // P8文件名
            $remark = $this->request->post("beizhu"); // 备注
            $p8FilePath = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $p8FileName; // P8文件路径
            $userDetails = Db::table("fa_user")->where("id", $userId)->order("id desc")->select(); // 用户详情
            $userMoney = $userDetails[0]["money"]; // 用户资金
            $username = $userDetails[0]["username"]; // 用户名
            $hostingFee = Db::table("fa_config")->where("name", "dlcfy")->value("value"); // 托管费
            if ($userMoney < $hostingFee) {
                $this->error("添加独立池需要" . $hostingFee . "元托管费，请充值余额！");
            }
            $clientConfig = array("iss" => $issuerId, "kid" => $keyId, "secret" => $p8FilePath); // 客户端配置
            $appStoreClient = new Client($clientConfig); // App Store客户端
            $jwtToken = $appStoreClient->getToken(); // JWT令牌
            $authHeader = array("Authorization" => "Bearer " . $jwtToken); // 授权头
            $appStoreClient->setHeaders($authHeader); // 设置头
            $certificateId = isset($_COOKIE["cid"]) ? $_COOKIE["cid"] : ""; // 证书ID
            $bundleId = isset($_COOKIE["bid"]) ? $_COOKIE["bid"] : ""; // 包ID
            if ($certificateId == "") {
                $certificateCreationResponse = $appStoreClient->api("certificates")->create(); // 证书创建响应
                $errors = isset($certificateCreationResponse["errors"]) ? $certificateCreationResponse["errors"] : 0; // 错误
                if ($errors != 0) {
                    $this->error(__("certificates添加失败：" . $certificateCreationResponse["errors"][0]["detail"], ""));
                }
                $certificateId = $certificateCreationResponse["data"]["id"]; // 证书ID
                setcookie("cid", $certificateId, time() + 300); // 设置cookie
            }
            if ($bundleId == "") {
                $bundleName = "suyun" . rand(1, 10000); // 包名
                $bundleType = "IOS"; // 包类型
                $bundleIdentifier = "app." . $bundleName . ".test"; // 包标识符
                $bundleRegistrationResponse = $appStoreClient->api("bundleId")->register($bundleName, $bundleType, $bundleIdentifier); // 包注册响应
                $errors = isset($bundleRegistrationResponse["errors"]) ? $bundleRegistrationResponse["errors"] : 0; // 错误
                if ($errors != 0) {
                    $this->error(__("bundleId添加失败：" . $bundleRegistrationResponse["errors"][0]["detail"], ""));
                }
                $bundleId = $bundleRegistrationResponse["data"]["id"]; // 包ID
                setcookie("bid", $bundleId, time() + 300); // 设置cookie
                $capabilities = [
                    "PUSH_NOTIFICATIONS", // 推送通知
                    "USER_MANAGEMENT",
                    "SIRIKIT", // SiriKit
                    "NETWORK_EXTENSIONS", // 网络扩展
                    "CLASSKIT", // ClassKit
                    "PERSONAL_VPN", // 个人VPN
                    "HEALTHKIT", // HealthKit
                    "GAME_CENTER", // GameCenter
                    "WALLET", // Wallet
                    "INTER_APP_AUDIO", // InterAppAudio
                    "ASSOCIATED_DOMAINS", // Associated Domains
                    "APP_GROUPS", // App Groups
                    "HOMEKIT", // HomeKit
                    "WIRELESS_ACCESSORY_CONFIGURATION", // Wireless Accessory Configuration
                    "APPLE_PAY", // Apple Pay
                    "MULTIPATH", // Multipath
                    "NFC_TAG_READING", // NFC Tag Reading
                    "AUTOFILL_CREDENTIAL_PROVIDER", // Autofill Credential Provider
                    "ACCESS_WIFI_INFORMATION", // Access WiFi Information
                    "COREMEDIA_HLS_LOW_LATENCY", // CoreMedia HLS Low Latency
                    "USERNOTIFICATIONS_COMMUNICATION", // 基本通知权限
                    "USERNOTIFICATIONS_TIMESENSITIVE", // 即时通知权限
                    "EXTENDED_VIRTUAL_ADDRESSING", // 扩展虚拟寻址
                    "INCREASED_MEMORY_LIMIT" // 增加内存限制
                ];

                // 循环启用每个能力
                foreach ($capabilities as $capability) {
                        $appStoreClient->api("bundleIdCapabilities")->enable($bundleId, $capability);
                }

            $certificateFilter = array("filter[id]" => $certificateId, "fields[certificates]" => "certificateContent,expirationDate,displayName"); // 证书过滤器
            $certificateResponse = $appStoreClient->api("certificates")->all($certificateFilter); // 证书响应
            $randomNumber = rand(1, 10000); // 随机数
            $dirPath = $_SERVER["DOCUMENT_ROOT"] . "/" . $randomNumber;
            if (!file_exists($dirPath)) {
                mkdir($dirPath);
            }
            $cerFilePath = $dirPath . "/ios_development.cer"; // cer文件路径
            $rootPath = $_SERVER["DOCUMENT_ROOT"] . "/"; // 根路径
            ($fileHandle = fopen($cerFilePath, "w")) || die("Unable to open file!"); // 文件句柄
            $expirationDate = $certificateResponse["data"][0]["attributes"]["expirationDate"]; // 到期日期
            $displayName = $certificateResponse["data"][0]["attributes"]["displayName"]; // 显示名称
            date_default_timezone_set("UTC"); // 设置时区
            $expirationTimestamp = strtotime($expirationDate); // 到期时间戳
            date_default_timezone_set("Asia/shanghai"); // 设置时区
            $expirationDate = date("Y-m-d H:i:s", $expirationTimestamp); // 到期日期
            fwrite($fileHandle, base64_decode($certificateResponse["data"][0]["attributes"]["certificateContent"])); // 写入文件
            fclose($fileHandle); // 关闭文件
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\nMIIEpAIBAAKCAQEAyUkAE+QAHweDCJeJR+2zJn7CoSqa21yW4k97K3KHBb9PWMmk\n3A7pBbH2V5vvbpiKkFdqD05tiLY39gdzb+kP3k9DtKDusouaFfvQprdaH6xuR1i5\nEreFesoiU8ntVqdPtL2zBgHX0/RDLQ0I/PM/QjB0eu3NZJRtBA8kYfsOIvu03ChO\nSVb/cgUcm06jpgqETYET5ngB05yayIzyK2vkeWmeqZdfxAKLU5D/Sok19JaowNus\nUk2cErg0zWjUS1VBPlgoyAaAIDe0X58Xv6KtIF+PmMdYuSpSWagkmk3WaU8iGnoN\nRPjF6IFRk9z09qQbrwI6QkLvzgP4xMCJ9L8C8QIDAQABAoIBABkoPZE+2uEF8FOf\nlPHffJegGjVIfOhTzyvj4TIR81w9h+5B7Y/vcSJcFrzmaWt6Nz9JHaFiHQCMPbxL\nPBtNlsUjRQQLZSn9lrmOqopbujDhPTs/lIoJU+5/2wB76WT+LlEZsIlcq5v7GHZF\n/cyXnl1obvZ6SER85I8wUUzJsv+eGz9e/iCw0bsDYadpks7wABwdPSC71QgwN91D\n/UlYTlGQpT5HLfl8YHua1hLSS9C0TQxUY6dKsvCHbd9MD4RqAovgqtuisORdui2f\nIXU/pnBYvoSotNdknjFWMw1Bo3kTi9z6pilRbXiT7FnzuYLfrf6+6fN8Of+Bxd4K\nULwYmxECgYEA6HbBqqWxWCUR2qwLE3Uou8YrHTuRpHy66ugn8k9+Y2kxijTRR/F+\nU9tUBF5N75vdkJj2SZ10HHJLWklcTobMLyc6P5wJIHxbjwekvcO4u0BstzJUJAXs\nL+HHb372dS2O926MQSZ4pYzc/Fw3vzE9DsxVM/xJcpB/EgshQijKGeUCgYEA3aoe\n3Wf8NqLFCyczfew5KuF9/ftZMs1jY4IjOWMM/L0dxSroZt+p0OUt1QXJHa9y3/EY\nLG20dJ5B1fYYn6GLwn2HZpV8R2KlJ0U/S4BtOHouM1YQ5Z/526YHAzIikb+MIYdw\njxTbHON9bJFfZ+IubCo//UqANZVpet+/ApaAhB0CgYEAxcQ6kP4zuSSYYuvY5G3Z\nAJ7gERebmU+QCccGLQxaHyLgRY8XuNgHvDms6aZ9MWrt/VVUul4c6RKHbsFYqWne\njgMWeAU8com5rx42lkbLg2qU0uobUSZEwJuZew6NiDUBGxnOcqLTIyyK2JtvxdWS\n92L43ag1qCSsJmKXodxny80CgYAfKTQvkdet4pHqsHcXo6ahtZNdqgDvGFp5eajz\n/02rFfbiadbD53ta52za/nY4Wxq+ComIbV+p6Tl+F5t8jVw1Wio3rJoM+vwWmjB8\nr7Aq+VoXU2kKrsOUMjHYLCsZ7CCJ8h1Lr/XhiMVwBruvwecew429UMTXQ4rRgDS8\n62VjrQKBgQDBh78KM4ws6Ka1FQTlAYadrP8vJZjtfrPMBEfepSOERExMqNZ/TYaP\n4jlVe2gIA99zQgG+oFM+dtSEXdxYo6wTSPOnCwXsb0EbnkwOKO8GGos7104oxzrI\n+SMuL+Bc/+hudh1cQ/vvHqFsAmoLqsaLqNa0moYm5t8TVzyz2TekfQ==\n-----END RSA PRIVATE KEY-----"; // 私钥
            $keyFilePath = $_SERVER["DOCUMENT_ROOT"] . "/" . $randomNumber . "/ios.key"; // 密钥文件路径
            $fileHandle = fopen($keyFilePath, "w"); // 文件句柄
            fwrite($fileHandle, $privateKey); // 写入文件
            fclose($fileHandle); // 关闭文件
            shell_exec("openssl x509 -in " . $cerFilePath . " -inform DER -outform PEM -out " . $rootPath . $randomNumber . "/ios_development.pem"); // 执行shell命令
            shell_exec("openssl pkcs12 -export -inkey " . $rootPath . $randomNumber . "/ios.key -in " . $rootPath . $randomNumber . "/ios_development.pem -out " . $rootPath . $randomNumber . "/ios_development.p12 -passout pass:1"); // 执行shell命令
            $encodedP12 = base64_encode(file_get_contents($randomNumber . "/ios_development.p12"));
            $deviceName = $displayName;
            while (Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("pname", $deviceName)->order("id desc")->select()) {
                $randomSuffix = $this->generateRandomString();
                $deviceName = $displayName . $randomSuffix;
            }
            setcookie("bid", "", time() + 10);
            setcookie("cid", "", time() + 10);
            Db::table("fa_agentapplelist")->insert(array("user" => $username, "uid" => $userId, "iss" => $issuerId, "kid" => $keyId, "p8" => $p8FileName, "devname" => $deviceName, "cid" => $certificateId, "bid" => $bundleId, "beizhu" => $remark, "p12" => $encodedP12, "dqtime" => $expirationDate));
            shell_exec("rm -rf " . $rootPath . $randomNumber);
            Db::table("fa_user")->where("id", $userId)->setDec("money", $hostingFee);
            Db::table("fa_user_money_log")->insert(array("user_id" => $userId, "money" => 0 - $hostingFee, "before" => $userMoney, "after" => $userMoney - $hostingFee, "memo" => "添加独立池", "createtime" => time()));
            $this->success();
        }
        return parent::add();
    }

    function generateRandomString($length = 1): string
    {
        $characters = "sign";
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString = $randomString . $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    function extractSubstring($inputString, $startString, $endString)
    {
        $startPosition = strpos($inputString, $startString);
        $endPosition = strpos($inputString, $endString, $startPosition);
        if ($startPosition < 0 || $endPosition < $startPosition) {
            return "";
        }
        return substr($inputString, $startPosition + strlen($startString), $endPosition - $startPosition - strlen($startString));
    }

//    function getDeviceUdid()
    function getudid()
    {
        if ($this->request->isPost()) {
            $agentAppleListId = $this->request->post("id");
            $token = $this->request->post("token");
            if ($token == "") {
                $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
            }
            $userDetails = Db::table("fa_user")->where("ktoken", $token)->order("id desc")->select();
            if (!$userDetails) {
                $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
            }
            $username = $userDetails[0]["username"];
            $agentAppleListDetails = Db::table("fa_agentapplelist")->where("id", $agentAppleListId)->order("id desc")->select();
            $issuerId = $agentAppleListDetails[0]["iss"];
            $keyId = $agentAppleListDetails[0]["kid"];
            $p8FileName = $agentAppleListDetails[0]["p8"];
            $deviceName = $agentAppleListDetails[0]["devname"];
            $certificateId = $agentAppleListDetails[0]["cid"];
            $bundleId = $agentAppleListDetails[0]["bid"];
            $p12Encoded = $agentAppleListDetails[0]["p12"];
            $expiryDate = $agentAppleListDetails[0]["dqtime"];
            $appStoreClient = $this->getAppStoreClient($p8FileName, $issuerId, $keyId);
            $deviceFilter = array("filter[platform]" => "IOS", "fields[devices]" => "udid,status,platform", "limit" => 200);
            $deviceDetails = $appStoreClient->api("device")->all($deviceFilter);
            if (isset($deviceDetails["errors"])) {
                $error = $deviceDetails["errors"][0];
                if ($error["code"] == "FORBIDDEN.REQUIRED_AGREEMENTS_MISSING_OR_EXPIRED") {
                    // 协议过期
                    $this->error("协议过期, 请先到苹果后台同意协议!");
                } else {
                    $this->error($deviceDetails["detail"]);
                }
            }
            $totalDevices = $deviceDetails["meta"]["paging"]["total"];
            $deviceListDetails = Db::table("fa_deviceslist")->where("chi", 1)->where("user", $username)->where("platform", "IOS")->where("pname", $deviceName)->order("id desc")->select();
            if (count($deviceListDetails) == $totalDevices) {
                $this->error("系统中已经拥有该账号全部的UDID");
            }
            // 生成device array
            $deviceSystemArray = array();
            foreach ($deviceListDetails as $device) {
                $deviceSystemArray[] = $device["deviceid"];
            }
            $currentTime = time();
            $enabledDevices = array();
            $deviceDetailsArray = array();
            $deviceDetailsArrayForInsert = array();
            foreach ($deviceDetails as $deviceDetail) {
                foreach ($deviceDetail as $device) {
                    if (!empty($device["attributes"]["udid"])) {
                        // 判断deviceid不在系统中
                        if (!in_array($device["id"], $deviceSystemArray)) {
                            $udid = $device["attributes"]["udid"];
                            $status = $device["attributes"]["status"];
                            $platform = $device["attributes"]["platform"];
                            $deviceId = $device["id"];
                            if ($status == "ENABLED") {
                                $enabledDevices[] = $deviceId;
                                $deviceDetailsArray[] = array("udid" => $udid, "deviceid" => $deviceId, "platform" => $platform);
                            } else {
                                $kid = $this->getkid();
                                while (Db::table("fa_deviceslist")->where("kid", $kid)->order("id desc")->select()) {
                                    $kid = $this->getkid();
                                }
                                $deviceDetailsArrayForInsert[] = array("chi" => 1, "platform" => $platform, "kid" => $kid, "zspt" => 1, "udid" => $udid, "deviceid" => $deviceId, "base64mp" => "", "zt" => "normal", "pname" => $deviceName, "user" => $username, "base64p12" => $p12Encoded, "tjtime" => $currentTime, "type" => 1, "dqtime" => $expiryDate);
                            }
                        }
                    }
                }
            }
            if ($enabledDevices != array()) {
                $profileType = "IOS_APP_ADHOC";
                $profileCreationResponse = $appStoreClient->api("profiles")->create("kkk" . rand(1, 99), $bundleId, $profileType, $enabledDevices, array($certificateId));
                $errors = isset($profileCreationResponse["errors"]) ? $profileCreationResponse["errors"] : 0;
                if ($errors != 0) {
                    return json(array("code" => 1001, "msg" => "描述文件创建失败", "data" => array()));
                }
                $profileContent = $profileCreationResponse["data"]["attributes"]["profileContent"];
                foreach ($deviceDetailsArray as $device) {
                    $kid = $this->getkid();
                    while (Db::table("fa_deviceslist")->where("kid", $kid)->order("id desc")->select()) {
                        $kid = $this->getkid();
                    }
                    $deviceDetailsArrayForInsert[] = array("chi" => 1,  "platform" => $device["platform"], "kid" => $kid, "zspt" => 1, "udid" => $device["udid"], "deviceid" => $device["deviceid"], "base64mp" => $profileContent, "zt" => "normal", "pname" => $deviceName, "user" => $username, "base64p12" =>
                        $p12Encoded,
                    "tjtime" => $currentTime, "type" => 0, "dqtime" => $expiryDate);
                }
            }
            if ($deviceDetailsArrayForInsert == array()) {
                $this->error("系统中已经拥有该账号全部的UDID");
            }
            $zsptArray = array_map(function($device) {
                return $device["zspt"];
            }, $deviceDetailsArrayForInsert);
            // $this->error(json_encode($deviceDetailsArrayForInsert));
            Db::table("fa_deviceslist")->insertAll($deviceDetailsArrayForInsert);
            $pendingDevices = $totalDevices - count($deviceDetailsArray) - count($deviceListDetails);
            $totalSyncedDevices = $totalDevices - count($deviceListDetails);
            $this->success("账号共有设备:". $totalDevices . "台设备，已同步到本站:". $totalSyncedDevices . "台设备，正常设备:". count($deviceDetailsArray) . "台，审核中设备:". $pendingDevices . "台");
        }
    }

//    function syncMacDevices()
    function getmacudid()
    {
        if ($this->request->isPost()) {
            $appleListId = $this->request->post("id");
            $token = $this->request->post("token");
            if ($token == "") {
                $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
            }
            $userDetails = Db::table("fa_user")->where("ktoken", $token)->order("id desc")->select();
            if (!$userDetails) {
                $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
            }
            $username = $userDetails[0]["username"];
            $appleListDetails = Db::table("fa_agentapplelist")->where("id", $appleListId)->order("id desc")->select();
            $issuerId = $appleListDetails[0]["iss"];
            $keyId = $appleListDetails[0]["kid"];
            $p8FileName = $appleListDetails[0]["p8"];
            $deviceName = $appleListDetails[0]["devname"];
            $certificateId = $appleListDetails[0]["cid"];
            $bundleId = $appleListDetails[0]["bid"];
            $p12Encoded = $appleListDetails[0]["p12"];
            $expiryDate = $appleListDetails[0]["dqtime"];
            $appStoreClient = $this->getAppStoreClient($p8FileName, $issuerId, $keyId);
            $deviceFilter = array("filter[platform]" => "MAC_OS", "fields[devices]" => "udid,status,platform", "limit" => 100);
            $deviceDetails = $appStoreClient->api("device")->all($deviceFilter);
            $totalDevices = $deviceDetails["meta"]["paging"]["total"];
            $deviceListDetails = Db::table("fa_deviceslist")->where("chi", 1)->where("user", $username)->where("platform", "MAC_OS")->where("pname", $deviceName)->order("id desc")->select();
            if (count($deviceListDetails) == $totalDevices) {
                $this->error("系统中已经拥有该账号全部的UDID");
            }
            $deviceListDetailsJson = json_encode($deviceListDetails);
            $currentTime = time();
            $enabledDevices = array();
            $deviceDetailsArray = array();
            $deviceDetailsArrayForInsert = array();
            foreach ($deviceDetails as $deviceDetail) {
                foreach ($deviceDetail as $device) {
                    if (!empty($device["attributes"]["udid"])) {
                        if (!stristr($deviceListDetailsJson, $device["attributes"]["udid"])) {
                            $udid = $device["attributes"]["udid"];
                            $status = $device["attributes"]["status"];
                            $platform = $device["attributes"]["platform"];
                            $deviceId = $device["id"];
                            if ($status == "ENABLED") {
                                $enabledDevices[] = $deviceId;
                                $deviceDetailsArray[] = array("udid" => $udid, "deviceid" => $deviceId, "platform" => $platform);
                            } else {
                                $kid = $this->getkid();
                                while (Db::table("fa_deviceslist")->where("kid", $kid)->order("id desc")->select()) {
                                    $kid = $this->getkid();
                                }
                                $deviceDetailsArrayForInsert[] = array("chi" => 1, "platform" => $platform, "kid" => $kid, "zspt" => 1, "udid" => $udid, "deviceid" => $deviceId, "base64mp" => "", "zt" => "normal", "pname" => $deviceName, "user" => $username, "base64p12" => $p12Encoded, "tjtime" =>
                                    $currentTime, "type" => 1, "dqtime" => $expiryDate);
                            }
                        }
                    }
                }
            }
            if ($enabledDevices != array()) {
                $profileType = "IOS_APP_ADHOC";
                $profileCreationResponse = $appStoreClient->api("profiles")->create("suyun" . rand(1, 99), $bundleId, $profileType, $enabledDevices, array($certificateId));
                $errors = isset($profileCreationResponse["errors"]) ? $profileCreationResponse["errors"] : 0;
                if ($errors != 0) {
                    return json(array("code" => 1001, "msg" => "描述文件创建失败". json_encode($errors), "data" => array()));
                }
                $profileContent = $profileCreationResponse["data"]["attributes"]["profileContent"];
                foreach ($deviceDetailsArray as $device) {
                    $kid = $this->getkid();
                    while (Db::table("fa_deviceslist")->where("kid", $kid)->order("id desc")->select()) {
                        $kid = $this->getkid();
                    }
                    $deviceDetailsArrayForInsert[] = array("chi" => 1, "platform" => $device["platform"], "kid" => $kid, "zspt" => 1, "udid" => $device["udid"], "deviceid" => $device["deviceid"], "base64mp" => $profileContent, "zt" => "normal", "pname" => $deviceName, "user" => $username, "base64p12" =>
                        $p12Encoded, "tjtime" => $currentTime, "type" => 0, "dqtime" => $expiryDate);
                }
            }
            if ($deviceDetailsArrayForInsert == array()) {
                $this->error("系统中已经拥有该账号全部的UDID");
            }
            Db::table("fa_deviceslist")->insertAll($deviceDetailsArrayForInsert);
            $syncedDevices = count($deviceDetailsArrayForInsert);
            $this->success("同步完成，账号共有MAC设备:" . $totalDevices . "<br>本次同步到本站设备:" . $syncedDevices);
        }
    }

    // Generate a random string of a given length
//    function generateRandomKey($length = 5)
    function getkid($length = 5)
    {
        $characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $randomKey = "";
        for ($i = 0; $i < $length; $i++) {
            $randomKey = $randomKey . $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $randomKey;
    }

    // Send a POST request to a given URL with given data
//    public function sendPostRequest($url = '', $postData = array())
    public function request_post($url = '', $postData = array())
    {
        if (empty($url) || empty($postData)) {
            return false;
        }
        $targetUrl = $url;
        $requestData = $postData;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $targetUrl);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    // Download mobile provision file
    function downmp()
//    function downloadMobileProvision()
    {
        $appleListId = $this->request->post("id");
        $token = $this->request->post("token");
        if ($token == "") {
            $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
        }
        $userDetails = Db::table("fa_user")->where("ktoken", $token)->order("id desc")->select();
        if (!$userDetails) {
            $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
        }
        // $userMac = $userDetails[0]["mac"];
        $userMac = 1;
        $appleListDetails = Db::table("fa_agentapplelist")->where("id", $appleListId)->order("id desc")->select();
        $issuerId = $appleListDetails[0]["iss"];
        $keyId = $appleListDetails[0]["kid"];
        $p8FileName = $appleListDetails[0]["p8"];
        $certificateId = $appleListDetails[0]["cid"];
        $bundleId = $appleListDetails[0]["bid"];
        $enabledDevices = array();
        $deviceName = $appleListDetails[0]["devname"];
        $appStoreClient = $this->getAppStoreClient($p8FileName, $issuerId, $keyId);
        $deviceFilter = array("filter[platform]" => "IOS", "fields[devices]" => "udid,status,platform", "limit" => 200);
        $deviceDetails = $appStoreClient->api("device")->all($deviceFilter);
        $errors = isset($deviceDetails["errors"]) ? $deviceDetails["errors"] : 0;
        if ($errors != 0) {
            return json(array("code" => 1001, "msg" => $deviceDetails["errors"][0]["detail"], "data" => array()));
        }
        foreach ($deviceDetails as $deviceDetail) {
            foreach ($deviceDetail as $device) {
                if (!empty($device["attributes"]["udid"])) {
                    $deviceStatus = $device["attributes"]["status"];
                    $deviceId = $device["id"];
                    if ($deviceStatus == "ENABLED") {
                        $enabledDevices[] = $deviceId;
                    }
                }
            }
        }
        if ($userMac == 1) {
            $deviceFilter = array("filter[platform]" => "MAC_OS", "fields[devices]" => "udid,status,platform", "limit" => 200);
            $macDeviceDetails = $appStoreClient->api("device")->all($deviceFilter);
            foreach ($macDeviceDetails as $deviceDetail) {
                foreach ($deviceDetail as $device) {
                    if (!empty($device["attributes"]["udid"])) {
                        $deviceStatus = $device["attributes"]["status"];
                        $deviceId = $device["id"];
                        if ($deviceStatus == "ENABLED") {
                            $enabledDevices[] = $deviceId;
                        }
                    }
                }
            }
        }
        if ($enabledDevices != array()) {
            $profileType = "IOS_APP_ADHOC";
            $profileCreationResponse = $appStoreClient->api("profiles")->create("ksq" . rand(1, 99), $bundleId, $profileType, $enabledDevices, array($certificateId));
            $errors = isset($profileCreationResponse["errors"]) ? $profileCreationResponse["errors"] : 0;
            if ($errors != 0) {
                return json(array("code" => 1001, "msg" => "描述文件创建失败", "data" => array()));
            }
            $profileContent = $profileCreationResponse["data"]["attributes"]["profileContent"];
            $tempDirectory = "temp";
            if (!file_exists($tempDirectory)) {
                mkdir($tempDirectory);
            }
            $locale = "zh_CN.UTF-8";
            setlocale(LC_ALL, $locale);
            putenv("LC_ALL=" . $locale);
            file_put_contents($tempDirectory . "/" . $deviceName . ".mobileprovision", base64_decode($profileContent));
            return json(array("code" => 1, "url" => "/" . $tempDirectory . "/" . $deviceName . ".mobileprovision", "data" => array()));
        } else {
            return json(array("code" => 1001, "msg" => "此账号无可用UDID创建描述文件", "data" => array()));
        }
    }

    public function getAppStoreClient($p8FileName, $issuerId, $keyId): Client
    {
        $p8FilePath = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $p8FileName;
        // Create the client configuration array
        $clientConfig = array("iss" => $issuerId, "kid" => $keyId, "secret" => $p8FilePath);
        // Create a new App Store Connect API client
        $appStoreClient = new Client($clientConfig);
        // Get the JWT token
        $jwtToken = $appStoreClient->getToken();
        // Create the authorization header
        $authHeader = array("Authorization" => "Bearer " . $jwtToken);
        // Set the headers on the client
        $appStoreClient->setHeaders($authHeader);
        return $appStoreClient;
    }
}