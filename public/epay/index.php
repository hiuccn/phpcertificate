<?php

require_once($_SERVER['DOCUMENT_ROOT']."/db.config.php");
$gid=$_GET['id'];

$res=Db::table('fa_paylist')-> where('oid',$gid)->find();
if($res){
    $money=$res['fee'];
    $num=$res['num'];
    if($num==''){
        $WIDsubject='充值余额';
    }else{
        $WIDsubject='购买设备数';
    }
}else{
    return;
}
?>
<!DOCTYPE html>

<html lang="">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<style type="text/css">
			.anticon { display: inline-block; color: inherit; font-style: normal;
						line-height: 0; text-align: center; text-transform: none; vertical-align:
						-0.125em; text-rendering: optimizeLegibility; -webkit-font-smoothing: antialiased;
						-moz-osx-font-smoothing: grayscale; } .anticon > * { line-height: 1; }
						.anticon svg { display: inline-block; } .anticon::before { display: none;
						} .anticon .anticon-icon { display: block; } .anticon[tabindex] { cursor:
						pointer; } .anticon-spin::before, .anticon-spin { display: inline-block;
						-webkit-animation: loadingCircle 1s infinite linear; animation: loadingCircle
						1s infinite linear; } @-webkit-keyframes loadingCircle { 100% { -webkit-transform:
						rotate(360deg); transform: rotate(360deg); } } @keyframes loadingCircle
						{ 100% { -webkit-transform: rotate(360deg); transform: rotate(360deg);
						} }
		</style>
		<style data-vc-order="prependQueue" data-css-hash="s20z75" data-token-hash="1akdac8">
			.anticon{display:inline-flex;align-items:center;color:inherit;font-style:normal;line-height:0;text-align:center;text-transform:none;vertical-align:-0.125em;text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;}.anticon
						>*{line-height:1;}.anticon svg{display:inline-block;}.anticon .anticon
						.anticon-icon{display:block;}
		</style>
		<style data-vc-order="prependQueue" data-css-hash="h4p5d6" data-token-hash="1akdac8">
			:where(.css-eq3tly) a{color:#1677ff;text-decoration:none;background-color:transparent;outline:none;cursor:pointer;transition:color
						0.3s;-webkit-text-decoration-skip:objects;}:where(.css-eq3tly) a:hover{color:#69b1ff;}:where(.css-eq3tly)
						a:active{color:#0958d9;}:where(.css-eq3tly) a:active,:where(.css-eq3tly)
						a:hover{text-decoration:none;outline:0;}:where(.css-eq3tly) a:focus{text-decoration:none;outline:0;}:where(.css-eq3tly)
						a[disabled]{color:rgba(0, 0, 0, 0.25);cursor:not-allowed;}
		</style>

		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=0"
			  name="viewport">
		<title>
			购买确认
		</title>

		<link href="js/chunk-vendors.d223fb8b.css" rel="stylesheet">
		<link href="js/app.66264b61.css" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="js/525.8f86686a.css">
		<link rel="stylesheet" type="text/css" href="js/412.87c4b3cc.css">
	</head>
	<body>
		<div id="app" data-v-app="">
			<br>
			
			<div data-v-1b9fa07b="" class="custom-container custom-main">
				<div data-v-1b9fa07b="" class="custom-main-menu">
					<!---->
					<!---->
				</div>
				<div data-v-1b9fa07b="" class="custom-main-content">
					<div class="custom-group">
						<div class="van-cell-group">
							<div class="van-cell">
								<!---->
								<div class="van-cell__title">
									<span>
										商品名称
									</span>
									<!---->
								</div>
								<div class="van-cell__value">
									<span>
										<?echo $WIDsubject;?>
									</span>
								</div>
								<!---->
								<!---->
							</div>
							<div class="van-cell">
								<!---->
								<div class="van-cell__title">
									<span>
										商品总价
									</span>
									<!---->
								</div>
								<div class="van-cell__value">
									<span>
										<?echo $money?>
									</span>
								</div>
								<!---->
								<!---->
							</div>

						</div>
					</div>
					<div class="custom-group" style="margin-top: 20px;">
						<div>
							支付方式
						</div>
						<div class="van-cell-group">
							<div data-v-224d03ce="" class="van-radio-group" role="radiogroup" style="width: 100%;">
								<div data-v-224d03ce="" class="van-cell custom-payment-radio-item">
									<img data-v-224d03ce="" src="js/alipay.png" style="height: 20px; width: 20px; margin-right: 5px;">
									<div class="van-cell__title">
										<span>
											支付宝
										</span>
										<!---->
									</div>
									<!---->
									<div data-v-224d03ce="" role="radio" class="van-radio" tabindex="0" aria-checked="false">
										<div  id="ali" onclick="change('ali')" class="van-radio__icon van-radio__icon--round van-radio__icon--checked">
											<i class="van-badge__wrapper van-icon van-icon-success">
												<!---->
												<!---->
												<!---->
											</i>
										</div>
										<!---->
									</div>
									<!---->
								</div>

								<div data-v-224d03ce="" class="van-cell custom-payment-radio-item">
									<img data-v-224d03ce="" src="js/wx.png" style="height: 20px; width: 20px; margin-right: 5px;">
									<div class="van-cell__title">
										<span>
											微信
										</span>
										<!---->
									</div>
									<!---->
									<div data-v-224d03ce="" role="radio" class="van-radio" tabindex="0" aria-checked="true">
										<div id="weixin" onclick="change('weixin')" class="van-radio__icon van-radio__icon--round ">
											<i class="van-badge__wrapper van-icon van-icon-success">
												<!---->
												<!---->
												<!---->
											</i>
										</div>
										<!---->
									</div>
									<!---->
								</div>
							</div>
						</div>
					</div>
					<div style="margin: 30px auto; width: 96%;">
						<button type="button"  onclick="pay()" class="van-button van-button--success van-button--normal van-button--block van-button--round">
							<div class="van-button__content">
								<!---->
								<span class="van-button__text">
									立即支付(金额: <?echo $money?>)
								</span>
								<!---->
							</div>
						</button>
					</div>
				</div>
			</div>
			<button data-v-1b9fa07b="" class="ant-float-btn ant-float-btn-default ant-float-btn-circle css-eq3tly"
					type="button" style="display: none;">
				<div class="ant-float-btn-body">
					<div class="ant-float-btn-content">
						<div class="ant-float-btn-icon">
							<span role="img" aria-label="vertical-align-top" class="anticon anticon-vertical-align-top">
								<svg focusable="false" class="" data-icon="vertical-align-top" width="1em"
									 height="1em" fill="currentColor" aria-hidden="true" viewBox="64 64 896 896">
									<path d="M859.9 168H164.1c-4.5 0-8.1 3.6-8.1 8v60c0 4.4 3.6 8 8.1 8h695.8c4.5 0 8.1-3.6 8.1-8v-60c0-4.4-3.6-8-8.1-8zM518.3 355a8 8 0 00-12.6 0l-112 141.7a7.98 7.98 0 006.3 12.9h73.9V848c0 4.4 3.6 8 8 8h60c4.4 0 8-3.6 8-8V509.7H624c6.7 0 10.4-7.7 6.3-12.9L518.3 355z">
									</path>
								</svg>
							</span>
						</div>
						<!---->
					</div>
				</div>
				<!---->
			</button>
			<!---->
			<div class="van-back-top__placeholder">
			</div>
		</div>
		<div class="van-back-top">
			<i class="van-badge__wrapper van-icon van-icon-back-top van-back-top__icon">
				<!---->
				<!---->
				<!---->
			</i>
		</div>
	</body>
<script>
  a='alipay';
  function change(type) {
    if(type=='ali'){
      a='alipay';
      document.getElementById("weixin").className = "van-radio__icon van-radio__icon--round";
      document.getElementById("ali").className = "van-radio__icon van-radio__icon--round van-radio__icon--checked";
    }else{
      document.getElementById("weixin").className = "van-radio__icon van-radio__icon--round van-radio__icon--checked";
      document.getElementById("ali").className = "van-radio__icon van-radio__icon--round";
      a='wxpay';
    }
    
  }

  function pay() {
    window.location.href="/epay/epayapi.php?id=<?echo $gid;?>&type="+a+"&WIDsubject=<?echo $WIDsubject;?>";
    
  }

</script>
</html>