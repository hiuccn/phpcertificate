<?
$UDID=$_GET['UDID'];
$d=$_GET['DEVICE_PRODUCT'];
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta charset="UTF-8">
<title>UDID 信息</title>
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
	width:90%;
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
	
.Get{
	text-decoration:none;
	width:90%;
	background-color:#536ee4;
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

</style>

</head>
<body oncontextmenu="return false" onselectstart="return false">
    
<center><a class="Top">UDID 信息</a> </center>
<center><div class="All">
		<div class="AllText">
			<center><p class="Info">设备UDID：<br><?echo $UDID?></p><!--udid内容-->
			<center><p class="Info">设备型号：<br><?echo $d?></p><!--手机型号-->
		</div>
</div></center>

<button class="Udid" onclick='copyudid()'>复制UDID</button><!--复制udid按钮-->
<a class="Get" href="./">重新获取</a><!--重新获取按钮-->

<!--复制代码开始-->
<script>
    function copyudid(){
        let udid = '设备UDID:<?echo $UDID?>  设备型号：<?echo $d?>';
        //拿到想要复制的值
        let copyInput = document.createElement('input');//创建input元素
        document.body.appendChild(copyInput);//向页面底部追加输入框
        copyInput.setAttribute('value', udid);//添加属性，将url赋值给input元素的value属性
        copyInput.select();//选择input元素
        document.execCommand("Copy");//执行复制命令
        alert("您获取的设备UDID已复制！");//弹出提示信息，不同组件可能存在写法不同
        //复制之后再删除元素，否则无法成功赋值
        copyInput.remove();//删除动态创建的节点
    }
</script>
<!--复制代码结束-->
</body>
</html>