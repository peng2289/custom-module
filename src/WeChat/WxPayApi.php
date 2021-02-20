<?php

/**
 * author: peng2289@163.com
 * Date: 2019/5/6
 * Time: 16:20
 * 参考文档 https://pay.weixin.qq.com/wiki/doc/api/index.html
 * 本版本基于v2版本开发
 */

namespace WeChat;


class WxPayApi
{
    /**
     * @var string 微信appid
     * appid是微信公众账号或开放平台APP的唯一标识
     */
    public $appId;

    /**
     * @var string 微信mch_id
     * 商户申请微信支付后，由微信支付分配的商户收款账号。
     */
    public $mchId;

    /**
     * @var string 微信key
     * 交易过程生成签名的密钥
     */
    public $key;

    /**
     * @var string 签名类型
     * 签名类型，默认为MD5，支持HMAC-SHA256和MD5。
     * 取值:md5、HMAC-SHA256
     */
    public $signType = "MD5";

    /**
     * @var string 客户端证书路径
     */
    public $sslCertPath;

    /**
     * @var string 客户端私钥的文件路径
     */
    public $sslKeyPath;

    /**
     * @var string 日志记录路径
     */
    public $logsPath;

    /**
     * @var string 客户端ip地址（选填）
     */
    public $ip;

    /**
     * 统一下单
     * @param array $enterParam 传入参数
     * (string) trade_type:交易类型；取值 JSAPI、NATIVE、APP、MWEB、MICROPAY
     * (string) out_trade_no:订单编号
     * (int)    total_fee:支付金额 单位分
     * (string) body:描述 ；格式 红旗超市-线下支付
     * (string) notify_url:回调地址
     * (string) openid:用户id（部分交易类型可不传）
     * (string) auth_code:用户付款码（仅刷卡支付使用）
     * (int)    valid_time 订单有效期 单位分钟
     * 其余参数参考微信支付官方文档
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function unifiedOrder($enterParam, &$result = [])
    {
        $comParam = [
            "appid" => (string)$this->appId,//微信支付分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            /**
             * 交易类型
             * JSAPI--JSAPI支付（或小程序支付）、
             * NATIVE--Native支付、
             * APP--app支付、
             * MWEB--H5支付
             * MICROPAY--付款码支付
             */
            "trade_type" => (string)$enterParam['trade_type'],
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            /*
             * String(32)商户系统内部订单号
             * 只能是数字、大小写字母_-|* 且在同一个商户号下唯一
             */
            "out_trade_no" => (string)$enterParam['out_trade_no'],
            "total_fee" => (int)$enterParam['total_fee'],//订单总金额，单位为分
            "sign_type" => (string)$this->signType,//签名类型
            /*
             * String(128)商品描述
             * 商品简单描述，该字段请按照规范传递
             * 参考地址：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=4_2
             */
            "body" => (string)$enterParam['body'],
            /*
             * String(256) 通知地址
             * 异步接收微信支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。
             */
            "notify_url" => (string)$enterParam['notify_url'],
            /*
             * String(64)终端IP
             * 支持IPV4和IPV6两种格式的IP地址
             * 调用微信支付API的机器IP
             */
            "spbill_create_ip" => (string)$this->getIp(),
        ];
        //用户openid处理
        if (!empty($enterParam['openid'])) {
            $comParam['openid'] = (string)$enterParam['openid'];//用户标识
        }
        //支付授权码处理
        if (!empty($enterParam['auth_code'])) {
            /*
             * String(128)授权码
             * 扫码支付授权码，设备读取用户微信中的条码或者二维码信息
             *（注：用户付款码条形码规则：18位纯数字，以10、11、12、13、14、15开头）
             */
            $comParam['auth_code'] = (string)$enterParam['auth_code'];
        }
        //有效期处理
        if (!empty($enterParam['valid_time'])) {
            //订单生成时间，格式为yyyyMMddHHmmss
            $comParam["time_start"] = (string)date("YmdHis");
            //订单失效时间，格式为yyyyMMddHHmmss
            $comParam["time_expire"] = (string)date("YmdHis", strtotime("+" . (int)$enterParam['valid_time'] . " minute"));
        }
        //是否需要分账
        if (isset($enterParam['profit_sharing'])) {
            /**
             * Y-是，需要分账 N-否，不分账 字母要求大写，不传默认不分账
             */
            $comParam['profit_sharing'] = (string)$enterParam['profit_sharing'];
        }
        $optParam = [
            /*
             * String(32)设备号
             * 自定义参数，可以为终端设备号(门店号或收银设备ID)，PC网页或公众号内支付可以传"WEB"
             */
            "device_info" => '',
            /*
             * String(6000)商品详情 JSON字符串格式
             * 商品详细描述，对于使用单品优惠的商户，该字段必须按照规范上传
             *  参考地址：https://pay.weixin.qq.com/wiki/doc/api/danpin.php?chapter=9_102&index=2
             */
            "detail" => "",
            /*
             *String(127)附加数据
             * 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
             */
            "attach" => "",
            /*
             * String(16)标价币种
             * 符合ISO 4217标准的三位字母代码，默认人民币：CNY
             * 境内商户号仅支持人民币 CNY：人民币
             */
            "fee_type" => "CNY",
            /*
             * String(32)  订单优惠标记
             * 订单优惠标记，使用代金券或立减优惠功能时需要的参数
             * 参考地址：https://pay.weixin.qq.com/wiki/doc/api/tools/sp_coupon.php?chapter=12_1
             */
            "goods_tag" => "",
            /*
             *String(32)商品ID
             * trade_type=NATIVE时，此参数必传。此参数为二维码中包含的商品ID，商户自行定义
             */
            "product_id" => "",
            /*
             *String(32)指定支付方式
             * 参数no_credit--可限制用户不能使用信用卡支付
             */
            "limit_pay" => "",
            /*
             *String(8)电子发票入口开放标识
             *  Y，传入Y时，支付成功消息和支付详情页将出现开票入口。需要在微信支付商户平台或微信公众平台开通电子发票功能，传此字段才可生效
             */
            "receipt" => "Y",
            /*
             *String(256)场景信息 JSON字符串格式
             * 该字段常用于线下活动时的场景信息上报，支持上报实际门店信息，商户也可以按需求自己上报相关信息
             */
            "scene_info" => "",
        ];
        //获取补充参数
        foreach ($enterParam as $k => $v) {
            if (isset($optParam[$k])) {
                $optParam[$k] = $v;
            }
        }
        //移除多余参数
        unset($enterParam);
        unset($optParam);
        //判断支付方式
        if ($comParam['trade_type'] == "MICROPAY") {
            //付款码
            $url = "https://api.mch.weixin.qq.com/pay/micropay";
        } else {
            //js、小程序、扫码支付、H5支付、app支付
            $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        }
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);

        return $this->curl(['url' => $url, "data" => $xml], $result);
    }

    /*
     * 小程序、H5、APP调起支付参数生成
     * @param array $enterParam 传入参数
     * (string) trade_type:交易类型；取值 JSAPI、NATIVE、APP、MWEB、MICROPAY
     * (string) prepay_id:微信返回的支付交易会话ID
     *
     * @param array $result 返回结果
     * @return int 处理状态码 非0即错误
     */
    public function paymentSignPackage($enterParam, &$result = [])
    {
        //判断支付方式
        if ($enterParam['trade_type'] == "APP") {
            $comParam = [
                "appid" => (string)$this->appId,//微信支付分配的应用ID
                "partnerid" => (string)$this->mchId,//微信支付分配的商户号
                "prepayid" => (string)$enterParam['prepay_id'],//微信返回的支付交易会话ID
                "package" => "Sign=WXPay",//暂填写固定值Sign=WXPay
                "nonce_str" => (string)$this->nonceStr(),//随机字符串
                "timestamp" => (string)time(),//时间戳
            ];
            $comParam['sign'] = $this->makeSign($comParam);
        } else {
            $comParam = [
                "appId" => (string)$this->appId,//微信支付分配的应用ID
                "timeStamp" => (string)time(),//时间戳从1970年1月1日00:00:00至今的秒数,即当前的时间
                "nonceStr" => (string)$this->nonceStr(),//随机字符串
                "package" => (string)"prepay_id=" . $enterParam['prepay_id'],//统一下单接口返回的 prepay_id 参数值
                "signType" => (string)$this->signType,//签名类型
            ];
            $comParam['paySign'] = $this->makeSign($comParam);
        }
        $result = $comParam;
        return 0;
    }

    /**
     * 授权码查询openid
     * @param array $enterParam 传入参数
     * (string) auth_code:用户付款码
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function authCode2Openid($enterParam, &$result = [])
    {
        $comParam = [
            "appid" => (string)$this->appId,//微信支付分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            "auth_code" => (string)$enterParam['auth_code'],//扫码支付授权码，设备读取用户微信中的条码或者二维码信息
        ];
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/tools/authcodetoopenid";
        return $this->curl(['url' => $url, "data" => $xml], $result);
    }

    /**
     * 查询订单
     * @param array $enterParam 传入参数
     * (string) transaction_id:微信订单号（优先）（二选一）
     * (string) out_trade_no:商户订单号（二选一）
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function orderQuery($enterParam, &$result = [])
    {
        $comParam = [
            "appid" => (string)$this->appId,//微信支付分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
        ];
        if (!empty($enterParam['transaction_id '])) {
            /*
             *String(32)微信订单号
             * 微信的订单号，建议优先使用
             */
            $comParam['transaction_id'] = (string)$enterParam['transaction_id'];
        }
        if (!empty($enterParam['out_trade_no'])) {
            /*
             *String(32)商户订单号
             * 商户系统内部订单号，只能是数字、大小写字母_-|*@ ，且在同一个商户号下唯一
             */
            $comParam['out_trade_no'] = (string)$enterParam['out_trade_no'];
        }
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        return $this->curl(['url' => $url, "data" => $xml], $result);
    }

    /**
     * 撤销订单
     * 调用支付接口后请勿立即调用撤销订单API，建议支付后至少15s后再调用撤销订单接口。
     * 支付交易返回失败或支付系统超时，调用该接口撤销交易。如果此订单用户支付失败，微信支付系统会将此订单关闭；如果用户支付成功，微信支付系统会将此订单资金退还给用户。
     * 注意：7天以内的交易单可调用撤销，其他正常支付的单如需实现相同功能请调用申请退款API。提交支付交易后调用【查询订单API】，没有明确的支付结果再调用【撤销订单API】。
     * @param array $enterParam 传入参数
     * (string) transaction_id:微信订单号（优先）（二选一）
     * (string) out_trade_no:商户订单号（二选一）
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function reverseOrder($enterParam, &$result = [])
    {
        $comParam = [
            "appid" => (string)$this->appId,//微信支付分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
        ];
        if (!empty($enterParam['transaction_id '])) {
            /*
             *String(32)微信订单号
             * 微信的订单号，建议优先使用
             */
            $comParam['transaction_id'] = (string)$enterParam['transaction_id'];
        }
        if (!empty($enterParam['out_trade_no'])) {
            /*
             *String(32)商户订单号
             * 商户系统内部订单号，只能是数字、大小写字母_-|*@ ，且在同一个商户号下唯一
             */
            $comParam['out_trade_no'] = (string)$enterParam['out_trade_no'];
        }
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/secapi/pay/reverse";
        return $this->curl(['url' => $url, "data" => $xml], $result, true);
    }

    /**
     * 企业付款到零钱
     * @param array $enterParam 传入参数
     * (string) partner_trade_no:商户订单号
     * (string) openid:用户id
     * (int)    amount:支付金额 单位分
     * (string) desc:付款备注
     * (string) device_info:设备信息
     * (string) check_name:校验用户姓名选项; NO_CHECK：不校验真实姓名 ; FORCE_CHECK：强校验真实姓名
     * (string) user_name:收款用户真实姓名
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function payment2Change($enterParam, &$result = [])
    {
        $comParam = [
            "mch_appid" => (string)$this->appId,//申请商户号的appid或商户号绑定的appid
            "mchid" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            /*
             * String(32)商户订单号
             * 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有其他字符)
             */
            "partner_trade_no" => (string)$enterParam['partner_trade_no'],
            "openid" => (string)$enterParam['openid'],//商户appid下，某用户的openid
            "amount" => (int)$enterParam['amount'],//订单总金额，单位为分
            /*
             * String(100)企业付款备注
             * 注意：备注中的敏感词会被转成字符*
             */
            "desc" => (string)$enterParam['desc'],
            /*
             * String(32)Ip地址
             * 该IP同在商户平台设置的IP白名单中的IP没有关联，该IP可传用户端或者服务端的IP。
             */
            "spbill_create_ip" => (string)$this->getIp(),
        ];
        if (!empty($enterParam['device_info'])) {
            $comParam['device_info'] = (string)$enterParam['device_info'];//String(32)微信支付分配的终端设备号
        }
        /*
         * String(16)校验用户姓名选项
         * NO_CHECK：不校验真实姓名
         * FORCE_CHECK：强校验真实姓名
         */
        $comParam['check_name'] = $enterParam['check_name'];
        if ($comParam['check_name'] == "FORCE_CHECK") {
            /*
             * String(64)收款用户姓名
             * 收款用户真实姓名。
             * 如果check_name设置为FORCE_CHECK，则必填用户真实姓名
             */
            $comParam['re_user_name'] = $enterParam['user_name'];
        }
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        return $this->curl(['url' => $url, "data" => $xml], $result, true);
    }

    /**
     * 查询企业付款结果(主动)
     * 用于商户的企业付款操作进行结果查询，返回付款操作详细结果。
     * 查询企业付款API只支持查询30天内的订单，30天之前的订单请登录商户平台查询。
     * @param array $enterParam 传入参数
     * (string) partner_trade_no:商户订单号
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function payment2ChangeQuery($enterParam, &$result = [])
    {
        $comParam = [
            "appid" => (string)$this->appId,//微信支付分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            "partner_trade_no" => (string)$enterParam['partner_trade_no'],//String(32) 商户调用企业付款API时使用的商户订单号
        ];
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo";
        return $this->curl(['url' => $url, "data" => $xml], $result, true);
    }

    /**
     * 发放普通红包
     *移动应用的appid无法使用红包接口
     * @param array $enterParam 传入参数
     * (string) mch_billno:商户订单号
     * (string) re_openid:用户id
     * (int)    total_amount:支付金额 单位分
     * (string) send_name:红包发送者名称
     * (string) wishing:红包祝福语
     * (string) act_name:活动名称
     * (string) remark:备注信息
     * (string) scene_id:发放红包使用场景，红包金额大于200或者小于1元时必传(参考文档或者注释)
     * (string) risk_info:活动信息(参考文档或者注释)
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function sendRedPack($enterParam, &$result = [])
    {
        $comParam = [
            "wxappid" => (string)$this->appId,//微信分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            "mch_billno" => (string)$enterParam['mch_billno'],//String(28) 商户订单号（每个订单号必须唯一。取值范围：0~9，a~z，A~Z）接口根据商户订单号支持重入，如出现超时可再调用。
            "send_name" => (string)$enterParam['send_name'],//String(32)商户名称 红包发送者名称 注意：敏感词会被转义成字符*
            "re_openid" => (string)$enterParam['re_openid'],//接受红包的用户openid
            "total_amount" => (int)$enterParam['total_amount'],//付款金额，单位分
            "total_num" => 1,//红包发放总人数
            "wishing" => (int)$enterParam['wishing'],//String(128)红包祝福语注意：敏感词会被转义成字符*
            "client_ip" => (string)$this->getIp(),//String(15)调用接口的机器Ip地址
            "act_name" => (int)$enterParam['act_name'],//String(32)活动名称
            "remark" => (int)$enterParam['remark'],//String(256) 备注信息
        ];
        if ($comParam['total_amount'] < 100 || $comParam['total_amount'] > 20000) {
            /*
             * String(32)场景id
             * 发放红包使用场景，红包金额大于200或者小于1元时必传
             * PRODUCT_1:商品促销
             * PRODUCT_2:抽奖
             * PRODUCT_3:虚拟物品兑奖
             * PRODUCT_4:企业内部福利
             * PRODUCT_5:渠道分润
             * PRODUCT_6:保险回馈
             * PRODUCT_7:彩票派奖
             * PRODUCT_8:税务刮奖
             */
            $comParam['scene_id'] = $enterParam['scene_id'];
        }
        if (!empty($enterParam['risk_info'])) {
            /*
             * String(128)活动信息
             * posttime:用户操作的时间戳
             * mobile:业务系统账号的手机号，国家代码-手机号。不需要+号
             * deviceid :mac 地址或者设备唯一标识
             * clientversion :用户操作的客户端版本
             * 把值为非空的信息用key=value进行拼接，再进行urlencode
             * urlencode(posttime=xx& mobile =xx&deviceid=xx)
             */
            $comParam['risk_info'] = (string)$enterParam['risk_info'];
        }
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
        return $this->curl(['url' => $url, "data" => $xml], $result, true);
    }

    /**
     * 发放裂变红包
     *裂变红包：一次可以发放一组红包。首先领取的用户为种子用户，种子用户领取一组红包当中的一个，并可以通过社交分享将剩下的红包给其他用户。
     * @param array $enterParam 传入参数
     * (string) mch_billno:商户订单号
     * (string) re_openid:用户id
     * (int)    total_amount:支付金额 单位分
     * (int)    total_num:红包发放总人数
     * (string) send_name:红包发送者名称
     * (string) wishing:红包祝福语
     * (string) act_name:活动名称
     * (string) remark:备注信息
     * (string) scene_id:发放红包使用场景，红包金额大于200或者小于1元时必传(参考文档或者注释)
     * (string) risk_info:活动信息(参考文档或者注释)
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function sendGroupRedPack($enterParam, &$result = [])
    {
        $comParam = [
            "wxappid" => (string)$this->appId,//微信分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            "mch_billno" => (string)$enterParam['mch_billno'],//String(28) 商户订单号（每个订单号必须唯一。取值范围：0~9，a~z，A~Z）接口根据商户订单号支持重入，如出现超时可再调用。
            "send_name" => (string)$enterParam['send_name'],//String(32)商户名称 红包发送者名称 注意：敏感词会被转义成字符*
            "re_openid" => (string)$enterParam['re_openid'],//接收红包的种子用户（首个用户）
            "total_amount" => (int)$enterParam['total_amount'],//付款金额，单位分
            "total_num" => (int)$enterParam['total_num'],//红包发放总人数
            /*
             *String(32) 红包金额设置方式
             * ALL_RAND—全部随机,商户指定总金额和红包发放总人数，由微信支付随机计算出各红包金额
             */
            "amt_type" => "ALL_RAND",
            "wishing" => (int)$enterParam['wishing'],//String(128)红包祝福语注意：敏感词会被转义成字符*
            "act_name" => (int)$enterParam['act_name'],//String(32)活动名称
            "remark" => (int)$enterParam['remark'],//String(256) 备注信息
        ];
        if ($comParam['total_amount'] < 100 || $comParam['total_amount'] > 20000) {
            /*
             * String(32)场景id
             * 发放红包使用场景，红包金额大于200或者小于1元时必传
             * PRODUCT_1:商品促销
             * PRODUCT_2:抽奖
             * PRODUCT_3:虚拟物品兑奖
             * PRODUCT_4:企业内部福利
             * PRODUCT_5:渠道分润
             * PRODUCT_6:保险回馈
             * PRODUCT_7:彩票派奖
             * PRODUCT_8:税务刮奖
             */
            $comParam['scene_id'] = $enterParam['scene_id'];
        }
        if (!empty($enterParam['risk_info'])) {
            /*
             * String(128)活动信息
             * posttime:用户操作的时间戳
             * mobile:业务系统账号的手机号，国家代码-手机号。不需要+号
             * deviceid :mac 地址或者设备唯一标识
             * clientversion :用户操作的客户端版本
             * 把值为非空的信息用key=value进行拼接，再进行urlencode
             * urlencode(posttime=xx& mobile =xx&deviceid=xx)
             */
            $comParam['risk_info'] = (string)$enterParam['risk_info'];
        }
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendgroupredpack";
        return $this->curl(['url' => $url, "data" => $xml], $result, true);
    }

    /**
     * 查询红包记录
     *用于商户对已发放的红包进行查询红包的具体信息，可支持普通红包和裂变包。
     * 查询红包记录API只支持查询30天内的红包订单
     * @param array $enterParam 传入参数
     * (string) mch_billno:商户订单号
     *
     * @param array $result 返回结果
     * @return array 处理状态码 非0即错误
     */
    public function redPackQuery($enterParam, &$result = [])
    {
        $comParam = [
            "appid" => (string)$this->appId,//微信分配的应用ID
            "mch_id" => (string)$this->mchId,//微信支付分配的商户号
            "nonce_str" => (string)$this->nonceStr(),//随机字符串
            "mch_billno" => (string)$enterParam['mch_billno'],//String(28) 商户发放红包的商户订单号
            "bill_type " => "MCHT",//String(32)订单类型  MCHT:通过商户订单号获取红包信息。
        ];
        $comParam['sign'] = $this->makeSign($comParam);
        $xml = $this->arr2Xml($comParam);
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo";
        return $this->curl(['url' => $url, "data" => $xml], $result, true);
    }

    /**
     * 生成签名
     * @param array $arr 签名参数
     * @return string 生成的签名字符串
     */
    public function makeSign($arr)
    {
        //按字典序排序参数
        ksort($arr);
        //对参数按照key=value的格式
        $string = $this->toUrlParams($arr);
        //拼接API密钥
        $string = "{$string}&key={$this->key}";
        //加密
        if ($this->signType = "md5") {
            $string = md5($string);
        } else if ($this->signType = "HMAC-SHA256") {
            $string = hash('sha256', $string, false);
        } else {
            $string = "null";
        }
        //所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 格式化参数格式化成url参数
     * @param array $arr 需要格式化的内容
     * @return string 格式化结果
     */
    private function ToUrlParams($arr)
    {
        $buff = "";
        foreach ($arr as $k => $v) {
            if ($v != "" && !is_array($v)) $buff .= "{$k}={$v}&";
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return string 产生的随机字符串
     */
    public function nonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $nonceStr = "";
        for ($i = 0; $i < $length; $i++) {
            $nonceStr .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $nonceStr;
    }

    /**
     * 数组转xml
     * @param array $arr 需要转换的数组
     * @return string xml内容
     */
    public function arr2Xml($arr)
    {
        $xml = "<xml>" . PHP_EOL;
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<{$key}>{$val}</{$key}>" . PHP_EOL;
            } else {
                $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>" . PHP_EOL;
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /*
     * 将XML转为array
     * @param string $xml xml内容
     * @return array 转换后的数组
     */
    public function xml2Array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $arr = json_decode(json_encode($obj), true);
        foreach ($arr as $k => $v) {
            if (is_array($v) && count($v) == 0) {
                $arr[$k] = "";
            }
        }
        return $arr;
    }

    /**
     * 获取ip
     * @return string ip地址
     */
    private function getIp()
    {
        if (!empty($this->ip)) return $this->ip;//返回自定义ip
        return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : "127.0.0.1";
    }

    /**
     * 微信返回结果处理
     * @param string $xml 需要处理的微信xml内容
     * @param array $result 处理的返回结果
     * @return array 处理状态码 非0即错误
     */
    public function resultHandle($xml, &$result = [])
    {
        $result = $this->xml2Array($xml);
        //返回状态码
        if (isset($result['return_code']) && $result['return_code'] == "FAIL") return [300, $result['return_msg']];
        //验证签名
        if (isset($result['sign'])) {
            $sign = $result['sign'];
            unset($result['sign']);
            $verifySign = $this->makeSign($result);
            if ($sign != $verifySign) return [400, "签名验证失败"];
        }
        //业务结果
        if (isset($result['result_code']) && $result['result_code'] == "FAIL") {
            return [500, "{$result['err_code']} 【{$result['err_code_des']}】"];
        }
        //去除多余参数
        unset(
            $result['return_code'],
            $result['return_msg'],
            $result['nonce_str'],
            $result['result_code']
        );
        return [0, "ok"];
    }

    /**
     * 请求
     * @param array $param 请求参数
     * (string) url:请求地址;
     * (string) data:post数据 get无需传参
     *
     * @param array $result 请求返回结果
     * @param bool $useCert 是否需要证书
     * @return array 处理状态码 非0即错误
     */
    private function curl($param = [], &$result = [], $useCert = false)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $param['url']);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);//验证服务器证书有效性
//            curl_setopt($ch,CURLOPT_CAPATH,dirname(__DIR__) . "/extend/wx-pay-cert/");//设置证书路径
            curl_setopt($ch, CURLOPT_CAINFO, dirname(dirname(__DIR__)) . "/cert/root_ca.pem");//具体的 CA 证书，和上一行效果一样，选用一个即可
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验 检验证书中的主机名和你访问的主机名是否一致
            if ($useCert == true) {
                //设置证书
                //使用证书：cert 与 key 分别属于两个.pem文件
                //证书文件请放入服务器的非web目录下
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//客户端证书路径
                curl_setopt($ch, CURLOPT_SSLCERT, $this->sslCertPath);
                curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLKEY, $this->sslKeyPath);//客户端私钥的文件路径
            }
            if (!empty($param['data'])) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param['data']);
            }
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                return [100, curl_error($ch)];
            } else {
                $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (200 !== $httpStatusCode) return [200, "状态码：" . $httpStatusCode . " 响应内容：" . $response];
            }
            if ($this->logsPath) {
                $logsPath = $this->logsPath;
                if (substr($this->logsPath, -1) != "/") $logsPath .= "/";//判断结束符号
                $logsPath .= date("Ym") . "/";
                if (!is_dir($logsPath)) mkdir($logsPath, 0755, true);//创建目录
                $str = date('Y-m-d H:i:s') . PHP_EOL . '[ 请求参数 ] ' . $param['url'] . PHP_EOL . $param['data'] . PHP_EOL . '[ 返回结果 ] ' . PHP_EOL . $response . PHP_EOL;
                file_put_contents($logsPath . "api_log_" . date('d') . '.log', $str, FILE_APPEND);
            }
            list($errCode, $msg) = $this->resultHandle($response, $result);
            if ($errCode != 0) return [$errCode, $msg];
            curl_close($ch);
            return [0, "ok"];
        } catch (\Exception $e) {
            return [600, $e->getMessage()];
        }
    }
}