<?php
include "weixinPayManager.class.php";
$weixinPayManager=new weixinPayManager();
$get_xml=$GLOBALS["HTTP_RAW_POST_DATA"];
echo $weixinPayManager->notify("fuwu",$get_xml);
?>