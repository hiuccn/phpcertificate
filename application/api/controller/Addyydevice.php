<?php

namespace app\api\controller;

use app\common\library\Email;
use think\cache\driver\Redis;
use think\Request;
use think\Db;
use app\common\controller\Api;
use app\common\controller\Checkp12;

require_once "../vendor/appstore-connect-api/vendor/autoload.php";

use MingYuanYun\AppStore\Client;
use think\Validate;

class Addyydevice extends Api
{
    protected $noNeedLogin = ["*"];
    protected $noNeedRight = ["*"];

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    private function getCost($warranty)
    {
        // 查询 其他价格
        $cost = 0;
        $costValues = explode(",", Db::table("fa_config")->where("name", "yy_cost")->value("value"));
        if ($warranty == 0) {
            // 无售后
            $cost = floatval($costValues[0]) * 100;
        } elseif ($warranty == 1) {
            // 标准
            $cost = floatval($costValues[1]) * 100;
        } elseif ($warranty == 2) {
            // 加强
            $cost = floatval($costValues[3]) * 100;
        } elseif ($warranty == 3) {
            // 稳定
            $cost = floatval($costValues[4]) * 100;
        } elseif ($warranty == 4) {
            $this->error("预约不支持摆烂");
        } elseif ($warranty == 5) {
            // 超标准
            $cost = floatval($costValues[2]) * 100;
        } else {
            // 无售后
            $cost = floatval($costValues[0]) * 100;
        }
        return $cost;
    }

    private function getPrice($userId, $warranty)
    {
        // 查询 其他价格
        $configValues = explode(",", Db::table("fa_config")->where("name", "yypz")->value("value"));
        $customPrice = explode(",", Db::table("fa_user")->where("id", $userId)->value("yy_price"));
        if ($warranty == 0) {
            // 躺平
            $devicePrice = $configValues[0];
            if ($customPrice[0] != 0 && $devicePrice > $customPrice[0]) {
                $devicePrice = $customPrice[0];
            }
            $openName = "open1";
            $warrantyName = "无售后";
        } elseif ($warranty == 2) {
            // 加强
            $devicePrice = $configValues[2];
            if ($customPrice[1] != 0 && $devicePrice > $customPrice[1]) {
                $devicePrice = $customPrice[1];
            }
            $openName = "open3";
            $warrantyName = "加强";
        } elseif ($warranty == 3) {
            // 稳定
            $devicePrice = $configValues[3];
            if ($customPrice[2] != 0 && $devicePrice > $customPrice[2]) {
                $devicePrice = $customPrice[2];
            }
            $openName = "open4";
            $warrantyName = "稳定";
        } elseif ($warranty == 4) {
            // 摆烂
            $devicePrice = $configValues[4];
            if ($customPrice[3] != 0 && $devicePrice > $customPrice[3]) {
                $devicePrice = $customPrice[3];
            }
            $openName = "open0";
            $warrantyName = "摆烂";
        } else {
            // 标准
            $devicePrice = $configValues[1];
            if ($customPrice[4] != 0 && $devicePrice > $customPrice[4]) {
                $devicePrice = $customPrice[4];
            }
            $openName = "open1";
            $warrantyName = "无售后";
        }
        return compact("devicePrice", "openName", "warrantyName");
    }

    public function index(Request $request)
    {
        // 设置超时时间和缓冲区
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");

        // 获取请求参数
        $type = $request->param("type");
        if ($type == "" || $type == null) {
            $type = 0;
        }
        $token = $request->param("token");
        $beizhu = $request->param("beizhu");
        $warranty = $request->param("warranty");

        // 检查token
        if ($token == "") {
            $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
        }

        if ($token == "请更换token") {
            $this->error("请先更换token", array("error_time" => time(), "error" => "token不正确"));
        }

        // 查询用户信息
        $userData = Db::table("fa_user")->where("ktoken", $token)->order("id desc")->select();
        if (!$userData) {
            $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
        }

        $money = $userData[0]["money"];
        $userId = $userData[0]["id"];
        $username = $userData[0]["username"];
        $mac = $userData[0]["mac"];
        $udid = $request->param("udid");

        // 检查UDID
        if (!(strlen($udid) == 25 || strlen($udid) == 40)) {
            $this->error("UDID错误", array("error_time" => time(), "error" => "UDID错误"));
        }

        // 生成设备ID
        $certId = $this->getKid();
        while (Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("kid", $certId)->order("id desc")->select()) {
            $certId = $this->getKid();
        }

        $tryPublic = false;

        $isWarranty = false;
        $shouhouType = 0;

        // 独立池
        if ($type == 1 || $type == 2) {
            $deviceData = Db::table("fa_deviceslist")->where("type", 1)->where("chi", 1)->where("udid", $udid)->where("user", $username)->order("id desc")->select();
            if ($deviceData) {
                foreach ($deviceData as $device) {
                    $base64mp = $device["base64mp"];
                    $base64p12 = $device["base64p12"];
                    if ($base64p12 == "") {
                        continue;
                    }
                    $checkP12 = new CheckP12();
                    $p12Content = $checkP12->loadP12($base64p12);
                    $p12Content = json_decode($p12Content->getContent(), true);
                    if ($p12Content["state"] == true) {
                        $this->success("独立池已存在该设备", array("id" => $device["kid"], "pname" => $device["pname"], "pool" => 1, "addtime" => $device["tjtime"], "mobileprovision" => $base64mp, "p12" => $base64p12, "state" => $p12Content["state"]));
                    }
                }
            }

            $agentData = Db::table("fa_agentapplelist")->where("uid", $userId)->where("zt", 1)->where("yy", 1)->order("id asc")->select();
            if ($agentData) {
                if ($mac == 0) {
                    foreach ($agentData as $agent) {
                        if ($agent["iphone"] > 0) {
                            $iss = $agent["iss"];
                            $kid = $agent["kid"];
                            $cid = $agent["cid"];
                            $bid = $agent["bid"];
                            $devname = $agent["devname"];
                            $p8Path = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $agent["p8"];
                            $osType = "IOS";
                            $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 1, $warranty);
                            $addResultJson = json_decode($addResult->getContent(), true);
                            if ($addResultJson["code"] == 1) {
                                return $addResult;
                            } else {
                                continue;
                            }
                            break;
                        }
                    }
                    // 那这可能是在加ipad
                    $agent = $agentData[0];
                    $iss = $agent["iss"];
                    $kid = $agent["kid"];
                    $cid = $agent["cid"];
                    $bid = $agent["bid"];
                    $devname = $agent["devname"];
                    $p8Path = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $agent["p8"];
                    $osType = "IOS";
                    $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 1, $warranty);
                    $addResultJson = json_decode($addResult->getContent(), true);
                    if ($addResultJson["code"] == 1) {
                        return $addResult;
                    } else {
                        $this->error("添加失败，独立池无可用证书", array("error" => $addResultJson));
                    }
                } else {
                    foreach ($agentData as $agent) {
                        if ($agent["iphone"] >= 0 || $agent["mac"] >= 0) {
                            $iss = $agent["iss"];
                            $kid = $agent["kid"];
                            $cid = $agent["cid"];
                            $bid = $agent["bid"];
                            $devname = $agent["devname"];
                            $p8Path = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $agent["p8"];
                            if ($agent["iphone"] > 0) {
                                if (strlen($udid) == 25) {
                                    $osType = "MAC_OS";
                                    if ($agent["mac"] <= 0) {
                                        $osType = "IOS";
                                    }
                                } else {
                                    $osType = "IOS";
                                }
                                $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 1, $warranty);
                                $addResultJson = json_decode($addResult->getContent(), true);
                                if ($addResultJson["code"] == 1) {
                                    return $addResult;
                                } elseif ($addResultJson["code"] == 101) {
                                    return $addResult;
                                }
                            } else {
                                if (strlen($udid) != 25) {
                                    continue;
                                }
                                $osType = "MAC_OS";
                                $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 1, $warranty);
                                $addResultJson = json_decode($addResult->getContent(), true);
                                if ($addResultJson["code"] == 1) {
                                    return $addResult;
                                } elseif ($addResultJson["code"] == 101) {
                                    return $addResult;
                                }
                            }
                        }
                    }
                    // 那这可能是在加ipad
                    $agent = $agentData[0];
                    $iss = $agent["iss"];
                    $kid = $agent["kid"];
                    $cid = $agent["cid"];
                    $bid = $agent["bid"];
                    $devname = $agent["devname"];
                    $p8Path = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $agent["p8"];
                    $osType = "IOS";
                    $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 1, $warranty);
                    $addResultJson = json_decode($addResult->getContent(), true);
                    if ($addResultJson["code"] == 1) {
                        return $addResult;
                    } else {
                        $this->error("添加失败，独立池无可用证书", array("error" => $addResultJson));
                    }
                }
            }
            if ($type == 2) {
                $tryPublic = true;
            } else {
                $this->error("添加失败，独立池无证书可预约", array("error" => "添加失败"));
            }
        }

        // 处理类型为0或$isPublic为true的情况
        if ($type == 0 || $tryPublic == true) {

            // 判断掉签情况
            $deviceData = Db::table("fa_deviceslist")
                ->field("base64mp,base64p12", true)
                ->where("type", 1) // 预约
                ->where("chi", 0)
                ->where("udid", $udid)
                ->where("user", $username)
                ->order("id asc")
                ->select();

            if ($deviceData) {
                $latestDevice = array();
                $p12Info["state"] = true;
                // 判断掉签
                foreach ($deviceData as $device) {
                    if ($device["zt"] == "hidden" || $device["zt"] == "expiration") {
                        $p12Info["state"] = false;
                    } else {
                        $p12Info["state"] = true;
                        $device = Db::table("fa_deviceslist")
                            ->where("type", 1)
                            ->where("chi", 0)
                            ->where("kid", $device["kid"])
                            ->where("user", $username)
                            ->order("id asc")
                            ->find();
                        $mobileprovision = $device["base64mp"];
                        $p12 = $device["base64p12"];
                        $this->success("设备已存在", array("id" => $device["kid"], "pname" => $device["pname"], "pool" => 0, "addtime" => $device["tjtime"], "mobileprovision" => $mobileprovision, "p12" => $p12, "state" => $p12Info["state"]));
                    }
                    $latestDevice = $device;
                }

                if ($latestDevice) {
                    $warrantyTime = 0;
                    if ($latestDevice["shtype"] == 1) {
                        $warrantyTime = 3456000;
                    } elseif ($latestDevice["shtype"] == 2) {
                        $warrantyTime = 15552000;
                    } elseif ($latestDevice["shtype"] == 3) {
                        $warrantyTime = 28512000;
                    }

                    // 获取最新不是售后的
                    $originDevice = Db::table("fa_deviceslist")
                        ->field("base64mp,base64p12", true)
                        ->where("type", 1) // 预约
                        ->where("chi", 0)
                        ->where("udid", $udid)
                        ->where("shouhou", 0)
                        ->where("user", $username)
                        ->where("shtype", $latestDevice["shtype"])
                        ->order("id desc")
                        ->select();
                    $expirationTime = $originDevice[0]["tjtime"] + $warrantyTime;

                    if (!$p12Info["state"] && time() < $expirationTime) {
                        $isWarranty = true;
                        $shouhouType = 1;
                        $warranty = $latestDevice["shtype"];
                    }
                }
            }


            //$this->error("hh: " . $isWarranty . $shouhouType);

            // "devicePrice", "openName", "warrantyName"
            extract($this->getPrice($userId, $warranty));

            if ($money < $devicePrice  && !$isWarranty) {
                $this->error("账号余额不足", array("error_time" => time(), "error" => "账号余额不足"));
            }

            $deviceData = Db::table("fa_deviceslist")->where("type", 1)->where("chi", 0)->where("udid", $udid)->where("user", $username)->order("id desc")->select();
            if ($deviceData) {
                foreach ($deviceData as $device) {
                    $base64mp = $device["base64mp"];
                    $base64p12 = $device["base64p12"];
                    if ($base64p12 == "") {
                        continue;
                    }
                    $checkP12 = new CheckP12();
                    $p12Content = $checkP12->loadP12($base64p12);
                    $p12Content = json_decode($p12Content->getContent(), true);
                    if ($p12Content["state"] == true) {
                        $this->success("预约设备已存在", array("id" => $device["kid"], "pname" => $device["pname"], "pool" => 0, "addtime" => $device["tjtime"], "mobileprovision" => $base64mp, "p12" => $base64p12, "state" => $p12Content["state"]));
                    }
                }
            }
            $appleIdData = Db::table("fa_appleidlist")
                ->where("zt", 1)
                ->where("yy", 1)
                ->where("open0", 0) // 不摆烂
                ->order("weigh asc")->select();
            if ($appleIdData) {
                foreach ($appleIdData as $appleId) {
                    if ($appleId["iphone"] > 0 || $appleId["mac"] > 0) {
                        $iss = $appleId["iss"];
                        $kid = $appleId["kid"];
                        $cid = $appleId["cid"];
                        $bid = $appleId["bid"];
                        $devname = $appleId["devname"];
                        $p8Path = $_SERVER["DOCUMENT_ROOT"] . $appleId["p8"];
                        if ($appleId["iphone"] > 0) {
                            if (strlen($udid) == 25 && $appleId["mac"] != 0) {
                                $osType = "MAC_OS";
                            } else {
                                $osType = "IOS";
                            }
                            $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 0, $warranty, $isWarranty, $shouhouType);
                            $addResultJson = json_decode($addResult->getContent(), true);
                            if ($addResultJson["code"] == 1) {
                                return $addResult;
                            }
                        } else {
                            if (strlen($udid) != 25) {
                                continue;
                            }
                            $osType = "MAC_OS";
                            $addResult = $this->addIphone($iss, $kid, $p8Path, $bid, $cid, $udid, $osType, $devname, $userId, $username, $money, $token, $certId, $beizhu, 0, $warranty, $isWarranty, $shouhouType);
                            $addResultJson = json_decode($addResult->getContent(), true);
                            if ($addResultJson["code"] == 1) {
                                return $addResult;
                            }
                        }
                    }
                }
            }
            $yscToken = Db::table("fa_config")->where("name", "ysctoken")->value("value");
            if ($yscToken != "") {
                $addResult = $this->toysc($udid, $username, $money, $token, $certId, $beizhu, $userId, $warranty);
                return $addResult;
            } else {
                $this->error("添加失败，公共池无证书可预约", array("error_time" => time(), "error" => "添加失败，公共池无证书可预约"));
            }
        }
    }

    function getkid($length = 6)
    {
        $characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $randomKey = "";
        for ($i = 0; $i < $length; $i++) {
            $randomKey = $randomKey . $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $randomKey;
    }

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

    function sendmsg($id, $username, $pt, $pname, $price, $money, $deviceszt, $udid, $warranty)
    {
        // 获取微信API地址
        $wechatApi = Db::table("fa_config")->where("name", "wechatapi")->value("value");
        // 构造消息体
        $msgData["msgtype"] = "markdown";

        if ($warranty == 0) {
            $warrantyName = "无售后";
        } elseif ($warranty == 1) {
            $warrantyName = "标准版";
        } elseif ($warranty == 2) {
            $warrantyName = "加强版";
        } elseif ($warranty == 3) {
            $warrantyName = "稳定版";
        } else {
            $warrantyName = "未知";
        }

        $msgData["markdown"] = array(
            "content" => "<font color=\"warning\">新出预约证书" . $warrantyName . "\r\n`" . date("Y-m-d H:i:s", time())
                . "`</font>\r\n        >#### 编号:<font color=\"comment\">" . $id
                . "</font>\r\n        >#### 用户:<font color=\"comment\">" . $username
                . "</font>\r\n        >#### 余额:<font color=\"comment\">" . $money . "-" . $price . "=" . ($money - $price)
                . "</font>\r\n        >#### 状态:<font color=\"info\">" . $deviceszt
                . "</font>\r\n        >#### 平台:<font color=\"info\">" . $pt
                . "</font>\r\n        >#### 名称:<font color=\"info\">" . $pname
                . "</font>\r\n        >#### <font color=\"warning\">" . $udid . "</font>"
        );

        // 发送消息
        $this->request_post($wechatApi, json_encode($msgData));
    }

    function addiPhone($iss, $kid, $secret, $bid, $cid, $udid, $zstype, $pname, $uid, $user, $money, $ktoken, $zsid, $beizhu, $chi, $warranty = 0, $ghqx = false, $shouhou = 0)
    {
        
        //$this->error("hh: " . $ghqx . $shouhou);
        $redis = new Redis();
        // if ($redis->has($udid)) {
        //     return json(array("code" => 1001, "msg" => "请勿在一分钟内添加同一个设备", "data" => array()));
        // }
        $redis->set($udid, true, 60);
        // 如果是独立池
        if ($chi == 1) {
            // 查询设备信息
            $deviceInfo = Db::table("fa_agentapplelist")->where("devname", $pname)->order("id desc")->select();
            if (!$deviceInfo) {
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "服务端错误1_2", "data" => array()));
            }
            $p12 = $deviceInfo[0]["p12"];
            $dqtime = $deviceInfo[0]["dqtime"];

            // 初始化客户端
            $authParams = array("iss" => $iss, "kid" => $kid, "secret" => $secret);
            $client = new Client($authParams);
            $token = $client->getToken();
            $headers = array("Authorization" => "Bearer " . $token);
            $client->setHeaders($headers);

            // 注册设备
            $deviceRegistration = $client->api("device")->register($udid, $zstype, $udid);
            $errorCount = isset($deviceRegistration["errors"]) ? $deviceRegistration["errors"] : 0;

            if ($errorCount != 0) {
                $errorDetail = $deviceRegistration["errors"][0]["detail"];
                if (strstr($errorDetail, "already exists on this team")) {
                    $existingDevice = Db::table("fa_deviceslist")->where("udid", $udid)->where("pname", $pname)->order("id desc")->select();
                    if ($existingDevice) {
                        $base64mp = $existingDevice[0]["base64mp"];
                        $zsid = $existingDevice[0]["kid"];
                        $p12 = $existingDevice[0]["base64p12"];
                        $redis->rm($udid);
                        return json(array("code" => 1, "msg" => "设备已存在", "data" => array("id" => $zsid, "pname" => $pname, "pool" => 1, "addtime" => $existingDevice[0]["tjtime"], "mobileprovision" => $base64mp, "p12" => $p12)));
                    } else {
                        $filterParams = array("filter[udid]" => $udid, "limit" => 1);
                        $deviceInfo = $client->api("device")->all($filterParams);
                        $deviceID = $deviceInfo["data"][0]["id"];
                        $deviceModel = $deviceInfo["data"][0]["attributes"]["model"];
                    }
                } elseif (strstr($errorDetail, "the maximum number of registered")) {
                    Db::table("fa_agentapplelist")->where("devname", $pname)->update(array("zt" => 0));
                    $redis->rm($udid);
                    return json(array("code" => 1001, "msg" => "设备上限", "data" => array()));
                } else {
                    $errorLog = date("Y-m-d H:i:s", time()) . " 独立池：预约设备 " . $udid . " " . $pname . " " . $errorDetail . "\r\n";
                    $errorLogFile = "error_log.txt";
                    $errorLogHandle = fopen($errorLogFile, "a+");
                    fwrite($errorLogHandle, iconv("UTF-8", "GBK", $errorLog));
                    fclose($errorLogHandle);
                    $redis->rm($udid);
                    return json(array("code" => 1001, "msg" => "设备添加失败：" . $errorDetail, "data" => array()));
                }
            } else {
                $deviceID = $deviceRegistration["data"]["id"];
                $deviceClass = $deviceRegistration["data"]["attributes"]["deviceClass"];
                $deviceModel = $deviceRegistration["data"]["attributes"]["model"];
                if ($deviceClass == "IPHONE") {
                    Db::table("fa_agentapplelist")->where("devname", $pname)->setDec("iphone", 1);
                }
                if ($deviceClass == "IPAD") {
                    Db::table("fa_agentapplelist")->where("devname", $pname)->setDec("ipad", 1);
                }
                if ($deviceClass == "MAC") {
                    Db::table("fa_agentapplelist")->where("devname", $pname)->setDec("mac", 1);
                }
            }
            $profileType = "IOS_APP_ADHOC";
            $deviceIDs = array($deviceID);
            $certificateIDs = array($cid);
            $profileCreation = $client->api("profiles")->create($udid . rand(1, 99), $bid, $profileType, $deviceIDs, $certificateIDs);
            $errorCount = isset($profileCreation["errors"]) ? $profileCreation["errors"] : 0;
            if ($errorCount != 0) {
                $errorDetail = json_encode($profileCreation["errors"][0]["detail"]);
                if (strstr($errorDetail, "no current ios devices on this team")) {
                    Db::table("fa_deviceslist")->insert(array("chi" => 1, "kid" => $zsid, "zspt" => 1, "udid" => $udid, "base64mp" => "", "deviceid" => $deviceID, "zt" => "normal", "pname" => $pname, "model" => $deviceModel, "user" => $user, "base64p12" => $p12, "tjtime" => time(), "beizhu" => $beizhu, "type" => 1, "dqtime" => $dqtime, "shtype" => $warranty));
                    $redis->rm($udid);
                    return json(array("code" => 1, "msg" => "预约成功，在设备列表可查看状态", "time" => time(), "data" => array("id" => $zsid, "pname" => $pname, "pool" => 1, "addtime" => time(), "mobileprovision" => "", "p12" => $p12)));
                }
                $errorLog = date("Y-m-d H:i:s", time()) . " 独立池：预约描述 " . $udid . " " . $pname . " " . $errorDetail . "\r\n";
                $errorLogFile = "error_log.txt";
                $errorLogHandle = fopen($errorLogFile, "a+");
                fwrite($errorLogHandle, iconv("UTF-8", "GBK", $errorLog));
                fclose($errorLogHandle);
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "Profiles添加失败 " . json_encode($profileCreation["errors"][0]["detail"]), "data" => array()));
            }
            $mobileProvision = $profileCreation["data"]["attributes"]["profileContent"];
            Db::table("fa_deviceslist")->insert(array("chi" => 1, "kid" => $zsid, "zspt" => 1, "udid" => $udid, "base64mp" => $mobileProvision, "deviceid" => $deviceID, "zt" => "normal", "pname" => $pname, "user" => $user, "base64p12" => $p12, "tjtime" => time(), "beizhu" => $beizhu, "type" => 1, "dqtime" => $dqtime, "shtype" => $warranty));
            $redis->rm($udid);
            return json(array("code" => 1, "msg" => "666，恭喜你拿到彩蛋，可以立即下载证书啦！", "time" => time(), "data" => array("id" => $zsid, "pname" => $pname, "pool" => 1, "addtime" => time(), "mobileprovision" => $mobileProvision, "p12" => $p12)));
        } else {
            // 如果是公共池

            $userObj = Db::table("fa_user")->where("username", $user)->find();
            $userId = $userObj["id"];
            // "devicePrice", "openName", "warrantyName"
            extract($this->getPrice($userId, $warranty));

            if ($money < $devicePrice) {
                $this->error("账号余额不足", array("error_time" => time(), "error" => "账号余额不足"));
            }


            $deviceInfo = Db::table("fa_appleidlist")->where("devname", $pname)->order("id desc")->select();
            if (!$deviceInfo) {
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "服务端错误1_2", "data" => array()));
            }
            $p12 = $deviceInfo[0]["p12"];
            $dqtime = $deviceInfo[0]["dqtime"];

            // 初始化客户端
            $authParams = array("iss" => $iss, "kid" => $kid, "secret" => $secret);
            $client = new Client($authParams);
            $token = $client->getToken();
            $headers = array("Authorization" => "Bearer " . $token);
            $client->setHeaders($headers);

            // 注册设备
            $deviceRegistration = $client->api("device")->register($udid, $zstype, $udid);
            $errorCount = isset($deviceRegistration["errors"]) ? $deviceRegistration["errors"] : 0;

            if ($errorCount != 0) {
                $errorDetail = $deviceRegistration["errors"][0]["detail"];
                $errorLog = date("Y-m-d H:i:s", time()) . " 公共池：预约设备 " . $udid . " " . $pname . " " . $errorDetail . "\r\n";
                $errorLogFile = "error_log.txt";
                $errorLogHandle = fopen($errorLogFile, "a+");
                fwrite($errorLogHandle, iconv("UTF-8", "GBK", $errorLog));
                fclose($errorLogHandle);
                if (strstr($errorDetail, "already exists on this team")) {
                    $existingDevice = Db::table("fa_deviceslist")->where("udid", $udid)->where("pname", $pname)->order("id desc")->select();
                    if ($existingDevice) {
                        $base64mp = $existingDevice[0]["base64mp"];
                        $zsid = $existingDevice[0]["kid"];
                        $p12 = $existingDevice[0]["base64p12"];
                        $redis->rm($udid);
                        return json(array("code" => 1, "msg" => "设备已存在", "data" => array("id" => $zsid, "pname" => $pname, "pool" => 0, "addtime" => $existingDevice[0]["tjtime"], "mobileprovision" => $base64mp, "p12" => $p12)));
                    } else {
                        $filterParams = array("filter[udid]" => $udid, "limit" => 1);
                        $deviceInfo = $client->api("device")->all($filterParams);
                        $deviceID = $deviceInfo["data"][0]["id"];
                        $deviceModel = $deviceInfo["data"][0]["attributes"]["model"];
                        $deviceStatus = $deviceInfo["data"][0]["attributes"]["status"];
                    }
                } elseif (strstr($errorDetail, "the maximum number of registered")) {
                    Db::table("fa_appleidlist")->where("devname", $pname)->update(array("zt" => 0));
                    $redis->rm($udid);
                    return json(array("code" => 1001, "msg" => "设备上限", "data" => array()));
                } else {
                    $redis->rm($udid);
                    return json(array("code" => 1001, "msg" => "设备添加失败：" . $errorDetail, "data" => array()));
                }
            } else {
                $deviceID = $deviceRegistration["data"]["id"];
                $deviceStatus = $deviceRegistration["data"]["attributes"]["status"];
                $deviceClass = $deviceRegistration["data"]["attributes"]["deviceClass"];
                $deviceModel = $deviceRegistration["data"]["attributes"]["model"];
                if ($deviceClass == "IPHONE") {
                    Db::table("fa_appleidlist")->where("devname", $pname)->setDec("iphone", 1);
                }
                if ($deviceClass == "IPAD") {
                    Db::table("fa_appleidlist")->where("devname", $pname)->setDec("ipad", 1);
                }
                if ($deviceClass == "MAC") {
                    Db::table("fa_appleidlist")->where("devname", $pname)->setDec("mac", 1);
                }
            }
            $profileType = "IOS_APP_ADHOC";
            $deviceIDs = array($deviceID);
            $certificateIDs = array($cid);
            $profileCreation = $client->api("profiles")->create($udid . rand(1, 99), $bid, $profileType, $deviceIDs, $certificateIDs);
            $errorCount = isset($profileCreation["errors"]) ? $profileCreation["errors"] : 0;
            if ($errorCount != 0) {
                $errorDetail = json_encode($profileCreation["errors"][0]["detail"]);
                if (strstr($errorDetail, "no current ios devices on this team")) {
                    Db::table("fa_deviceslist")->insert(array(
                        "kid" => $zsid,
                        "zspt" => 1,
                        "udid" => $udid,
                        "base64mp" => "",
                        "deviceid" => $deviceID,
                        "zt" => "normal",
                        "pname" => $pname,
                        "user" => $user,
                        "model" => $deviceModel,
                        "base64p12" => $p12,
                        "tjtime" => time(),
                        "cost" => $this->getCost($warranty),
                        "price" => floatval($devicePrice) * 100,
                        "beizhu" => $beizhu,
                        "type" => 1,
                        "shouhou" => $shouhou,
                        "dqtime" => $dqtime,
                        "shtype" => $warranty));
                    if (!$ghqx) {
                        Db::table("fa_user")->where("ktoken", $ktoken)->setDec("money", $devicePrice);
                        $this->sendmsg($zsid, $user, "预约证书", $pname, $devicePrice, $money, $deviceStatus, $udid, $warranty);
                        Db::table("fa_user_money_log")->insert(array("user_id" => $uid, "money" => 0 - $devicePrice, "before" => $money, "after" => $money - $devicePrice, "memo" => "预约证书[" . $zsid . "]", "createtime" => time()));
                    } else {
                        $this->sendmsg($zsid, $user, "预约证书[售后]", $pname, 0, $money, $deviceStatus, $udid, $warranty);
                    }
                    $redis->rm($udid);
                    // $this->sendemail($udid, $zsid, $pname, $money, $devicePrice, $warranty, $user);
                    return json(array("code" => 1, "msg" => "预约成功，您可以随时查询状态", "time" => time(), "data" => array("id" => $zsid, "pname" => $pname, "pool" => 0, "addtime" => time(), "mobileprovision" => "", "p12" => $p12)));
                }
                $errorLog = date("Y-m-d H:i:s", time()) . " 公共池：预约描述 " . $udid . " " . $pname . " " . $errorDetail . "\r\n";
                $errorLogFile = "error_log.txt";
                $errorLogHandle = fopen($errorLogFile, "a+");
                fwrite($errorLogHandle, iconv("UTF-8", "GBK", $errorLog));
                fclose($errorLogHandle);
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "Profiles添加失败 " . json_encode($profileCreation["errors"][0]["detail"]), "data" => array()));
            }
            $mobileProvision = $profileCreation["data"]["attributes"]["profileContent"];
            Db::table("fa_deviceslist")->insert(array(
                "kid" => $zsid,
                "zspt" => 1,
                "udid" => $udid,
                "base64mp" => $mobileProvision,
                "deviceid" => $deviceID,
                "zt" => "normal",
                "pname" => $pname,
                "user" => $user,
                "base64p12" => $p12,
                "model" => $deviceModel,
                "tjtime" => time(),
                "beizhu" => $beizhu,
                "type" => 1,
                "shouhou" => $shouhou,
                "cost" => $this->getCost($warranty),
                "price" => floatval($devicePrice) * 100,
                "dqtime" => $dqtime,
                "shtype" => $warranty));
            if (!$ghqx) {
                Db::table("fa_user")->where("ktoken", $ktoken)->setDec("money", $devicePrice);
                $this->sendmsg($zsid, $user, "预约证书", $pname, $devicePrice, $money, "彩蛋", $udid, $warranty);
                Db::table("fa_user_money_log")->insert(array("user_id" => $uid, "money" => 0 - $devicePrice, "before" => $money, "after" => $money - $devicePrice, "memo" => "预约证书[" . $zsid . "]", "createtime" => time()));
            } else {
                $this->sendmsg($zsid, $user, "预约证书[售后]", $pname, 0, $money, "彩蛋", $udid, $warranty);
            }
            $redis->rm($udid);
            // $this->sendemail($udid, $zsid, $pname, $money, $devicePrice, $warranty, $user);
            return json(array("code" => 1, "msg" => "666，恭喜你拿到彩蛋，可以立即使用啦！", "time" => time(), "data" => array("id" => $zsid, "pname" => $pname, "pool" => 0, "addtime" => time(), "mobileprovision" => $mobileProvision, "p12" => $p12)));
        }
    }

    public function sendemailtest()
    {
        $this->sendemail("00008101-0012259401E9001E", "123456", "测试证书", 100, 20, 1, "admin");
        $this->success("111");
    }

    public function sendemail($udid, $zsid, $pname, $money, $moneyS, $warranty, $username)
    {
        // 查询用户表
        $userResult = Db::table("fa_user")->where("username", $username)->order("id desc")->select();
        // 获取用户ID
        $isReceive = $userResult[0]["notification"];
        if ($isReceive == 0) {
            return;
        }
        $receiver = $userResult[0]["email"];

        if ($receiver) {
            if (!Validate::is($receiver, "email")) {
                $this->error(__('Please input correct email'));
            }
            $email = new Email;

            if ($warranty == 0) {
                $version = "无售后";
            } elseif ($warranty == 1) {
                $version = "标准版";
            } elseif ($warranty == 2) {
                $version = "加强版";
            } elseif ($warranty == 3) {
                $version = "稳定版";
            } else {
                $version = "未知";
            }
            $messageContent = "<div><font color=\"warning\">新出预约证书<br>`" . date("Y-m-d H:i:s", time()) . "`</font><br>"
                . ">#### 编号: <font color=\"comment\">" . $zsid . "</font><br>"
                . ">#### 余额变动: <font color=\"comment\">" . $money . "-" . $moneyS . "=" . ($money - $moneyS) . "</font><br>"
                . ">#### 证书名称: <font color=\"info\">" . $pname . "</font><br>"
                . ">#### 质保: <font color=\"info\">" . $version . "</font><br>"
                . ">#### UDID: <font color=\"warning\">" . $udid . "</font></div>";

            $result = $email
                ->to($receiver)
                ->subject("余额变动提醒: " . $money . "-" . $moneyS . "=" . ($money - $moneyS))
                ->message($messageContent)
                ->send();
            if ($result) {
                $this->success();
            } else {
                $this->error($email->getError());
            }
        } else {
            $this->error(__('Invalid parameters'));
        }
    }


    function getCerName($base64P12, $pw = '1')
    {
        // Generate a random directory name
        $tempDir = rand(1, 100);
        if (!file_exists($tempDir)) {
            mkdir($tempDir);
        }
        // Save the base64 decoded P12 file to the directory
        file_put_contents($tempDir . "/sign.p12", base64_decode($base64P12));
        // Get the server root path
        $serverRoot = $_SERVER["DOCUMENT_ROOT"] . "/" . $tempDir;
        $certificates = array();
        // Read the P12 file contents
        $p12Content = file_get_contents($serverRoot . "/sign.p12");
        // Extract certificates from the P12 file
        openssl_pkcs12_read($p12Content, $certificates, $pw);
        // Write the certificate to a PEM file
        $certFilePath = $serverRoot . "/sign.pem";
        ($fileHandle = fopen($certFilePath, "w")) || die("Unable to open file!");
        fwrite($fileHandle, $certificates["cert"]);
        fclose($fileHandle);
        // Get the subject of the certificate
        $subject = shell_exec("openssl x509 -in " . $certFilePath . " -noout -subject -nameopt RFC2253 2>&1");
        $subject = $this->getSubstr($subject, "O=", ",");
        // Get the expiry date of the certificate
        $expiryDate = shell_exec("openssl x509 -in " . $certFilePath . " -noout -dates 2>&1");
        $expiryDate = $this->getSubstr($expiryDate, "notAfter=", "T") . "T";
        $expiryTime = strtotime($expiryDate);
        $formattedExpiry = date("Y-m-d H:i:s", $expiryTime);
        // Initialize base64P12
        $base64P12 = "";
        // If password is not "1", create a new P12 file with modified password
        if ($pw != "1") {
            shell_exec("openssl pkcs12 -in " . $serverRoot . "/sign.p12 -password pass:\"" . $pw . "\" -passout pass:\"123456\" -out " . $serverRoot . "/temp.pem");
            shell_exec("openssl pkcs12 -passin pass:\"123456\" -passout pass:\"1\" -export -in " . $serverRoot . "/temp.pem -out " . $serverRoot . "/developer.p12");
            $base64P12 = base64_encode(file_get_contents($tempDir . "/developer.p12"));
        }
        // Remove the temporary directory
        shell_exec("rm -rf " . $serverRoot);
        // Return the certificate information as JSON
        return json(array("code" => 1, "msg" => "成功", "data" => array("devname" => $subject, "dqtime" => $formattedExpiry, "newp12" => $base64P12)));
    }

    function toySc($udid, $user, $money, $ktoken, $zsid, $beizhu, $uid, $warranty)
    {
        $redis = new Redis();
        if ($redis->has($udid)) {
            return json(array("code" => 1001, "msg" => "请勿在一分钟内添加同一个设备", "data" => array()));
        }
        $redis->set($udid, true, 60);

        // Get token for toySC
        $toyToken = Db::table("fa_config")->where("name", "ysctoken")->value("value");

        $userResult = Db::table("fa_user")->where("username", $user)->order("id desc")->select();
        $userId = $userResult[0]["id"];

        // "devicePrice", "openName", "warrantyName"
        extract($this->getPrice($userId, $warranty));

        if ($money < $devicePrice) {
            $this->error("账号余额不足", array("error_time" => time(), "error" => "账号余额不足"));
        }

        // Get API URL for toySC
        $apiEndpoint = "https://open.hiuc.cn/api/addyydevice";
        // Prepare data for toySC API request
        $requestData = [
            "udid"     => $udid,
            "type"     => 0,
            "beizhu"   => $user,
            "token"    => $toyToken,
            "warranty" => 0
        ];
        // Send request to toySC API
        $response = $this->request_post($apiEndpoint, $requestData);
        $responseData = json_decode($response, true);
        if ($responseData["code"] == 1) {
            // Process successful response
            $mobileProvision = $responseData["data"]["mobileprovision"];
            $p12 = $responseData["data"]["p12"];
            // Get certificate name and expiration date
            $certInfo = json_decode($this->getCerName($p12)->getContent(), true);
            // Insert device information into database
            Db::table("fa_deviceslist")->insert([
                "kid"       => $zsid,
                "zspt"      => 2,
                "udid"      => $udid,
                "base64mp"  => $mobileProvision,
                "deviceid"  => $responseData["data"]["id"],
                "zt"        => "normal",
                "pname"     => $certInfo["data"]["devname"],
                "shtype"    => $warranty,
                "user"      => $user,
                "base64p12" => $p12,
                "tjtime"    => time(),
                "beizhu"    => $beizhu,
                "type"      => 1,
                "dqtime"    => $certInfo["data"]["dqtime"]
            ]);
            // Decrease user's money balance
            Db::table("fa_user")->where("ktoken", $ktoken)->setDec("money", $devicePrice);
            // Send message about certificate reservation
            $this->sendmsg($zsid, $user, "优速测" . $warrantyName, $certInfo["data"]["devname"], $devicePrice, $money, "未知", $udid, $warranty);
            // Log money transaction
            Db::table("fa_user_money_log")->insert([
                "user_id"    => $uid,
                "money"      => 0 - $devicePrice,
                "before"     => $money,
                "after"      => $money - $devicePrice,
                "memo"       => "预约证书" . $warrantyName . "[" . $zsid . "]",
                "createtime" => time()
            ]);
            // Return success response
            $redis->rm($udid);
            // $this->sendemail($udid, $zsid, $certInfo["data"]["devname"], $money, $devicePrice, $warranty, $user);
            return json([
                "code" => 1,
                "msg"  => "添加成功",
                "data" => [
                    "id"              => $zsid,
                    "pname"           => $certInfo["data"]["devname"],
                    "pool"            => 0,
                    "addtime"         => time(),
                    "mobileprovision" => $mobileProvision,
                    "p12"             => $p12
                ]
            ]);
        } else {
            // Return failure response
            $redis->rm($udid);
            return json([
                "code" => 1001,
                "msg"  => "预约失败",
                "data" => [
                    // "sites" => "优速测",
                    "error" => $responseData["msg"]
                ]
            ]);
        }
    }

    function getSubstr($str, $leftStr, $rightStr)
    {
        // Find the position of the left string
        $startPos = strpos($str, $leftStr);
        // Find the position of the right string starting from the left string position
        $endPos = strpos($str, $rightStr, $startPos);
        // Check if either of the positions is invalid
        if ($startPos < 0 || $endPos < $startPos) {
            return "";
        }
        // Extract the substring between the left and right strings
        return substr($str, $startPos + strlen($leftStr), $endPos - $startPos - strlen($leftStr));
    }
}
