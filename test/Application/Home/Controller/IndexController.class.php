<?php
namespace Home\Controller;
use Think\Controller;
class IndexController extends Controller {
    public function index(){
        Vendor('Weixinpay.Weixinpay');
        $order_info = new \Weixinpay();
        $data["order"] = $order_info->getParameters('请参考微信开发者文档获取用户的openid');
		$this->assign('data',$data);
        $this->show();
    }
}