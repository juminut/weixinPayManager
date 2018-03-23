<?php
/*
 * 微信官方支付文档中称为公众号支付
 * 主要是微信内浏览的h5页面，调用支付
 * 因为是在微信内，所以可以顺利的获取支付者的openid，因此，公众号支付，需要传送openid
 * openid的获取方法，请参考https://github.com/juminut/weixinOauth2中的example_getinfo.php
 *
 * 首先调用统一下单接口，进行预下单，得到prepay_id
 * 根据prepay_id生成js内容，调用支付
 *
 * 微信配置如下：
 * 支付平台jsapi地址设置
 *
 */

if(isset($_GET['state'])){
    //前端界面ajax来获取用户支付状态，由nonce_str，和ustate可以判断出当前状态，并返回
    //此段程序为判断用户支付状态举例代码。如果
    $ustate=$_GET['state'];
    $state=@include "config/state.php";
    $times=10;
    while ($times>0 and $state['state']==$ustate){
        sleep(1);
        $times--;
        $state=@include "config/state.php";
    }
    $state['time']=date("Y-m-d H:i:s");
    echo json_encode($state);
	exit;
}

$config=include "config/config.php"; //获取公众号配置文件
$AccountType="fuwu";
$openid="ofjkc1R1zQ9OWC54J0qt85WoX928";//openid的获取方法，请参考https://github.com/juminut/weixinOauth2中的example_getinfo.php

include "weixinPayManager.class.php";

$weixinPayManager=new weixinPayManager();

$nonce_str=md5("test".time());
$pay=$weixinPayManager->unifiedorder($AccountType,$nonce_str,"body","test".time(),1,"192.168.1.1","https://store.99jr.cn/weixin/gettoken/weixinpaymanager/example_notify.php","JSAPI","","",$openid);

file_put_contents("config/state.php", "<?php return array('state'=>0,'msg'=>'生成jsapi支付订单');?>");

libxml_disable_entity_loader(true);
$pay=simplexml_load_string($pay, 'SimpleXMLElement', LIBXML_NOCDATA);

print_r($pay);
$strArr=array(
    "appId"=>$config[$AccountType]['APPID'],
    "timeStamp"=>time(),
    "nonceStr"=>$nonce_str,
    "package"=>"prepay_id=".$pay->prepay_id,
    "signType"=>$config[$AccountType]['sign_type'],
);

$sign=$weixinPayManager->sign($strArr,$AccountType);

?>
<div id="msg"><?=date("Y-m-d H:i:s");?>状态：</div>
<script src="https://code.jquery.com/jquery-3.3.1.min.js">
</script>
<script language="JavaScript">
    function onBridgeReady(){
        WeixinJSBridge.invoke(
            'getBrandWCPayRequest', {
                "appId":"<?=$strArr['appId'];?>",     //公众号名称，由商户传入
                "timeStamp":"<?=$strArr['timeStamp'];?>",         //时间戳，自1970年以来的秒数
                "nonceStr":"<?=$strArr['nonceStr'];?>", //随机串
                "package":"<?=$strArr['package'];?>",
                "signType":"<?=$strArr['signType'];?>",         //微信签名方式：
                "paySign":"<?=$sign;?>" //微信签名
            },
            function(res){
                alert(res.err_msg);
                if(res.err_msg == "get_brand_wcpay_request:ok" ) {}     // 使用以上方式判断前端返回,微信团队郑重提示：res.err_msg将在用户支付成功后返回    ok，但并不保证它绝对可靠。
            }
        );
    }
    if (typeof WeixinJSBridge == "undefined"){
        if( document.addEventListener ){
            document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);
        }else if (document.attachEvent){
            document.attachEvent('WeixinJSBridgeReady', onBridgeReady);
            document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);
        }
    }else{
        onBridgeReady();
    }
	
$(document).ready(function() {
	function getstate(state) {
		$.get("<?=$_SERVER["REQUEST_URI"];?>?state="+state,function(result){
			result=JSON.parse(result);
			state=result['state'];
			if(state==3){//付款成功
				msg=result['time']+"状态："+ result['msg'] + "，<a href='example_refund.php?transaction_id=" + result['transaction_id']+"' target='_bland'>点此退款，如果不是1分钱，修改example_refund.php文件</a>";
				$("#msg").html(msg);
			}else{
				$("#msg").html(result['time']+"状态："+result['msg']);
				getstate(state);
			}
		});
	}
	getstate(0);
});
</script>