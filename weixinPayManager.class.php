<?php
class weixinPayManager{
    private $config;
    function __construct(){
        $this->config=include "config/config.php"; //获取公众号配置文件
        /*$this->config=array(
            "fuwu"=>array(
                "APPID"=>"wxe654ef627d4da5ac", //公众账号ID
                "mch_id"=>"1497022832", //商户号
                "key"=>"3d825d9566f635958b353790ce528ea7",
                "sign_type"=>"MD5" //MD5 HMAC-SHA256
            )
        );
        */

    }

    //统一下单接口
    //AccountType 对应config配置
    //nonce_str 随机字符串
    //body 商品描述
    //out_trade_no 商户订单号
    //total_fee 订单总金额 单位为分
    //spbill_create_ip 终端IP APP和网页支付提交用户端ip，Native支付填调用微信支付API的机器IP
    //notify_url 通知地址
    //trade_type 交易类型 取值如下：JSAPI，NATIVE，APP等
    //limit_pay 指定支付方式 上传此参数no_credit--可限制用户不能使用信用卡支付
    //openid trade_type=JSAPI时（即公众号支付），此参数必传，此参数为微信用户在商户对应appid下的唯一标识。openid

    public function unifiedorder($AccountType,$nonce_str,$body,$out_trade_no,$total_fee,$spbill_create_ip,$notify_url,$trade_type,$product_id,$limit_pay="",$openid=""){
        $strArray=array(
            "appid"=>$this->config[$AccountType]['APPID'],
            "sign_type"=>$this->config[$AccountType]['sign_type'],
            "mch_id"=>$this->config[$AccountType]['mch_id'],
            "nonce_str"=>$nonce_str,
            "body"=>$body,
            "out_trade_no"=>$out_trade_no,
            "total_fee"=>$total_fee,
            "spbill_create_ip"=>$spbill_create_ip,
            "notify_url"=>$notify_url,
            "trade_type"=>$trade_type,
			"product_id"=>$product_id,
			"limit_pay"=>$limit_pay,
			"openid"=>$openid
            );

        $sign=$this->sign($strArray,$AccountType);

        $xml="<xml>";
		foreach($strArray as $key=>$value){
			if($value<>""){
				$xml.="<".$key."><![CDATA[".$value."]]></".$key.">";
			}
		}
		$xml.="<sign><![CDATA[".$sign."]]></sign>";
        $xml.="</xml>";

        $url="https://api.mch.weixin.qq.com/pay/unifiedorder";

        $r=$this->curl($url,$xml);
        return $r;
    }

    //申请退款
    //id_type 订单号类型 transaction_id微信订单号，out_trade_no商户订单号
    //total_fee 订单总金额，单位为分，只能为整数，
    //refund_fee 退款总金额，订单总金额，单位为分，只能为整数
    //refund_desc 若商户传入，会在下发给用户的退款消息中体现退款原因
    public function refund($AccountType,$nonce_str,$id_type,$id,$out_refund_no,$total_fee,$refund_fee,$refund_desc){
        $strArray=array(
            "appid"=>$this->config[$AccountType]['APPID'],
            "mch_id"=>$this->config[$AccountType]['mch_id'],
            "nonce_str"=>$nonce_str,
            "sign_type"=>$this->config[$AccountType]['sign_type'],
            "out_refund_no"=>$out_refund_no,
            "total_fee"=>$total_fee,
            "refund_fee"=>$refund_fee,
            "refund_desc"=>$refund_desc
        );
        if($id_type=="transaction_id"){
            $strArray['transaction_id']=$id;
        }else{
            $strArray['out_trade_no']=$id;
        }

        $sign=$this->sign($strArray,$AccountType);

        $xml="<xml>";
        foreach($strArray as $key=>$value){
            if($value<>""){
                $xml.="<".$key."><![CDATA[".$value."]]></".$key.">";
            }
        }
        $xml.="<sign><![CDATA[".$sign."]]></sign>";
        $xml.="</xml>";

        $url="https://api.mch.weixin.qq.com/secapi/pay/refund";
        $r=$this->curl($url,$xml,true,true);


        /*<xml><return_code><![CDATA[SUCCESS]]></return_code>
<return_msg><![CDATA[OK]]></return_msg>
<appid><![CDATA[wxe654ef627d4da5ac]]></appid>
<mch_id><![CDATA[1497022832]]></mch_id>
<nonce_str><![CDATA[jjp0qv5q1Q4GkEJV]]></nonce_str>
<sign><![CDATA[5F04504853EE308233BC97DA02171196]]></sign>
<result_code><![CDATA[SUCCESS]]></result_code>
<transaction_id><![CDATA[4200000069201801272048436193]]></transaction_id>
<out_trade_no><![CDATA[test1517062038]]></out_trade_no>
<out_refund_no><![CDATA[out1]]></out_refund_no>
<refund_id><![CDATA[50000105472018012703331676707]]></refund_id>
<refund_channel><![CDATA[]]></refund_channel>
<refund_fee>1</refund_fee>
<coupon_refund_fee>0</coupon_refund_fee>
<total_fee>1</total_fee>
<cash_fee>1</cash_fee>
<coupon_refund_count>0</coupon_refund_count>
<cash_refund_fee>1</cash_refund_fee>
</xml>
        */
        libxml_disable_entity_loader(true);
        $get_xml = json_decode(json_encode(simplexml_load_string($r, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $sign=$this->sign($get_xml,$AccountType);
        if($sign==$get_xml['sign']){
            return $get_xml;//返回array
        }else{
            return false;
        }
    }






    //统一交易状态通知接口
    public function notify($AccountType,$xml){
        file_put_contents("config/xml.php", $xml);
        /*
<xml><appid><![CDATA[wxe654ef**********]]></appid>
<bank_type><![CDATA[COMM_CREDIT]]></bank_type>
<cash_fee><![CDATA[1]]></cash_fee>
<fee_type><![CDATA[CNY]]></fee_type>
<is_subscribe><![CDATA[Y]]></is_subscribe>
<mch_id><![CDATA[1497******]]></mch_id>
<nonce_str><![CDATA[ed867678dc03c9d24631c4d7c8aeae41]]></nonce_str>
<openid><![CDATA[ofjkc1R1zQ9OWC54J0qt85WoX928]]></openid>
<out_trade_no><![CDATA[test1517062038]]></out_trade_no>
<result_code><![CDATA[SUCCESS]]></result_code>
<return_code><![CDATA[SUCCESS]]></return_code>
<sign><![CDATA[8A36EFDA51426D2A9591E642FBB00DC0]]></sign>
<time_end><![CDATA[20180127220728]]></time_end>
<total_fee>1</total_fee>
<trade_type><![CDATA[NATIVE]]></trade_type>
<transaction_id><![CDATA[4200000069201801272048436193]]></transaction_id>
</xml>
         */
        libxml_disable_entity_loader(true);
        $get_xml = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        $return_code="FAIL";
        $return_msg="";
        if($get_xml['return_code']=="SUCCESS"){
            //通信标识成功
            $sign=$this->sign($get_xml,$AccountType);//验证签名
            if($get_xml['sign']==$sign){
                //验证签名成功
                $appid=$get_xml['appid'];
                $mch_id=$get_xml['mch_id'];
                $nonce_str=$get_xml['nonce_str'];
                $result_code=$get_xml['result_code'];
                $err_code=isset($get_xml['err_code'])?$get_xml['err_code']:"";
                $err_code_des=isset($get_xml['err_code_des'])?$get_xml['err_code_des']:"";
                $openid=$get_xml['openid'];//用户在商户appid下的唯一标识
                $is_subscribe=isset($get_xml['is_subscribe'])?$get_xml['is_subscribe']:"";//用户是否关注公众账号，Y-关注，N-未关注，仅在公众账号类型支付有效
                $trade_type=$get_xml['trade_type'];//交易类型
                $bank_type=$get_xml['bank_type'];//付款银行	参考（https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=4_2）中的8、银行类型
                $total_fee=$get_xml['total_fee'];//订单总金额，单位为分
                $cash_fee=$get_xml['cash_fee'];//现金支付金额订单现金支付金额
                $transaction_id=$get_xml['transaction_id'];//微信支付订单号
                $out_trade_no=$get_xml['out_trade_no'];//商户系统内部订单号，要求32个字符内，只能是数字、大小写字母_-|*@ ，且在同一个商户号下唯一。
                $time_end=$get_xml['time_end'];//支付完成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010

                if($appid<>$this->config[$AccountType]['APPID']){
                    $return_code="FAIL";
                    $return_msg="appid不对";
                }elseif($mch_id<>$this->config[$AccountType]['mch_id']){
                    $return_code="FAIL";
                    $return_msg="mch_id不对";
                }elseif($result_code=="SUCCESS"){
                    /*******************用户付款成功********************/
                    $return_code="SUCCESS";
                    $return_msg="处理成功";
                    file_put_contents("config/state.php", "<?php return array('state'=>3,'transaction_id'=>'".$transaction_id."','msg'=>'交易类型".$trade_type."，支付成功');?>");
                }else{
                    /*******************用户付款失败********************/
                    $return_code="SUCCESS";
                    $return_msg="处理成功";
                    file_put_contents("config/state.php", "<?php return array('state'=>4,'msg'=>'交易类型：".$trade_type."，err_code：".$err_code."，err_code_des：".$err_code_des."');?>");
                }
            }else{
                //验证签名失败
                $return_code="FAIL";
                $return_msg="签名失败";
            }

        }else{
            //通信标识失败，通过$get_xml['return_msg']获取错误原因。
            $return_code="FAIL";
            $return_msg="通信标识失败";
        }
        return "<xml>
  <return_code><![CDATA[".$return_code."]]></return_code>
  <return_msg><![CDATA[".$return_msg."]]></return_msg>
</xml>";
    }

    public function sign($strArray,$AccountType){
		$strArr=array();
		foreach($strArray as $key=>$value){
			if($key<>"sign" and $value<>"" and $value<>array()){
				$strArr[]=$key."=".$value;
			}
		}
        sort($strArr, SORT_STRING);
		$str=implode("&",$strArr)."&key=".$this->config[$AccountType]['key'];

        if($this->config[$AccountType]['sign_type']=="MD5"){
            return strtoupper(md5($str));
        }else{
            //hash_hmac
            return strtoupper(hash_hmac('sha256', $str, $this->config[$AccountType]['key']));
        }
    }

    //curl网络请求
    //url 请求地址
    //data post数据，如果为空，则使用get方式获取，如果不为空，则post方式传输data
    //verify 是否验证服务器证书
    //timeout 超时（秒），0不限
    //cainfo 签署服务器证书的权威机构的根证书保存路径，相对于当前文件位置，可以用来验证服务器证书的真实性，个人经验，大部分环境和工具未内置权威机构的根证书，所以直接都写上了。
    private function curl($url,$data="",$verify=true,$useCert=false,$timeout=500,$cainfo="/cert/rootca.pem",$sslcert="/cert/apiclient_cert.pem",$sslkey="/cert/apiclient_key.pem"){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);//以文件流的形式返回
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

        if($verify){//是否验证访问的域名证书
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
            curl_setopt($curl,CURLOPT_CAINFO,dirname(__FILE__).$cainfo);
        }else{
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
		
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($curl,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($curl,CURLOPT_SSLCERT, dirname(__FILE__).$sslcert);
			curl_setopt($curl,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($curl,CURLOPT_SSLKEY, dirname(__FILE__).$sslkey);
		}

        if($data<>""){//data有内容，则通过post方式发送
            curl_setopt($curl,CURLOPT_POST,true);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }

        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        if($res){
            curl_close($curl);
            return $res;
        } else {
            $error = curl_errno($curl);
            curl_close($curl);
            throw new Exception("curl出错，错误码:$error");
        }
    }


}
?>