<?php


require_once($_SERVER['DOCUMENT_ROOT']."/db.config.php");

$dmf_public_key =Db::table('fa_config')-> where('name','dmf_public_key')->find();
$public_key= $dmf_public_key['value'];

$dmf_appid =Db::table('fa_config')-> where('name','dmf_appid')->find();
$appid= $dmf_appid['value'];

$dmf_private_key =Db::table('fa_config')-> where('name','dmf_private_key')->find();
$private_key= $dmf_private_key['value'];

$pay_config = array(
	//站点标题
	'title'=>"自助下单",
	
	//站点描述
	'describe'=>"自助下单",
	
	//站点关键词
	'keywords'=>"自助下单",
	
	//站长QQ
	'qq'=>"",
	
	//站长昵称
	'name'=>"自助下单",
	
	//站长签名
	'qqinfo'=>"心中有理想，脚下的路再远，也永远不会迷失方向。",

	//签名方式,默认为RSA2(RSA2048)
	'signType' =>"RSA2",

	//支付宝公钥
	'public_key' =>$public_key,
	
	//商户私钥
	'private_key' =>$private_key,
	
	//应用ID
	'appid' =>$appid //https://open.alipay.com 账户中心->密钥管理->开放平台密钥，填写添加了电脑网站支付的应用的APPID

	
);