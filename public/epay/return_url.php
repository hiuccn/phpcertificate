<?php
/* * 
 * 功能：彩虹易支付页面跳转同步通知页面
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */

require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");
?>
<!DOCTYPE HTML>
<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>支付返回页面</title>
	</head>
	<body>
<?php
//计算得出通知验证结果
$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyReturn();

if($verify_result) {//验证成功

	//商户订单号
	$out_trade_no = $_GET['out_trade_no'];

	//支付宝交易号
	$trade_no = $_GET['trade_no'];

	//交易状态
	$trade_status = $_GET['trade_status'];

	//支付方式
	$type = $_GET['type'];


	if($_GET['trade_status'] == 'TRADE_SUCCESS') {
		
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
                
               
            }
        
     }
     
     
	}else {
		echo "trade_status=".$_GET['trade_status'];
	}
    $url="https://".$_SERVER['SERVER_NAME'];
	header("Location: $url");
}
else {
	//验证失败
	echo "<h3>验证失败</h3>";
}
?>
	</body>
</html>