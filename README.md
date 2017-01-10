# wxpay
微信支付
#如何使用：
1.微信支付文件Weixinpay，放到ThinkPHP-Library-Vendor 文件夹中

2.修改配置文件，在Application-Common-Conf-config.php 中输入如下代码：

//'配置项'=>'配置值'
	'WEIXINPAY_CONFIG'       => array(
        'APPID'              => '', // 微信支付APPID
        'MCHID'              => '', // 微信支付MCHID 商户收款账号
        'KEY'                => '', // 微信支付KEY
        'APPSECRET'          => '',  //公众帐号secert
        'NOTIFY_URL'         => 'http://baijunyao.com/Api/WeixPay/notify/order_number/', // 接收支付状态的连接
    ),

3.调用：
        Vendor('Weixinpay.Weixinpay');
        $order_info = new \Weixinpay();
        $data["order"] = $order_info->getParameters('请参考微信开发者文档获取用户的openid');
		    $this->assign('data',$data);
        $this->show();
