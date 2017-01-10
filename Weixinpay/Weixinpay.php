<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 定义时区
ini_set('date.timezone','Asia/Shanghai');

class Weixinpay {
    // 定义配置项
    private $config=array();

    // 构造函数
    public function __construct($config){
        // 如果配置项为空 则直接返回
        if (empty($config)) {
            $this->config=C('WEIXINPAY_CONFIG');
        }else{
            $this->config=$config;
        }
    }
    /**
     * 
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public function getNonceStr($length = 32) 
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {  
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
        } 
        return $str;
    }

    /**
     * 统一下单
     * @param  array $order 订单 必须包含支付所需要的参数 body(产品描述)、total_fee(订单金额)、out_trade_no(订单号)、product_id(产品id)、trade_type(类型：JSAPI，NATIVE，APP)
     */
    public function unifiedOrder($order){
        // 获取配置项
        $weixinpay_config=$this->config;
        $config=array(
            'appid'=>$weixinpay_config['APPID'],
            'mch_id'=>$weixinpay_config['MCHID'],
            'nonce_str'=>$this->getNonceStr(),
            'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],
            'notify_url'=>$weixinpay_config['NOTIFY_URL']
            );
        // 合并配置数据和订单数据
        $data=array_merge($order,$config);
        // 生成签名
        $sign=$this->makeSign($data);
        $data['sign']=$sign;
        $xml=$this->toXml($data);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';//接收xml数据的文件
        $header[] = "Content-type: text/xml";//定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 兼容本地没有指定curl.cainfo路径的错误
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            // 显示报错信息；终止继续执行
            die(curl_error($ch));
        }
        curl_close($ch);
        $result=$this->toArray($response);
        // 显示错误信息
        if ($result['return_code']=='FAIL') {
            die($result['return_msg']);
        }
        $result['sign']=$sign;
        $result['nonce_str']='test';
        return $result;
    }


    /**
     * 验证
     * @return array 返回数组格式的notify数据
     */
    public function notify(){
        // 获取xml
        $xml=file_get_contents('php://input', 'r'); 
        // 转成php数组
        $data=$this->toArray($xml);
        // 保存原sign
        $data_sign=$data['sign'];
        // sign不参与签名
        unset($data['sign']);
        $sign=$this->makeSign($data);
        // 判断签名是否正确  判断支付状态
        if ($sign===$data_sign && $data['return_code']=='SUCCESS' && $data['result_code']=='SUCCESS') {
            $result=$data;
        }else{
            $result=false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

    /**
     * 输出xml字符
     * @throws WxPayException
    **/
    public function toXml($data){
        if(!is_array($data) || count($data) <= 0){
            throw new WxPayException("数组数据异常！");
        }
        $xml = "<xml>";
        foreach ($data as $key=>$val){
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml; 
    }

    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function makeSign($data){
        // 去空
        $data=array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a=http_build_query($data);
        $string_a=urldecode($string_a);
        //签名步骤二：在string后加入KEY
        $config=$this->config;
        $string_sign_temp=$string_a."&key=".$config['KEY'];
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result=strtoupper($sign);
        return $result;
    }

    /**
     * 将xml转为array
     * @param  string $xml xml字符串
     * @return array       转换得到的数组
     */
    public function toArray($xml){   
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result= json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);        
        return $result;
    }

    /**
     * 获取jssdk需要用到的数据
     * @return array jssdk需要用到的数据
     */
    public function getParameters($openid){
        // 获取配置项
        $config=$this->config;
       
        // 订单数据  请根据订单号out_trade_no 从数据库中查出实际的body、total_fee、out_trade_no、product_id
        $order=array(
            'body'=>'test',// 商品描述（需要根据自己的业务修改）
            'total_fee'=>1,// 订单金额  以(分)为单位（需要根据自己的业务修改）
            'out_trade_no'=>$config["MCHID"].date("YmdHis"),// 订单号（需要根据自己的业务修改）
            'trade_type'=>'JSAPI',// JSAPI公众号支付
            'openid'=>$openid// 获取到的openid
        );
        // 统一下单 获取prepay_id
        $unified_order=$this->unifiedOrder($order);
        // 获取当前时间戳
        $time=time();
        // 组合jssdk需要用到的数据
        $data=array(
            'appId'=>$config['APPID'], //appid
            'timeStamp'=>strval($time), //时间戳
            'nonceStr'=>$unified_order['nonce_str'],// 随机字符串
            'package'=>'prepay_id='.$unified_order['prepay_id'],// 预支付交易会话标识
            'signType'=>'MD5'//加密方式
        );
        // 生成签名
        $data['paySign']=$this->makeSign($data);
        return $data;
       
    }

}
