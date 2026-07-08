<?php

namespace app\api\controller;

use think\Request;
use think\Db;
use app\common\controller\Api;

// require_once "../vendor/appstore-connect-api/vendor/autoload.php";

use MingYuanYun\AppStore\Client;

class Signtool extends Api
{
    protected $noNeedLogin = ["*"];
    protected $noNeedRight = ["*"];

    public function index()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $bundleId = $data['app_bundle_id'];
            $signTool = Db::table('fa_signtool')->where('app_bundle_id', $bundleId)->find();
            if ($signTool) {
                $result = [
                    "domain"        => "cert.vxinc.cn",
                    "app_bundle_id" => $bundleId,
                    "config"        => [
                        "app_icon_url"            => $signTool["app_icon_url"],
                        "app_name"                => $signTool["app_name"] ? $signTool["app_name"] : "速云签",
                        "buy_url"                 => $signTool["buy_url"] ? $signTool["buy_url"] : "https://www.baidu.com",
                        "contact_url"             => $signTool["contact_url"] ? $signTool["contact_url"] : "https://www.baidu.com",
                        "default_app_source_urls" => $signTool["default_app_source_urls"],
                        //                        "app_category_default" => [],
                        //                        "cert_search_url" => "",
                        //                        "cert_content_url" => "",
                        //                        "voucher_consume_url" => "",
                        "exchange"                => $signTool["exchange"] == 1 ? true : false,
                        "group"                   => $signTool["group"] ? $signTool["group"] : "https://www.baidu.com",
                        "import_permissions"      => $signTool["import_permissions"] == 1 ? true : false,
                        "notice"                  => $signTool["notice"],
                        "whitelist"               => $signTool["whitelist"] == 1 ? true : false,
                        "tutorial_url"            => $signTool["tutorial_url"] ? $signTool["tutorial_url"] : "https://www.baidu.com",
                        "app_source_urls_qx"      => true,
                        //                        "online_cert"             => false,
                    ],
                ];
                return $this->success('success', $result);
            }
            return $this->error('error');
        }
    }
}