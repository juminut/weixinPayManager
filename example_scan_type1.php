<?php
/*
 * 扫码支付
 *
 * 预生成二维码
 * 用户扫码二维码后，将用户扫码事件传送给调用扫码回调链接中设置的地址，包含：是否关注公众账号is_subscribe，随机字符串nonce_str，商品IDproduct_id等信息。
 * 扫码回调链接对应的处理程序，判断签名，然后调用统一下单接口进行预下单，并将接口信息组合成返回信息，进行返回。
 * 微信等待正确返回后，展示付款界面进行支付。
 *
 * 注：前端展示界面，应该定时使用ajax或者长连接的方式，获取支付状态，以便做出相应改变。
 * ***示例界面采用长连接方式来获取状态。由于是演示，没有数据库支持，所以在预生成二维码时，新建了一个文件（config/state.php)保存内容为0，代表等待扫描。
 * ***用户扫描后，设置为1，用户支付失败设置为2，用户支付成功设置为3.
 * ***实际使用中，可以通过为每个用户生成的，out_trade_no，不同，来判断支付状态，前端页面也通过传送，out_trade_no，服务器来回复对应的状态。
 *
 * ***扫码支付，方式一与方式二区别***
 * 方式一，采用先生成一个较为通用的二维码，其中包含product_id字段。当有人扫描二维码，会调用（商户平台——产品中心——开发配置——扫码回调链接）中设置的链接。链接对应的处理程序携带商户订单号调用（统一下单接口），并返回调用状态。用户开始支付，调用统一下单接口中设置的支付状态回调地址
 * 方式二，采用直接携带商户订单号调用（统一下单接口），返回后对其中的code_url生成二维码。用户扫码后，用户直接开始支付，调用统一下单接口中设置的支付状态回调地址
 *
 * ***两种方式的优劣对比，及使用场景分析***
 * 方式一，不直接生成订单号，及腾讯也不直接生成相应订单，当用户扫码，会调用（商户平台——产品中心——开发配置——扫码回调链接），然后再调用统一下单接口。这种方式适合展示二维码，但是不一定用户会付款，省得
 * 方式二，直接调用统一下单接口，得到code_url，生成二维码，用户直接扫码付款。在用户付款的概率很大的时候，采用这种方式，处理速度更快。
 * f
 */

include "weixinPayManager.class.php";
$weixinPayManager=new weixinPayManager();

$config=include "config/config.php"; //获取公众号配置文件
$AccountType="fuwu";


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

}elseif (isset($GLOBALS["HTTP_RAW_POST_DATA"])){
    //用户扫码后，微信会调用扫码回调链接，并将信息放在$GLOBALS["HTTP_RAW_POST_DATA"]中
    $get_xml=$GLOBALS["HTTP_RAW_POST_DATA"];
    /*获得到的数据内容
    <xml><appid><![CDATA[wxe654ef627a******]]></appid>
    <openid><![CDATA[ofjkc1R1zQ9OWC54J0qt85WoX928]]></openid>
    <mch_id><![CDATA[14970*****]]></mch_id>
    <is_subscribe><![CDATA[Y]]></is_subscribe>
    <nonce_str><![CDATA[JTzlX64R8QN86pDC]]></nonce_str>
    <product_id><![CDATA[112000]]></product_id>
    <sign><![CDATA[9F637696D685AF2B4D7ABC65634E82E2]]></sign>
    </xml>
    */
    libxml_disable_entity_loader(true);
    $get_xml = json_decode(json_encode(simplexml_load_string($get_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    //验证签名

    $sign=$weixinPayManager->sign($get_xml,$AccountType);

    $strArr=array(
        "return_code"=>"FAIL",
        "appid"=>$config[$AccountType]['APPID'],
        "mch_id"=>$config[$AccountType]['mch_id'],
        "nonce_str"=>md5("test".time()),
        "prepay_id"=>0,
        "result_code"=>"FAIL"
    );

    if($get_xml['sign']==$sign){
        //签名验证成功，开始调用统一下单接口
        $pay= $weixinPayManager->unifiedorder($AccountType,md5("test".time()),"body","test".time(),1,"192.168.1.1","https://store.99jr.cn/weixin/gettoken/weixinpaymanager/example_notify.php","NATIVE","product_id","","");
        libxml_disable_entity_loader(true);
        $pay=simplexml_load_string($pay, 'SimpleXMLElement', LIBXML_NOCDATA);

        $strArr['prepay_id']=$pay->prepay_id;
        $strArr['return_code']="SUCCESS";
        $strArr['result_code']="SUCCESS";
        file_put_contents("config/state.php", "<?php return array('state'=>1,'msg'=>'扫码成功');?>");
    }else{
        //签名验证失败，开始返回失败信息
        $strArr['return_msg']="签名失败1111";
        $strArr['err_code_des']="签名失败，请重试！";
        file_put_contents("config/state.php", "<?php return array('state'=>2,'msg'=>'扫码签名失败');?>");
    }

    $sign=$weixinPayManager->sign($strArr,$AccountType);

    $xml="<xml>";
    foreach($strArr as $key=>$value){
        if($value<>""){
            $xml.="<".$key."><![CDATA[".$value."]]></".$key.">";
        }
    }
    $xml.="<sign><![CDATA[".$sign."]]></sign>";
    $xml.="</xml>";
    echo $xml;
}else{
    //什么都没有，那就是用户访问的，扫码界面喽
    //生成二维码

    //设置config/state.php文件内容为0
    file_put_contents("config/state.php", "<?php return array('state'=>0,'msg'=>'生成二维码');?>");

    $strArr=array(
        "appid"=>$config[$AccountType]['APPID'],
        "mch_id"=>$config[$AccountType]['mch_id'],
        "time_stamp"=>time(),
        "nonce_str"=>md5("test".time()),
        "product_id"=>time()
    );
    $sign=$weixinPayManager->sign($strArr,$AccountType);
    $url="weixin://wxpay/bizpayurl?appid=".$strArr['appid']."&mch_id=".$strArr['mch_id']."&nonce_str=".$strArr['nonce_str']."&product_id=".$strArr['product_id']."&time_stamp=".$strArr['time_stamp']."&sign=".$sign;
    echo "可以使用phpqrcode，对以下内容生成二维码。测试，只需将以下链接，粘贴到（<a href='http://www.liantu.com/' target='_blank'>http://www.liantu.com/</a>），生成二维码既可测试。<br>";
    echo $url;

    //ajax长链接内容
    $redirectUri=$_SERVER["REQUEST_URI"]."?state=";
    ?>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js">
    </script>
    <script lang="javascript">
        $(document).ready(function() {
            function getstate(state) {
                $.get("<?=$redirectUri;?>"+state,function(result){
                    result=JSON.parse(result);
                    state=result['state'];
                    if(state==3){//付款成功
                        $("#msg").html(result['time']+"状态："+result['msg']+",<a href='example_refund.php?transaction_id="+result['transaction_id']+"' target='_bland'>点此退款，如果不是1分钱，修改example_refund.php文件</a>");
                    }else{
                        $("#msg").html(result['time']+"状态："+result['msg']);
                        getstate(state);
                    }
                });
            }
            getstate(0);
        });
    </script>
<div id="msg"><?=date("Y-m-d H:i:s");?>状态：</div>
<?php
}
?>