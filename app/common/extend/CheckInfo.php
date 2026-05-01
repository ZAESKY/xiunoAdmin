<?php
namespace app\common\extend;

use TrueBV\Punycode;

class CheckInfo
{
    /**
     * 判断授权内容规则
     * @return array
     * @since 2022/1/24
     * @author 陌上花开
     * @ps 需要修改默认规则或添加新规则在下方写即可
     */
    public function check($type = null, $content = null){
        
        if($type == null){
            return message(t('auth.content_type_empty'), false);
        }
        if($content == null){
            return message(t('auth.content_empty'), false);
        }
        switch ($type){
            case 'domain':
                $Punycode = new Punycode();
                if(!$this->isValidDomain($Punycode->encode($content))){
                    return message(t('auth.domain_format_error'), false);
                }else{
                    return message('success', true);
                }
                break;
            case 'qq':
                if (!$this->isQQ($content)){
                    return message(t('auth.qq_format_error'), false);
                }else{
                    return message('success', true);
                }
                break;
            case 'musicAnalysis':
                //这是一个示例
                if(!is_numeric($content)){
                    return message(t('auth.number_format_error'), false);
                }
                break;
        }
    }
    /**
     * 判断是否为QQ
     * @param $qq
     * @return bool
     * @since 2022/1/24
     * @author 陌上花开
     */
    public function isQQ($qq)
    {
        if (strlen($qq) > 12|| !is_numeric($qq) || strlen($qq) < 5 || !preg_match('/^[0-9]+$/',$qq)){
            return false;
        }else{
            return true;
        }
    }
    /**
     * 判断是否为域名（中文域名必须先转码，否则将返回 false）
     * ！！！注意判断的是域名，不是 URL！！！
     * @param $domain
     * @return bool
     * @since 2022/1/24
     * @author 陌上花开
     */
    public function isValidDomain($domain)
    {
        //判断是否包含 .
        if(strpos($domain, '.') === false) {
            return false;//未包含“.”
        }

        //以 . 分割
        $explode = explode(".", $domain);
        $strlenAll = 0;

        foreach ($explode as $k=>$v) {
            if(empty($v)){
                return false;
                break;
            }
            //判断是否包含了除 a-z或A-Z或0-9或- 以外的字符
            $rule = '/^(?!-)+[a-zA-Z0-9-\*]*$/';
            $match = preg_match($rule, $v);
            if($match === (int) 0){
                return false;//包含了除 a-z或A-Z或0-9或- 以外的字符
                break;
            }

            $strlen = strlen($v);
            if($k > (int) 0){
                $strlenAll = $strlen+$strlenAll;
            }

            //判断每段域名长度是否大于 1位 和 小于 63位
            if($strlen < (int) 1 || $strlen > (int) 63){
                return false;//域名长度格式不规范
                break;
            }

            //判断域名总长度是否超过 255位
            if($strlenAll > (int) 255){
                return false;//域名总长度超过了 255（不包含连接符“.”）
                break;
            }
        }

        return true;
    }
}