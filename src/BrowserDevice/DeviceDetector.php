<?php

namespace BrowserDevice;

class DeviceDetector
{
    /**
     * @var string json文件路径
     */
    public $jsonPath = __DIR__ . '/../../data/mobiles.json';
    /**
     * @var string 浏览器信息
     */
    private $userAgent;

    /**
     * DeviceDetector constructor.
     */
    public function __construct()
    {
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? "";//获取浏览器信息
    }

    /**
     * @param string $ymlPath yml文件路径
     * @return false|int 写入状态
     * @name:   yml文件转json文件
     * @author: peng2289@163.com
     * @date:   2020/12/25
     */
    public function ymlToJson($ymlPath = null)
    {
        require_once "spyc/spyc.php";
        if (empty($ymlPath) || !file_exists($ymlPath)) $ymlPath = __DIR__ . '/../../data/mobiles.yml';//初始文件
        $arr = spyc_load_file($ymlPath);
        return file_put_contents($this->jsonPath, json_encode($arr, JSON_PRETTY_PRINT));
    }

    /**
     * @param string $browserStr 浏览器标识
     * @return array 返回结果
     * @name:   获取设备型号
     * @author: peng2289@163.com
     * @date:   2020/12/24
     */
    public function getBrowserDeviceInfo($browserStr = null)
    {
        if (is_null($browserStr)) $browserStr = $this->userAgent;//初始化
        $json = file_get_contents($this->jsonPath);
        $arr = json_decode($json, true);
        foreach ($arr as $k => $v) {
            //检测品牌
            if (stripos($browserStr, $k) === false) continue;//跳过
            if (preg_match($this->regex($v['regex']), $browserStr, $match)) {
                if (!isset($match[0])) continue;//跳过
                foreach ($v['models'] as $v2) {
                    if (preg_match($this->regex($v2['regex']), $match[0], $match2)) {
                        $v2['model'] = $this->regexModel($v2['model'], $match2);//替换特征符
                        return ['brand' => $k, "alias" => $v2['model'], "device" => $k . " " . $v2['model']];
                    }
                }
                foreach ($v['models'] as $v2) {
                    if (preg_match($this->regex($v2['regex']), $browserStr, $match2)) {
                        $v2['model'] = $this->regexModel($v2['model'], $match2);//替换特征符
                        return ['brand' => $k, "alias" => $v2['model'], "device" => $k . " " . $v2['model']];
                    }
                }
                return ['brand' => $k];
            }
        }
        return [];
    }

    /**
     * @param $regex
     * @return string
     * @name:   正则格式转化
     * @author: peng2289@163.com
     * @date:   2020/12/25
     */
    private function regex($regex)
    {
        return '/(?:^|[^A-Z0-9\-_]|[^A-Z0-9\-]_|sprd-)(?:' . \str_replace('/', '\/', $regex) . ')/i';
    }

    /**
     * @param $model
     * @param $match
     * @return string|string[]|null
     * @name:   替换设备中特殊符号
     * @author: peng2289@163.com
     * @date:   2020/12/25
     */
    private function regexModel($model, $match)
    {
        return preg_replace_callback('/\$(\d)/i', function ($match2) use ($match) {
            if (isset($match2[1]) && isset($match[$match2[1]])) {
                return str_replace('$' . $match2[1], $match[$match2[1]], $match2[0]);
            }
        }, $model);
    }

    /**
     * @param $browserStr string 浏览器信息
     * @return array 结果信息
     * @name:   获取设备信息
     * @author: peng2289@163.com
     * @date:   2020/10/11
     */
    public function getBrowserInfo($browserStr = null)
    {
        if (is_null($browserStr)) $browserStr = $this->userAgent;//初始化
        $info = [];
        if (preg_match("/Mozilla\/(.*?) \((.*?)\)/", $browserStr, $match)) {
            if (isset($match[2])) {
                $tmp = explode(";", $match[2]);
                if (isset($tmp[0])) {
                    switch ($tmp[0]) {
                        case "Linux"://可能是安卓
                            $info['os'] = explode(" ", trim($tmp[1]))[0];
                            $info['version'] = trim($tmp[1]);
                            $info['model'] = ucwords(strtolower(trim(trim(explode("/", $tmp[2])[0], "Build"))));
                            break;
                        case "iPhone":
                            $info['os'] = $tmp[0];
                            $info['version'] = "ios " . str_replace("_", ".", explode(" ", trim($tmp[1]))[3]);
                            break;
                        case "iPad":
                            $info['os'] = $tmp[0];
                            $info['version'] = "ios " . str_replace("_", ".", explode(" ", trim($tmp[1]))[2]);
                            break;
                        default:
                            $info['os'] = explode(" ", trim($tmp[0]))[0];
                            $info['version'] = trim($tmp[0]);
                            break;
                    }
                }
            }
        }
        //获取微信版本
        if (preg_match("/MicroMessenger\/([\d\.]+)/i", $browserStr, $match)) {
            $info['wechat_version'] = trim($match[1]);
        }
        return $info;
    }

    /**
     * @param string $browserStr 浏览器标识
     * @return array 返回结果
     * @name:   获取设备信息合并
     * @author: peng2289@163.com
     * @date:   2020/12/25
     */
    public function getBrowserInfoAll($browserStr = null)
    {
        if (is_null($browserStr)) $browserStr = $this->userAgent;//初始化
        return array_merge(
            $this->getBrowserInfo($browserStr),
            $this->getBrowserDeviceInfo($browserStr)
        );
    }
}

?>