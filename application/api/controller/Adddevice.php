<?php

namespace app\api\controller;

use MingYuanYun\AppStore\Exceptions\ConfigException;
use MingYuanYun\AppStore\Exceptions\InvalidArgumentException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Request;
use think\Db;
use think\cache\driver\Redis;
use app\common\controller\Api;
use app\common\controller\Checkp12;
use app\common\library\Email;
use think\Validate;


require_once "../vendor/appstore-connect-api/vendor/autoload.php";

use MingYuanYun\AppStore\Client;

class Adddevice extends Api
{
    protected $noNeedLogin = ["*"];
    protected $noNeedRight = ["*"];

    private function getCost($warranty, $requestDeviceType = "iphone", $useYY = false)
    {
        // 查询 其他价格
        $cost = 0;
        $costValues = explode(",", Db::table("fa_config")->where("name", "mc_cost")->value("value"));
        $yyCostValues = explode(",", Db::table("fa_config")->where("name", "yy_cost")->value("value"));
        if ($warranty == 0) {
            // 无售后
            if ($requestDeviceType == "iphone") {
                $cost = floatval($costValues[1]) * 100;
                if ($useYY) {
                    $cost = floatval($yyCostValues[2]) * 100;
                }
            }
        } elseif ($warranty == 1) {
            // 标准
            if ($requestDeviceType == "iphone") {
                $cost = floatval($costValues[2]) * 100;
                if ($useYY) {
                    $cost = floatval($yyCostValues[2]) * 100;
                }
            }
        } elseif ($warranty == 2) {
            // 加强
            if ($requestDeviceType == "iphone") {
                $cost = floatval($costValues[3]) * 100;
            }
        } elseif ($warranty == 3) {
            // 稳定
            if ($requestDeviceType == "iphone") {
                $cost = floatval($costValues[4]) * 100;
            }
        } elseif ($warranty == 4) {
            // 摆烂
            if ($requestDeviceType == "iphone") {
                $cost = floatval($costValues[0]) * 100;
            }
        } elseif ($warranty == 5) {
            $this->error("预约不支持超标准");
        } else {
            // 标准
            if ($requestDeviceType == "iphone") {
                $cost = floatval($costValues[2]) * 100;
            }
        }
        return $cost;
    }

    private  function getPrice($userId, $warranty, $requestDeviceType = "iphone")
    {
        // 查询 其他价格
        $pljgValues = explode(",", Db::table("fa_config")->where("name", "pljg")->value("value"));
        $configValues = explode(",", Db::table("fa_config")->where("name", "qtjg")->value("value"));
        $ipadValues = explode(",", Db::table("fa_config")->where("name", "ipad_price")->value("value"));
        $customPrice = explode(",", Db::table("fa_user")->where("id", $userId)->value("price"));
        if ($warranty == 0) {
            // 躺平
            if ($requestDeviceType == "ipad") {
                $devicePrice = $ipadValues[0];
            } else {
                $devicePrice = $configValues[0];
                if ($customPrice[0] != 0 && $devicePrice > $customPrice[0]) {
                    $devicePrice = $customPrice[0];
                }
            }
            $openName = "open1";
            $warrantyName = "无售后";
        } elseif ($warranty == 2) {
            // 加强
            if ($requestDeviceType == "ipad") {
                $devicePrice = $ipadValues[1];
            } else {
                $devicePrice = $configValues[1];
                if ($customPrice[1] != 0 && $devicePrice > $customPrice[1]) {
                    $devicePrice = $customPrice[1];
                }
            }
            $openName = "open3";
            $warrantyName = "加强";
        } elseif ($warranty == 3) {
            // 稳定
            if ($requestDeviceType == "ipad") {
                $devicePrice = $ipadValues[2];
            } else {
                $devicePrice = $configValues[2];
                if ($customPrice[2] != 0 && $devicePrice > $customPrice[2]) {
                    $devicePrice = $customPrice[2];
                }
            }
            $openName = "open4";
            $warrantyName = "稳定";
        } elseif ($warranty == 4) {
            // 摆烂
            if ($requestDeviceType == "ipad") {
                $devicePrice = $ipadValues[3];
            } else {
                $devicePrice = $configValues[3];
                if ($customPrice[3] != 0 && $devicePrice > $customPrice[3]) {
                    $devicePrice = $customPrice[3];
                }
            }
            $openName = "open0";
            $warrantyName = "摆烂";
        } else {
            // 标准
            $warranty = 1;
            if ($requestDeviceType == "ipad") {
                $devicePrice = $ipadValues[4];
            } else {
                $devicePrice = $pljgValues[0];
                if ($customPrice[4] != 0 && $devicePrice > $customPrice[4]) {
                    $devicePrice = $customPrice[4];
                }
            }
            $openName = "open2";
            $warrantyName = "标准";
        }
        return compact("devicePrice", "openName", "warrantyName");
    }

    private function getAppleAccountParams($appleId, $chi)
    {
        if ($chi == 1) {
            $appleAccount = Db::table("fa_agentapplelist")->where("id", $appleId)->find();
        } else {
            $appleAccount = Db::table("fa_appleidlist")->where("id", $appleId)->find();
        }
        $iss = $appleAccount["iss"];
        $kid = $appleAccount["kid"];
        $cid = $appleAccount["cid"];
        $bid = $appleAccount["bid"];
        $pname = $appleAccount["devname"];
        $secret = $_SERVER["DOCUMENT_ROOT"] . $appleAccount["p8"];
        if (!file_exists($secret)) {
            $secret = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $appleAccount["p8"];
        }
        return compact('iss', 'kid', 'cid', 'bid', 'pname', 'secret');
    }

    public function index(Request $request)
    {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");
        $token = $request->param("token");
        $beizhu = $request->param("beizhu");
        $type = $request->param("type");
        $warranty = $request->param("warranty");
        $requestDeviceType = $request->param("deviceType");
        if ($warranty == "" || $warranty == null) {
            $warranty = 1;
        }

        if ($requestDeviceType == "" || $requestDeviceType == null) {
            $requestDeviceType = "iphone";
        }

        // if ($token == "eRb6hgfMCGddg4gMSSL5lk3KPlD3UBYk") {
        //     $requestDeviceType = "ipad";
        // }

        if ($type == "" || $type == null) {
            $type = 0;
        } else {
            $type = intval($type);
        }

        if ($token == "") {
            $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
        }
        if ($token == "请更换token") {
            $this->error("请先更换token", array("error_time" => time(), "error" => "token不正确"));
        }

        if ($warranty == 4 && $type == 1) {
            $this->error("私有池不支持摆烂", array("error_time" => time(), "error" => "私有池不支持摆烂"));
        }

        $userData = Db::table("fa_user")->where("ktoken", $token)->order("id desc")->select();
        if (!$userData) {
            $this->error("token不正确", array("error_time" => time(), "error" => "token不正确"));
        }
        $deviceBalance = $userData[0]["score"];
        $balance = $userData[0]["money"];
        $userId = $userData[0]["id"];
        $username = $userData[0]["username"];
        $userMac = $userData[0]["mac"];
        $udid = $request->param("udid");
        if (!(strlen($udid) == 25 || strlen($udid) == 40)) {
            $this->error("UDID错误", array("error_time" => time(), "error" => "UDID错误"));
        }
//        $redis = new Redis();
        $certId = $this->getkid();
        while (Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("kid", $certId)->order("id desc")->select()) {
            $certId = $this->getkid();
        }
        $hasWarranty = false;
        $shouhouType = 0;
        // type 1: 独立池 2: 公共池/独立池自动选择 0: 公共池
        if (($type == 1 || $type == 2) && $warranty != 4) {
            $deviceData = Db::table("fa_deviceslist")->where("type", 0)->where("chi", 1)->where("udid", $udid)->where("user", $username)->order("id desc")->select();
            if ($deviceData) {
                foreach ($deviceData as $device) {
                    $mobileprovision = $device["base64mp"];
                    $p12 = $device["base64p12"];
                    if ($device["zt"] == "hidden") {
                        continue;
                    }
                    $checkp12 = new Checkp12();
                    $p12Info = $checkp12->loadp12($p12);
                    $p12Info = json_decode($p12Info->getContent(), true);
                    if ($p12Info["state"] == true) {
                        $this->success("独立池已存在该设备", array("id" => $device["kid"], "pname" => $device["pname"], "pool" => 1, "addtime" => $device["tjtime"], "mobileprovision" => $mobileprovision, "p12" => $p12, "state" => $p12Info["state"]));
                    }
                }
            }
            $agentAppleIds = Db::table("fa_agentapplelist")->where("uid", $userId)->where("zt", 1)->where("yy", 0)->order("id asc")->select();
            if ($agentAppleIds) {
                if ($userMac == 0) {
                    foreach ($agentAppleIds as $appleAccount) {
                        if ($appleAccount["iphone"] >= 0) {
                            $deviceType = "IOS";
                            $addResult = $this->addiphone($appleAccount["id"], $udid, $deviceType, $username, $certId, $beizhu, 1, false, 0, $warranty);
                            $addResultArray = json_decode($addResult->getContent(), true);
                            if ($addResultArray["code"] == 1) {
                                return $addResult;
                            } elseif ($addResultArray["code"] == 101) {
                                return $addResult;
                            }
                        }
                    }
                } else {
                    foreach ($agentAppleIds as $appleAccount) {
                        if ($appleAccount["iphone"] >= 0 || $appleAccount["mac"] > 0) {
                            if ($appleAccount["iphone"] > 0) {
                                if (strlen($udid) == 25) {
                                    $deviceType = "MAC_OS";
                                    if ($appleAccount["mac"] <= 0) {
                                        $deviceType = "IOS";
                                    }
                                } else {
                                    $deviceType = "IOS";
                                }
                                $addResult = $this->addiphone($appleAccount["id"], $udid, $deviceType, $username, $certId, $beizhu, 1, false, 0, $warranty);
                                $addResultArray = json_decode($addResult->getContent(), true);
                                if ($addResultArray["code"] == 1) {
                                    return $addResult;
                                } elseif ($addResultArray["code"] == 101) {
                                    return $addResult;
                                }
                            } else {
                                if (strlen($udid) != 25) {
                                    continue;
                                }
                                $deviceType = "MAC_OS";
                                $addResult = $this->addiphone($appleAccount["id"], $udid, $deviceType, $username, $certId, $beizhu, 1, false, 0, $warranty);
                                $addResultArray = json_decode($addResult->getContent(), true);
                                if ($addResultArray["code"] == 1) {
                                    return $addResult;
                                } elseif ($addResultArray["code"] == 101) {
                                    return $addResult;
                                }
                            }
                        }
                    }
                }
            }
            if ($type == 1) {
                $this->error("添加失败，独立池无可用证书", array("error" => "添加失败"));
            }
        }
        if ($type == 0 || $type == 2) {
            // 直接看看有没有书已经数据库有了
            $deviceData = Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("type", 0)->where("chi", 0)->where("udid", $udid)->where("user", $username)->order("id asc")->select();
            $revokedCount = Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("type", 0)->where("chi", 0)->where("udid", $udid)->where("zt", "hidden")->count();
            if ($deviceData) {
                $latestDevice = array();
                foreach ($deviceData as $device) {
                    if ($device["zt"] == "hidden" || $device["zt"] == "expiration") {
                        $p12Info["state"] = false;
                    } else {
                        $p12Info["state"] = true;
                        $device = Db::table("fa_deviceslist")->where("type", 0)->where("chi", 0)->where("kid", $device["kid"])->where("user", $username)->order("id asc")->select();
                        $device = $device[0];
                        $mobileprovision = $device["base64mp"];
                        $p12 = $device["base64p12"];
//                        $addResultArray = $redis->get("checkcer");
//                        if ($addResultArray == false) {
//                            $addResultArray = array();
//                        }
//                        array_push($addResultArray, $device["pname"]);
//                        $redis->set("checkcer", $addResultArray);
                        $this->success("设备已存在", array("id" => $device["kid"], "pname" => $device["pname"], "pool" => 0, "addtime" => $device["tjtime"], "mobileprovision" => $mobileprovision, "p12" => $p12, "state" => $p12Info["state"]));
                    }
                    $latestDevice = $device;
                }

                if ($latestDevice["shtype"] == 1) {
                    $latestDevice1 = Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("type", 0)->where("chi", 0)->where("udid", $udid)->where("shouhou", 0)->where("user", $username)->where("shtype", 1)->order("id desc")->select();
                    $expirationTime = $latestDevice1[0]["tjtime"] + 3456000;
                } elseif ($latestDevice["shtype"] == 2) {
                    $expirationTime1 = Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("type", 0)->where("chi", 0)->where("udid", $udid)->where("shouhou", 0)->where("user", $username)->where("shtype", 2)->order("id desc")->select();
                    $expirationTime = $expirationTime1[0]["tjtime"] + 15552000;
                } elseif ($latestDevice["shtype"] == 3) {
                    $expirationTime2 = Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("type", 0)->where("chi", 0)->where("udid", $udid)->where("shouhou", 0)->where("user", $username)->where("shtype", 3)->order("id desc")->select();
                    $expirationTime = $expirationTime2[0]["tjtime"] + 28512000;
                } else {
                    $expirationTime = $latestDevice["tjtime"];
                }

                if ($p12Info["state"] == false && time() < $expirationTime) {
                    $hasWarranty = true;
                    $shouhouType = 1;
                    $warranty = $latestDevice["shtype"];
                }
            }
            // "devicePrice", "openName", "warrantyName"
            extract($this->getPrice($userId, $warranty, $requestDeviceType));

            // 质保40天
            if ($warranty == 1) {
                // 质保40天, 判断设备数
                if ($deviceBalance < 1 && $hasWarranty == false) {
                    if ($balance < $devicePrice && $hasWarranty == false) {
                        $this->error("账号设备数或余额不足", array("error_time" => time(), "error" => "账号设备数或余额不足"));
                    }
                }
            } else {
                // 非质保40天, 判断余额
                if ($balance < $devicePrice && $hasWarranty == false) {
                    $this->error("账号余额不足", array("error_time" => time(), "error" => "账号余额不足"));
                }
            }

            // 自己没有摆烂, 就不要试了
            $yscToken = Db::table("fa_config")->where("name", "ysctoken")->value("value");
            if ($warranty == 4) {
                if ($yscToken != "") {
                    $addResult = $this->toPlatform($udid, $username, $certId, $beizhu, $hasWarranty, $shouhouType, $warranty, $devicePrice);
                    return $addResult;
                } else {
                    $this->error("添加失败，请检查库存", array("error_time" => time(), "error" => "添加失败，请检查库存"));
                }
            }

            // 是不是要IPAD
            if ($requestDeviceType == "ipad") {
                $ipadAppleAccounts = Db::table("fa_appleidlist")
                    ->where("zt", 1)    // 状态 1
                    ->where("yy", 0)    // 预约 0
                    ->where("open_ipad", 1)    // ipad 1
                    ->order("weigh asc")->select();

                if ($ipadAppleAccounts) {
                    // 随机选号
                    $shuffleFlag = Db::table("fa_config")->where("name", "suijihao")->value("value");
                    if ($shuffleFlag == 1) {
                        shuffle($ipadAppleAccounts);
                    }
                    foreach ($ipadAppleAccounts as $ipadAppleAccount) {
                        $addResult = $this->addiphone($ipadAppleAccount["id"], $udid, "IOS", $username, $certId, $beizhu, 0, $hasWarranty, $shouhouType, $warranty, "ipad");
                        $addResultArray = json_decode($addResult->getContent(), true);
                        if ($addResultArray["code"] == 1 || $addResultArray["code"] == 1002) {
                            return $addResult;
                        } elseif ($addResultArray["msg"] == "设备上限") {
                            $this->error("不要拿IPHONE来试IPAD好吗?", array("error_time" => time(), "error" => "不要拿IPHONE来试IPAD好吗?"));
                        }
                    }
                }
                $this->error("添加失败，IPAD库存不足, 请联系客服", array("error_time" => time(), "error" => "添加失败，IPAD库存不足, 请联系客服"));
            } else {
                // 请求的是iphone
                // 试试出ipad看看能不能白嫖
                if ($warranty != 4) {
                    $ipadAppleId = Db::table("fa_appleidlist")
                        ->where("zt", 1)    // 状态 1
                        ->where("yy", 0)    // 预约 0
                        ->where("open_ipad", 1)    // 质保类型 1
                        ->where("ipad", ">", 0)    // 质保类型 1
                        ->find();

                    if ($ipadAppleId) {
                        $devname = $ipadAppleId["devname"];
                        $addResult = $this->addiphone($ipadAppleId["id"], $udid, "IOS", $username, $certId, $beizhu, 0, $hasWarranty, $shouhouType, $warranty, "iphone", false);
                        $addResultArray = json_decode($addResult->getContent(), true);
                        if ($addResultArray["code"] == 1) {
                            // 发个消息庆祝一下
                            // 获取配置值
                            $wechatApiUrl = Db::table("fa_config")->where("name", "wechatapi")->value("value");
                            // 构建消息内容
                            $messageContent = "<font color=\"warning\">请求iphone,实际出的ipad!\n`" . date("Y-m-d H:i:s", time()) . "`</font>\n"
                                . ">#### 用户:<font color=\"comment\">" . $username . "</font>\n"
                                . ">#### 名称:<font color=\"info\">" . $devname . "</font>\n"
                                . ">#### <font color=\"warning\">" . $udid . "</font>";

                            // 构建请求参数
                            $requestData["msgtype"] = "markdown";
                            $requestData["markdown"] = ["content" => $messageContent];
                            // 发送请求
                            $this->request_post($wechatApiUrl, json_encode($requestData));
                            return $addResult;
                        } elseif ($addResultArray["code"] == 1002) {
                            return $addResult;
                        }
                    }
                }
            }


//            // 先试试IPAD
//            if ($warranty != 4) {
//                $ipadAppleId = Db::table("fa_appleidlist")
//                    ->where("zt", 1)    // 状态 1
//                    ->where("yy", 0)    // 预约 0
//                    ->where("open_ipad", 1)    // 质保类型 1
//                    ->find();
//
//                if ($ipadAppleId) {
//                    $iss = $ipadAppleId["iss"];
//                    $kid = $ipadAppleId["kid"];
//                    $cid = $ipadAppleId["cid"];
//                    $bid = $ipadAppleId["bid"];
//                    $devname = $ipadAppleId["devname"];
//                    $p8Path = $_SERVER["DOCUMENT_ROOT"] . $ipadAppleId["p8"];
//                    $addResult = $this->addiphone($appleAccount["id"], $udid, $deviceType, $username, $certId, $beizhu, 0, $hasWarranty, $shouhouType, $warranty);
//                    $addResult = $this->addiphone($iss, $kid, $p8Path, $bid, $cid, $udid, "IOS", $devname, $username, $userMoney, $token);
//                    $addResultArray = json_decode($addResult->getContent(), true);
//                    if ($addResultArray["code"] == 1) {
//                        // 发个消息庆祝一下
//                        // 获取配置值
//                        $wechatApiUrl = Db::table("fa_config")->where("name", "wechatapi")->value("value");
//                        // 构建消息内容
//                        $messageContent = "<font color=\"warning\">又出个IPAD, 赚大发啦!\n`" . date("Y-m-d H:i:s", time()) . "`</font>\n"
//                            . ">#### 用户:<font color=\"comment\">" . $username . "</font>\n"
//                            . ">#### 名称:<font color=\"info\">" . $devname . "</font>\n"
//                            . ">#### <font color=\"warning\">" . $udid . "</font>";
//
//                        // 构建请求参数
//                        $requestData["msgtype"] = "markdown";
//                        $requestData["markdown"] = ["content" => $messageContent];
//
//                        // 发送请求
//                        $this->request_post($wechatApiUrl, json_encode($requestData));
//                        return $addResult;
//                    } elseif ($addResultArray["code"] == 1002) {
//                        return $addResult;
//                    }
//                }
//            }

            $query = Db::table("fa_appleidlist")
                ->where("zt", 1)    // 状态 1
                ->where("yy", 0)    // 预约 0
                ->where("open_ipad", 0)    // ipad 0
                ->where($openName, 1);    // 质保类型

            if ($revokedCount >= 3) {
                $query = $query->where("sh", 1);    // 售后 1
            }

            $appleAccounts = $query->order("weigh asc")->select();
            if ($warranty == 0 && $username != 'camenling') {
                $appleAccounts2 = Db::table("fa_appleidlist")
                    ->where("zt", 1)    // 状态 1
                    ->where("yy", 1)    // 预约 1
                    ->where("open0", 1)    // 摆烂1
                    ->select();
                $appleAccounts = array_merge($appleAccounts, $appleAccounts2);
            }

            if ($appleAccounts) {
                // 随机选号
                $shuffleFlag = Db::table("fa_config")->where("name", "suijihao")->value("value");
                if ($shuffleFlag == 1) {
                    shuffle($appleAccounts);
                }
                foreach ($appleAccounts as $appleAccount) {
                    if ($appleAccount["iphone"] >= 0 || $appleAccount["mac"] >= 0) {
                        $isBuildAccount = $appleAccount["open0"];  // 构建号或者预约号

                        if ($isBuildAccount && $appleAccount["iphone"] <= 90 && $appleAccount["mac"] <= 90) {
                            // 自动修改构建号/预约号状态, 关闭open0
                            Db::table("fa_appleidlist")->where("devname", $pname)->update(["open0" => 0]);
                            continue;
                        }

                        if ($appleAccount["iphone"] > 0) {
                            if (strlen($udid) == 25) {
                                $deviceType = "MAC_OS";
                                if ($appleAccount["mac"] <= 0 || ($isBuildAccount && $appleAccount["mac"] <= 90)) {
                                    $deviceType = "IOS";
                                }
                            } else {
                                $deviceType = "IOS";
                            }
                            $addResult = $this->addiphone($appleAccount["id"], $udid, $deviceType, $username, $certId, $beizhu, 0, $hasWarranty, $shouhouType, $warranty);
                            $addResultArray = json_decode($addResult->getContent(), true);
                            if ($addResultArray["code"] == 1) {
                                return $addResult;
                            } elseif ($addResultArray["code"] == 1002) {
                                return $addResult;
                            } else {
                                continue;
                            }
                        } else {
                            if (strlen($udid) != 25) {
                                continue;
                            }
                            $deviceType = "MAC_OS";
                            $addResult = $this->addiphone($appleAccount["id"], $udid, $deviceType, $username, $certId, $beizhu, 0, $hasWarranty, $shouhouType, $warranty);
                            $addResultArray = json_decode($addResult->getContent(), true);
                            if ($addResultArray["code"] == 1) {
                                return $addResult;
                            } elseif ($addResultArray["code"] == 1002) {
                                return $addResult;
                            } else {
                                continue;
                            }
                        }
                        break;
                    }
                }
            }

            // 都没有, 走优速测
            if ($yscToken != "") {
                $addResult = $this->toPlatform($udid, $username, $certId, $beizhu, $hasWarranty, $shouhouType, $warranty, $devicePrice);
                return $addResult;
            } else {
                $this->error("添加失败，请检查库存", array("error_time" => time(), "error" => "添加失败，请检查库存"));
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

    public function sendemailtest() {
        $this->sendemail("00008101-0012259401E9001E", "123456", "测试证书", 100, 20, 1, "admin");
    }

    public function sendemail($udid, $zsid, $pname, $balance, $price, $warranty, $username)
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
            } elseif ($warranty == 3) {
                $version = "摆烂版";
            } else {
                $version = "未知";
            }
            $messageContent = "<div><font color=\"warning\">新出秒出证书<br>`" . date("Y-m-d H:i:s", time()) . "`</font><br>"
                . ">#### 编号: <font color=\"comment\">" . $zsid . "</font><br>"
                . ">#### 余额变动: <font color=\"comment\">" . $balance . "-" . $price . "=" . ($balance - $price) . "</font><br>"
                . ">#### 证书名称: <font color=\"info\">" . $pname . "</font><br>"
                . ">#### 质保: <font color=\"info\">" . $version . "</font><br>"
                . ">#### UDID: <font color=\"warning\">" . $udid . "</font><br>"
                . ">操作：<a href='https://" . $_SERVER["HTTP_HOST"] . "/api/getdown?all=" . $zsid . "'>点击下载该证书</a></div>";

            $result = $email
                ->to($receiver)
                ->subject("余额变动提醒: " . $balance . "-" . $price . "=" . ($balance - $price))
                ->message($messageContent)
                ->send();
        }
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

    function sendmsg($udid, $id, $username, $pt, $pname, $balance, $price, $warranty, $useDeviceBalance = false, $requestDeviceType = "iphone")
    {
        // 查询用户表
        $userResult = Db::table("fa_user")->where("username", $username)->order("id desc")->select();
        // 获取用户ID
        $platformType = "余额";
        // 获取配置值并拆分为数组

        $actionType = "添加";
        if ($warranty == 0) {
            $version = "无售后";
            if ($price == 0) {
                $actionType = "补签";
            }
        } elseif ($warranty == 1) {
            $version = "标准版";
        } elseif ($warranty == 2) {
            $version = "加强版";
        } elseif ($warranty == 3) {
            $version = "稳定版";
        } elseif ($warranty == 4) {
            $version = "摆烂版";
        } else {
            $version = "未知";
        }

        if ($useDeviceBalance) {
            $platformType = "设备";
            $price = 1;
            $balance = $userResult[0]["score"] + 1;
        }

        // 获取配置值
        $wechatApiUrl = Db::table("fa_config")->where("name", "wechatapi")->value("value");

        // 构建消息内容
        $messageContent = "<font color=\"warning\">新出秒出证书\n`" . date("Y-m-d H:i:s", time()) . "`</font>\n"
            . ">#### 编号:<font color=\"comment\">" . $id . "</font>\n"
            . ">#### 用户:<font color=\"comment\">" . $username . "</font>\n"
            . ">#### " . $platformType . ":<font color=\"comment\">" . $balance . "-" . $price . "=" . ($balance - $price) . "</font>\n"
            . ">#### 版本:<font color=\"comment\">" . $version . "</font>\n"
            . ">#### 平台:<font color=\"info\">" . $pt . "</font>\n"
            . ">#### 名称:<font color=\"info\">" . $pname . "</font>\n"
            . ">#### 设备:<font color=\"info\">" . $requestDeviceType . "</font>\n"
            . ">#### <font color=\"warning\">" . $udid . "</font>\n"
            . ">操作：[点击下载该证书](https://" . $_SERVER["HTTP_HOST"] . "/api/getdown?all=" . $id . ")";

        // 构建请求参数
        $requestData["msgtype"] = "markdown";
        $requestData["markdown"] = ["content" => $messageContent];

        // 发送请求
        $this->request_post($wechatApiUrl, json_encode($requestData));
    }

    function senderror($error, $udid, $user, $pt, $pname="未知")
    {
        // 获取配置值
        $wechatApiUrl = Db::table("fa_config")->where("name", "wechatapi")->value("value");

        // 构建消息内容
        // 构建消息内容
        $messageContent = "<font color=\"warning\">添加设备出错\n`" . date("Y-m-d H:i:s", time()) . "`</font>\n"
            . ">#### 错误:<font color=\"comment\">" . $error . "</font>\n"
            . ">#### 证书名称:<font color=\"info\">" . $pname . "</font>\n"
            . ">#### 用户:<font color=\"info\">" . $user . "</font>\n"
            . ">#### 平台:<font color=\"info\">" . $pt . "</font>\n"
            . ">#### UDID:<font color=\"info\">" . $udid . "</font>";

        // 构建请求参数
        $requestData["msgtype"] = "markdown";
        $requestData["markdown"] = ["content" => $messageContent];

        // 发送请求
        $this->request_post($wechatApiUrl, json_encode($requestData));
    }

    function getCerName($base64P12, $pw = '1')
    {
        // 生成随机目录
        $randNum = rand(1, 100);
        if (!file_exists($randNum)) {
            mkdir($randNum);
        }
        // 写入p12文件
        file_put_contents($randNum . "/sign.p12", base64_decode($base64P12));
        // p12文件路径
        $p12FilePath = $_SERVER["DOCUMENT_ROOT"] . "/" . $randNum;
        // 初始化证书数组
        $certificates = array();
        // 读取p12文件
        $p12Content = file_get_contents($p12FilePath . "/sign.p12");
        // 解析p12文件
        openssl_pkcs12_read($p12Content, $certificates, $pw);
        // pem文件路径
        $pemFilePath = $p12FilePath . "/sign.pem";
        // 打开pem文件
        ($fileHandle = fopen($pemFilePath, "w")) || die("Unable to open file!");
        // 写入cert内容到pem文件
        fwrite($fileHandle, $certificates["cert"]);
        // 关闭文件句柄
        fclose($fileHandle);
        // 获取证书名
        $certName = shell_exec("openssl x509 -in " . $pemFilePath . " -noout -subject -nameopt RFC2253 2>&1");
        $certName = $this->getSubstr($certName, "O=", ",");
        // 获取到期时间
        $validity = shell_exec("openssl x509 -in " . $pemFilePath . " -noout -dates 2>&1");
        $validity = $this->getSubstr($validity, "notAfter=", "T") . "T";
        $validityTime = strtotime($validity);
        $formattedValidityTime = date("Y-m-d H:i:s", $validityTime);
        // 初始化base64p12
        $base64P12 = "";
        // 如果密码不为1，则重新生成p12文件
        if ($pw != "1") {
            shell_exec("openssl pkcs12 -in " . $p12FilePath . "/sign.p12 -password pass:\"" . $pw . "\" -passout pass:\"123456\" -out " . $p12FilePath . "/temp.pem");
            shell_exec("openssl pkcs12 -passin pass:\"123456\" -passout pass:\"1\" -export -in " . $p12FilePath . "/temp.pem -out " . $p12FilePath . "/developer.p12");
            // 读取重新生成的p12文件并base64编码
            $base64P12 = base64_encode(file_get_contents($randNum . "/developer.p12"));
        }
        // 删除临时目录
        shell_exec("rm -rf " . $p12FilePath);
        // 返回结果
        return json(array("code" => 1, "msg" => "成功", "data" => array("devname" => $certName, "dqtime" => $formattedValidityTime, "newp12" => $base64P12)));
    }

    function getSubstr($str, $leftStr, $rightStr)
    {
        // 获取左边界
        $leftBoundary = strpos($str, $leftStr);
        // 获取右边界
        $rightBoundary = strpos($str, $rightStr, $leftBoundary);
        // 如果左边界小于0或右边界小于左边界，返回空字符串
        if ($leftBoundary < 0 || $rightBoundary < $leftBoundary) {
            return "";
        }
        // 返回截取的子字符串
        return substr($str, $leftBoundary + strlen($leftStr), $rightBoundary - $leftBoundary - strlen($leftStr));
    }

    /**
     * @throws DataNotFoundException
     * @throws PDOException
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     * @throws ConfigException
     * @throws DbException
     * @throws Exception
     */
    function addiphone($appleId, $udid, $deviceType, $username, $zsid, $beizhu, $chi, $ghqx, $shouhou, $warranty = 1, $requestDeviceType = 'iphone', $sendLimitError = true): \think\response\Json
    {
        // $iss, $kid, $secret, $bid, $cid
        // 'iss', 'kid', 'cid', 'bid', 'pname', 'secret'
        extract($this->getAppleAccountParams($appleId, $chi));
        // 如果是独立池
        $redis = new Redis();
        // if ($redis->has($udid)) {
        //     return json(array("code" => 1001, "msg" => "请勿在一分钟内添加同一个设备", "data" => array()));
        // }
        $redis->set($udid, true, 60);

        $userObj = Db::table("fa_user")->where("username", $username)->find();
        $balance = $userObj["money"];
        $userId = $userObj["id"];
        $deviceBalance = $userObj["score"];
        // "devicePrice", "openName", "warrantyName"
        extract($this->getPrice($userId, $warranty, $requestDeviceType));

        if ($chi == 1) {
            // 获取最新的苹果设备信息
            $latestAppleDeviceInfo = Db::table("fa_agentapplelist")->where("devname", $pname)->order("id desc")->select();
            if (!$latestAppleDeviceInfo) {
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "服务端错误1_2", "data" => array()));
            }
            $p12 = $latestAppleDeviceInfo[0]["p12"];
            $dqtime = $latestAppleDeviceInfo[0]["dqtime"];

            // 获取访问token
            $clientInfo = array("iss" => $iss, "kid" => $kid, "secret" => $secret);
            $client = new Client($clientInfo);
            $token = $client->getToken();
            $headers = array("Authorization" => "Bearer " . $token);
            $client->setHeaders($headers);

            // 注册设备
            $deviceRegistration = $client->api("device")->register($udid, $deviceType, $udid);
            $errorCount = isset($deviceRegistration["errors"]) ? $deviceRegistration["errors"] : 0;

            // 如果注册设备失败
            if ($errorCount != 0) {
                $errorDetail = $deviceRegistration["errors"][0]["detail"];
                $logMessage = date("Y-m-d H:i:s", time()) . " 独立池：添加设备 " . $udid . " " . $pname . " " . $errorDetail . "\n";
                $logFile = "error_log.txt";
                $logHandler = fopen($logFile, "a+");
                fwrite($logHandler, iconv("UTF-8", "GBK", $logMessage));
                fclose($logHandler);

                // 判断失败原因
                if (strstr($errorDetail, "already exists on this team")) {
                    $existingDevice = Db::table("fa_deviceslist")->where("udid", $udid)->where("pname", $pname)->order("id desc")->select();
                    if ($existingDevice) {
                        $base64mp = $existingDevice[0]["base64mp"];
                        $zsid = $existingDevice[0]["kid"];
                        $p12 = $existingDevice[0]["base64p12"];
                        $redis->rm($udid);
                        return json(array("code" => 1, "msg" => "设备已存在", "data" => array("id" => $zsid, "mobileprovision" => $base64mp, "p12" => $p12)));
                    } else {
                        $deviceInfo = array("filter[udid]" => $udid, "limit" => 1);
                        $deviceData = $client->api("device")->all($deviceInfo);
                        $deviceID = $deviceData["data"][0]["id"];
                        $model = $deviceData["data"][0]["attributes"]["model"];
                    }
                } elseif (strstr($errorDetail, "the maximum number of registered")) {
                    $redis->rm($udid);
                    return json(array("code" => 1001, "msg" => "设备上限", "data" => array()));
                } else {
                    $redis->rm($udid);
                    return json(array("code" => 1001, "msg" => "设备添加失败：" . $errorDetail, "data" => array()));
                }
            } else {
                $deviceID = $deviceRegistration["data"]["id"];
                $model = $deviceRegistration["data"]["attributes"]["model"];
                $deviceClass = $deviceRegistration["data"]["attributes"]["deviceClass"];

                // 根据设备类型减少相应设备数量
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
            $certificates = array($cid);
            $profileCreation = $client->api("profiles")->create($udid . rand(1, 99), $bid, $profileType, $deviceIDs, $certificates);
            $errorCount = isset($profileCreation["errors"]) ? $profileCreation["errors"] : 0;

            if ($errorCount != 0) {
                $errorDetail = json_encode($profileCreation["errors"][0]["detail"]);
                $logMessage = date("Y-m-d H:i:s", time()) . " 独立池：添加描述 " . $udid . " " . $pname . " " . $errorDetail . "\n";
                $logFile = "error_log.txt";
                $logHandler = fopen($logFile, "a+");
                fwrite($logHandler, iconv("UTF-8", "GBK", $logMessage));
                fclose($logHandler);

                if (strstr($errorDetail, "no current ios devices on this team")) {
                    Db::table("fa_agentapplelist")->where("devname", $pname)->update(array("yy" => 1));
                    Db::table("fa_deviceslist")->insert(array("chi" => 1, "kid" => $zsid, "zspt" => 1, "udid" => $udid, "base64mp" => "", "deviceid" => $deviceID, "zt" => "normal", "pname" => $pname, "user" => $username, "base64p12" => $p12, "tjtime" => time(), "beizhu" => $beizhu, "type" => 1, "dqtime" => $dqtime, "shtype" => $warranty));
                    $redis->rm($udid);
                    return json(array("code" => 101, "msg" => "此账号卡设备，已为你转为预约模式", "time" => time(), "data" => array("id" => $zsid, "pname" => $pname, "pool" => 1, "addtime" => time(), "mobileprovision" => "", "p12" => $p12)));
                }
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "Profiles添加失败 " . json_encode($profileCreation["errors"][0]["detail"]), "data" => array()));
            }

            $mobileProvision = $profileCreation["data"]["attributes"]["profileContent"];
            $latestAppleDeviceInfo = Db::table("fa_agentapplelist")->where("devname", $pname)->order("id desc")->select();
            if (!$latestAppleDeviceInfo) {
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "服务端错误1_2", "data" => array()));
            }
            $p12 = $latestAppleDeviceInfo[0]["p12"];
            $dqtime = $latestAppleDeviceInfo[0]["dqtime"];

            Db::table("fa_deviceslist")->insert(array("chi" => 1, "kid" => $zsid, "zspt" => 1, "udid" => $udid, "base64mp" => $mobileProvision, "deviceid" => $deviceID, "zt" => "normal", "pname" => $pname, "user" => $username, "model" => $model, "base64p12" => $p12, "tjtime" => time(), "beizhu" => $beizhu, "type" => 0, "dqtime" => $dqtime, "shtype" => $warranty));
            $redis->rm($udid);
            $this->sendemail($udid, $zsid, $pname, $balance, $devicePrice, $warranty, $username);
            return json(array("code" => 1, "msg" => "添加成功", "data" => array("id" => $zsid, "pname" => $pname, "pool" => 1, "addtime" => time(), "mobileprovision" => $mobileProvision, "p12" => $p12, "warranty_time" => time())));
        } else {
            // 公共池
            $clientInfo = array("iss" => $iss, "kid" => $kid, "secret" => $secret);
            $client = new Client($clientInfo);
            $token = $client->getToken();
            $headers = array("Authorization" => "Bearer " . $token);
            $client->setHeaders($headers);

            $deviceRegistration = $client->api("device")->register(uniqid(), $deviceType, $udid);
            $errorCount = isset($deviceRegistration["errors"]) ? $deviceRegistration["errors"] : 0;

            if ($errorCount != 0) {
                $errorDetail = $deviceRegistration["errors"][0]["detail"];
                $logMessage = date("Y-m-d H:i:s", time()) . " 公共池：添加设备 " . $udid . " " . $pname . " " . $errorDetail . "\n";
                $logFile = "error_log.txt";
                $logHandler = fopen($logFile, "a+");
                fwrite($logHandler, iconv("UTF-8", "GBK", $logMessage));
                fclose($logHandler);

                if (strstr($errorDetail, "already exists on this team")) {
                    $existingDevice = Db::table("fa_deviceslist")->where("udid", $udid)->where("pname", $pname)->order("id desc")->select();
                    if ($existingDevice) {
                        $base64mp = $existingDevice[0]["base64mp"];
                        $zsid = $existingDevice[0]["kid"];
                        $p12 = $existingDevice[0]["base64p12"];
                        $redis->rm($udid);
                        return json(array("code" => 1, "msg" => "设备已存在", "data" => array("id" => $zsid, "pname" => $pname, "pool" => 0, "addtime" => $existingDevice[0]["tjtime"], "mobileprovision" => $base64mp, "p12" => $p12)));
                    } else {
                        $deviceInfo = array("filter[udid]" => $udid, "limit" => 1);
                        $deviceData = $client->api("device")->all($deviceInfo);
                        $deviceID = $deviceData["data"][0]["id"];
                        $model = $deviceData["data"][0]["attributes"]["model"];
                    }
                } elseif (strstr($errorDetail, "the maximum number of registered")) {
                    $appleAccount = Db::table("fa_appleidlist")->where("devname", $pname)->find();
                    if ($requestDeviceType == "iphone" && $appleAccount["open_ipad"] != 1) {
                        Db::table("fa_appleidlist")->where("devname", $pname)->update(array("zt" => 0));
                    }
                    $redis->rm($udid);
                    if ($sendLimitError) {
                        $this->senderror("设备上限", $udid, $username, $deviceType, $pname);
                    }
                    return json(array("code" => 1001, "msg" => "设备上限", "data" => array()));
                } elseif (strstr($errorDetail, "An invalid value")) {
                    $redis->rm($udid);
                    $this->senderror("UDID错误", $udid, $username, $deviceType, $pname);
                    return json(array("code" => 1002, "msg" => "UDID错误", "data" => array()));
                } else {
                    $redis->rm($udid);
                    $this->senderror("设备添加失败：" . $errorDetail, $udid, $username, $deviceType, $pname);
                    return json(array("code" => 1001, "msg" => "设备添加失败：" . $errorDetail, "data" => array()));
                }
            } else {
                $deviceID = $deviceRegistration["data"]["id"];
                $model = $deviceRegistration["data"]["attributes"]["model"];
                $deviceClass = $deviceRegistration["data"]["attributes"]["deviceClass"];

                // 根据设备类型减少相应设备数量
                if ($deviceClass == "IPHONE") {
                    Db::table("fa_appleidlist")->where("devname", $pname)->setDec("iphone", 1);
                }
                if ($deviceClass == "IPAD") {
                    Db::table("fa_appleidlist")->where("devname", $pname)->setDec("ipad", 1);
                }
                if ($deviceClass == "MAC") {
                    Db::table("fa_appleidlist")->where("devname", $pname)->setDec("mac", 1);
                }

                // 获取更新后的记录
                $record = Db::table("fa_appleidlist")->where("devname", $pname)->find();

                // 检查open0和yy字段的值，以及iphone和mac字段的值
                if ($record["open0"] == 1 && $record["iphone"] <= 90 && $record["mac"] <= 90) {
                    // 如果满足条件，更新open0字段的值
                    Db::table("fa_appleidlist")->where("devname", $pname)->update(["open0" => 0]);
                }

            }

            $profileType = "IOS_APP_ADHOC";
            $deviceIDs = array($deviceID);
            $certificates = array($cid);
            $profileCreation = $client->api("profiles")->create($udid . rand(1, 99), $bid, $profileType, $deviceIDs, $certificates);
            $errorCount = isset($profileCreation["errors"]) ? $profileCreation["errors"] : 0;

            if ($errorCount != 0) {
                $errorDetail = json_encode($profileCreation["errors"][0]["detail"]);
                $logMessage = date("Y-m-d H:i:s", time()) . " 公共池：添加描述 " . $udid . " " . $pname . " " . $errorDetail . "\n";
                $logFile = "error_log.txt";
                $logHandler = fopen($logFile, "a+");
                fwrite($logHandler, iconv("UTF-8", "GBK", $logMessage));
                fclose($logHandler);

                if (strstr($errorDetail, "no current ios devices on this team")) {
                    $appleAccount =  Db::table("fa_appleidlist")->where("devname", $pname)->find();
                    if ($appleAccount["open0"] == 1) {
                        // 摆烂
                        $redis->rm($udid);
                        return json(array("code" => 1001, "msg" => "此UDID无法添加此类型的证书", "data" => array()));
                    }
                }
                $redis->rm($udid);
                $this->senderror("Profiles添加失败 " . $errorDetail, $udid, $username, $deviceType, $pname);
                return json(array("code" => 1001, "msg" => "Profiles添加失败: " . json_encode($profileCreation["errors"][0]["detail"]), "data" => array()));
            }

            $mobileProvision = $profileCreation["data"]["attributes"]["profileContent"];
            $latestAppleDeviceInfo = Db::table("fa_appleidlist")->where("devname", $pname)->order("id desc")->select();
            if (!$latestAppleDeviceInfo) {
                $redis->rm($udid);
                return json(array("code" => 1001, "msg" => "服务端错误1_2", "data" => array()));
            }
            $p12 = $latestAppleDeviceInfo[0]["p12"];
            $dqtime = $latestAppleDeviceInfo[0]["dqtime"];

            if ($latestAppleDeviceInfo[0]["yy"] == 1) {
                $useYY = true;
            } else {
                $useYY = false;
            }

            Db::table("fa_deviceslist")->insert(array(
                "chi" => 0,
                "kid" => $zsid,
                "zspt" => 1,
                "udid" => $udid,
                "base64mp" => $mobileProvision,
                "deviceid" => $deviceID,
                "zt" => "normal",
                "pname" => $pname,
                "user" => $username,
                "model" => $model,
                "base64p12" => $p12,
                "tjtime" => time(),
                "beizhu" => $beizhu,
                "type" => 0,
                "cost" => $this->getCost($warranty, $requestDeviceType, $useYY),
                "price" => floatval($devicePrice) * 100,
                "shouhou" => $shouhou,
                "dqtime" => $dqtime,
                "shtype" => $warranty));
            if ($ghqx == false) {
                if ($warranty == 1) {
                    if ($requestDeviceType != 'ipad' && $deviceBalance >= 1) {
                        \app\common\model\User::score(-1,  $userId, "添加" . $requestDeviceType . "[" . $warrantyName . "]证书, UDID:" . $udid);
                        $this->sendmsg($udid, $zsid, $username, "本站", $pname, $balance, 0, $warranty, true);
                    } else {
                        \app\common\model\User::money(-$devicePrice, $userId, "添加" . $requestDeviceType . "[" . $warrantyName . "]证书, UDID:" . $udid . "变动" . -$devicePrice . "元余额");
                        $this->sendmsg($udid, $zsid, $username, "本站", $pname, $balance, $devicePrice, $warranty, false, $requestDeviceType);
                    }
                } else {
                    \app\common\model\User::money(-$devicePrice, $userId, "添加" . $requestDeviceType . "[" . $warrantyName . "]证书, UDID:" . $udid . "变动" . -$devicePrice . "元余额");
                    $this->sendmsg($udid, $zsid, $username, "本站", $pname, $balance, $devicePrice, $warranty, false, $requestDeviceType);
                }
            } else {
                $this->sendmsg($udid, $zsid, $username, "本站售后", $pname, $balance, 0, 0, false, $requestDeviceType);
            }
            $redis->rm($udid);
            $this->sendemail($udid, $zsid, $pname, $balance, $devicePrice, $warranty, $username);
            return json(array("code" => 1, "msg" => "添加成功", "data" => array("id" => $zsid, "pname" => $pname, "pool" => 0, "addtime" => time(), "mobileprovision" => $mobileProvision, "p12" => $p12)));
        }
    }

    function toPlatform($udid, $username, $zsid, $beizhu, $ghqx, $shouhou, $warranty = 1, $price = 1)
    {
        $redis = new Redis();
        // if ($redis->has($udid)) {
        //     return json(array("code" => 1001, "msg" => "请勿在一分钟内添加同一个设备", "data" => array()));
        // }
        // $redis->set($udid, true, 60);
        // 获取优速测token
        $yscToken = Db::table("fa_config")->where("name", "ysctoken")->value("value");
        // 获取API地址
        $apiUrl = "https://cer.52tzs.com/api/addDevice";

        if ($warranty == 0) {
            $useWarranty = 0;
        } else {
            $useWarranty = 1;
        }

        $platform = "优速测";
        $platformId = 2;

        if ($warranty == 4) {
            $useWarranty = $warranty;
            $apiUrl = "https://cert.vxinc.cn/api/addDevice";
            $yscToken = "B4dXPmbjSz5hxRPD9XnKTC2AdBxQbeyp";
            $platform = "速云签";
            $platformId = 4;
        }
        
        if ($warranty == 0 || $warranty == 1) {
            $randomFloat = mt_rand() / mt_getrandmax(); // 生成一个 0 到 1 之间的浮点数
            if ($randomFloat <= 1.0) {
                // 30% 概率走这段逻辑
                $useWarranty = 0;
                $apiUrl = "https://ioskfz.com/api/addDevice";
                $yscToken = "RklPz8FwlK5FpyLvVFNCEda70MYRW58U";
                $platform = "云腾";
                $platformId = 88;
            } else {
                // 70% 概率走这段逻辑
                $useWarranty = 0;
                $apiUrl = "https://cert.vxinc.cn/api/addDevice";
                $yscToken = "B4dXPmbjSz5hxRPD9XnKTC2AdBxQbeyp";
                $platform = "速云签";
                $platformId = 66;
            }
        }

        // 构建请求参数
        $requestData = array(
            "udid" => $udid,
            "beizhu" => $username,
            "warranty" => $useWarranty,
            "type" => 0,
            "token" => $yscToken
        );

        $userResult = Db::table("fa_user")->where("username", $username)->order("id desc")->select();
        $userId = $userResult[0]["id"];
        $balance = $userResult[0]["money"];
        $deviceBalance = $userResult[0]["score"];

        extract($this->getPrice($userId, $warranty));
        // 发送POST请求
        $response = $this->request_post($apiUrl, $requestData);
        $responseData = json_decode($response, true);

        // 处理响应数据
        if ($responseData["code"] == 1) {
            // 提取相关信息
            $mobileProvision = $responseData["data"]["mobileprovision"];
            $p12 = $responseData["data"]["p12"];
            $pname = $responseData["data"]["pname"];

            // 获取证书信息
            $cerNameResponse = $this->getCerName($p12);
            $cerName = json_decode($cerNameResponse->getContent(), true);

            // 插入数据库
            Db::table("fa_deviceslist")->insert(array(
                "kid" => $zsid,
                "zspt" => $platformId,
                "udid" => $udid,
                "base64mp" => $mobileProvision,
                "deviceid" => $responseData["data"]["id"],
                "zt" => "normal",
                "pname" => $pname,
                "shtype" => $warranty,
                "user" => $username,
                "base64p12" => $p12,
                "tjtime" => time(),
                "type" => 0,
                "cost" => $this->getCost($useWarranty),
                "price" => floatval($devicePrice) * 100,
                "beizhu" => $beizhu,
                "shouhou" => $shouhou,
                "dqtime" => $cerName["data"]["dqtime"]
            ));

            // 更新用户积分或余额
            if ($ghqx == false) {
                if ($warranty == 1) {
                    if ($deviceBalance >= 1) {
                        \app\common\model\User::score(-1,  $userId, "添加[" . $warrantyName . "]证书, UDID:" . $udid);
                        $this->sendmsg($udid, $zsid, $username, $platform, $pname, $balance, 0, $warranty, true);
                    } else {
                        \app\common\model\User::money(-$devicePrice, $userId, "添加[" . $warrantyName . "]证书, UDID:" . $udid . "变动" . -$devicePrice . "元余额");
                        $this->sendmsg($udid, $zsid, $username, $platform, $pname, $balance, $price, $warranty);
                    }
                } else {
                    \app\common\model\User::money(-$devicePrice, $userId, "添加[" . $warrantyName . "]证书, UDID:" . $udid . "变动" . -$devicePrice . "元余额");
                    $this->sendmsg($udid, $zsid, $username, $platform, $pname, $balance, $price, $warranty);
                }
            } else {
                $this->sendmsg($udid, $zsid, $username, $platform . "_售后", $pname, $balance, 0, 0);
            }

            // 返回成功信息
            $redis->rm($udid);
            $this->sendemail($udid, $zsid, $pname, $balance, $price, $warranty, $username);
            return json(array(
                "code" => 1,
                "msg" => "添加成功",
                "data" => array(
                    "id" => $zsid,
                    "pname" => $pname,
                    "pool" => 0,
                    "addtime" => time(),
                    "mobileprovision" => $mobileProvision,
                    "p12" => $p12
                )
            ));
        } else {
            // 发送错误信息
            $redis->rm($udid);
            $this->senderror($responseData["msg"],  $udid, $username, $platform);
            return json(array(
                "code" => 1001,
                "msg" => "添加失败",
                "data" => array(
                    // "sites" => $platform,
                    "error" => $responseData["msg"]
                )
            ));
        }
    }
}