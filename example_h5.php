<?php
/*
 * 微信外，浏览器调用微信支付
 * 实际使用中需要修改:
 * 1、调用通用接口中设置的支付状态通知地址（notify_url)
 * 2、支付回调地址redirect_url
 * 3、此接口需要获取用户的IP地址
 * f
 */


if(isset($_GET['back'])){
    $state=@include "config/state.php";
    while ($state['state']<>3){
        sleep(1);
        $state=@include "config/state.php";
    }
    echo "支付成功<a href='example_refund.php?transaction_id=".$state['transaction_id']."' target='_bland'>点此退款，如果不是1分钱，修改example_refund.php文件</a>";
    exit;
}
file_put_contents("config/state.php", "<?php return array('state'=>0,'msg'=>'生成新的h5支付');?>");
$config=include "config/config.php"; //获取公众号配置文件
$AccountType="fuwu";
include "weixinPayManager.class.php";

$weixinPayManager=new weixinPayManager();

$pay=$weixinPayManager->unifiedorder($AccountType,md5("test".time()),"body","test".time(),1,ip(),"https://store.99jr.cn/weixin/gettoken/weixinpaymanager/example_notify.php","MWEB","","");
libxml_disable_entity_loader(true);
$pay=simplexml_load_string($pay, 'SimpleXMLElement', LIBXML_NOCDATA);

$url=$pay->mweb_url."&redirect_url=https%3a%2f%2fstore.99jr.cn%2fweixin%2fgettoken%2fweixinpaymanager%2fexample_h5.php%3fback%3dy";

?>
    <a href="<?=$url;?>">go</a>
<?php
function ip(){
    $cip = "0.0.0.0";
    if(!empty($_SERVER["HTTP_CLIENT_IP"])){
        $cip = $_SERVER["HTTP_CLIENT_IP"];
    }
    elseif(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])){
        $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    elseif(!empty($_SERVER["REMOTE_ADDR"])){
        $cip = $_SERVER["REMOTE_ADDR"];
    }
    return $cip;
}
?>