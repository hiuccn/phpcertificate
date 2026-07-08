<?php

require_once 'service.class.php';
require_once 'query.class.php';
require_once 'config.php';
$domain = RealUrl();
if($_REQUEST['action'] == "serve"){   
    $json = Query($pay_config['appid'],$pay_config['private_key'],$_POST['gid']);
    echo json_encode($json,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}

//发起订单
function Service($appid,$private,$payAmount,$orderDes,$OrderID,$notify=''){
	$aliPay = new AlipayService();
	$aliPay->setAppid($appid);
	$aliPay->setNotifyUrl($notify);
	$aliPay->setRsaPrivateKey($private);
	$aliPay->setTotalFee($payAmount);
	$aliPay->setOutTradeNo($OrderID);
	$aliPay->setOrderName($orderDes);
	$result = $aliPay->doPay();
	$value = $result['alipay_trade_precreate_response'];
	return $value;
}
//查询订单状态
function Query($appid,$private,$outTradeNo){
    

	$aliPay = new AlipayQuery();
	$aliPay->setAppid($appid);
	$aliPay->setRsaPrivateKey($private);
	$aliPay->setOutTradeNo($outTradeNo);
	$result = $aliPay->doQuery();
	$data = $result['alipay_trade_query_response'];		
	if($data['trade_status'] == 'TRADE_SUCCESS'){

		$res=Db::table('fa_paylist')-> where('oid',$outTradeNo)->find();
        if($res){
            if($res['zt']==0){
                
                $uid=$res['uid'];
                $fee=$res['fee'];
                $res=Db::table('fa_user')-> where('id',$uid)->find();
                $now=$res['money'];
                $time = time();
                $ye=$now+$fee;
                $username=$res['username'];
                Db::table('fa_paylist')->where('oid',$outTradeNo)->update(['username'=>$username,'zt'=>1,'fktime'=>time()]);
                Db::table('fa_user')->where('id',$uid)->update(['money'=>$ye]);
              	Db::table('fa_user_money_log')->add(['user_id'=>$uid,'money'=>$fee,'before'=>$now,'after'=>$ye,'memo'=>'在线充值金额','createtime'=>$time]);
        
                $value = array('code'=>200,'msg'=>'支付成功!');
               
               
               
            }else{
                $value = array('code'=>201,'msg'=>'已处理的订单');
            }
        
    }
		
	}else{
		$value = array('code'=>202,'msg'=>'未知状态');
	}
	return $value;
}
//获取当前域名
function RealUrl(){
	static $real_url = NULL;   
	if ($real_url !== NULL){return $real_url;}
	$real_url  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
	$real_url .= $_SERVER["SERVER_NAME"];
	$real_url .= in_array($_SERVER['SERVER_PORT'], array(80, 443)) ? '/' : ':' . $_SERVER['SERVER_PORT'];
	return $real_url;
}
