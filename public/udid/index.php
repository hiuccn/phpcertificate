<?
$UDID=$_GET['UDID'];
$d=$_GET['DEVICE_PRODUCT'];
require_once('WxqqJump/WxqqJump.php');
?>
<!doctype html>

<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta charset="UTF-8">
<title>获取 UDID</title>
<style type="text/css">@media print{
BODY {display:none}
}
  
body{
	margin:0px;
	padding:0px;
	font-family:Arial;
	border:0px;
	z-index:1;
	background-color: #f5f5f5;
	}	

.All{
	width: 100%;
	height: 100%;
	display: block;
	max-width: 800px;
	margin-top:15px;
	margin-left: auto;
	margin-right: auto;
	}
	
	
.AllText{
	width: 80%;
	height: auto;
	margin-left: 10px;
	margin-right: 10px;
	background-color: #fff;
	border-radius: 10px;
	padding:20px;
	}

.Info{
	font-size: 15px;
	color: #757575;
	font-weight:bold;
	line-height: 30px;
	display: block;
	margin-top: 5px;
	}

.Udid{
	text-decoration:none;
	width:70%;
	background-color:#57a67c;
	color:#fff;
	font-weight:bold;
	text-align:center;
	height:50px;
	display:block;
	line-height:50px;
	font-size:15px;
	border-radius:10px;
	margin-left:auto;
	margin-right:auto;
	margin-top:30px;
	}
	
.Top{
    font-weight:bold;
    color: #757575;
    background: #fff;
    font-size: 16px;
    text-align: center;
    line-height: 40px;
    width: 100%;
    height: 20%;
    display: block;
    }
    
.img{
    margin-top: 5%;
}

.Problem{
    color: #757575;
    font-weight:bold;
    float: left;
    margin-left: 5%;
    font-size: 16px;
    line-height: 10px;
    display: block;
    margin-top: 40px;
}

.Answer{
    color: #757575;
    float: left;
    margin-left: 5%;
    font-size: 14px;
    line-height: 30px;
    display: block;
    line-height: 25px;
    width: 90%;;
}

</style>

</head>
<body oncontextmenu="return false" onselectstart="return false">
<center><a class="Top">获取 UDID</a> </center><!--顶部标题-->
<center><img class="img" src="img/BeiJing.png" style="width:30%;height:30%;"></center><!--图标-->
<a class="Udid" onclick="getudid()">获取UDID</a><!--获取udid-->
<!--提示开始-->
<p class="Problem">系统提示输入密码?</p>
<p class="Answer">如果您安装描述文件系统提示输入密码，请输入锁屏密码。</p>    
<p class="Problem">什么是UDID?</p>
<p class="Answer">UDID，是iOS设备的一个唯一识别码，每台iOS设备都有一个独一无二的编码，这个编码，我们称之为识别码，也叫做UDID（ Unique Device Identifier）。</p>
<!--提示结束-->
<!--自动跳转设置开始-->
<script>
function getudid() {
    var userAgent = navigator.userAgent;
    var iOSVersion = parseFloat((userAgent.match(/OS (\d+)_(\d+)_?(\d+)?/) || [])[1]);
    if (iOSVersion < 17.0) {
        window.location.href = 'udid.mobileconfig'; // 实际获取udid描述文件
        setTimeout(function() {
            window.location.href = 'TZ.mobileprovision'; // 跳转系统设置描述文件
        }, 1500); // 1500代表时间
    } else {
        window.location.href = 'udid.mobileconfig'; // 实际获取udid描述文件
        alert('当前设备系统版本为17.0及以上，请前往iPhone设置安装描述文件以获取UDID');
    }
}

</script>
<!--自动跳转设置结束-->
</body>
</html>