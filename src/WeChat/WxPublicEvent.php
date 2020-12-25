<?php

namespace WeChat;
class WxPublicEvent
{
    public $appId;
    public $token;
    public $encodingAesKey;

    /**
     * @param string $message 待解密的xml内容
     * @param string $xml 解密后的xml内容
     * @return int|string
     * @name:   消息解密
     * @author: peng2289@163.com
     * @date:   2020/11/4
     */
    public function decryptMsg($message, &$xml = '')
    {
        if (strlen($this->encodingAesKey) != 43) return -40004;//encodingAesKey 非法
        try {
            $key = base64_decode($this->encodingAesKey . '=');
            $cipherText_dec = base64_decode($message);
            $iv = substr($key, 0, 16);
            $decrypted = openssl_decrypt($cipherText_dec, 'AES-256-CBC', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
            if ($decrypted === false) return -40007;
        } catch (\Exception $e) {
            return -40007;//aes 解密失败
        }
        try {
            $pad = ord(substr($decrypted, -1));
            //去除补位字符
            if ($pad < 1 || $pad > 32) $pad = 0;
            $result = substr($decrypted, 0, (strlen($decrypted) - $pad));
            if (strlen($result) < 16) return "";
            //去除16位随机字符串,网络字节序和AppId
            $content = substr($result, 16, strlen($result));
            $xml_len = unpack("N", substr($content, 0, 4))[1];
            $xml = substr($content, 4, $xml_len);
            $from_appId = substr($content, $xml_len + 4);
            if ($from_appId != $this->appId) return -40005;//appId 校验错误
            return 0;
        } catch (\Exception $e) {
            return -40008;//解密后得到的buffer非法
        }
    }

    /**
     * @param string $replyMsg 待加密的xml内容
     * @param string $encrypt_msg 加密后的xml内容
     * @return int 状态 0成功 非0错误
     * @name:   加密模块
     * @author: peng2289@163.com
     * @date:   2020/11/4
     */
    private function encrypt($replyMsg, &$encrypt_msg = '')
    {
        if (strlen($this->encodingAesKey) != 43) return -40004;//encodingAesKey 非法
        try {
            //使用自定义的填充方式对明文进行补位填充
            $text = $this->random(16) . pack("N", strlen($replyMsg)) . $replyMsg . $this->appId;
            $key = base64_decode($this->encodingAesKey . '=');
            $iv = substr($key, 0, 16);
            $encrypted = openssl_encrypt($text, 'AES-256-CBC', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
            //echo openssl_error_string();
            if (false === $encrypted) return -40006;
            //使用BASE64对加密后的字符串进行编码
            $encrypt_msg = base64_encode($encrypted);
            return 0;
        } catch (\Exception $e) {
            //aes 加密失败
            return -40006;
        }
    }

    /**
     * 获取随机字符串
     * @param number $length 长度
     * @param string $type 类型 支持number letter string all
     * @return string 随机字符串
     */
    public function random($length = 6, $type = 'string')
    {
        $chars = [
            'number' => '1234567890',
            'letter' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'string' => 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789',
            'all' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'
        ];
        if (!isset($chars[$type])) $type = 'string';
        $string = $chars[$type];
        $randStr = '';
        $strLen = strlen($string) - 1;
        for ($i = 0; $i < $length; $i++) $randStr .= $string{mt_rand(0, $strLen)};
        return $randStr;
    }

    /**
     * @param string $encrypt 加密的内容,可空
     * @param mixed $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $signature 加密后的内容
     * @return int 状态 0成功 非0错误
     * @name:   消息签名
     * @author: peng2289@163.com
     * @date:   2020/11/5
     */
    public function msgSHA1($encrypt, $timestamp, $nonce, &$signature)
    {
        //排序
        try {
            $array = [$encrypt, $this->token, $timestamp, $nonce];
            sort($array, SORT_STRING);
            $signature = sha1(implode($array));
            return 0;
        } catch (\Exception $e) {
            //sha加密生成签名失败
            return -40003;
        }
    }

    /**
     * @param string $replyMsg 生成xml消息
     * @param string $xml 生成的加密xml
     * @param string $nonce 随机字符串
     * @return int 状态 0成功 非0错误
     * @name:   生成加密xml
     * @author: peng2289@163.com
     * @date:   2020/11/4
     */
    private function encryptMsg($replyMsg, &$xml, $nonce = '')
    {
        $errCode = $this->encrypt($replyMsg, $encrypt);
        if ($errCode != 0) return $errCode;
        $timestamp = time();
        if (empty($nonce)) $nonce = $this->random(16);
        //获取签名
        $errCode = $this->msgSHA1($encrypt, (string)$timestamp, $nonce, $signature);
        if ($errCode != 0) return $errCode;
        $format = "<xml>
        <Encrypt><![CDATA[%s]]></Encrypt>
        <MsgSignature><![CDATA[%s]]></MsgSignature>
        <TimeStamp>%s</TimeStamp>
        <Nonce><![CDATA[%s]]></Nonce>
        </xml>";
        $xml = sprintf($format, $encrypt, $signature, $timestamp, $nonce);
        return 0;
    }

    /**
     * @param array $arr 回复内容
     * @param string $xml 返回的xml内容
     * @param bool $is_encrypt 是否返回签名后数据
     * @return int 状态 0成功 非0错误
     * @name:   返回消息内容
     * @author: peng2289@163.com
     * @date:   2020/11/5
     */
    public function returnMsg($arr, &$xml, $is_encrypt = false)
    {
        $xml = $this->xml_encode($arr);
        if ($is_encrypt) {
            $errCode = $this->encryptMsg($this->xml_encode($arr), $xml);
        } else {
            $errCode = 0;
        }
        return $errCode;
    }



    /**
     * @param string $xml xml内容
     * @param bool $assoc 转数组/对象
     * @return mixed 返回数组/对象
     * @name:   模块名称
     * @author: peng2289@163.com
     * @date:   2020/11/4
     */
    public function xml_decode($xml, $assoc = true)
    {
        libxml_disable_entity_loader(true);
        $xmlObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xmlObj), $assoc);
    }

    /**
     * @param array $arr 待转入xml数据
     * @return string xml内容
     * @name:   数组转xml数据
     * @author: peng2289@163.com
     * @date:   2020/11/5
     */
    public function xml_encode($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . $this->xml_encode($val) . "</" . $key . ">";
            } else {
                if (is_numeric($val)) {
                    $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
                } else {
                    $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
                }
            }
        }
        $xml .= "</xml>";
        return $xml;
    }
}