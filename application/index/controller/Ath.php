<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Exception;
use think\Db;
/**
 * 充值
 */
class Ath extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['epay'];
    protected $noNeedRight = ['*'];



    /**
     * 贴牌授权
     * @return string
     */
    public function ath()
    {
        $id=$_GET['id'];
        $userid=$this->auth->id;
        $chkis = \think\Db::table("fa_website")->where("user_id", $userid)->where("id", $id)->order("id desc")->select();
        if (!$chkis) {
			$domain='暂无站点';
			$endtime ='15000000';
		}else{
		    if($chkis[0]['bidswitch']==0){
		        $this->error("该站点暂未开通贴牌权限！");
		    }
		$aid =  $chkis[0]['id'];
		$appid =  $chkis[0]['bidname'];
		$appname =  $chkis[0]['appname'];
		$appstore =  $chkis[0]['appstore'];
		$appstoretype =  $chkis[0]['inappstoreswitch'];
		$importswitch =  $chkis[0]['importswitch'];
		$exportswitch =  $chkis[0]['exportswitch'];
        $plistswitch =  $chkis[0]['plistswitch'];
        $checkudidswitch =  $chkis[0]['checkudidswitch'];
		$timelock =  $chkis[0]['timelockswitch'];
		$buy =  $chkis[0]['buy'];
		$contact =  $chkis[0]['contact'];
		$qq =  $chkis[0]['qq'];
		$jc =  $chkis[0]['jc'];

		}
		
		$this->view->assign('id', $aid);
		$this->view->assign('appid', $appid);
		$this->view->assign('appname', $appname);
		$this->view->assign('appstore', $appstore);
		$this->view->assign('appstoretype', $appstoretype);
		$this->view->assign('importswitch', $importswitch);
		$this->view->assign('exportswitch', $exportswitch);
		$this->view->assign('plistswitch', $plistswitch);
		$this->view->assign('checkudidswitch', $checkudidswitch);
		$this->view->assign('timelock', $timelock);
		$this->view->assign('buy', $buy);
		$this->view->assign('contact', $contact);
		$this->view->assign('qq', $qq);
		$this->view->assign('jc', $jc);
        $this->view->assign('title', '贴牌信息');
        return $this->view->fetch();
    }

    public function add()
    {
        $userid=$this->auth->id;
        $this->view->assign('title', __('Ath'));
        return $this->view->fetch();
    }
    /**
     * 创建请求
     */
    public function addset()
    {
        $userid=$this->auth->id;
        $siteurl = $this->request->post('siteurl');
        
        $pattern = '/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i';
    
        if (preg_match($pattern, $siteurl)) {
        } else {
         $this->error("域名不正确！");
        }
        $chkis = \think\Db::table("fa_website")->where("user_id", $userid)->order("id desc")->select();
        if ($chkis) {
			 $this->error("一个用户只能申请一个贴牌");
		}
         $chkis = \think\Db::table("fa_website")->where("siteurl", $siteurl)->order("id desc")->select();
        if ($chkis) {
			 $this->error("已存在授权域名！");
		}
		\think\Db::table("fa_website")->insert(["siteurl" => $siteurl, "user_id" => $userid, "switch" => 1, "createtime" => time(), "updatetime" => time(), "endtime" => time()]);
        $this->success("添加成功!", url("/index/user/website"));
        //return $response;
    }
    /**
     * 修改请求
     */
    public function set()
    {
        $userid=$this->auth->id;
        $id = $this->request->post('id');
        $appid = $this->request->post('appid');
        $appname = $this->request->post('appname');
        $appstore = $this->request->post('appstore');
        $appstoretype = $this->request->post('appstoretype');
        $importswitch = $this->request->post('importswitch');
        $exportswitch = $this->request->post('exportswitch');
        $plistswitch = $this->request->post('plistswitch');
        $checkudidswitch = $this->request->post('checkudidswitch');
        $timelock = $this->request->post('timelock');
        $buy = $this->request->post('buy');
        $contact = $this->request->post('contact');
        $qq = $this->request->post('qq');
        $jc = $this->request->post('jc');
        $chkis = \think\Db::table("fa_website")->where("user_id", $userid)->where("id", $id)->order("id desc")->select();
        if (!$chkis) {
			 $this->error("暂无授权站点贴牌");
		}else{
		$id=    $chkis[0]['id'];
		}
		if($appid){
        Db::table('fa_website')->where('id', $id)->update(array('bidname' => $appid,'appname' => $appname,'appstore' => $appstore,'inappstoreswitch' => $appstoretype,'buy' => $buy,'contact' => $contact,'qq' => $qq,'jc' => $jc,'timelockswitch' => $timelock,'exportswitch' => $exportswitch,'importswitch' => $importswitch,'plistswitch' => $plistswitch,'checkudidswitch' => $checkudidswitch));
		}else{
		 Db::table('fa_website')->where('id', $id)->update(array('appname' => $appname,'appstore' => $appstore,'inappstoreswitch' => $appstoretype,'buy' => $buy,'contact' => $contact,'qq' => $qq,'jc' => $jc,'timelockswitch' => $timelock,'importswitch' => $importswitch,'exportswitch' => $exportswitch,'plistswitch' => $plistswitch,'checkudidswitch' => $checkudidswitch));   
		}
        $this->success("修改成功!", url("/index/user/website"));
        //return $response;
    }

    /**
     * 企业支付通知和回调
     */
    public function epay()
    {
        $type = $this->request->param('type');
        $paytype = $this->request->param('paytype');
        if ($type == 'notify') {
            $pay = \addons\epay\library\Service::checkNotify($paytype);
            if (!$pay) {
                echo '签名错误';
                return;
            }
            $data = $pay->verify();
            try {
                $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
                \addons\recharge\library\Order::settle($data['out_trade_no'], $payamount);
            } catch (Exception $e) {
            }
            return $pay->success()->send();
        } else {
            $pay = \addons\epay\library\Service::checkReturn($paytype);
            if (!$pay) {
                $this->error('签名错误');
            }
            //微信支付没有返回链接
            if ($pay === true) {
                $this->success("请返回网站查看支付状态!", "");
            }

            //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
            $this->success("恭喜你！充值成功!", url("user/index"));
        }
        return;
    }
}
