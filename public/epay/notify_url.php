<?php
/* *
 * 功能：彩虹易支付异步通知页面
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */

require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");
require_once($_SERVER['DOCUMENT_ROOT']."/db.config.php");
//计算得出通知验证结果
$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyNotify();

if($verify_result) {//验证成功

	//商户订单号
	$out_trade_no = $_GET['out_trade_no'];

	//彩虹易支付交易号
	$trade_no = $_GET['trade_no'];

	//交易状态
	$trade_status = $_GET['trade_status'];

	//支付方式
	$type = $_GET['type'];

	//支付金额
	$money = $_GET['money'];

	if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
	    
	    
	    $res=Db::table('fa_paylist')-> where('oid',$out_trade_no)->find();
        if($res){
            if($res['zt']==0){
                
                $uid=$res['uid'];
                $fee=$res['fee'];
                $num=$res['num'];
                $res=Db::table('fa_user')-> where('id',$uid)->find();
                $username=$res['username'];
                $time = time();
                if($num==''){
                    
                    $now=$res['money'];
                    $ye=$now+$fee;
                    Db::table('fa_paylist')->where('oid',$out_trade_no)->update(['username'=>$username,'zt'=>1,'fktime'=>$time]);
                    Db::table('fa_user')->where('id',$uid)->update(['money'=>$ye]);
                  	Db::table('fa_user_money_log')->add(['user_id'=>$uid,'money'=>$fee,'before'=>$now,'after'=>$ye,'memo'=>'在线充值金额','createtime'=>$time]);
        
                }else{
                        
                    $tuid=$res['tuid'];
                    $now=$res['score'];
                    $ye=$now+$num;
                    Db::table('fa_paylist')->where('oid',$out_trade_no)->update(['username'=>$username,'zt'=>1,'fktime'=>$time]);
                    Db::table('fa_user')->where('id',$uid)->update(['score'=>$ye]);
                    
                    // Db::table('fa_user')->where('id',$tuid)->update(['jifen'=>`jifen`+$num]);
                    
                }
                
         
           
               
               echo "success";
               
               
            }else{
                echo "success";
            }
        
     }
 }

	//验证成功返回
	
}
else {
	//验证失败
	echo "fail";
}
?>