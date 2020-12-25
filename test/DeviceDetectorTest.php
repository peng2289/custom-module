<?php
require "../vendor/autoload.php";
$model=new BrowserDevice\DeviceDetector();
//var_dump($model->ymlToJson());//初始化
$str="Mozilla/5.0 (Linux; Android 10; AQM-AL00 Build/HUAWEIAQM-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/78.0.3904.62 XWEB/2693 MMWEBSDK/201101 Mobile Safari/537.36 MMWEBID/2230 MicroMessenger/7.0.21.1800(0x27001539) Process/toolsmp WeChat/arm64 Weixin NetType/WIFI Language/zh_CN ABI/arm64";
$data=$model->getBrowserInfoAll($str);
print_r($data);