<?php
/*
 * 退款
 */
$transaction_id=$_GET['transaction_id'];
include "weixinPayManager.class.php";
$weixinPayManager=new weixinPayManager();
$r= $weixinPayManager->refund("fuwu",md5("test".time()),"transaction_id",$transaction_id,"test".time(),1,1,"测试");
print_r($r);
?>