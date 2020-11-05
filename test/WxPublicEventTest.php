<?php
new \WxPublicEventTest();

class WxPublicEventTest
{
    private $eventData = [];

    public function __construct()
    {
        $this->init();
    }

    /**
     * @name:   入口
     * @author: peng2289@163.com
     * @date:   2020/11/5
     */
    private function init()
    {
        $xml = file_get_contents('php://input');
        $get = $_GET;

        require "../vendor/autoload.php";
        $event = new WeChat\WxPublicEvent();
        $event->appId = '';
        $event->token = '';
        $event->encodingAesKey = '';

        /************    验证是否微信来源 start       ************/
        $errCode = $event->msgSHA1("", $get['timestamp'] ?? "", $get['nonce'] ?? "", $sign);
        if ($errCode != 0) exit("签名生成错误；code：" . $errCode);
        if ($sign != ($get['signature'] ?? "")) exit("签名验证错误");
        /************         验证是否微信来源 end          ************/

        //判断验证
        if (isset($get['echostr']) && empty($xml)) die($get['echostr'] ?? "");

        /************        解析消息密文 start        ************/
        if (isset($get['encrypt_type']) && $get['encrypt_type'] === "aes") {
            $tmp = $event->xml_decode($xml, true);
            if (!empty($get['msg_signature'])) {
                $errCode = $event->msgSHA1($tmp['Encrypt'] ?? "", $get['timestamp'] ?? "", $get['nonce'] ?? "", $sign);
                if ($errCode != 0) exit("消息签名生成错误；code：" . $errCode);
                if ($sign != $get['msg_signature']) exit("消息签名验证错误");
            }
            $errCode = $event->decryptMsg($tmp['Encrypt'], $xml);
            if ($errCode != 0) exit("消息解密解析失败；code：" . $errCode);
            unset($tmp);
        }
        /************        解析消息密文 end       ************/

        $this->eventData = $event->xml_decode($xml, true);//装入数据
//        print_r($xml);
//        print_r($this->eventData);
        $this->eventHandle();//事件处理
        $arr = [
            'ToUserName' => $this->eventData['FromUserName'],
            'FromUserName' => $this->eventData['ToUserName'],
            'CreateTime' => time(),
            'MsgType' => "text",
            'Content' => "哈喽" . date("Y-m-d H:i:s"),
        ];
        $event->returnMsg($arr, $str, true);
//        $tmp=$event->xml_decode($str, true);
//        print_r($tmp);
//        $errCode = $event->msgSHA1($tmp['Encrypt'] ?? "", $tmp['TimeStamp'] ?? "", $tmp['Nonce'] ?? "", $sign);
//        if ($errCode != 0) exit("消息签名生成错误；code：" . $errCode);
//        if ($sign != $tmp['MsgSignature']) exit("消息签名验证错误");
        print_r($str);
    }


    /**
     * @name:   事件处理入口
     * @author: peng2289@163.com
     * @date:   2020/11/4
     */
    private function eventHandle()
    {
        switch ($this->eventData['MsgType']) {
            case 'event'://事件消息(支持回复用户)
                $this->eventMsg();
                break;
            case 'text'://文本消息(支持回复用户)
                break;
            case 'image'://图片消息(支持回复用户)
                break;
            case 'location'://地理位置消息(不支持回复用户)
                break;
            case 'voice'://语音消息(支持回复用户)
                break;
            case 'video'://视频消息(支持回复用户)
                break;
            case 'link'://链接消息
                break;
            case 'shortvideo'://小视频消息
                break;
            default:
                break;
        }
    }

    /**
     * @name:   事件消息处理
     * @author: peng2289@163.com
     * @date:   2020/11/4
     */
    private function eventMsg()
    {
        switch ($this->eventData['Event']) {
            case 'subscribe'://订阅
                break;
            case 'unsubscribe'://取消订阅
                break;
            case 'SCAN'://扫描带参数二维码事件(用户已关注时的事件推送)(不支持回复用户)
                break;
            case 'LOCATION'://上报地理位置事件
                break;
            case 'CLICK'://自定义菜单事件(点击菜单拉取消息时的事件推送)
                break;
            case 'VIEW'://自定义菜单事件(点击菜单跳转链接时的事件推送)
                break;
            case 'scancode_push'://扫码推事件的事件推送
                break;
            case 'scancode_waitmsg'://扫码推事件且弹出“消息接收中”提示框的事件推送
                break;
            case 'pic_sysphoto'://弹出系统拍照发图的事件推送
                break;
            case 'pic_photo_or_album'://弹出拍照或者相册发图的事件推送
                break;
            case 'pic_weixin'://弹出微信相册发图器的事件推送
                break;
            case 'location_select'://弹出地理位置选择器的事件推送
                break;
            case 'view_miniprogram'://点击菜单跳转小程序的事件推送
                break;
            default:
                break;
        }
    }
}
