<?php

namespace app\index\controller;

use app\common\controller\Checkp12;
use app\common\controller\Frontend;
use think\cache\driver\Redis;
use think\Config;
use think\Cookie;
use think\Db;
use think\Valid;
use Qcloud\Cos\Client;
use app\common\controller\Parser;
use CFPropertyList\CFPropertyList;
use app\common\model\User;

require_once '../vendor/tencentcos/vendor/autoload.php';
require_once '../vendor/CFPropertyList/vendor/autoload.php';

class Index extends Frontend
{
    protected $noNeedRight = '*';
    protected $noNeedLogin = ['import_ksq', 'import_ysc', 'import_jk', 'xgbeizhu','downipa','uploadlogo','dlist','ipasign','uploadipa','checkapp','search'];
    protected $layout = '';


    function updateDuplicateKids() {
        // 获取所有重复的kid
        $duplicateKids = Db::table('fa_deviceslist')
            ->field('kid, COUNT(kid) as count')
            ->group('kid')
            ->having('count > 1')
            ->select();

        // 遍历每个重复的kid
        foreach ($duplicateKids as $duplicateKid) {
            // 获取重复的kid的所有行
            $rows = Db::table('fa_deviceslist')->where('kid', $duplicateKid['kid'])->select();

            // 保留第一行不变，更新其他行的kid
            array_shift($rows);
            foreach ($rows as $row) {
                // 使用getUniqueKid方法生成新的唯一kid
                $newKid = $this->getUniqueKid();

                // 更新数据库
                Db::table('fa_deviceslist')->where('id', $row['id'])->update(['kid' => $newKid]);
            }
        }
    }

    public function changeNotification() {
        if ($this->request->isPost()) {
            $userId = Cookie::get('uid');
            if ($userId) {
                $notification = $this->request->post('notification');
                $email = $this->request->post('email');
                if ($notification == 'true') {
                    $notification = 1;
                } else {
                    $notification = 0;
                }
                Db::table('fa_user')->where('id', $userId)->update(array('email' => $email, 'notification' => $notification));
                $this->success('修改成功');
            } else {
                $this->error('请先登录');
            }
        }
    }

    public function index()
    {
        // Get user id from cookie
        $userId = Cookie::get('uid');
        if ($userId) {
            // Fetch user details from database
            $userDetails = Db::table('fa_user')->where('id', $userId)->order('id desc')->select();
            $username = $userDetails[0]['username'];
            $userMoney = $userDetails[0]['money'];
            $userLevel = $userDetails[0]['level'];
        }
        // Fetch payment type from database
        $paymentType = Db::table('fa_config')->where('name', 'payxx')->value('value');

        // 批量价格
        $priceLevelDetails = Db::table('fa_config')->where('name', 'pljg')->value('value');
        $priceLevels = explode(",", $priceLevelDetails);

        // 批量区间
        $priceRangeDetails = Db::table('fa_config')->where('name', 'plqj')->value('value');
        $priceRanges = explode(",", $priceRangeDetails);
        $customPrice = explode(",", Db::table("fa_user")->where("id", $userId)->value("price"));


        // Check if request is POST
        if ($this->request->isPost()) {
            $ktoken = $this->request->post('ktoken');
            $feetonum = $this->request->post('feetonum');
            $num = $this->request->post('num');
            $fee = $this->request->post('fee');
            $mypw = $this->request->post('mypw');

            // Check if num is not empty
            if ($num != '') {
                if ($num > 3000) return json(['code' => 1001, 'msg' => '单笔充值不能大于3000', 'data' => []]);
                $price = $priceLevels[0];
                if ($priceRanges[1] <= intval($num) && intval($num) < $priceRanges[2]) $price = $priceLevels[1];
                if ($priceRanges[2] <= intval($num) && intval($num) < $priceRanges[3]) $price = $priceLevels[2];
                if ($priceRanges[3] <= intval($num) && intval($num) < $priceRanges[4]) $price = $priceLevels[3];
                if ($priceRanges[4] <= intval($num) && intval($num) < $priceRanges[5]) $price = $priceLevels[4];
                if (intval($num) >= $priceRanges[5]) $price = $priceLevels[5];

                // 私密价格
                if ($customPrice[4] != 0) {
                    $devicePrice = $customPrice[4];
                    if ($devicePrice < $price) {
                        $price = $devicePrice;
                    }
                }

                $fee = $num * $price;
                $id = time();
                Db::table('fa_paylist')->insert(array('oid' => $id, 'zt' => 0, 'username' => $username, 'num' => $num, 'fee' => $fee, 'uid' => $userId, 'tjtime' => time()));
                return json(['code' => 1, 'msg' => '成功', 'data' => ['fee' => $fee, 'id' => $id]]);
            }

            // Check if fee is not empty
            if ($fee != '') {
                if ($fee > 10000) return json(['code' => 1001, 'msg' => '单笔充值不能大于10000', 'data' => []]);
                $id = time();
                Db::table('fa_paylist')->insert(array('oid' => $id, 'zt' => 0, 'username' => $username, 'type' => 2, 'fee' => $fee, 'uid' => $userId, 'tjtime' => time()));
                return json(['code' => 1, 'msg' => '成功', 'data' => ['fee' => $fee, 'id' => $id]]);
            }

            // Check if feetonum is not empty
            if ($feetonum != '') {
                $num = ceil($feetonum);

                $price = $priceLevels[0];
                if ($priceRanges[1] <= intval($num) && intval($num) < $priceRanges[2]) $price = $priceLevels[1];
                if ($priceRanges[2] <= intval($num) && intval($num) < $priceRanges[3]) $price = $priceLevels[2];
                if ($priceRanges[3] <= intval($num) && intval($num) < $priceRanges[4]) $price = $priceLevels[3];
                if ($priceRanges[4] <= intval($num) && intval($num) < $priceRanges[5]) $price = $priceLevels[4];
                if (intval($num) >= $priceRanges[5]) $price = $priceLevels[5];

                // 私密价格
                if ($customPrice[4] != 0) {
                    $devicePrice = $customPrice[4];
                    if ($devicePrice < $price) {
                        $price = $devicePrice;
                    }
                }

                $fee = $num * $price;
                if ($userMoney < $fee) {
                    return json(['code' => 1001, 'msg' => '余额不足！', 'data' => []]);
                } else {
                    User::money2score(-$fee, $num, $userId, '充值设备数[' . $num . ']');
                    return json(['code' => 1, 'msg' => '充值成功', 'data' => []]);
                }

            }

            // Check if ktoken is not empty
            if ($ktoken != '') {
                Db::table('fa_user')->where('id', $userId)->update(array('ktoken' => $ktoken));
                return 'token更换成功';
            }

            // Check if mypw is not empty
            if ($mypw != '') {
                if ($this->auth->login($username, $mypw)) {
                    $ktoken = $userDetails[0]['ktoken'];
                    return json(['code' => 1, 'msg' => '成功', 'data' => ['ktoken' => $ktoken]]);
                } else {
                    return json(['code' => 0, 'msg' => '失败', 'data' => []]);
                }
            }
        }
        // Fetch device list from database
        // $deviceList = Db::table('fa_deviceslist')->where('user', $username)->order('id desc')->select();
        // $deviceCount = $deviceList ? count($deviceList) : 0;

        // Assign values for view
        $this->assign('title', Db::table('fa_config')->where('name', 'name')->value('value'));
        $this->assign('pljg', $priceLevels); // 批量价格
        $this->assign('plqj', $priceRanges); // 批量价格
        $this->assign('customprice', $customPrice); // 批量价格
        $this->assign('paytype', $paymentType); // 支付类型
        // $this->assign('countrow', $deviceCount);

        // Render view
        return $this->view->fetch();
    }

    public function xgbeizhu()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $beizhu = $this->request->post('beizhu');
            $row = Db::table('fa_deviceslist')->where('id', $id)->update(array('beizhu' => $beizhu));
            if ($row) {
                $this->success();
            } else {
                $this->error('修改失败');
            }
        }
    }

    public function oss()
    {
        if ($this->request->isPost()) {

            $uid = Cookie::get('uid');
            $type = $this->request->post('type');
            $costype = $this->request->post('costype');
            $txid = $this->request->post('txid');
            $txkey = $this->request->post('txkey');
            $txendpoint = $this->request->post('txendpoint');
            $txbucket = $this->request->post('txbucket');
            $aliid = $this->request->post('aliid');
            $alikey = $this->request->post('alikey');
            $aliendpoint = $this->request->post('aliendpoint');
            $alibucket = $this->request->post('alibucket');
            if ($type == 0) $row = Db::table('fa_user')->where('id', $uid)->update(array('costype' => $costype));
            if ($type == 1) $row = Db::table('fa_user')->where('id', $uid)->update(array('txid' => $txid, 'txkey' => $txkey, 'txendpoint' => $txendpoint, 'txbucket' => $txbucket));
            if ($type == 2) $row = Db::table('fa_user')->where('id', $uid)->update(array('aliid' => $aliid, 'alikey' => $alikey, 'aliendpoint' => $aliendpoint, 'alibucket' => $alibucket));

            if ($row) {
                $this->success('修改成功');
            } else {
                $this->error('修改失败');
            }

        }
        $this->assign('title', '配置对象存储');
        return $this->view->fetch();
    }

    public function DevicelistPage()
    {
        $uid = Cookie::get('uid');
        $page = $this->request->get('page') ? $this->request->get('page') : 1;
        $perPage = 20; // 每页显示的数量
        $users = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $nickname = $users[0]['username'];
        $rows = Db::table('fa_deviceslist')
            ->field('base64p12', true)
            ->where('user', $nickname)
            ->order('id desc')
            ->paginate($perPage, false, ['page' => $page]);
        // 遍历判断是否有base64mp, 如果有设为true, 否则设为false
        foreach ($rows as $key => $value) {
            $temp = $rows[$key];
            $temp['base64mp'] = $value['base64mp'] ? true : false;
            $rows[$key] = $temp;
        }
        return json([
            "msg" => "success",
            "code" => 1,
            "data" => $rows
        ]);
    }

    public function devicelist()
    {
        $uid = Cookie::get('uid');
        $users = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $yypz = explode(",", Db::table('fa_config')->where('name', 'yypz')->value('value'));
        $qtjg = explode(",", Db::table('fa_config')->where('name', 'qtjg')->value('value'));
        $nickname = $users[0]['username'];
        $token = $users[0]['ktoken'];
        $page = $this->request->get('page') ? $this->request->get('page') : 1;
        $perPage = 30; // 每页显示的数量
        if ($this->request->isPost()) {
            $udid = $this->request->post('udid');
            $beizhu = $this->request->post('beizhu');
            $type = $this->request->post('type');
            $warranty = $this->request->post('shtype');
            $chi = $this->request->post('chi');
            $deviceType = $this->request->post('deviceType');
            if ($type == '查询') {
                $udid = $this->request->post('cudid');
                $beizhu = $this->request->post('cbeizhu');
                $zt = $this->request->post('zttype');
                if ($udid != '') {
                    $rows = Db::table('fa_deviceslist')
                        ->field('base64p12', true)
                        ->where('user', $nickname)
                        ->where('udid', $udid)
                        ->order('id desc')
                        ->paginate($perPage, false, ['page' => $page]);
                } else if ($zt != 'all') {
                    $rows = Db::table('fa_deviceslist')
                        ->field('base64p12', true)
                        ->where('user', $nickname)
                        ->where('zt', $zt)
                        ->order('id desc')
                        ->paginate($perPage, false, ['page' => $page]);
                } else if ($beizhu != '') {
                    $rows = Db::table('fa_deviceslist')
                        ->field('base64p12', true)
                        ->where('user', $nickname)
                        ->where('beizhu', $beizhu)
                        ->order('id desc')
                        ->paginate($perPage, false, ['page' => $page]);
                } else {
                    $rows = Db::table('fa_deviceslist')
                        ->field('base64p12', true)
                        ->where('user', $nickname)
                        ->order('id desc')
                        ->paginate($perPage, false, ['page' => $page]);
                }
            }
            if ($type == '添加此UDID') {
                if ($deviceType == 'ipad' && $chi == '1') {
                    $this->error('ipad设备暂不支持独立池');
                }
                if ($udid == '') $this->error('UDID不能为空');
                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    // SSL connection is active
                    $scheme = 'https';
                } else {
                    $scheme = 'http';
                }
                $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/api/adddevice';
                $data['udid'] = $udid;
                $data['beizhu'] = $beizhu;
                $data['warranty'] = $warranty;
                $data['token'] = $token;
                $data['type'] = $chi;
                $data['deviceType'] = $deviceType;
                $row = json_decode($this->request_post($url, $data), true);
                if ($row['code'] == 1) {
                    $this->success($row['msg']);
                } else {
                    $this->error($row['msg']);
                }
            }
            if ($type == '预约此UDID') {
                if($deviceType == 'ipad') {
                    $this->error('暂不出售预约ipad设备');
                }
                if ($udid == '') $this->error('UDID不能为空');
                if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
                    $scheme = 'https://';
                } else {
                    $scheme = 'http://';
                }
                $url = $scheme . $_SERVER['HTTP_HOST'] . '/api/addyydevice';
                $data['udid'] = $udid;
                $data['warranty'] = $warranty;
                $data['beizhu'] = $beizhu;
                $data['token'] = $token;
                $data['type'] = $chi;
                $row = json_decode($this->request_post($url, $data), true);
                if ($row['code'] == 1) {
                    $this->success($row['msg']);
                } else {
                    $this->error($row['msg']);
                }
            }
        } else {
            $query = Db::table('fa_deviceslist')
                ->field('base64p12', true)
                ->order('id desc');

            if ($uid != 10086) {
                $query = $query->where('user', $nickname);
            }
            $rows = $query->paginate($perPage, false, ['page' => $page]);
        }
        $total = $rows->total();
        if (!$total) {
            $countrow = 0;
        } else {
            $countrow = $total;
        }
        // 遍历判断是否有base64mp, 如果有设为true, 否则设为false
        foreach ($rows as $key => $value) {
            $temp = $rows[$key];
            $temp['base64mp'] = $value['base64mp'] ? true : false;
            $rows[$key] = $temp;
        }

        $this->assign('title', '设备管理');
        $this->assign('row', $rows);
        $this->assign('yypz', $yypz);
        $this->assign('qtjg', $qtjg);
        $this->assign('countrow', $countrow);
        $this->assign('current_page', 1);
        $this->assign('last_page', ceil($total / $perPage));
        return $this->view->fetch();
    }

    public function paylist()
    {
        $uid = Cookie::get('uid');
        $row = Db::table('fa_user_money_log')->where('user_id', $uid)->order('id desc')->select();
        $sum = Db::table('fa_user_money_log')->where('user_id', $uid)->where('money', 'lt', 0)->sum('money');
        $this->assign('title', '账变记录');
        $this->assign('list', $row);
        $this->assign('sum', $sum);
        return $this->view->fetch();
    }

    public function signlist()
    {
        $uid = Cookie::get('uid');
        $row = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $nickname = $row[0]['username'];
        if ($this->request->isPost()) {
            $checkbox = $this->request->post('id/a');
            if ($checkbox == []) $this->success('请先选择要操作的项目');
            $type = $this->request->post('type');
            $i = 0;
            if ($type == 'del') {
                foreach ($checkbox as $id) {
                    Db::table('fa_signlist')->where('id', $id)->delete();
                    $i++;
                }
                $this->success('成功删除' . $i . "条记录");
            }

            if ($type == 'copy') {
                $text = '';
                foreach ($checkbox as $id) {
                    $row = Db::table('fa_signlist')->where('id', $id)->order('id desc')->select();
                    $url = '' . $row[0]['appname'] . '-UDID[****' . substr($row[0]['udid'], -6) . ']-安装地址:' . $row[0]['url'];
                    $text = $text . $url . "<br>";
                    $i++;
                }
                return $text;
            }
        }

        $applist = Db::name('signlist')
            ->where('user', $nickname)
            ->order('id desc')
            ->field('*')
            ->select();
        $this->assign('title', '签名记录');
        $this->assign('applist', $applist);
        return $this->view->fetch();
    }

    public function Sign()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $name = $this->request->post('name');
            $px = $this->request->post('px');
            $ipa = $this->request->post('ipa');
            if ($name != '') {
                Db::table('fa_category')->where('id', $id)->update(array('name' => $name));
            }
            if ($ipa != '') {

                Db::table('fa_category')->where('id', $id)->delete();
                exec('rm -rf ' . $ipa);
                return '删除成功';
            }
            if ($px != '') {
                Db::table('fa_category')->where('id', $id)->update(array('weigh' => $px));
            }
        }

        $uid = Cookie::get('uid');
        $row = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $nickname = $row[0]['username'];
        $applist = Db::name('category')
            ->where('status', "normal")
            ->where(['type' => 'default'])
            ->whereor(['username' => $nickname])
            ->where('id', '<>', 0)
            ->order('weigh', 'desc')
            ->field('*')
            ->select();
        $this->assign('title', '签名管理');
        $this->assign('applist', $applist);
        return $this->view->fetch();
    }

    public function request_post($url = '', $post_data = array())
    {
        if (empty($url)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $post_data;
        $ch = curl_init();
        //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);
        //post提交方式
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //不验证证书下同
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //不验证证书下同
        $data = curl_exec($ch);
        //运行curl
        curl_close($ch);
        return $data;
    }


    public function uploadipa()
    {
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        $udid = isset($_POST['udid']) ? $_POST['udid'] : '';
        // 移动到框架应用根目录/public/uploads/ 目录下
        if ($file) {
            $info = $file->move(ROOT_PATH . 'public' . DS . 'cos');
            if ($info) {
                $datas = array(
                    "udid" => $udid,
                    "apppath" => $info->getSaveName(),
                );
                return json(['code' => 1, 'msg' => 'ok', 'data' => $datas]);
            } else {
                // 上传失败获取错误信息
                // return $file->getError();
                return json(['code' => 1001, 'msg' => $file->getError(), 'data' => []]);
            }
        }
    }

    function checkapp()
    {
        $uid = Cookie::get('uid');
        $row = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $nickname = $row[0]['username'];
        $ipaPath = $this->request->post('ipaPath');
        $locale = 'zh_CN.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL=' . $locale);
        //var_dump($ipa_file);
        // 获取文件地址
        $ss = $_SERVER['DOCUMENT_ROOT'] . "/cos/" . substr($ipaPath, 0, -36);
        $md5 = substr(substr($ipaPath, -36), 0, 32);

        $filePath = $_SERVER['DOCUMENT_ROOT'] . "/cos/" . $ipaPath;
        $filesPath = $_SERVER['DOCUMENT_ROOT'] . "/cos/" . $md5;
        // 解压文件
        $_cmd = 'unzip -u ' . $filePath . ' -d ' . $filesPath;
        exec($_cmd, $output, $return_var);

        //查询文件.app并去掉后缀
        $dir = iconv("gbk", "utf-8", $filesPath . '/Payload');
        $fileName = scandir($dir);
        foreach ($fileName as $f) {
            if (strpos($f, '.app')) {
                $newFileName = $f;
            }
        }
        //	$file_name = explode('.app',$newFileName);
        $newFile_name = $newFileName;//$file_name[0];
        $content = file_get_contents($filesPath . '/Payload/' . $newFile_name . '/Info.plist');
        $plist = new CFPropertyList();
        $plist->parse($content);
        $data = $plist->toArray();
        $info['icon'] = '';
        $info['name'] = isset($data['CFBundleDisplayName']) ? $data['CFBundleDisplayName'] : $data['CFBundleName'];
        $info['sys_name'] = $data['CFBundleIdentifier'];
        $info['version'] = $data['CFBundleShortVersionString'];
        //$info['version_code'] = $data['CFBundleVersion'];
        // 获取图标
        //$CFBundleIcons = $data['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'];
        $iconName = isset($data['CFBundleIcons']) ? $data['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'] : $data['CFBundleIconFiles'];
        //['CFBundleIcons']
        $iconName = end($iconName);
        if (strstr($iconName, 'png')) {
            $icon_name1 = $filesPath . '/Payload/' . $newFile_name . '/' . $iconName;
        } else {
            $icon_name1 = $filesPath . '/Payload/' . $newFile_name . '/' . $iconName . '.png';
            $icon_name2 = $filesPath . '/Payload/' . $newFile_name . '/' . $iconName . '@2x.png';
            $icon_name3 = $filesPath . '/Payload/' . $newFile_name . '/' . $iconName . '@3x.png';
        }
        $temp_path = $filesPath . '/';
        if (!file_exists($temp_path)) {
            mkdir($temp_path, 0777, true);
        }
        //require_once($path . '/helpers/parsers_helper.php');
        $filename = null;
        if (file_exists($icon_name1)) {
            $filename = $icon_name1; //需要解密的文件路径
        } elseif (file_exists($icon_name2)) {
            $filename = $icon_name2; //需要解密的文件路径
        } elseif (file_exists($icon_name3)) {
            $filename = $icon_name3; //需要解密的文件路径
        }

        if ($filename !== null) {
            $nm = time();
            if (!file_exists('cos/' . $uid)) {
                mkdir('cos/' . $uid);
            }
            $path = 'cos/' . $uid . '/' . str_replace(' ', '', $info['name']);
            if (!file_exists($path)) {
                mkdir($path);
            }
            $_rename = 'mv ' . $filePath . '  ' . $_SERVER['DOCUMENT_ROOT'] . "/cos/" . $uid . '/' . str_replace(' ', '', $info['name']) . '/' . '_' . $nm . '.ipa';
            exec($_rename, $out, $return);
            $newFilename = 'cos/' . $uid . '/' . str_replace(' ', '', $info['name']) . '/' . '_' . $iconName . '_' . $nm . '.png'; //解密后的文件路径
            Parser::fix($filename, $newFilename);
            $info['icon'] = 'https://' . $_SERVER['HTTP_HOST'] . '/cos/' . $uid . '/' . str_replace(' ', '', $info['name']) . '/' . '_' . $iconName . '_' . $nm . '.png';
            $info['url'] = $_SERVER['DOCUMENT_ROOT'] . "/cos/" . $uid . '/' . str_replace(' ', '', $info['name']) . '/' . '_' . $nm . '.ipa';
            $info['filesize'] = $this->getFileSize(filesize("cos/" . $uid . '/' . str_replace(' ', '', $info['name']) . '/' . '_' . $nm . '.ipa'));

        }
        exec('rm -rf ' . $ss);
        exec('rm -rf ' . $filePath);
        exec('rm -rf ' . $_SERVER['DOCUMENT_ROOT'] . "/cos/" . $md5);
        Db::table('fa_category')->insert(array('type' => 'personal', 'name' => $info['name'], 'upipa' => $info['url'], 'baoming' => $info['sys_name'], 'image' => $info['icon'], 'banben' => $info['version'], 'username' => $nickname, 'filesize' => $info['filesize'], 'create_time' => time(), 'update_time' => time()));
        return json($info);

    }

    function import() {
        return $this->view->fetch();
    }

    function request_get($url, $header = array()) {
        // 初始化cURL会话
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($header)) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // 记录请求信息
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);

        // 记录响应状态码
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        // 记录错误信息
        if ($response === false) {
            $error = curl_error($ch);
        }
        curl_close($ch);
        // 打印日志
        echo "Request URL: " . $info['url'] . "<br>";
        echo "Request Header: " . implode(', ', $header) . "<br>";
        echo "Response Code: " . $httpcode . "<br>";
        if (isset($error)) {
            echo "cURL Error: " . $error . "<br>";
        }
        return $response;
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

    private function getUniqueKid()
    {
        $kid = $this->getkid();
        while (Db::table("fa_deviceslist")->field("base64mp,base64p12", true)->where("kid", $kid)->order("id desc")->select()) {
            $kid = $this->getkid();
        }
        return $kid;
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
        if (!isset($certificates["cert"])) {
            return json(array("code" => 0, "msg" => "证书密码错误"));
        }
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

    function import_data($row, $user, $startNum) {
        foreach ($row['content'] as $key => $value) {
            $udid = $value['udid'];
            $pwd = $value['certPwd'];
            $deviceID = $value['id'];
            $p12 = $value['p12'];
            $mobileProvision = $value['mp'];
            $certInfo = json_decode($this->getCerName($p12, $pwd)->getContent(), true);
            if ($certInfo["code"] == 0) {
                echo '导入第' . $startNum . '个设备' . $udid . '失败, 证书密码错误<br>';
                $startNum++;
                continue;
            }
            $row = Db::table('fa_deviceslist')->where('udid', $udid)->order('id desc')->select();
            if (!$row) {
                Db::table("fa_deviceslist")->insert([
                    "kid" => $this->getUniqueKid(),
                    "zspt" => 1,
                    "udid" => $udid,
                    "base64mp" => $mobileProvision,
                    "deviceid" => $deviceID,
                    "zt" => "normal",
                    "pname" => $certInfo["data"]["devname"],
                    "user" => $user,
                    "base64p12" => $p12,
                    "tjtime" => time(),
                    "beizhu" => "导入自极客签",
                    "type" => 1,
                    "dqtime" => $certInfo["data"]["dqtime"]
                ]);
                echo '导入第' . $startNum . '个设备' . $udid . ', 证书名: ' . $certInfo["data"]["devname"] . '成功<br>';
                $startNum++;
            } else {
                echo '导入第' . $startNum . '个设备' . $udid . '失败, 设备已存在<br>';
            }
        }
        return $startNum;
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

    function import_jk() {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");
        $uid = $this->request->param('uid');
        $token = $this->request->param('token');
        $user = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $url = 'https://www.jikeq.com/public-api/query-devices';
        $row = json_decode($this->request_get($url, ['token: ' . $token]), true);
        echo '开始导入设备, 共' . $row['totalElements'] . '台设备<br>';
        echo '共' . $row['totalPages'] . '页<br>';
        $startNum = 1;
        for ($i = 0; $i < $row['totalPages']; $i++) {
            $row = json_decode($this->request_get($url . '?page=' . $i, ['token: ' . $token]), true);
            $startNum = $this->import_data($row, $user[0]['username'], $startNum);
            echo '导入第' . ($i + 1) . '页成功<br>';
        }
        echo '导入完成';
    }

    function import_ksq() {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");
        $this->import_common('https://cert.4yun.cn');
    }

    function import_ysc() {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");
        $this->import_common('https://cer.52tzs.com');
    }

    function import_common($domain) {
        $url = $domain . "/api/getalldevice";
        $deviceUrl =  $domain . "/api/Getcertificate";
        $uid = $this->request->param('uid');
        $token = $this->request->param('token');
        // 获取起始位置, 如果没有设置就从1开始
        $startFrom = $this->request->param('start') ? intval($this->request->param('start')) : 1;
        $user = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        // 构建请求参数
        $requestData["token"] = $token;
        $resp = json_decode($this->request_post($url, $requestData), true);
        if ($resp['code'] != 1) {
            echo '导入失败, 错误信息: ' . $resp['msg'];
            return;
        }
        $data = $resp['data'];
        echo '开始导入设备, 共' . $data['total'] . '台设备<br>';
        $startNum = 1;
        foreach ($data['list'] as $key => $value) {
            try {
                if ($startNum < $startFrom) {
                    $startNum++;
                    continue;
                }
                $udid = $value['udid'];
                $exist = Db::table('fa_deviceslist')->where('udid', $udid)->order('id desc')->select();
                if ($exist) {
                    echo '导入第' . $startNum . '个设备' . $udid . '失败, 设备已存在<br>';
                    $startNum++;
                    continue;
                }
                $pwd = "1";
                $deviceID = $value['id'];

                $requestData["token"] = $token;
                $requestData["id"] = $deviceID;
                $deviceResp = json_decode($this->request_post($deviceUrl, $requestData), true);

                if ($deviceResp['code'] != 1) {
                    echo '导入第' . $startNum . '个设备' . $udid . '失败, 错误信息: ' . $deviceResp['msg'] . '<br>';
                    $startNum++;
                    continue;
                }

                $p12 = $deviceResp['data']['p12'];
                $mobileProvision = $deviceResp['data']['mobileprovision'];
                $addtime = $deviceResp['data']['addtime'];
                $state = $deviceResp['data']['state'];

                if (!$state) {
                    echo '导入第' . $startNum . '个设备' . $udid . '失败, 设备已掉签<br>';
                    $startNum++;
                    continue;
                }

                $certInfo = json_decode($this->getCerName($p12, $pwd)->getContent(), true);
                if ($certInfo["code"] == 0) {
                    echo '导入第' . $startNum . '个设备' . $udid . '失败, 证书密码错误<br>';
                    $startNum++;
                    continue;
                }
                $insertData = [
                    "kid" => $this->getUniqueKid(),
                    "zspt" => 1,
                    "udid" => $udid,
                    "base64mp" => $mobileProvision,
                    "deviceid" => $deviceID,
                    "zt" => "normal",
                    "pname" => $certInfo["data"]["devname"],
                    "user" => $user[0]['username'],
                    "base64p12" => $p12,
                    "tjtime" => $addtime,
                    "beizhu" => "导入自快闪签",
                    "type" => 1,
                    "dqtime" => $certInfo["data"]["dqtime"]
                ];
                Db::table("fa_deviceslist")->insert($insertData);
                echo '导入第' . $startNum . '个设备' . $udid . ', 证书名: ' . $certInfo["data"]["devname"] . '成功<br>';
                $startNum++;
            } catch (\Exception $e) {
                echo '导入第' . $startNum . '个设备' . $udid . '失败, 错误信息: ' . $e->getMessage() . '<br>';
                $startNum++;
            }
        }
        echo '导入完成';
    }

    function getFileSize($size)
    {
        $dw = "Byte";
        if ($size >= pow(2, 40)) {
            $size = round($size / pow(2, 40), 2);
            $dw = "TB";
        } else if ($size >= pow(2, 30)) {
            $size = round($size / pow(2, 30), 2);
            $dw = "GB";
        } else if ($size >= pow(2, 20)) {
            $size = round($size / pow(2, 20), 2);
            $dw = "MB";
        } else if ($size >= pow(2, 10)) {
            $size = round($size / pow(2, 10), 2);
            $dw = "KB";
        } else {
            $dw = "Bytes";
        }
        return $size . $dw;

    }

    public function ipasign()
    {
        $uid = Cookie::get('uid');
        $row = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $nickname = $row[0]['username'];
        $udid = isset($_POST['udid']) ? $_POST['udid'] : '';
        $ipaid = isset($_POST['ipaid']) ? $_POST['ipaid'] : '';
        $appbid = $this->request->post('appbid');
        $appname = $this->request->post('appname');

        $ipaicon = $this->request->post('appicon');
        if ($ipaicon == '') {
            $xgtb = false;
        } else {
            $xgtb = true;
        }

        if (!(strlen($udid) == 25 || strlen($udid) == 40)) $this->error('UDID格式错误');

        $row = Db::table('fa_deviceslist')->where('user', $nickname)->where('base64mp != ""')->where('udid', $udid)->where('zt', 'normal')->order('id asc')->select();
        if (!$row) $this->error("该UDID不存在可用证书<br>请先添加再签名");
        $base64mp = $row[0]['base64mp'];
        $base64p12 = $row[0]['base64p12'];
        $applist = Db::name('category')->where('id', $ipaid)->order('id desc')->select();
        if (!$applist) $this->error("该APP不存在");
        $appicon = $applist[0]['image'];
        $apppath = $applist[0]['upipa'];

        $gm = '1';
        if ($appbid == '') $appbid = $applist[0]['baoming'];
        if ($appname == '') {
            $appname = $applist[0]['name'];
            $gm = "0";
        }

        if (!file_exists('temp')) {
            mkdir('temp');
        }
        $ap = time();
        if (!file_exists('temp/' . $ap)) {
            mkdir('temp/' . $ap);
        }

        $locale = 'zh_CN.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL=' . $locale);

        $filesPath = $_SERVER['DOCUMENT_ROOT'] . "/temp/" . $ap;
        // 解压文件
        $_cmd = 'unzip -u ' . $apppath . ' -d ' . $filesPath;
        exec($_cmd, $output, $return_var);

        //查询文件.app并去掉后缀
        $dir = iconv("gbk", "utf-8", $filesPath . '/Payload');
        $fileName = scandir($dir);
        foreach ($fileName as $f) {
            if (strpos($f, '.app')) {
                $newFileName = $f;
            }
        }

        $apppath = $filesPath . '/Payload/' . $newFileName;
        if ($xgtb) {
            $data = $this->infoplist($apppath, $ipaicon);
            if (!$data) {
                exec('rm -rf ' . $filesPath);
                return json(['code' => 1001, 'msg' => '图标修改失败', 'data' => []]);
            }
        }

        $ttname = $ap;
        file_put_contents("temp/" . $ttname . ".p12", base64_decode($base64p12));
        file_put_contents("temp/" . $ttname . ".mobileprovision", base64_decode($base64mp));
        $absolute_apppath = str_replace("application/index/controller", "public/temp/" . $ttname, dirname(__FILE__));
        if ($gm == '0') {
            $return = shell_exec('zsign -k ' . $absolute_apppath . '.p12 -p 1 -m  ' . $absolute_apppath . '.mobileprovision -o ' . $absolute_apppath . '.ipa -b ' . $appbid . ' ' . $apppath . ' -z 9 2>&1');
        } else {

            $return = shell_exec('zsign -k ' . $absolute_apppath . '.p12 -p 1 -m  ' . $absolute_apppath . '.mobileprovision -o ' . $absolute_apppath . '.ipa -b ' . $appbid . ' -n "' . $appname . '" ' . $apppath . ' -z 9 2>&1');
        }

        if (strstr($return, 'Signed OK')) {
            $ossconfig = Db::table('fa_config')->where('name', 'ossconfig')->value('value');
            $installUrl = Db::table('fa_config')->where('name', 'azdl')->value('value');
            if ($ossconfig == 0) {

                $downurl = "https://" . $_SERVER['HTTP_HOST'] . "/temp/" . $ttname . '.ipa';
                $tk = $appname . ',' . $appicon . ',' . $appbid . ',' . $downurl;
                $data = $this->_encrypt($tk, '8659471');
                $row = $this->request_post($installUrl . '/pages/api.php', "url=$installUrl/wz.php?url=$installUrl/plist.php?" . $data);
                $row = json_decode($row, true);
                $datas = array("url" => $row['url']);
                Db::table('fa_signlist')->insert(array('user' => $nickname, 'url' => $row['url'], 'appname' => $appname, 'udid' => $udid, 'tjtime' => time()));
                return json(['code' => 1, 'msg' => '签名成功', 'data' => $datas]);

            } else {
                $tcosurl = Db::table('fa_config')->where('name', 'tcosurl')->value('value');
                $ipaname = time() . '.ipa';
                $downurl = $tcosurl . '/dev_sign/' . $ipaname;
                $oss = $this->tupload($absolute_apppath . '.ipa', $ipaname);
                if ($oss) {
                    exec('rm -rf ' . $filesPath);
                    exec('rm -rf ' . $absolute_apppath . '.ipa 2>&1');
                    exec('rm -rf ' . $absolute_apppath . '.p12 2>&1');
                    exec('rm -rf ' . $absolute_apppath . '.mobileprovision 2>&1');
                    $tk = $appname . ',' . $appicon . ',' . $appbid . ',' . $downurl;
                    $data = $this->_encrypt($tk, '8659471');
                    $row = $this->request_post($installUrl . '/pages/api.php', "url=$installUrl/wz.php?url=$installUrl/plist.php?" . $data);
                    $row = json_decode($row, true);
                    $datas = array("url" => $row['url']);
                    Db::table('fa_signlist')->insert(array('user' => $nickname, 'url' => $row['url'], 'appname' => $appname, 'udid' => $udid, 'tjtime' => time()));
                    return json(['code' => 1, 'msg' => '签名成功', 'data' => $datas]);
                } else {
                    $this->error("下载地址获取失败");
                }
            }
        } else {
            $this->error($return);

        }
    }
    
    public function deleteRow() {
        $uid = Cookie::get('uid');
        if ($this->request->isPost()) {
            $ids = $this->request->post('ids');
            // 将逗号分隔的ids字符串转换成数组
            $idsArray = explode(',', $ids);
            // 批量删除
            Db::table('fa_kami')->whereIn('id', $idsArray)->where('uid', $uid)->delete();
            return json(['code'=>1,'msg'=>'成功','data'=>[]]);
        }
    }


    public function kami()
    {
        $uid=Cookie::get('uid');
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $act = $this->request->post('act');
            $act1=isset($_GET['act'])?$_GET['act']:'';
            if($act1=='list')$act='list';
            if($act=='list'){
                $row = Db::table('fa_kami')->where('uid',$uid)->order('id desc')->select();
                return $row;
            }
            if($act=='del'){
                Db::table('fa_kami')->where('id', $id)->where('uid', $uid)->delete();
                return json(['code'=>1,'msg'=>'成功','data'=>[]]);
            }
            if($act=='set'){
                $beizhu = $this->request->post('beizhu');
                $zt = $this->request->post('zt');
                $update=array();
                if($zt!='')$update['open']=$zt;
                if($beizhu!='')$update['beizhu']=$beizhu;
                $row=Db::table('fa_kami')->where('uid', $uid)->where('id', $id)->update($update);
                if($row){
                    $this->success();
                }else{
                    $this->error('修改失败');
                }
            }
            $this->error('非法请求！');
        }else{
            $count = 2000 - Db::table('fa_kami')->where('uid',$uid)->where('udid','')->order('id desc')->count();
            $this->assign('count',$count);
            $this->assign('title','卡密管理');
            return $this->view->fetch();
        }
    }

    public function tupload($srcPath, $ipaname)
    {
        //腾讯云cos
        $secretId = Db::table('fa_config')->where('name', 'tSecretId')->value('value');;
        $secretKey = Db::table('fa_config')->where('name', 'tsecretkey')->value('value');;
        $region = Db::table('fa_config')->where('name', 'tregion')->value('value');;
        $bucket = Db::table('fa_config')->where('name', 'tbucket')->value('value');;
        $cosClient = new Client(
            array(
                'region' => $region,
                'schema' => 'http', //协议头部，默认为http
                'credentials' => array(
                    'secretId' => $secretId,
                    'secretKey' => $secretKey)
            )
        );
        $keyv = "/dev_sign/" . $ipaname;
        # 上传文件
        ### 上传文件流
        try {
            $bucket = $bucket;
            $key = $keyv;
            $file = fopen($srcPath, 'rb');
            if ($file) {
                $cosClient->Upload(
                    $bucket = $bucket,
                    $key = $key,
                    $body = $file
                );
            }
        } catch (\Exception $e) {
            return 0;
        }
        return 1;

    }

    function _encrypt($data, $key, $expire = 3600 * 24)
    {
        $key = md5($key);
        $data = base64_encode($data);
        $x = 0;
        $len = strlen($data);
        $l = strlen($key);
        $char = '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) $x = 0;
            $char .= substr($key, $x, 1);
            $x++;
        }
        $str = sprintf('%010d', $expire ? $expire + time() : 0);
        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
        }
        $str = base64_encode($str);
        $str = str_replace(array('=', '+', '/'), array('ksq', 'wangbei', 'haha'), $str);
        return $str;
    }

    //开启文件访问权限
    function infoplist($filesPath)
    {
        $save_path = str_replace($_SERVER['DOCUMENT_ROOT'] . "/", '', $filesPath);
        $filesPath = $filesPath . "/Info.plist";
        $content = file_get_contents($filesPath);
        $plist = new CFPropertyList();
        $plist->parse($content);
        $data = $plist->toArray();
        foreach ($data as $key => $value) {
            if ($key == 'UIFileSharingEnabled' || $key == 'UISupportsDocumentBrowser') {
                unset($data[$key]);
            }
        }

        $arr ["UIFileSharingEnabled"] = true;
        $arr ["UISupportsDocumentBrowser"] = true;
        $data = array_merge_recursive($arr, $data);
        try {
            $options = array("CFDictionary", "CFArray", "CFString");
            $plist = CFPropertyList::guess($data, $options);
            $results = new CFPropertyList();
            $results->add($plist);
            $path = $save_path . "/Info.plist";
            $results->saveXML($path);
        } catch (IOException $e) {
            return false;
        }
        return true;
    }

    public function uploadp8()
    {
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if ($file) {
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if ($info) {
                $datas = array(
                    "apppath" => $info->getSaveName(),
                );
                return json(['code' => 1, 'msg' => 'ok', 'data' => $datas]);
            } else {
                // 上传失败获取错误信息
                // return $file->getError();
                return json(['code' => 1001, 'msg' => $file->getError(), 'data' => []]);
            }
        }
    }

    public function my_xgbeizhu()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $beizhu = $this->request->post('beizhu');
            $row = Db::table('fa_agentapplelist')->where('id', $id)->update(array('beizhu' => $beizhu));
            if ($row) {
                $this->success();
            } else {
                $this->error('修改失败');
            }
        }
    }

    public function my_xgyl()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $iphone = $this->request->post('iphone');
            $ipad = $this->request->post('ipad');
            $mac = $this->request->post('mac');
            $update = array();
            if ($iphone != '') $update['yliphone'] = $iphone;
            if ($ipad != '') $update['ylipad'] = $ipad;
            if ($mac != '') $update['ylmac'] = $mac;
            $row = Db::table('fa_agentapplelist')->where('id', $id)->update($update);
            if ($row) {
                $this->success();
            } else {
                $this->error('修改失败');
            }
        }
    }

    public function editcer()
    {
        if ($this->request->isPost()) {
            $uid = Cookie::get('uid');
            $id = $this->request->post('id');
            $yy = $this->request->post('yy');
            $zt = $this->request->post('zt');

            $update = array();
            if ($zt != '') $update['zt'] = $zt;
            if ($yy != '') $update['yy'] = $yy;
            $row = Db::table('fa_agentapplelist')->where('uid', $uid)->where('id', $id)->update($update);
            if ($row) {
                $this->success();
            } else {
                $this->error('修改失败');
            }
        }
    }

    public function agent() {
        $uid=Cookie::get('uid');
        $user = Db::table('fa_user')->where('id',$uid)->order('id desc')->select();
        $nickname = $user[0]['username'];
        $user_money = $user[0]['money'];
        $usersite = $user[0]['site'];
        $sm=0;
        if ($this->request->isPost()) {
            $act = $this->request->post("act");
            $params = $this->request->post("row/a",'',null);
            if ($params) {
                $update=array();
                $update['site'] = trim($params['site']);
                $update['sitename'] = trim($params['sitename']);
                $update['background_url'] = trim($params['background_url']);
                $update['background_color'] = trim($params['background_color']);
                $update['btn_color'] = trim($params['btn_color']);
                $update['buy_url'] = trim($params['buy_url']);
                $update['theme'] = trim($params['theme']);
                $update['domain_url'] = trim($params['domain_url']);
                // $update['font_color'] = trim($params['font_color']);
                $update['contact_url'] = trim($params['contact_url']);
                $update['bottom_html'] = $params['bottom_html'];
                $update['notice'] = trim($params['notice']);
                $update['icon_url'] = trim($params['icon_url']);
                $update['time'] = time();
                $row=Db::table('fa_agentsite')->where('user', $nickname)->where('site',$update['site'])->update($update);
                if($row){
                    $this->success('修改成功');
                }else{
                    $this->error('修改失败');
                }
            }
            if($act=='add'){
                $site = $this->request->post("site");
                $array = ['cert','sign','tool','udid','cloud','install','tools','down','developer','dumpapp','apple'];
                if(in_array($site, $array))$this->error('此域名前缀禁止设置！');
                if(Db::table('fa_agentsite')->where('site',$site)->order('id desc')->select())$this->error('此域名前缀已被他人使用');
                if($usersite==null){
                    Db::table('fa_user')->where('id', $uid)->where('username',$nickname)->update(['site'=>$site]);
                    $row=Db::table('fa_agentsite')->insert(array('user'=>$nickname,'site'=>$site));
                    if($row){
                        $this->success('创建成功');
                    }else{
                        $this->error('创建失败');
                    }
                }else{
                    if($user_money<50)$this->error('账号余额不足！');
                    $row=Db::table('fa_agentsite')->insert(array('user'=>$nickname,'site'=>$site));
                    if($row){
                        Db::table('fa_user')->where('id',$uid)->setDec('money', 50);
                        Db::table('fa_user_money_log')->insert(array('user_id'=>$uid,'money'=>-50,'before'=>$user_money,'after'=>$user_money-50,'memo'=>"添加代销站点[$site]",'createtime'=>time()));
                        $jobData = ['username'=> $nickname,'msg' => "购买代销站点"] ;
                        // $job = new \wb\Push('Notice', 'noticejob',$jobData);
                        $this->success('创建成功');
                    }else{
                        $this->error('创建失败');
                    }
                }
            }
        }
        $rows = Db::table('fa_agentsite')->where('user', $nickname)->order('id asc')->select();
        if(!$rows){
            // $rows = 0;
            if($usersite!=null){
                Db::table('fa_agentsite')->insert(array('user'=>$nickname,'site'=>$usersite));
                $rows = Db::table('fa_agentsite')->where('user',$nickname)->order('id asc')->select();
                $this->assign('row',$rows[0]);
            }
        } else {
            $this->assign('row',$rows[0]);
        }
        $this->assign('title','简易代销');
        return $this->view->fetch();
    }

    public function my()
    {
        $uid = Cookie::get('uid');
        $row = Db::table('fa_agentapplelist')->where('uid', $uid)->order('id desc')->select();
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            Db::table('fa_agentapplelist')->where('id', $id)->delete();
            return '删除成功';
        }

        //$this->assign('list',$row);
        $this->assign('title', '独立池');
        $this->assign('row', $row);
        return $this->view->fetch();
    }

    public function dulichi()
    {
        $uid = Cookie::get('uid');
        $row = Db::table('fa_agentapplelist')->where('uid', $uid)->order('id desc')->select();
        if ($row) {
            return json(['code' => 1, 'msg' => 'ok', 'data' => $row]);
        }
    }

    public function uploadlogo(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if($info){
                $datas= array(
                    "logopath" => 'https://'.$_SERVER['SERVER_NAME'].'/uploads/'.$info->getSaveName(),
                );
                return json(['code'=>1,'msg'=>'ok','data'=>$datas]);
            }else{
                // 上传失败获取错误信息
                // return $file->getError();
                return json(['code'=>1001,'msg'=>$file->getError(),'data'=>[]]);
            }
        }
    }

    public function redisKeys() {
        $redis = new Redis();
        $keys = $redis->allKeys();
        echo json_encode($keys);
    }

    public function updateKids()
    {
        // Fetch all records with kid count > 1
        $records = Db::query("SELECT kid, COUNT(*) as count FROM fa_deviceslist GROUP BY kid HAVING count > 1");

        // Loop through each record
        foreach ($records as $record) {
            // Get all rows with the same kid
            $rows = Db::table('fa_deviceslist')->where('kid', $record['kid'])->select();

            // Loop through each row
            foreach ($rows as $row) {
                // Generate a new unique kid
                do {
                    $newKid = $this->getkid();
                } while (Db::table('fa_deviceslist')->where('kid', $newKid)->find());

                // Update the row with the new kid
                Db::table('fa_deviceslist')->where('id', $row['id'])->update(['kid' => $newKid]);
                echo "success update kid: " . $row['kid'] . " to " . $newKid . "\n";
            }
        }
    }

    public function addkami()
    {
        if ($this->request->isPost()) {
            $uid=Cookie::get('uid');
            $row = Db::table('fa_user')->where('id',$uid)->order('id desc')->select();
            $user = $row[0]['username'];
            $kmsl = intval($this->request->post('kami'));
            $type = intval($this->request->post('type'));
            $shtype = intval($this->request->post('shtype'));
            $pool = intval($this->request->post('pool'));
            $beizhu = $this->request->post('beizhu');
            $deviceType = $this->request->post('deviceType');
            $kamiArray = [];
            if($kmsl ==0){
                $this->error('数量需大于0');
            }
            if($beizhu ==''){
                $beizhu='无备注';
            }
            if($type==0){
                if($shtype==0){$kmqz = 'SS-TP';}
                if($shtype==1){$kmqz = 'SS-BZ';}
                if($shtype==2){$kmqz = 'SS-JQ';}
                if($shtype==3){$kmqz = 'SS-WD';}
                if($shtype==4){$kmqz = 'SS-BL';}
            }else{
                if($shtype==0){$kmqz = 'YY-TP';}
                if($shtype==1){$kmqz = 'YY-BZ';}
                if($shtype==2){$kmqz = 'YY-JQ';}
                if($shtype==3){$kmqz = 'YY-WD';}
                if($shtype==4){$kmqz = 'YY-BL';}
            }
            $jsq = $kmsl;
            $gtime = time();
            $gtm = date('YmdHis',time());
            for ($i=1;$i<=$jsq;$i++) {
                $data = array();
                $rd = rand(1,15);
                $data['kami'] = strtoupper($kmqz.substr(md5(($gtm.'Km'.$i)),$rd,12));
                $data['udid'] = '';
                $data['type'] = $type;
                $data['jhtime'] = '';
                $data['shtype'] = $shtype;
                $data['user'] = $user;
                $data['uid'] = $uid;
                $data['beizhu'] = $beizhu;
                $data['pool'] = $pool;
                $data['deviceType'] = $deviceType;
                Db::table('fa_kami')->insert($data);
                $kamiArray[] = $data['kami'];
            }
            $this->success("共生成" . $kmsl . "个卡密", "", array("kami" => $kamiArray));
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }

    public function signtool()
    {
        $uid = Cookie::get('uid');
        $user = Db::table('fa_user')->where('id', $uid)->order('id desc')->select();
        $nickname = $user[0]['username'];
        $usersite = $user[0]['site'];

        if ($this->request->isPost()) {
            $act = $this->request->post("act");
            $params = $this->request->post("row/a");
            if ($params) {
                $update = array();
                $update['app_bundle_id'] = trim($params['app_bundle_id']);
                $update['app_name'] = trim($params['app_name']);
                $update['plist_url'] = trim($params['plist_url']);
                $update['default_app_source_urls'] = trim($params['default_app_source_urls']);
                $update['app_icon_url'] = trim($params['app_icon_url']);
                $update['app_launch_icon_url'] = trim($params['app_launch_icon_url']);
                $update['group'] = trim($params['group']);
                $update['contact_url'] = trim($params['contact_url']);
                $update['buy_url'] = trim($params['buy_url']);
                $update['tutorial_url'] = trim($params['tutorial_url']);
                $update['notice'] = trim($params['notice']);
                $update['exchange'] = trim($params['exchange']) == 'true' ? '1' : '0';
                $update['whitelist'] = trim($params['whitelist']) == 'true' ? '1' : '0';
                $update['import_permissions'] = trim($params['import_permissions']) == 'true' ? '1' : '0';
                $update['updatetime'] = time();
                $row = Db::table('fa_signtool')->where('user', $nickname)->where('app_bundle_id', $update['app_bundle_id'])->update($update);
                if ($row) {
                    $this->success('修改成功');
                } else {
                    $this->error('修改失败');
                }
            }
            if ($act == 'add') {
                $app_bundle_id = "com.ihyys." . $usersite;
                if (Db::table('fa_signtool')->where('app_bundle_id', $app_bundle_id)->order('id desc')->select()) $this->error('此BundleId已被他人使用');
                $row = Db::table('fa_signtool')->insert(array('user' => $nickname, 'app_bundle_id' => $app_bundle_id, 'short_site' => $usersite));
                if ($row) {
                    $this->success('创建成功');
                } else {
                    $this->error('创建失败');
                }
            }
            if ($act == 'addsite') {
                $site = $this->request->post("site");
                $array = ['cert', 'sign', 'tool', 'udid', 'cloud', 'install', 'tools', 'down', 'developer', 'dumpapp', 'apple'];
                if (in_array($site, $array)) $this->error('此域名前缀禁止设置！');
                if (Db::table('fa_agentsite')->where('site', $site)->order('id desc')->select()) $this->error('此域名前缀已被他人使用');
                Db::table('fa_user')->where('id', $uid)->where('username', $nickname)->update(['site' => $site]);
                $row = Db::table('fa_signtool')->where('user', $nickname)->update(['short_site' => $site]);
                if ($row) {
                    $this->success('创建成功');
                } else {
                    $this->error('创建失败');
                }
            }
        }

        $row = Db::table('fa_signtool')->where('user', $nickname)->order('id desc')->select();
        if ($row) {
            $rows = $row[0];
            if ($usersite != null && $row[0]['short_site'] == '') {
                Db::table('fa_signtool')->where('user', $nickname)->update(['short_site' => $usersite]);
                $row = Db::table('fa_signtool')->where('user', $nickname)->order('id desc')->select();
                $rows = $row[0];
            }
        } else {
            $rows = 0;
        }
        $this->assign('title', '贴牌配置');
        $this->assign('row', $rows);
        return $this->view->fetch();
    }


    public function uploadcer(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if($info){
                $datas= array(
                    "apppath" => 'uploads/'.$info->getSaveName(),
                );
                return json(['code'=>1,'msg'=>'ok','data'=>$datas]);
            }else{
                // 上传失败获取错误信息
                // return $file->getError();
                return json(['code'=>1001,'msg'=>$file->getError(),'data'=>[]]);
            }
        }
    }

    public function uploadp12(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if($info){
                $datas= array(
                    "apppath" => $info->getSaveName(),
                );
                return json(['code'=>1,'msg'=>'ok','data'=>$datas]);
            }else{
                // 上传失败获取错误信息
                // return $file->getError();
                return json(['code'=>1001,'msg'=>$file->getError(),'data'=>[]]);
            }
        }
    }

    private function changeP12Password($p12Path,$pwd)
    {
        if (!file_exists($p12Path)) {
            return false;
        }
        $path = 'temp';
        $sj=time();
        if (!file_exists($path)){
            mkdir($path);
        }
        if (!file_exists($path.'/'.$sj)){
            mkdir($path.'/'.$sj);
        }
        $path=$_SERVER['DOCUMENT_ROOT']."/temp/".$sj;
        $a=shell_exec('openssl pkcs12 -in '.$p12Path.' -password pass:"'.$pwd.'" -passout pass:"123456" -out '.$path.'/temp.pem 2>&1') ;
        if(strstr($a, 'invalid password')){
            return false;
        };
        $b=shell_exec('openssl pkcs12 -passin pass:"123456" -passout pass:"1" -export -in '.$path.'/temp.pem -out '.$path.'/developer.p12 2>&1') ;
        if($b!=''){
            return false;
        }
        $p12Path = $path.'/developer.p12';
        return $p12Path;
    }

    function extractUDIDFromMobileProvision($filePath) {
        // 读取文件内容
        $content = file_get_contents($filePath);
        // 使用正则表达式匹配 UDID
        preg_match('/<key>ProvisionedDevices<\/key>\s*<array>(.*?)<\/array>/s', $content, $matches);
        if (isset($matches[1])) {
            $devicesXml = $matches[1];
            // 使用正则表达式匹配 UDID
            preg_match_all('/<string>(.*?)<\/string>/', $devicesXml, $udids);
            // 返回 UDID 数组
            return $udids[1];
        }
        return [];
    }

    function upcer() {
        // 上传证书
        $udidlist=[];
        $serialNumber = $this->request->post('serialNumber');
        $expiryDate = $this->request->post('dqtime');
        $base64mp = $this->request->post('base64mp');
        $base64p12 = $this->request->post('base64p12');
        $udids = $this->request->post('udid/a');
        $pname = $this->request->post('pname');
        $upcer_price = Db::table('fa_config')-> where('name','upcer_price')->value('value');
        $use_money = count($udids) * $upcer_price;
        $uid=Cookie::get('uid');

        if (empty($uid)) {
            return json(['code' => 1001, 'msg' => '用户不存在', 'data' => []]);
        }
        $userRow = Db::table('fa_user')->where('id', $uid)->find();
        if (empty($userRow)) {
            return json(['code' => 1001, 'msg' => '用户不存在', 'data' => []]);
        }
        $username = $userRow['username'];
        if ($userRow['money'] < $use_money) {
            return json(['code' => 0, 'msg' => '用户余额不足', 'data' => []]);
        }

        // 插入设备列表
        foreach ($udids as $udid) {
            $kid = $this->getKid();
            $udidlist[]=array(
                'kid' => $kid,
                'udid' => $udid,
                'base64p12' => $base64p12,
                'base64mp' => $base64mp,
                'zt' => 'normal',
                'user' => $username,
                'pname' => $pname,
                'serialNumber'=>$serialNumber,
                'tjtime' => time(),
                'dqtime' => $expiryDate,
                'deviceid'=>$kid,
                'chi' => 1,
                'shtype' => 0,
                'type' => 2,
            );
        }

        //批量插入
        Db::table('fa_deviceslist')->insertAll($udidlist);
        $res = Db::table('fa_user')->where('id',$uid)->setDec('money', $use_money);
        if($res != 1){
            return json(['code' => 0, 'msg' => '扣除余额失败', 'data' => []]);
        }
        // 添加用户金额变动
        Db::table('fa_user_money_log')->insert(array('user_id'=>$uid,'money'=>0-$use_money,'before'=>$userRow['money'],'after'=>$userRow['money']-$use_money,'memo'=>'自定义上传证书','createtime'=>time()));
        return json(['code' => 1, 'msg' => '添加设备成功', 'data' => []]);
    }

    public function customadddevice(){
        // 接收参数
        $p12Path= $this->request->post('p12path');
        $mpPath= $this->request->post('mppath');
        $p12pw= $this->request->post('pwd');

        if (empty($p12Path) || empty($mpPath)) {
            return json(['code' => 1001, 'msg' => '请上传证书']);
        }
        // 判断密码是否为1，不为1 去修改
        if ($p12pw != '1') {
            // 修改证书密码
            if (!($p12Path = $this->changeP12Password($p12Path,$p12pw))) {
                return json(['code' => 1001, 'msg' => '修改证书密码失败']);
            }
        }
        // 读取并编码 p12 文件
        if (!($base64P12 = $this->encodeFile($p12Path))) {
            return json(['code' => 1001, 'msg' => '无法读取证书文件']);
        }
        // 读取并编码 mp 文件
        if (!($base64Mp = $this->encodeFile($mpPath))) {
            return json(['code' => 1001, 'msg' => '无法读取 mobileprovision 文件']);
        }
        $res = new \tools\Checker($p12Path,$p12pw,$mpPath);
        $data = $res->contents();
        $data= json_decode($data,true);
        if($data['code']==1){
            $pname=$data['CertificationName'];
            $expiryDate=$data['LocalEndDate'];
            $ProvisionUUID =$data['ProvisionUUID'];
            $serial=$data['Serial'];
            if($data['same'] == false){
                return json(['code' => 1001, 'msg' => 'P12和描述文件不匹配！','pname'=>$pname,'expiryDate'=>$expiryDate,'serial'=>$serial,'ProvisionUUID'=>$ProvisionUUID]);
            }
            $checkp12 = new  Checkp12();
            $state= $checkp12->loadp12($base64P12);
            $state=json_decode($state->getContent(),true);
            if($state['state']==false){
                if($state['getRevocationReason']==4){
                    $zt = '封号';
                }else{
                    $zt = '撤销';
                }
            }else{
                $zt = '正常';
            }
        }else{
            return json(['code' => 1001, 'msg' => '无法读取 P12 文件']);
        }

        // 读取mp文件中的udid
        $udids = $this->extractUDIDFromMobileProvision($mpPath);
        if (empty($udids)) {
            return json(['code' => 1001, 'msg' => '企业证书，无法添加！']);
        }
        $udiddata=array(
            'base64p12' => $base64P12,
            'base64mp' => $base64Mp,
            'pname' => $pname,
            'serialNumber'=>$serial,
            'dqtime' => $expiryDate,
            'udid' => $udids,
            'zt'=>$zt
        );
        return json(['code' => 1, 'msg' => 'ok', 'data' => $udiddata]);
    }

    private function encodeFile($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return false;
        }
        return base64_encode($fileContent);
    }
}
