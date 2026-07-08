<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use CFPropertyList\CFPropertyList;
use think\cache\driver\Redis;
use think\Db;
use think\Log;

use app\common\controller\Api;

require_once '../vendor/tencentcos/vendor/autoload.php';
require_once '../vendor/CFPropertyList/vendor/autoload.php';

use Qcloud\Cos\Client;

/**
 * 首页接口qs
 */
class Ipasign extends Frontend
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    /**
     * 首页
     *
     */
    public function index()
    {
        if ($this->request->isPost()) {
            $code = $this->request->post('code');
            $appid = $this->request->post('appid');
            $udid = $this->request->post('udid');
            if (!(strlen($udid) == 25 || strlen($udid) == 40)) return json(['code' => 1001, 'msg' => 'UDID格式错误', 'data' => []]);
            $isSigntool = false;
            if (empty($code)) {
                // TODO 直接用udid查出来的设备判断用户, 不正确
                $deviceRows = Db::table('fa_deviceslist')->where('udid', $udid)->where('zt', 'normal')->order('id asc')->select();
                if (!$deviceRows) {
                    
                    return json(['code' => 1001, 'msg' => "该UDID暂无可用证书<br>请填写卡密", 'data' => []]);
                }
                $username = $deviceRows[0]["user"];
                $userRows = Db::table('fa_user')->where('username', $username)->order('id desc')->select();
                $apiToken = $userRows[0]['ktoken'];
                $base64p12 = $deviceRows[0]['base64p12'];
                $base64mp = $deviceRows[0]['base64mp'];
                if ($base64mp == '' || !isset($base64mp)) {
                    // 查询一下看描述文件出了没
                    $url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/Getcertificate';
                    $post_data1['id'] = $deviceRows[0]['kid'];
                    $post_data1['token'] = $apiToken;
                    $res = $this->request_post($url, $post_data1);
                    $certificate = json_decode($res, true);
                    if ($certificate["code"] != 1) {
                        
                        return json(['code' => 1001, 'msg' => $certificate["msg"], 'data' => []]);
                    }
                    $base64mp = $certificate['data']['mobileprovision'];
                }
            } else {
                $kami = Db::table('fa_kami')->where('kami', $code)->order('id desc')->select();
                if (!$kami) {
                    
                    return json(['code' => 1001, 'msg' => "卡密不存在！！", 'data' => []]);
                }
                $userRows = Db::table('fa_user')->where('id', $kami[0]['uid'])->order('id desc')->select();
                $apiToken = $userRows[0]['ktoken'];
                $username = $userRows[0]["username"];
                if ($kami[0]['open'] != 1) {
                    
                    return json(['code' => 1001, 'msg' => "此卡密已被停用！！！", 'data' => []]);
                }
                if ($kami[0]['udid'] != '') {
                    if ($kami[0]['udid'] != $udid) {
                        
                        return json(['code' => 1001, 'msg' => "此卡密已被使用！！！", 'data' => []]);
                    }
                    $url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/Getcertificate';
                    $post_data1['id'] = $kami[0]['zsid'];
                    $post_data1['token'] = $apiToken;
                    $res = $this->request_post($url, $post_data1);
                    $certificate = json_decode($res, true);
                    if ($certificate["code"] != 1) {
                        
                        return json(['code' => 1001, 'msg' => $certificate["msg"], 'data' => []]);
                    }
                    $base64mp = $certificate['data']['mobileprovision'];
                    $base64p12 = $certificate['data']['p12'];
                } else {
                    // 有udid
                    if ($kami[0]['type'] == 0) {
                        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/adddevice';
                    } else {
                        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/addyydevice';
                    }
                    if ($kami[0]['beizhu'] == '无备注') {
                        $post_data['beizhu'] = $code;
                    } else {
                        $post_data['beizhu'] = $kami[0]['beizhu'];
                    }
                    $post_data['udid'] = $udid;
                    $post_data['warranty'] = $kami[0]['shtype'];
                    $post_data['type'] = $kami[0]['pool'];
                    $post_data['deviceType'] = $kami[0]['deviceType']==1 ? "ipad" : "iphone";
                    $post_data['token'] = $apiToken;
                    $res = $this->request_post($url, $post_data);
                    $certificate = json_decode($res, true);
                    if ($certificate["code"] != 1) {
                        
                        return json(['code' => 1001, 'msg' => $certificate["msg"], 'data' => []]);
                    }

                    $zsid = $certificate['data']['id'];
                    $base64p12 = $certificate['data']['p12'];
                    $base64mp = $certificate['data']['mobileprovision'];

                    Db::table('fa_kami')->where('kami', $code)->update(array('zsid' => $zsid, 'udid' => $udid, 'jhtime' => date("Y-m-d H:i:s")));
                }
            }

            if ($appid != 0) {
                $app = Db::name('category')->where('id', $appid)->find();
                if (!$app) {
                    return json(['code' => 1001, 'msg' => "该APP不存在", 'data' => []]);
                }
                $apppath = $app['upipa'];
                $isSigntool = $app['is_signtool'] == "1" ? true : false;
                if ($isSigntool) {
                    // 贴牌工具
                    $signTool = Db::table('fa_signtool')->where('user', $username)->find();
                    if (!$signTool) {
                        
                        return json(['code' => 1001, 'msg' => "该用户未开通贴牌工具", 'data' => []]);
                    }
                    $appicon = $signTool['app_icon_url']; // 图标
                    $appname = $signTool['app_name'];     // 软件名
                    $appbid = $signTool['app_bundle_id']; // bundle id
                } else {
                    $appicon = $app['image'];
                    $appbid = $app['baoming'];
                    $appname = $app['name'];
                }
            }

            if ($base64mp == '') {
                
                return json(['code' => 1001, 'msg' => '预约尚未成功，请耐心等待', 'data' => []]);
            }
            if (!file_exists('temp')) {
                mkdir('temp');
            }
            $ttname = time();
            $dir = './temp/' . $ttname;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $locale = 'zh_CN.UTF-8';
            setlocale(LC_ALL, $locale);
            putenv('LC_ALL=' . $locale);

            // 生成P12文件
            $url1 = $dir . '/证书文件.p12';
            $file1 = fopen($url1, "a+");
            fwrite($file1, base64_decode($base64p12));
            fclose($file1);
            // 生成描述文件
            $url2 = $dir . '/描述文件.mobileprovision';
            $file2 = fopen($url2, "a+");
            fwrite($file2, base64_decode($base64mp));
            fclose($file2);

            $url3 = $dir . '/密码.txt';
            $file3 = fopen($url3, "a+");
            fwrite($file3, "密码：1");
            fclose($file3);

            $domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
            // 打包zip文件
            $fileList = [$url1, $url2, $url3];
            $zipFilepath = $dir . '/证书_' . $udid . '.zip';
            $zip = new \ZipArchive();
            $zip->open($zipFilepath, \ZipArchive::CREATE);   //打开压缩包
            foreach ($fileList as $file) {
                $zip->addFile($file, basename($file));   //向压缩包中添加文件
            }
            $zip->close();

            $zip = $domain . '/temp/' . $ttname . '/证书_' . $udid . '.zip';

            if ($appid == 0) {
                $datas = array('zip' => $zip, "url" => '');
                
                return json(['code' => 1, 'msg' => '成功', 'data' => $datas]);
            }
            file_put_contents("temp/" . $ttname . ".p12", base64_decode($base64p12));
            file_put_contents("temp/" . $ttname . ".mobileprovision", base64_decode($base64mp));
            $absolute_apppath = str_replace("application/api/controller", "public/temp/" . $ttname, dirname(__FILE__));
            // 解压$apppath到$absolute_apppath
            $tempAppPath = $absolute_apppath . '/app';
            // 解压
            exec('unzip -o ' . $apppath . ' -d ' . $tempAppPath . ' 2>&1');

            $appUnzipPath = $tempAppPath . '/Payload/HillMountPlatform.app/';

            if ($isSigntool) {
                // 修改icon, 即替换目录HillMountPlatform.app下的icon.png, icon@2x.png, icon@3x.png
                $iconPath = $appUnzipPath . 'icon.png';
                $icon2xPath = $appUnzipPath . 'icon@2x.png';
                $icon3xPath = $appUnzipPath . 'icon@3x.png';

                $icon = file_get_contents($appicon);
                file_put_contents($iconPath, $icon);
                file_put_contents($icon2xPath, $icon);
                file_put_contents($icon3xPath, $icon);

                // 修改appname, 即读取HillMountPlatform.app下的Info.plist, 并修改
                $infoPlistPath = $appUnzipPath . 'Info.plist';
                $infoPlist = file_get_contents($infoPlistPath);
                $plist = new CFPropertyList();
                $plist->parse($infoPlist);
                $data = $plist->toArray();
                foreach ($data as $key => $value) {
                    if ($key == 'CFBundleDisplayName') {
                        $data[$key] = $appname;
                    }
                }
                $arr ["UIFileSharingEnabled"] = true;
                $arr ["UISupportsDocumentBrowser"] = true;
                $data = array_merge_recursive($arr, $data);
                $options = array("CFDictionary", "CFArray", "CFString");
                $plist = CFPropertyList::guess($data, $options);
                $results = new CFPropertyList();
                $results->add($plist);
                $results->saveXML($infoPlistPath);

                // 修改bundle id, 即读取HillMountPlatform.app下的Config.plist, 并修改APPBundle这个key的值
                $configPlistPath = $appUnzipPath . 'config.plist';
                $configPlist = file_get_contents($configPlistPath);
                $plist = new CFPropertyList();
                $plist->parse($configPlist);
                $data = $plist->toArray();
                foreach ($data as $key => $value) {
                    if ($key == 'APPBundle') {
                        $data[$key] = $appbid;
                    }
                }
                $options = array("CFDictionary", "CFArray", "CFString");
                $plist = CFPropertyList::guess($data, $options);
                $results = new CFPropertyList();
                $results->add($plist);
                $results->saveXML($configPlistPath);

                // 修改启动图
                if ($signTool['app_launch_icon_url'] != '' || $signTool['app_launch_icon_url'] != null) {
                    $launch_path = $appUnzipPath . "/website/bg.png";
                    $launch = file_get_contents($signTool['app_launch_icon_url']);
                    file_put_contents($launch_path, $launch);
                }

                // 内置证书
                // 设置udid和内置证书
                $files = glob($dir . "/*.mobileprovision");
                $cert_zip_path = $zipFilepath;
                if (!empty($files) && file_exists($cert_zip_path)) {
                    $mpFile = $files[0];
                    // 读取文件内容
                    $mpContent = file_get_contents($mpFile);
                    $pattern = '/<key>UUID<\/key>\s*<string>([a-f0-9-]+)<\/string>/';
                    preg_match($pattern, $mpContent, $matches);
                    $mpUUID = $matches[1];
                    $psd = 1;
                    $encrypted_file_path = $dir . "cert.mango";
                    $this->encryptFile1($cert_zip_path, $encrypted_file_path, $udid, $psd, $mpUUID);

                    if (file_exists($encrypted_file_path)) {
                        // 移动文件到打包路径
                        $cert_encrypted_path = $appUnzipPath . "/certificate";
                        if (!is_dir($cert_encrypted_path)) {
                            // 目录不存在，创建新目录
                            if (!mkdir($cert_encrypted_path, 0777, true)) {
                                // 创建目录失败
                                Log::info("无法创建目录: " . $cert_encrypted_path);
                            } else {
                                Log::info("成功创建目录: " . $cert_encrypted_path);
                            }
                        }
                        // return json(["code" => 1000, "msg" =>$encrypted_file_path.$cert_encrypted_path]);

                        if (rename($encrypted_file_path, $cert_encrypted_path . "/cert.mango")) {
                            // return json(["code" => 1000, "msg" => "文件内置成功"]);
                            Log::info("文件内置成功");
                        } else {
                            Log::info("文件内置失败");
                        }
                    }
                }
            }

            // 重签名
            $return = shell_exec('zsign -k ' . $absolute_apppath . '.p12 -p 1 -m  ' . $absolute_apppath . '.mobileprovision -o ' . $absolute_apppath . '.ipa -b ' . $appbid . ' ' . $tempAppPath . ' -z 9 2>&1');
            if (strstr($return, 'Signed OK')) {
                // 签名完删除临时文件
                exec('rm -rf ' . $tempAppPath . '.p12 2>&1');
                $ossconfig = Db::table('fa_config')->where('name', 'ossconfig')->value('value');
                $installUrl = Db::table('fa_config')->where('name', 'azdl')->value('value');
                if ($ossconfig == 0) {
                    $downurl = "https://" . $_SERVER['HTTP_HOST'] . "/temp/" . $ttname . '.ipa';
                    $tk = $appname . ',' . $appicon . ',' . $appbid . ',' . $downurl;
                    $data = $this->_encrypt($tk, '8659471');
                    $row = $this->request_post($installUrl . '/pages/api.php', "url=" . $installUrl . "/wz.php?url=" . $installUrl . "/plist.php?" . $data);
                    $row = json_decode($row, true);
                    $datas = array('zip' => $zip, "url" => $row['url']);
                    
                    return json(['code' => 1, 'msg' => '成功', 'data' => $datas]);

                } else {

                    $tcosurl = Db::table('fa_config')->where('name', 'tcosurl')->value('value');
                    $ipaname = time() . '.ipa';
                    $downurl = $tcosurl . '/dev_sign/' . $ipaname;
                    $oss = $this->tupload($absolute_apppath . '.ipa', $ipaname);
                    if ($oss) {

                        exec('rm -rf ' . $absolute_apppath . '.ipa 2>&1');
                        exec('rm -rf ' . $absolute_apppath . '.p12 2>&1');
                        exec('rm -rf ' . $absolute_apppath . '.mobileprovision 2>&1');

                        $tk = $appname . ',' . $appicon . ',' . $appbid . ',' . $downurl;
                        $data = $this->_encrypt($tk, '8659471');
                        $row = $this->request_post($installUrl . '/pages/api.php', "url=" . $installUrl . "/wz.php?url=" . $installUrl . "/plist.php?" . $data);
                        $row = json_decode($row, true);
                        $datas = array('zip' => $zip, "url" => $row['url']);
                        
                        return json(['code' => 1, 'msg' => '成功', 'data' => $datas]);

                    } else {
                        
                        return json(['code' => 1001, 'msg' => '下载地址获取失败', 'data' => []]);
                    }
                }

            } else {
                
                return json(['code' => 1001, 'msg' => $return, 'data' => []]);
            }
        }
    }

    private function encryptFile1($file_path, $encrypted_file_path, $udid, $cert_psd, $profile_uuid)
    {
        // 填充UDID到48位
        $udid = str_pad($udid, 48, ' ');

        // 填充密码到16位
        $cert_psd = str_pad($cert_psd, 16, ' ');

        // 加密密钥和初始化向量 (IV)
        $key = 'Y2FtZW5tYW5nb21t'; // 这里需要用你的密钥替换
        $iv = openssl_random_pseudo_bytes(16); // 随机生成一个16字节的IV

        // 读取文件内容
        $file_data = file_get_contents($file_path);

        // 填充profile uuid到48位
        $profile_uuid = str_pad($profile_uuid, 48, ' ');

        $file_data = $udid . $cert_psd . $profile_uuid . $file_data;

        // 使用PKCS7填充模式对文件数据进行填充
        $block_size = 16; // AES块大小为16字节
        $padding = $block_size - (strlen($file_data) % $block_size);
        $file_data .= str_repeat(chr($padding), $padding);

        // 创建AES加密器
        $ciphered_data = openssl_encrypt($file_data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // 保存加密后的文件数据和元数据
        file_put_contents($encrypted_file_path, $iv . $ciphered_data);
    }

    public function checkStatus()
    {
        if ($this->request->isPost()) {
            $value = $this->request->post('value');
            if (!(strlen($value) == 25 || strlen($value) == 40)) {
                $kami = Db::table('fa_kami')->where('kami', $value)->order('id desc')->select();
                if (!$kami) return json(['code' => 1001, 'msg' => "卡密不存在！", 'data' => []]);
                if ($kami[0]['udid'] != '') {
                    if ($kami[0]['open'] != 1) return json(['code' => 1001, 'msg' => "此卡密已被停用！", 'data' => []]);
                    $value = $kami[0]['udid'];
                } else {
                    return json(['code' => 1001, 'msg' => "卡密未使用！", 'data' => []]);
                }
            }
            $row = Db::table('fa_deviceslist')->where('udid', $value)->field('base64p12,beizhu,chi,deviceid,dqtime,id,isrepeat,model,user,shouhou,zspt', true)->order('id desc')->select();
            if ($row) {
                $data = [];
                foreach ($row as $dev) {
                    $yymsg = '';
                    $zt = $dev['zt'];
                    $ztv = '正常';
                    $type = '秒出';
                    if ($dev['type'] == 1) {
                        $type = '预约';
                    }
                    if ($zt == 'expiration') {
                        $ztv = '过期';
                    }
                    if ($zt == 'fenghao') {
                        $ztv = '封号';
                    }
                    if ($zt == 'hidden') {
                        $ztv = '撤销';
                    }
                    if ($zt == 'normal' && $dev['base64mp'] == '') {
                        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/Checkdevice';
                        $post_data['kid'] = $dev['kid'];
                        $res = $this->request_post($url, $post_data);
                        $state = json_decode($res, true);
                        $yymsg = $state['msg'];
                    }
                    $data[] = ['id' => $dev['kid'], 'pname' => $dev['pname'], 'addtime' => date('Y-m-d H:i:s', $dev['tjtime']), 'type' => $type, 'state' => $ztv, 'udid' => $value, 'yy' => $yymsg];
                }
                return json(['code' => 1, 'msg' => "查询成功", 'data' => $data]);
            }
            return json(['code' => 2, 'msg' => '无此UDID记录']);
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
                'region'      => $region,
                'schema'      => 'http', //协议头部，默认为http
                'credentials' => array(
                    'secretId'  => $secretId,
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

    function _encrypt($data, $key, $expire = 30000)
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


    public function agent()
    {
        $site = $this->request->post('site');
        $agentsite = Db::table('fa_agentsite')
            ->alias('a') // alias for the main table
            ->join('fa_signtool s', 'a.site = s.short_site', 'LEFT') // left join with fa_signtool
            ->field([
                'a.id',
                'a.site',
                'a.user as agent_user', // renaming fa_agentsite user column
                'a.sitename',
                'a.notice',
                'a.buy_url',
                'a.background_url',
                'a.btn_color',
                'a.bottom_html',
                'a.icon_url',
                'a.time',
                'a.background_color',
                'a.domain_url',
                'a.theme',
                'a.contact_url',
                // 'a.question_url',
                // 'a.font_color',
                's.app_bundle_id',
                's.app_name',
                's.plist_url',
                's.default_app_source_urls',
                's.app_icon_url',
                's.group',
                's.tutorial_url',
                's.createtime',
                's.updatetime',
                's.exchange',
                's.import_permissions',
                's.whitelist',
                's.short_site',
                's.user as signtool_user' // renaming fa_signtool user column
            ])
            ->where('a.site', $site)
            ->find();
        if (empty($agentsite)) {
            return json(false);
        }

        $user = Db::table('fa_user')->where('username', $agentsite['agent_user'])->find();
        $signtoolApp = Db::table('fa_category')->where('is_signtool', 1)->find();
        // 判断是否有签名工具权限
        $agentsite['signtool'] = $user['signtool'];
        if (!$signtoolApp) {
            $agentsite['signtool'] = 0;
        }
        if ($agentsite['signtool'] == 1 && $agentsite['app_bundle_id']) {
            $agentsite['appid'] = $signtoolApp['id'];
        }
        $rows = isset($agentsite) ? $agentsite : false;
        return json($rows);
    }

    /*   取文本中间  */
    function getSubstr($str, $leftStr, $rightStr)
    {
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr, $left);
        //echo '<br>右边:'.$right;
        if ($left < 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));

    }

}
