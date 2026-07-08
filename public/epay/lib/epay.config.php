<?php
/* *
 * 配置文件
 */
require_once($_SERVER['DOCUMENT_ROOT']."/db.config.php");

$epay_token =Db::table('fa_config')-> where('name','epay_token')->find();
$epay_config['key']= $epay_token['value'];

$epay_appid =Db::table('fa_config')-> where('name','epay_appid')->find();
$epay_config['pid']= $epay_appid['value'];

$epay_url =Db::table('fa_config')-> where('name','epay_url')->find();
$epay_config['apiurl']= $epay_url['value'];
