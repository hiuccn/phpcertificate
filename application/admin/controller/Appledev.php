<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

require_once "../vendor/appstore-connect-api/vendor/autoload.php";
require_once '../vendor/autoload.php';
use MingYuanYun\AppStore\Client;
use Curl\Curl;


/**
 *
 *
 * @icon fa fa-circle-o
 */
class Appledev extends Backend
{

    /**
     * Kami模型对象
     * @var \app\admin\model\Kami
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Appledev;

    }


    public function addbundleIdCapabilities() {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");
        $appleAccounts = Db::table("fa_appleidlist")
            ->where("zt", 1)    // 状态 1
            ->order("weigh asc")->select();

        foreach ($appleAccounts as $appleAccount) {
            $secret = $_SERVER["DOCUMENT_ROOT"] . $appleAccount["p8"];
            if (!file_exists($secret)) {
                $secret = $_SERVER["DOCUMENT_ROOT"] . "/uploads/" . $appleAccount["p8"];
            }

            $config = [
                'iss' => $appleAccount['iss'],
                'kid' => $appleAccount['kid'],
                'secret' => $secret,
            ];
            $client = new Client($config);
            $token = $client->getToken();
            $headers = [
                'Authorization' => 'Bearer ' . $token,
            ];
            $client->setHeaders($headers);
            $bundleId = $appleAccount['bid'];
            echo '开始修改' . $appleAccount["devname"] . "bubdle: " . $bundleId . "的权限\n";
            $client->api("bundleIdCapabilities")->enable($bundleId, "PUSH_NOTIFICATIONS"); // 启用推送通知✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "SIRIKIT"); // 启用SiriKit✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "NETWORK_EXTENSIONS"); // 启用网络扩展✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "CLASSKIT"); // 启用ClassKit✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "PERSONAL_VPN"); // 启用个人VPN✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "HEALTHKIT"); // 启用HealthKit✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "GAME_CENTER"); // 启用GameCenter✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "WALLET"); // 启用WALLET✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "INTER_APP_AUDIO"); // 启用InterAppAudio✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "ASSOCIATED_DOMAINS"); // 启用ASSOCIATED_DOMAINS✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "APP_GROUPS"); // 启用APP_GROUPS✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "HOMEKIT"); // 启用HOMEKIT✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "WIRELESS_ACCESSORY_CONFIGURATION"); // 启用WIRELESS_ACCESSORY_CONFIGURATION✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "APPLE_PAY"); // 启用APPLE_PAY✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "MULTIPATH"); // 启用MULTIPATH✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "NFC_TAG_READING"); // 启用NFC_TAG_READING✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "AUTOFILL_CREDENTIAL_PROVIDER"); // 启用AUTOFILL_CREDENTIAL_PROVIDER✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "ACCESS_WIFI_INFORMATION"); // 启用ACCESS_WIFI_INFORMATION✅
            $client->api("bundleIdCapabilities")->enable($bundleId, "COREMEDIA_HLS_LOW_LATENCY"); // 启用COREMEDIA_HLS_LOW_LATENCY✅
        }
        echo "全部修改完成";
    }
    public function add()
    {
        if ($this->request->isPost()) {

            $params = $this->request->post("row/a");
            if ($params) {
                $iss = trim($params['iss']);
                $kid = trim($params['kid']);
                $p8 = trim($params['p8']);
                $secret = $_SERVER['DOCUMENT_ROOT'] . $p8;
                $beizhu = trim($params['beizhu']);
                $config = [
                    'iss' => $iss,
                    'kid' => $kid,
                    'secret' => $secret
                ];
                $client = new Client($config);
                // get jwt auth token, expired after 20 minutes later
                $token = $client->getToken();
                // set request auth header
                $headers = [
                    'Authorization' => 'Bearer ' . $token,
                ];
                $client->setHeaders($headers);
//                $api_url=file_get_contents(BASE_URI.'api_url.txt');
//                $url = $api_url.'/api/whitelist/new_fz';
//                $post_data['site'] = $_SERVER['HTTP_HOST'];
//                $post_data['time'] = time();
//                $post_data['type'] = 'add';
//                $post_data['iss'] = $iss;
//                $post_data['kid'] = $kid;
//                $post_data['secret'] = file_get_contents($secret);
//                $res = $this->request_post($url, $post_data);
//                $whitelist=json_decode( _decrypt($res,$post_data['time']),true);
//                if($whitelist["code"]!=1)$this->error($whitelist["msg"]);
                $cid = $_COOKIE['cid'] ?? '';
                $bid = $_COOKIE['bid'] ?? '';
                // 添加证书
                if ($cid == '') {
                    $result = $client->api('certificates')->create();
                    $zt = $result['errors'] ?? 0;
                    if ($zt != 0) $this->error(__('certificates添加失败：' . $result['errors'][0]['detail'], ''));
                    $cid = $result['data']['id'];
                    setcookie('cid', $cid, time() + 300);
                }
                // 添加bundleId
                if ($bid == '') {
                    $name = 'neicexiahh' . rand(10, 10000);
                    $platform = 'IOS';
                    $bundleId = 'com.' . $name . '.sign' . rand(10, 10000);;
                    $result = $client->api('bundleId')->register($name, $platform, $bundleId);
                    $zt = $result['errors'] ?? 0;
                    if ($zt != 0) $this->error(__('bundleId添加失败：' . $result['errors'][0]['detail'], ''));
                    $bid = $result['data']['id'];
                    setcookie('bid', $bid, time() + 300);
                }
                $client->api("bundleIdCapabilities")->enable($bid, "PUSH_NOTIFICATIONS"); // 启用推送通知
                $client->api("bundleIdCapabilities")->enable($bid, "SIRIKIT"); // 启用SiriKit
                $client->api("bundleIdCapabilities")->enable($bid, "NETWORK_EXTENSIONS"); // 启用网络扩展
                $client->api("bundleIdCapabilities")->enable($bid, "CLASSKIT"); // 启用ClassKit
                $client->api("bundleIdCapabilities")->enable($bid, "PERSONAL_VPN"); // 启用个人VPN
                $client->api("bundleIdCapabilities")->enable($bid, "HEALTHKIT"); // 启用HealthKit
                $client->api("bundleIdCapabilities")->enable($bid, "GAME_CENTER"); // 启用GameCenter
                $client->api("bundleIdCapabilities")->enable($bid, "WALLET"); // 启用WALLET
                $client->api("bundleIdCapabilities")->enable($bid, "INTER_APP_AUDIO"); // 启用InterAppAudio
                $client->api("bundleIdCapabilities")->enable($bid, "ASSOCIATED_DOMAINS"); // 启用ASSOCIATED_DOMAINS
                $client->api("bundleIdCapabilities")->enable($bid, "APP_GROUPS"); // 启用APP_GROUPS
                $client->api("bundleIdCapabilities")->enable($bid, "HOMEKIT"); // 启用HOMEKIT
                $client->api("bundleIdCapabilities")->enable($bid, "WIRELESS_ACCESSORY_CONFIGURATION"); // 启用WIRELESS_ACCESSORY_CONFIGURATION
                $client->api("bundleIdCapabilities")->enable($bid, "APPLE_PAY"); // 启用APPLE_PAY
                $client->api("bundleIdCapabilities")->enable($bid, "MULTIPATH"); // 启用MULTIPATH
                $client->api("bundleIdCapabilities")->enable($bid, "NFC_TAG_READING"); // 启用NFC_TAG_READING
                $client->api("bundleIdCapabilities")->enable($bid, "AUTOFILL_CREDENTIAL_PROVIDER"); // 启用AUTOFILL_CREDENTIAL_PROVIDER
                $client->api("bundleIdCapabilities")->enable($bid, "ACCESS_WIFI_INFORMATION"); // 启用ACCESS_WIFI_INFORMATION
                $client->api("bundleIdCapabilities")->enable($bid, "COREMEDIA_HLS_LOW_LATENCY"); // 启用COREMEDIA_HLS_LOW_LATENCY

                $params = [
                    'filter[id]' => $cid,
                    'fields[certificates]' => 'certificateContent,expirationDate,displayName'
                ];
                $result = $client->api('certificates')->all($params);
                $id = rand(1, 100);
                if (!file_exists($id)) {
                    mkdir($id);
                }
                $cerpath = $_SERVER['DOCUMENT_ROOT'] . "/" . $id . "/ios_development.cer";
                $path = $_SERVER['DOCUMENT_ROOT'] . "/";
                $xmlFile = fopen($cerpath, "w") or die("Unable to open file!");
                $dqtime = $result['data'][0]['attributes']['expirationDate'];
                $devname = $result['data'][0]['attributes']['displayName'];
                date_default_timezone_set("UTC");
                $timestamp_in_gmt = strtotime($dqtime);
                date_default_timezone_set('Asia/shanghai');
                $dqtime = date("Y-m-d H:i:s", $timestamp_in_gmt);
                fwrite($xmlFile, base64_decode($result['data'][0]['attributes']['certificateContent']));
                fclose($xmlFile);
                $keystr = "-----BEGIN RSA PRIVATE KEY-----\nMIIEpAIBAAKCAQEAyUkAE+QAHweDCJeJR+2zJn7CoSqa21yW4k97K3KHBb9PWMmk\n3A7pBbH2V5vvbpiKkFdqD05tiLY39gdzb+kP3k9DtKDusouaFfvQprdaH6xuR1i5\nEreFesoiU8ntVqdPtL2zBgHX0/RDLQ0I/PM/QjB0eu3NZJRtBA8kYfsOIvu03ChO\nSVb/cgUcm06jpgqETYET5ngB05yayIzyK2vkeWmeqZdfxAKLU5D/Sok19JaowNus\nUk2cErg0zWjUS1VBPlgoyAaAIDe0X58Xv6KtIF+PmMdYuSpSWagkmk3WaU8iGnoN\nRPjF6IFRk9z09qQbrwI6QkLvzgP4xMCJ9L8C8QIDAQABAoIBABkoPZE+2uEF8FOf\nlPHffJegGjVIfOhTzyvj4TIR81w9h+5B7Y/vcSJcFrzmaWt6Nz9JHaFiHQCMPbxL\nPBtNlsUjRQQLZSn9lrmOqopbujDhPTs/lIoJU+5/2wB76WT+LlEZsIlcq5v7GHZF\n/cyXnl1obvZ6SER85I8wUUzJsv+eGz9e/iCw0bsDYadpks7wABwdPSC71QgwN91D\n/UlYTlGQpT5HLfl8YHua1hLSS9C0TQxUY6dKsvCHbd9MD4RqAovgqtuisORdui2f\nIXU/pnBYvoSotNdknjFWMw1Bo3kTi9z6pilRbXiT7FnzuYLfrf6+6fN8Of+Bxd4K\nULwYmxECgYEA6HbBqqWxWCUR2qwLE3Uou8YrHTuRpHy66ugn8k9+Y2kxijTRR/F+\nU9tUBF5N75vdkJj2SZ10HHJLWklcTobMLyc6P5wJIHxbjwekvcO4u0BstzJUJAXs\nL+HHb372dS2O926MQSZ4pYzc/Fw3vzE9DsxVM/xJcpB/EgshQijKGeUCgYEA3aoe\n3Wf8NqLFCyczfew5KuF9/ftZMs1jY4IjOWMM/L0dxSroZt+p0OUt1QXJHa9y3/EY\nLG20dJ5B1fYYn6GLwn2HZpV8R2KlJ0U/S4BtOHouM1YQ5Z/526YHAzIikb+MIYdw\njxTbHON9bJFfZ+IubCo//UqANZVpet+/ApaAhB0CgYEAxcQ6kP4zuSSYYuvY5G3Z\nAJ7gERebmU+QCccGLQxaHyLgRY8XuNgHvDms6aZ9MWrt/VVUul4c6RKHbsFYqWne\njgMWeAU8com5rx42lkbLg2qU0uobUSZEwJuZew6NiDUBGxnOcqLTIyyK2JtvxdWS\n92L43ag1qCSsJmKXodxny80CgYAfKTQvkdet4pHqsHcXo6ahtZNdqgDvGFp5eajz\n/02rFfbiadbD53ta52za/nY4Wxq+ComIbV+p6Tl+F5t8jVw1Wio3rJoM+vwWmjB8\nr7Aq+VoXU2kKrsOUMjHYLCsZ7CCJ8h1Lr/XhiMVwBruvwecew429UMTXQ4rRgDS8\n62VjrQKBgQDBh78KM4ws6Ka1FQTlAYadrP8vJZjtfrPMBEfepSOERExMqNZ/TYaP\n4jlVe2gIA99zQgG+oFM+dtSEXdxYo6wTSPOnCwXsb0EbnkwOKO8GGos7104oxzrI\n+SMuL+Bc/+hudh1cQ/vvHqFsAmoLqsaLqNa0moYm5t8TVzyz2TekfQ==\n-----END RSA PRIVATE KEY-----"; // 私钥
                $keypath = $_SERVER['DOCUMENT_ROOT'] . "/" . $id . "/ios.key";
                $xmlFile = fopen($keypath, "w") or die("Unable to open file!");
                fwrite($xmlFile, $keystr);
                fclose($xmlFile);
                shell_exec('openssl x509 -in ' . $cerpath . ' -inform DER -outform PEM -out ' . $path . $id . '/ios_development.pem');
                shell_exec('openssl pkcs12 -export -inkey ' . $path . $id . '/ios.key -in ' . $path . $id . '/ios_development.pem -out ' . $path . $id . '/ios_development.p12 -passout pass:1');
                $base64p12 = base64_encode(file_get_contents($id . '/ios_development.p12'));
                $devnames = $devname;
                while (Db::table('fa_deviceslist')->field('base64mp,base64p12', true)->where('pname', $devnames)->order('id desc')->select()) {
                    $a = $this->getsj();
                    $devnames = $devname . $a;
                }
                setcookie('bid', '', time() + 10);
                setcookie('cid', '', time() + 10);
                $insertedId = Db::table('fa_appleidlist')->insertGetId(array('iss' => $iss, 'kid' => $kid, 'p8' => $p8, 'devname' => $devnames, 'cid' => $cid, 'bid' => $bid, 'beizhu' => $beizhu, 'p12' => $base64p12, 'dqtime' => $dqtime));

                // 刷新余量
                $url1 = '/api/getdown?yl=' . $insertedId;
                $this->request_get($url1);

                // 刷新权限
                $url2 = '/api/bundle/getBundleIDCapabilities';
                $post_data2['id'] = $insertedId;
                $this->request_get($url2, $post_data2);

                shell_exec('rm -rf ' . $path . $id);
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }


        return parent::add();
    }


    function getsj($length = 1)
    {
        // 暗码字符集,可任意添加你需要的字符 
        $chars = 'sign';
        $password = '';
        for ($i = 0; $i < $length; $i++) {

            $password = $password . $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password;
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


    function downmp()
    {
        $id = $this->request->post('id');
        $macqx = 1;
        $row = Db::table('fa_appleidlist')->where('id', $id)->order('id desc')->select();
        $iss = $row[0]['iss'];
        $kid = $row[0]['kid'];
        $p8 = $row[0]['p8'];
        $cid = $row[0]['cid'];
        $bid = $row[0]['bid'];
        $appleid = [];
        $devname = $row[0]['devname'];
        $secret = $p8;
        $config = [
            'iss' => $iss,
            'kid' => $kid,
            'secret' => $_SERVER['DOCUMENT_ROOT'] . $secret
        ];
        $client = new Client($config);
        $token = $client->getToken();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];
        $client->setHeaders($headers);
        $params = [
            'filter[platform]' => 'IOS',
            'fields[devices]' => 'udid,status,deviceClass',
            'limit' => 200
        ];
        $datas = $client->api('device')->all($params);
        $zt = isset($datas['errors']) ? $datas['errors'] : 0;
        if ($zt != 0) {
            return json(['code' => 1001, 'msg' => $datas['errors'][0]['detail'], 'data' => []]);
        }
        foreach ($datas as $data) {
            foreach ($data as $device) {
                if (!empty($device['attributes']['udid'])) {
                    $mpstatus = $device['attributes']['status'];
                    $deviceid = $device['id'];
                    if ($mpstatus == 'ENABLED' && $device['attributes']['deviceClass'] != 'APPLE_TV') {
                        $appleid[] = $deviceid;
                    }
                }
            }
        }
        if ($macqx == 1) {
            $params = [
                'filter[platform]' => 'MAC_OS',
                'fields[devices]' => 'udid,status,deviceClass',
                'limit' => 200
            ];
            $datas1 = $client->api('device')->all($params);
            foreach ($datas1 as $data) {
                foreach ($data as $device) {
                    if (!empty($device['attributes']['udid'])) {
                        $mpstatus = $device['attributes']['status'];
                        $deviceid = $device['id'];
                        if ($mpstatus == 'ENABLED') {
                            $appleid[] = $deviceid;
                        }
                    }
                }
            }
        }
        if ($appleid != []) {
            $profileType = 'IOS_APP_ADHOC';
            $profilesresult = $client->api('profiles')->create('all' . rand(1, 999), $bid, $profileType, $appleid, [$cid]);
            $zt = isset($profilesresult['errors']) ? $profilesresult['errors'] : 0;
            if ($zt != 0) {
                return json(['code' => 1001, 'msg' => '描述文件创建失败', 'data' => []]);
            }
            $base64mp = $profilesresult['data']['attributes']['profileContent'];
            $path = 'temp';
            if (!file_exists($path)) {
                mkdir($path);
            }
            $locale = 'zh_CN.UTF-8';
            setlocale(LC_ALL, $locale);
            putenv('LC_ALL=' . $locale);
            file_put_contents($path . "/" . $devname . ".mobileprovision", base64_decode($base64mp));
            return json(['code' => 1, 'url' => "/" . $path . "/" . $devname . ".mobileprovision", 'data' => []]);
        } else {
            return json(['code' => 1001, 'msg' => '此账号无可用UDID创建描述文件', 'data' => []]);
        }
    }

    function request_get($url, $header = array())
    {
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

    public function request_post($url = '', $post_data = array())
    {
        if (empty($url) || empty($post_data)) {
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
        //解压
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Encoding: gzip, deflate,flate'));
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

}
