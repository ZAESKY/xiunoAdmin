<?php
// 应用公共文件

use think\exception\ValidateException;
use think\facade\Db;
use Symfony\Component\VarExporter\VarExporter;
use app\common\service\LocalFilesystemService;
use app\common\service\PluginStorageService;

if (!function_exists('sf_action_log_params')) {
    /**
     * Redact credentials and tokens before persisting request parameters.
     */
    function sf_action_log_params(array $params): string
    {
        $sensitiveKeys = [
            'password', 'passwd', 'pwd', 'old_password', 'new_password',
            'repassword', 'confirm_password', 'token', 'access_token',
            'refresh_token', 'authorization', 'authcode', 'secret',
            'client_secret', 'license_secret', 'private_key', 'qrsig', 'cookie',
            'request_code', 'activation_code', 'oss_access_key_id',
            'oss_access_key_secret', 'code', 'state', 'pending_token',
            'flow_id', 'proof_token', 'oauth_error', 'sms_access_key_id',
            'sms_access_key_secret', 'sms_code',
        ];

        $redact = static function ($value) use (&$redact, $sensitiveKeys) {
            if (!is_array($value)) {
                return $value;
            }
            foreach ($value as $key => $item) {
                if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                    $value[$key] = '[REDACTED]';
                    continue;
                }
                $value[$key] = $redact($item);
            }
            return $value;
        };

        $encoded = json_encode($redact($params), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '';
    }
}

if (!function_exists('sf_safe_unserialize_array')) {
    /**
     * Decode legacy serialized settings without allowing PHP object creation.
     */
    function sf_safe_unserialize_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        try {
            $decoded = @unserialize($value, ['allowed_classes' => false]);
        } catch (\Throwable $e) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('sf_page_limit')) {
    /**
     * Normalize a client supplied page size and enforce a resource ceiling.
     */
    function sf_page_limit($value, int $default = 10, int $max = 100): int
    {
        $default = max(1, $default);
        $max = max(1, $max);
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return min($default, $max);
        }
        return max(1, min((int)$value, $max));
    }
}

if (!function_exists('sf_page_number')) {
    /**
     * Normalize a client supplied page number.
     */
    function sf_page_number($value, int $max = 100000): int
    {
        $max = max(1, $max);
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return 1;
        }
        return max(1, min((int)$value, $max));
    }
}

if (!function_exists('sf_download_mode')) {
    /**
     * Normalize legacy numeric download configuration to current template names.
     */
    function sf_download_mode(): string
    {
        $mode = strtolower(trim((string)conf('download')));
        $legacy = ['1' => 'mail', '2' => 'qrcode', '3' => 'info'];
        $mode = $legacy[$mode] ?? $mode;
        return in_array($mode, ['mail', 'qrcode', 'info'], true) ? $mode : '0';
    }
}

if (!function_exists('sf_password_hash')) {
    function sf_password_hash(){
        return 'SF*(!@#%!s!0+-*~_-2129876388';
    }
}

if (!function_exists('sf_money_to_cents')) {
    /**
     * 将金额转换为整数分，业务计算过程中不直接使用二进制浮点金额。
     */
    function sf_money_to_cents($amount)
    {
        $value = trim((string)$amount);
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/D', $value, $matches)) {
            if (!is_numeric($amount)) {
                throw new \InvalidArgumentException('金额格式无效');
            }
            $value = number_format((float)$amount, 8, '.', '');
            preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/D', $value, $matches);
        }

        $fraction = str_pad($matches[3] ?? '', 3, '0');
        $cents = ((int)$matches[2] * 100) + (int)substr($fraction, 0, 2);
        if ((int)$fraction[2] >= 5) {
            $cents++;
        }
        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }
}

if (!function_exists('sf_money_from_cents')) {
    /**
     * 将整数分转换成固定两位小数的金额字符串。
     */
    function sf_money_from_cents($cents)
    {
        $cents = (int)$cents;
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign . intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('sf_money_format')) {
    function sf_money_format($amount)
    {
        return sf_money_from_cents(sf_money_to_cents($amount));
    }
}

if (!function_exists('sf_money_add')) {
    function sf_money_add($left, $right)
    {
        return sf_money_from_cents(sf_money_to_cents($left) + sf_money_to_cents($right));
    }
}

if (!function_exists('sf_money_subtract')) {
    function sf_money_subtract($left, $right)
    {
        return sf_money_from_cents(sf_money_to_cents($left) - sf_money_to_cents($right));
    }
}

if (!function_exists('sf_money_apply_rate')) {
    /**
     * 按百分比计算金额。百分比支持两位小数，数量必须为非负整数。
     * 例如 sf_money_apply_rate('306.00', 70) 返回 '214.20'。
     */
    function sf_money_apply_rate($unitAmount, $ratePercent, $quantity = 1)
    {
        $quantity = max(0, (int)$quantity);
        $amountCents = sf_money_to_cents($unitAmount);
        $rateBasisPoints = sf_money_to_cents($ratePercent);
        $numerator = $amountCents * $quantity * $rateBasisPoints;
        $roundedCents = $numerator >= 0
            ? intdiv($numerator + 5000, 10000)
            : -intdiv(abs($numerator) + 5000, 10000);
        return sf_money_from_cents($roundedCents);
    }
}

if (!function_exists('sf_money_daily_rate')) {
    /**
     * 按总价计算每日单价（向上取整到分），与授权自定义天数计价规则一致。
     */
    function sf_money_daily_rate($totalAmount, $days)
    {
        $days = (int)$days;
        if ($days <= 0) {
            throw new \InvalidArgumentException('计价天数必须大于 0');
        }
        $totalCents = sf_money_to_cents($totalAmount);
        if ($totalCents < 0) {
            throw new \InvalidArgumentException('计价金额不能为负数');
        }
        return sf_money_from_cents(intdiv($totalCents + $days - 1, $days));
    }
}
if (!function_exists('addNoticeGroupList')) {
    /**
     * 添加新标签到系统设置标签组里
     * @param array $wap
     * @return boolean
     */
    function addNoticeGroupList(array $wap = []){
        if(empty($wap)) return false;
        $groupList = config('notice');
        $array = array_merge($groupList, $wap);
        $file = APP_PATH . DS . 'admin' . DS . 'config' . DS . 'notice.php';
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, '<?php'.PHP_EOL.PHP_EOL.'return ' . VarExporter::export($array) . ';'.PHP_EOL);
            fclose($handle);
        } else {
            throw new Exception("文件没有写入权限");
        }
        return true;
    }
}
if (!function_exists('deleteNoticeGroupList')) {
    /**
     * 删除系统设置标签组里的标签
     * @param array $wap
     * @return boolean
     */
    function deleteNoticeGroupList(array $wap = []){
        if(empty($wap)) return false;
        $groupList = config('notice');
        $array = array_diff_key($groupList, $wap);
        $file = APP_PATH . DS . 'admin' . DS . 'config' . DS . 'notice.php';
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, '<?php'.PHP_EOL.PHP_EOL.'return ' . VarExporter::export($array) . ';'.PHP_EOL);
            fclose($handle);
        } else {
            throw new Exception("文件没有写入权限");
        }
        return true;
    }
}
if (!function_exists('addSiteGroupList')) {
    /**
     * 添加新标签到系统设置标签组里
     * @param array $wap
     * @return boolean
     */
    function addSiteGroupList(array $wap = []){
        if(empty($wap)) return false;
        $groupList = config('site.groupList');
        $array = ['groupList' => array_merge($groupList, $wap)];
        $file = APP_PATH . DS . 'admin' . DS . 'config' . DS . 'site.php';
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, '<?php'.PHP_EOL.PHP_EOL.'return ' . VarExporter::export($array) . ';'.PHP_EOL);
            fclose($handle);
        } else {
            throw new Exception("文件没有写入权限");
        }
        return true;
    }
}
if (!function_exists('deleteSiteGroupList')) {
    /**
     * 删除系统设置标签组里的标签
     * @param array $wap
     * @return boolean
     */
    function deleteSiteGroupList(array $wap = []){
        if(empty($wap)) return false;
        $groupList = config('site.groupList');
        $array = ['groupList' => array_diff_key($groupList, $wap)];
        $file = APP_PATH . DS . 'admin' . DS . 'config' . DS . 'site.php';
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, '<?php'.PHP_EOL.PHP_EOL.'return ' . VarExporter::export($array) . ';'.PHP_EOL);
            fclose($handle);
        } else {
            throw new Exception("文件没有写入权限");
        }
        return true;
    }
}
if (!function_exists('getArrayData')) {
    /**
     * 将多个数组合并成一个
     * @param array   $data
     * @return array
     */
    function getArrayData($data){
        if (!isset($data['value'])) {
            $result = [];
            foreach ($data as $index => $datum) {
                $result['field'][$index] = $datum['key'];
                $result['value'][$index] = $datum['value'];
            }
            $data = $result;
        }
        $fieldarr = $valuearr = [];
        $field = isset($data['field']) ? $data['field'] : (isset($data['key']) ? $data['key'] : []);
        $value = isset($data['value']) ? $data['value'] : [];
        foreach ($field as $m => $n) {
            if ($n != '') {
                $fieldarr[] = $field[$m];
                $valuearr[] = $value[$m];
            }
        }
        return $fieldarr ? array_combine($fieldarr, $valuearr) : [];
    }
}
if (!function_exists('var_export_short')) {

    /**
     * 使用短标签打印或返回数组结构
     * @param mixed   $data
     * @param boolean $return 是否返回数据
     * @return string
     */
    function var_export_short($data, $return = true)
    {
        return var_export($data, $return);
        $replaced = [];
        $count = 0;

        //判断是否是对象
        if (is_resource($data) || is_object($data)) {
            return var_export($data, $return);
        }

        //判断是否有特殊的键名
        $specialKey = false;
        array_walk_recursive($data, function (&$value, &$key) use (&$specialKey) {
            if (is_string($key) && (stripos($key, "\n") !== false || stripos($key, "array (") !== false)) {
                $specialKey = true;
            }
        });
        if ($specialKey) {
            return var_export($data, $return);
        }
        array_walk_recursive($data, function (&$value, &$key) use (&$replaced, &$count, &$stringcheck) {
            if (is_object($value) || is_resource($value)) {
                $replaced[$count] = var_export($value, true);
                $value = "##<{$count}>##";
            } else {
                if (is_string($value) && (stripos($value, "\n") !== false || stripos($value, "array (") !== false)) {
                    $index = array_search($value, $replaced);
                    if ($index === false) {
                        $replaced[$count] = var_export($value, true);
                        $value = "##<{$count}>##";
                    } else {
                        $value = "##<{$index}>##";
                    }
                }
            }
            $count++;
        });

        $dump = var_export($data, true);

        $dump = preg_replace('#(?:\A|\n)([ ]*)array \(#i', '[', $dump); // Starts
        $dump = preg_replace('#\n([ ]*)\),#', "\n$1],", $dump); // Ends
        $dump = preg_replace('#=> \[\n\s+\],\n#', "=> [],\n", $dump); // Empties
        $dump = preg_replace('#\)$#', "]", $dump); //End

        if ($replaced) {
            $dump = preg_replace_callback("/'##<(\d+)>##'/", function ($matches) use ($replaced) {
                return isset($replaced[$matches[1]]) ? $replaced[$matches[1]] : "''";
            }, $dump);
        }

        if ($return === true) {
            return $dump;
        } else {
            echo $dump;
        }
    }
}
if (!function_exists('is_really_writable')) {
    function is_really_writable($file)
    {
        // 在 Unix 内核系统中关闭了 safe_mode, 可以直接使用 is_writable()
        if (DIRECTORY_SEPARATOR == '/' and @ini_get("safe_mode") == FALSE) {
            return is_writable($file);
        }

        // 在 Windows 系统中打开了 safe_mode的情况
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5(mt_rand(1, 100) . mt_rand(1, 100));

            if (($fp = @fopen($file, 'ab')) === FALSE) {
                return FALSE;
            }

            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        } elseif (($fp = @fopen($file, 'ab')) === FALSE) {
            return FALSE;
        }

        fclose($fp);
        return TRUE;
    }
}

if (!function_exists('hello_user')) {
    /**
     * 提示语
     * @param string $name 用户名
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function hello_user($name){
        $date = date("H:i:s");
        if ($date >= '03:00:00' && $date < '06:00:00') {
            return t('greeting.early_morning', ['name' => $name]);
        } elseif ($date >= '06:00:00' && $date < '08:00:00') {
            return t('greeting.morning', ['name' => $name]);
        } elseif ($date >= '08:00:00' && $date < '11:00:00') {
            return t('greeting.forenoon', ['name' => $name]);
        } elseif ($date >= '11:00:00' && $date < '13:00:00') {
            return t('greeting.noon', ['name' => $name]);
        } elseif ($date >= '13:00:00' && $date < '17:00:00') {
            return t('greeting.afternoon', ['name' => $name]);
        } elseif ($date >= '17:00:00' && $date < '19:00:00') {
            return t('greeting.evening', ['name' => $name]);
        } elseif ($date >= '19:00:00' && $date < '23:00:00') {
            return t('greeting.night', ['name' => $name]) . '<i class="layui-icon layui-icon-heart-fill" style="color:red"></i>~';
        } else {
            return t('greeting.late_night', ['name' => $name]) . '<i class="layui-icon layui-icon-heart-fill" style="color:red"></i>~';
        }
    }
}
if (!function_exists('conf')) {

    /**
     * 获取系统配置
     * @param string $key 键值
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function conf($name)
    {
        $value = \think\facade\Cache::get($name);
        if(!empty($value)) return $value;
        $row = Db::name('config')->where('name', $name)->find();
        if(!$row) return null;
        if($row['type'] == 'array') {
            $value = json_decode($row['value'], true);
        }elseif($row['type'] == 'checkbox'){
            $value = explode(',', $row['value']);
        }else{
            $value = $row['value'];
        }
        \think\facade\Cache::tag('SF_Set')->set($name, $value);
        return $value;
    }

}
if (!function_exists('feature_enabled')) {

    /**
     * 功能访问开关，配置值为 0 时关闭。
     */
    function feature_enabled($name)
    {
        $value = conf($name);
        return (string)$value !== '0';
    }

}
if (!function_exists('feature_filter_menus')) {

    /**
     * 根据功能访问开关过滤左侧菜单。
     */
    function feature_filter_menus(array $menus)
    {
        $filtered = [];
        foreach ($menus as $menu) {
            $name = (string)($menu['name'] ?? '');
            $url = (string)($menu['url'] ?? '');

            if (!feature_enabled('feature_point_exchange_enabled') && feature_menu_matches($name, $url, 'point_exchange')) {
                continue;
            }
            if (!feature_enabled('feature_admin_plugin_enabled') && feature_menu_matches($name, $url, 'admin_plugin')) {
                continue;
            }
            if (!feature_enabled('feature_user_plugin_enabled') && feature_menu_matches($name, $url, 'user_plugin')) {
                continue;
            }
            if (!feature_enabled('feature_withdraw_enabled') && feature_menu_matches($name, $url, 'withdraw')) {
                continue;
            }

            if (!empty($menu['children']) && is_array($menu['children'])) {
                $menu['children'] = feature_filter_menus($menu['children']);
                if (empty($menu['children']) && in_array($url, ['', '#'], true)) {
                    continue;
                }
            }
            $filtered[] = $menu;
        }

        return array_values($filtered);
    }

}
if (!function_exists('feature_menu_matches')) {

    function feature_menu_matches(string $name, string $url, string $type)
    {
        switch ($type) {
            case 'point_exchange':
                return strpos($url, 'PointExchange/') !== false
                    || strpos($url, 'PointProduct/') !== false
                    || in_array($name, ['积分兑换', '积分商品', '兑换记录'], true);
            case 'admin_plugin':
                return strpos($url, 'Plugin/') !== false
                    || strpos($url, 'PluginOrder/') !== false
                    || strpos($url, 'PluginComment/') !== false
                    || in_array($name, ['插件列表', '插件订单', '插件评论'], true);
            case 'user_plugin':
                return strpos($url, 'UserPlugin/') !== false
                    || strpos($url, '/UserPlugin/') !== false
                    || in_array($name, ['发布插件', '插件市场', '我的插件', '评论管理', '我的购买'], true);
            case 'withdraw':
                return strpos($url, 'Withdraw/') !== false
                    || strpos($url, 'Order/withdraw') !== false
                    || in_array($name, ['提现记录', '提现管理'], true);
            default:
                return false;
        }
    }

}
if (!function_exists('__')) {

    /**
     * 获取语言变量值
     * @param string $name 语言变量名
     * @param array $vars 动态变量值
     * @param string $lang 语言
     * @return mixed 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function __($name, $vars = [], $lang = '')
    {
        if (is_numeric($name) || !$name) {
            return $name;
        }
        if (!is_array($vars)) {
            $vars = func_get_args();
            array_shift($vars);
            $lang = '';
        }
        return lang($name, $vars, $lang);
    }

}

if (!function_exists('t')) {
    /**
     * Lightweight i18n helper. Use dot keys such as common.save or auth.login.
     * Lang::get() internally wraps keys with {:} so callers use bare keys.
     *
     * Usage:
     *   t('common.save')                 // simple
     *   t('greeting.hello', ['name' => 'World'])  // with vars
     */
    function t($name, $vars = [], $lang = '')
    {
        static $loaded = [];
        static $detected = false;

        if (!$detected) {
            $detected = true;
            $request = app()->request;
            $langConfig = app()->config->get('lang');
            $langSet = $langConfig['default_lang'] ?? 'en-us';
            $normalizer = function ($value) use ($langConfig) {
                $value = strtolower(trim((string)$value));
                if ($value === '') {
                    return '';
                }
                $value = str_replace('_', '-', $value);
                $map = $langConfig['accept_language'] ?? [];
                if (isset($map[$value])) {
                    return $map[$value];
                }
                if (strpos($value, 'zh') === 0) {
                    return 'zh-cn';
                }
                if (strpos($value, 'en') === 0) {
                    return 'en-us';
                }
                return '';
            };

            if ($request->get($langConfig['detect_var'] ?? 'lang')) {
                $langSet = $normalizer($request->get($langConfig['detect_var']));
            } elseif ($request->header($langConfig['header_var'] ?? 'think-lang')) {
                $langSet = $normalizer($request->header($langConfig['header_var']));
            } elseif ($request->cookie($langConfig['cookie_var'] ?? 'think_lang')) {
                $langSet = $normalizer($request->cookie($langConfig['cookie_var']));
            } elseif ($request->server('HTTP_ACCEPT_LANGUAGE')) {
                $accepted = explode(',', (string)$request->server('HTTP_ACCEPT_LANGUAGE'));
                $langSet = $normalizer($accepted[0] ?? '');
            }

            if (!in_array($langSet, ['zh-cn', 'en-us'])) {
                $langSet = 'en-us';
            }
            app()->lang->setLangSet($langSet);
        }

        $range = $lang ? strtolower(str_replace('_', '-', $lang)) : app()->lang->getLangSet();
        if ($range === 'zh') {
            $range = 'zh-cn';
        } elseif ($range === 'en') {
            $range = 'en-us';
        }

        if (empty($loaded[$range])) {
            $langFile = app()->getRootPath() . 'app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $range . '.php';
            if (is_file($langFile)) {
                app()->lang->load($langFile, $range);
            }
            $loaded[$range] = true;
        }
        $text = __($name, $vars, $range);
        return ($text === '' || $text === $name) ? $name : $text;
    }
}

if (!function_exists('array2xml')) {

    /**
     * 数组转XML
     * @param array $arr 数据源
     * @param bool $ignore XML解析器忽略
     * @param int $level 层级
     * @return string|string[]|null 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function array2xml($arr, $ignore = true, $level = 1)
    {
        $s = $level == 1 ? "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n<root>\r\n" : '';
        $space = str_repeat("\t", $level);
        foreach ($arr as $k => $v) {
            if (!is_array($v)) {
                $s .= $space . "<item id=\"$k\">" . ($ignore ? '<![CDATA[' : '') . $v . ($ignore ? ']]>' : '')
                    . "</item>\r\n";
            } else {
                $s .= $space . "<item id=\"$k\">\r\n" . array2xml($v, $ignore, $level + 1) . $space . "</item>\r\n";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</root>" : $s;
    }

}

if (!function_exists('xml2array')) {

    /**
     * XML转数组
     * @param string $xml xml格式内容
     * @param bool $isnormal
     * @return array
     */
    /**
     * xml转数组
     * @param $xml xml文本内容
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function xml2array(&$xml)
    {
        $xml = "<xml>";
        foreach ($xml as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

}

if (!function_exists('array_sort')) {

    /**
     * 二位数组排序
     * @param array $arr 数据源
     * @param string $keys KEY
     * @param bool $desc 排序方式（默认：asc）
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function array_sort($arr, $keys, $desc = false)
    {
        $key_value = $new_array = array();
        foreach ($arr as $k => $v) {
            $key_value[$k] = $v[$keys];
        }
        if ($desc) {
            arsort($key_value);
        } else {
            asort($key_value);
        }
        reset($key_value);
        foreach ($key_value as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }
}

if (!function_exists('array_merge_multiple')) {

    /**
     * 多维数组合并
     * @param array $array1 数组1
     * @param array $array2 数组2
     * @return array 返回合并数组
     * @author 陌上花开
     * @date 2022-01-19
     */
    function array_merge_multiple($array1, $array2)
    {
        $merge = $array1 + $array2;
        $data = [];
        foreach ($merge as $key => $val) {
            if (isset($array1[$key])
                && is_array($array1[$key])
                && isset($array2[$key])
                && is_array($array2[$key])
            ) {
                $data[$key] = array_merge_multiple($array1[$key], $array2[$key]);
            } else {
                $data[$key] = isset($array2[$key]) ? $array2[$key] : $array1[$key];
            }
        }
        return $data;
    }
}

if (!function_exists('array_key_value')) {
    /**
     * 获取数组中某个字段的所有值
     * @param $arr 数组
     * @param string $name 字段值
     * @return array
     * @since 2021/2/1
     * @author 陌上花开
     */
    function array_key_value($arr, $name = "")
    {
        $return = array();
        if ($arr) {
            foreach ($arr as $key => $val) {
                if ($name) {
                    $return[] = $val[$name];
                } else {
                    $return[] = $key;
                }
            }
        }
        $return = array_unique($return);
        return $return;
    }
}

if (!function_exists('curl_url')) {

    /**
     * 获取当前访问的完整URL
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function curl_url()
    {
        $pageURL = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === 'on') {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

}

if (!function_exists('curl_get')) {

    /**
     * curl请求(GET)
     * @param string $url 请求地址
     * @param array $data 请求参数
     * @return bool|string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function curl_get($url, $data = [])
    {
        // 处理get数据
        if (!empty($data)) {
            $url = $url . '?' . http_build_query($data);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}

if (!function_exists('curl_post')) {

    /**
     * curl请求(POST)
     * @param string $url 请求地址
     * @param array $data 请求参数
     * @return bool|string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function curl_post($url, $data = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}

if (!function_exists('curl_request')) {

    /**
     * curl请求(支持get和post)
     * @param $url 请求地址
     * @param array $data 请求参数
     * @param string $type 请求类型(默认：post)
     * @param bool $https 是否https请求true或false
     * @return bool|string 返回请求结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function curl_request($url, $data = [], $type = 'post', $https = false)
    {
        // 初始化
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        // 设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 是否要求返回数据
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($https) {
            // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            // 从证书中检查SSL加密算法是否存在
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if (strtolower($type) == 'post') {
            // 设置post方式提交
            curl_setopt($ch, CURLOPT_POST, true);
            // 提交的数据
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } elseif (!empty($data) && is_array($data)) {
            // get网络请求
            $url = $url . '?' . http_build_query($data);
        }
        // 设置抓取的url
        curl_setopt($ch, CURLOPT_URL, $url);
        // 执行命令
        $result = curl_exec($ch);
        if ($result === false) {
            return false;
        }
        // 关闭URL请求(释放句柄)
        curl_close($ch);
        return $result;
    }
}

if (!function_exists('datetime')) {

    /**
     * 时间戳转日期格式
     * @param int $time 时间戳
     * @param string $format 转换格式(默认：Y-m-d h:i:s)
     * @return false|string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function datetime($time = null, $format = 'Y-m-d H:i:s')
    {
        if (empty($time)) {
            $time = time();
        }
        $time = is_numeric($time) ? $time : strtotime($time);
        return date($format, $time);
    }

}

if (!function_exists('get_curl')) {
    function get_curl($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0, $addheader = 0, $split = 0){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $httpheader[] = "Accept: */*";
        $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        if ($addheader) {
            $httpheader = array_merge($httpheader, $addheader);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($referer) {
            if ($referer == 1) {
                curl_setopt($ch, CURLOPT_REFERER, 'http://m.qzone.com/infocenter?g_f=');
            } else {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }
        }
        if ($ua) {
            curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        }

        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }

        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        if ($split) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($ret, 0, $headerSize);
            $body = substr($ret, $headerSize);
            $ret=array();
            $ret['header']=$header;
            $ret['body']=$body;
        }
        curl_close($ch);
        return $ret;
    }
}
if (!function_exists('data_auth_sign')) {

    /**
     * 数字签名认证
     * @param $data 签名认证数据
     * @return string
     * @author 陌上花开
     * @date 2019/2/1
     */
    function data_auth_sign($data)
    {
        //数据类型检测
        if (!is_array($data)) {
            $data = (array)$data;
        }
        // 排序
        ksort($data);
        // url编码并生成query字符串
        $code = http_build_query($data);
        //生成签名
        $sign = sha1($code);
        return $sign;
    }
}

if (!function_exists('decrypt')) {

    /**
     * DES解密
     * @param string $data 解密字符串
     * @param string $key 解密KEY
     * @return mixed
     * @author 陌上花开
     * @date 2022-01-19
     */
    function decrypt($data, $key = 'p@ssw0rd')
    {
        return openssl_decrypt($data, 'des-ecb', $key);
    }
}

if (!function_exists('encrypt')) {

    /**
     *
     * @param string $data 加密字符串
     * @param string $key 加密KEY
     * @return string
     * @author 陌上花开
     * @date 2022-01-19
     */
    function encrypt($data, $key = 'p@ssw0rd')
    {
        return openssl_encrypt($data, 'des-ecb', $key);
    }
}

if (!function_exists('export_excel')) {

    /**
     * 数据导出Excel(csv文件)
     * @param string $file_name 文件名称
     * @param array $tile 标题
     * @param array $data 数据源
     * @author 陌上花开
     * @date 2022-01-19
     */
    function export_excel($file_name, $tile = [], $data = [])
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
        ob_end_clean();
        ob_start();
        header("Content-Type: text/csv");
        header("Content-Disposition:filename=" . $file_name);
        $fp = fopen('php://output', 'w');
        // 转码 防止乱码(比如微信昵称)
        fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fp, $tile);
        $index = 0;
        foreach ($data as $item) {
            if ($index == 1000) {
                $index = 0;
                ob_flush();
                flush();
            }
            $index++;
            fputcsv($fp, $item);
        }
        ob_flush();
        flush();
        ob_end_clean();
    }
}

if (!function_exists('format_bytes')) {

    /**
     * 将字节转换为可读文本
     * @param int $size 字节大小
     * @param string $delimiter 分隔符
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function format_bytes($size, $delimiter = '')
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 6; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . $delimiter . $units[$i];
    }

}

if (!function_exists('get_guid_v4')) {

    /**
     * 获取唯一性GUID
     * @param bool $trim 是否去除{}
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_guid_v4($trim = true)
    {
        // Windows
        if (function_exists('com_create_guid') === true) {
            $charid = com_create_guid();
            return $trim == true ? trim($charid, '{}') : $charid;
        }
        // OSX/Linux
        if (function_exists('openssl_random_pseudo_bytes') === true) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
        // Fallback (PHP 4.2+)
        mt_srand((double)microtime() * 10000);
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45);                  // "-"
        $lbrace = $trim ? "" : chr(123);    // "{"
        $rbrace = $trim ? "" : chr(125);    // "}"
        $guidv4 = $lbrace .
            substr($charid, 0, 8) . $hyphen .
            substr($charid, 8, 4) . $hyphen .
            substr($charid, 12, 4) . $hyphen .
            substr($charid, 16, 4) . $hyphen .
            substr($charid, 20, 12) .
            $rbrace;
        return $guidv4;
    }
}

if (!function_exists('getter')) {

    /**
     * 获取数组的下标值
     * @param array $data 数据源
     * @param string $field 字段名称
     * @param string $default 默认值
     * @return mixed|string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function getter($data, $field, $default = '')
    {
        $result = $default;
        if (isset($data[$field])) {
            if (is_array($data[$field])) {
                $result = $data[$field];
            } else {
                $result = trim($data[$field]);
            }
        }
        return $result;
    }
}

if (!function_exists('sf_secure_token')) {

    /**
     * 生成密码学安全的十六进制令牌
     *
     * 安全用途（授权码、下载票据、用户令牌、密钥）一律使用本函数，
     * 不得再使用 rand() / uniqid() / md5(time()...) 等可预测来源。
     *
     * @param int $bytes 随机字节数，输出长度为 $bytes * 2
     * @return string 小写十六进制字符串
     * @since 2026-08-16 P0 安全加固
     */
    function sf_secure_token($bytes = 16)
    {
        $bytes = max(8, (int)$bytes);
        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('sf_generate_authcode')) {

    /**
     * 生成授权码
     *
     * 修复 A-01：原实现为 md5(time() . $qq . 'SF')，三项输入中 'SF' 是源码常量、
     * $qq 业务上公开、time() 仅秒级熵，可在极小候选空间内离线推导出他人授权码。
     *
     * 现改为 CSPRNG。输出仍为 32 位十六进制，与既有 VARCHAR(32) 列和
     * 客户端长度假设完全兼容，属于可直接替换的实现。
     *
     * @return string 32 位小写十六进制授权码
     * @since 2026-08-16 P0 安全加固
     */
    function sf_generate_authcode()
    {
        return sf_secure_token(16);
    }
}

if (!function_exists('get_random_str')) {

    /**
     * 生成随机字符串
     * @param int $length 生成长度
     * @param int $type 生成类型：0-小写字母+数字，1-小写字母，2-大写字母，3-数字，4-小写+大写字母，5-小写+大写+数字
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     *
     * 注意：本函数使用 rand()，属于非密码学安全随机数，仅可用于验证码、
     * 文件名等非安全场景。授权码、令牌、密钥等一律改用 sf_secure_token()。
     */
    function get_random_str($length = 8, $type = 0)
    {
        $a = 'abcdefghijklmnopqrstuvwxyz';
        $A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $n = '0123456789';

        switch ($type) {
            case 1:
                $chars = $a;
                break;
            case 2:
                $chars = $A;
                break;
            case 3:
                $chars = $n;
                break;
            case 4:
                $chars = $a . $A;
                break;
            case 5:
                $chars = $a . $A . $n;
                break;
            default:
                $chars = $a . $n;
        }

        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

}

if (!function_exists('get_random_code')) {

    /**
     * 获取指定位数的随机码
     * @param int $num 随机码长度
     * @return string 返回字符串
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_random_code($num = 12)
    {
        $codeSeeds = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeSeeds .= "abcdefghijklmnopqrstuvwxyz";
        $codeSeeds .= "0123456789_";
        $len = strlen($codeSeeds);
        $code = "";
        for ($i = 0; $i < $num; $i++) {
            $rand = rand(0, $len - 1);
            $code .= $codeSeeds[$rand];
        }
        return $code;
    }
}

if (!function_exists('get_server_ip')) {

    /**
     * 获取服务端IP地址
     * @return string 返回IP地址
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_server_ip()
    {
        if (isset($_SERVER)) {
            if ($_SERVER['SERVER_ADDR']) {
                $server_ip = $_SERVER['SERVER_ADDR'];
            } else {
                $server_ip = $_SERVER['LOCAL_ADDR'];
            }
        } else {
            $server_ip = getenv('SERVER_ADDR');
        }
        return $server_ip;
    }
}

if (!function_exists('get_client_ip')) {

    /**
     * 获取客户端IP地址
     * @param int $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param bool $adv 否进行高级模式获取（有可能被伪装）
     * @return mixed 返回IP
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_client_ip($type = 0, $adv = false)
    {
        $type = $type ? 1 : 0;
        static $ip = null;
        if ($ip !== null) {
            return $ip[$type];
        }
        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }

}

if (!function_exists('get_zodiac_sign')) {


    /**
     * 根据月、日获取星座
     *
     * @param unknown $month 月
     * @param unknown $day 日
     * @return boolean|multitype:
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_zodiac_sign($month, $day)
    {
        // 检查参数有效性
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return false;
        }

        // 星座名称以及开始日期
        $signs = array(
            array("20" => t('zodiac.aquarius')),
            array("19" => t('zodiac.pisces')),
            array("21" => t('zodiac.aries')),
            array("20" => t('zodiac.taurus')),
            array("21" => t('zodiac.gemini')),
            array("22" => t('zodiac.cancer')),
            array("23" => t('zodiac.leo')),
            array("23" => t('zodiac.virgo')),
            array("23" => t('zodiac.libra')),
            array("24" => t('zodiac.scorpio')),
            array("22" => t('zodiac.sagittarius')),
            array("22" => t('zodiac.capricorn'))
        );
        list($sign_start, $sign_name) = each($signs[(int)$month - 1]);
        if ($day < $sign_start) {
            list($sign_start, $sign_name) = each($signs[($month - 2 < 0) ? $month = 11 : $month -= 2]);
        }
        return $sign_name;
    }

}

if (!function_exists('get_format_time')) {

    /**
     * 获取格式化显示时间
     * @param int $time 时间戳
     * @return false|string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_format_time($time)
    {
        $time = (int)substr($time, 0, 10);
        $int = time() - $time;
        $str = '';
        if ($int <= 2) {
            $str = t('time.just_now');
        } elseif ($int < 60) {
            $str = t('time.seconds_ago', ['seconds' => $int]);
        } elseif ($int < 3600) {
            $str = t('time.minutes_ago', ['minutes' => floor($int / 60)]);
        } elseif ($int < 86400) {
            $str = t('time.hours_ago', ['hours' => floor($int / 3600)]);
        } elseif ($int < 1728000) {
            $str = t('time.days_ago', ['days' => floor($int / 86400)]);
        } else {
            $str = date(t('time.year_month_day'), $time);
        }
        return $str;
    }

}

if (!function_exists('check_url')) {

    /**
     * 判断是否为URL
     * @return boolean 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function check_url($url){
        $preg = "/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is";
        if(preg_match($preg,$url)){
            return true;
        }else{
            return false;
        } 
    }
}
if (!function_exists('get_device_type')) {

    /**
     * 获取设备类型(苹果或安卓)
     * @return int 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_device_type()
    {
        // 全部变成小写字母
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $type = 0;
        // 分别进行判断
        if (strpos($agent, 'iphone') !== false || strpos($agent, 'ipad') !== false) {
            $type = 1;
        }
        if (strpos($agent, 'android') !== false) {
            $type = 2;
        }
        return $type;
    }

}

if (!function_exists('get_password')) {

    /**
     * 获取双MD5加密密码
     * @param string $password 加密字符串
     * @return string 返回结果
     * @author 陌上花开
     * @date 2019/4/5
     */
    function get_password($password)
    {
        return md5(md5($password));
    }

}

if (!function_exists('sf_password_make')) {
    /**
     * 使用 PHP 当前推荐算法生成密码哈希。
     *
     * 数据库中的 password 字段为 varchar(150)，可容纳 bcrypt/Argon2 哈希。
     */
    function sf_password_make($password)
    {
        $hash = password_hash((string) $password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('Password hashing failed.');
        }
        return $hash;
    }
}

if (!function_exists('sf_password_verify')) {
    /**
     * 验证现代哈希及历史密码格式。
     *
     * 历史生产环境曾直接保存密码，后续版本又使用 md5(md5(password))。
     * 兼容分支只用于迁移；成功验证历史格式后由调用方立即写回现代哈希。
     *
     * @param string $password 用户提交的明文密码
     * @param string $stored   数据库中保存的值
     * @param bool   $needsRehash 是否应在本次成功登录后重哈希
     */
    function sf_password_verify($password, $stored, &$needsRehash = false)
    {
        $password = (string) $password;
        $stored = (string) $stored;
        $needsRehash = false;

        if ($stored === '') {
            return false;
        }

        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            if (!password_verify($password, $stored)) {
                return false;
            }
            $needsRehash = password_needs_rehash($stored, PASSWORD_DEFAULT);
            return true;
        }

        if (hash_equals($stored, $password) || hash_equals($stored, get_password($password))) {
            $needsRehash = true;
            return true;
        }

        return false;
    }
}

if (!function_exists('get_image_url')) {

    /**
     * 获取网络图片地址
     * @param string $image_url 图片地址
     * @return string 输出网络图片地址
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_image_url($image_url)
    {
        return IMG_URL . $image_url;
    }

}

if (!function_exists('get_uid')) {

    /**
     * 获取管理员登录ID
     * @return bool
     * @author 陌上花开
     * @date 2019/2/1
     */
    function get_uid()
    {
        $adminInfo = session('adminInfo');
        if (session('admin_auth_sign') == data_auth_sign($adminInfo)) {
            return $adminInfo['id'];
        } else {
            return false;
        }
    }
}
if (!function_exists('is_https')) {
    /**
     * 判断是否为https
     * @return boolean
     * @author 陌上花开
     * @date 2022-01-19
     */
    function is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        return false;
    }
}
if (!function_exists('is_email')) {

    /**
     * 判断是否为邮箱
     * @param string $str 邮箱
     * @return boolean
     * @author 陌上花开
     * @date 2022-01-19
     */
    function is_email($str)
    {
        return preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/', $str);
    }

}

if (!function_exists('is_mobile')) {

    /**
     * 判断是否为手机号
     * @param string $mobile 手机号码
     * @return false 返回结果true或false
     * @author 陌上花开
     * @date 2022-01-19
     */
    function is_mobile($mobile)
    {
//        return preg_match('/^1(3|4|5|6|7|8|9)\d{9}$/', $mobile);
        return preg_match('/^1[3456789]{1}\d{9}$/', $mobile);
    }

}

if (!function_exists('is_zipcode')) {

    /**
     * 验证邮编是否正确
     * @param string $code 邮编
     * @return false 返回结果true或false
     * @author 陌上花开
     * @date 2022-01-19
     */
    function is_zipcode($code)
    {
        return preg_match('/^[1-9][0-9]{5}$/', $code);
    }

}

if (!function_exists('is_idcard')) {

    /**
     * 验证身份证是否正确
     * @param string $idno 身份证号
     * @return bool 返回结果true或false
     * @author 陌上花开
     * @date 2022-01-19
     */
    function is_idcard($idno)
    {
        $idno = strtoupper($idno);
        $regx = '/(^\d{15}$)|(^\d{17}([0-9]|X)$)/';
        $arr_split = array();
        if (!preg_match($regx, $idno)) {
            return false;
        }
        // 检查15位
        if (15 == strlen($idno)) {
            $regx = '/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/';
            @preg_match($regx, $idno, $arr_split);
            $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
            if (!strtotime($dtm_birth)) {
                return false;
            } else {
                return true;
            }
        } else {
            // 检查18位
            $regx = '/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/';
            @preg_match($regx, $idno, $arr_split);
            $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
            // 检查生日日期是否正确
            if (!strtotime($dtm_birth)) {
                return false;
            } else {
                // 检验18位身份证的校验码是否正确。
                // 校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
                $arr_int = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
                $arr_ch = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
                $sign = 0;
                for ($i = 0; $i < 17; $i++) {
                    $b = (int)$idno[$i];
                    $w = $arr_int[$i];
                    $sign += $b * $w;
                }
                $n = $sign % 11;
                $val_num = $arr_ch[$n];
                if ($val_num != substr($idno, 17, 1)) {
                    return false;
                } else {
                    return true;
                }
            }
        }
    }

}

if (!function_exists('is_empty')) {

    /**
     * 判断是否为空
     * @param $value 参数值
     * @return bool 返回结果true或false
     * @author 陌上花开
     * @date 2022-01-19
     */
    function is_empty($value)
    {
        // 判断是否存在该值
        if (!isset($value)) {
            return true;
        }

        // 判断是否为empty
        if (empty($value)) {
            return true;
        }

        // 判断是否为null
        if ($value === null) {
            return true;
        }

        // 判断是否为空字符串
        if (trim($value) === '') {
            return true;
        }

        // 默认返回false
        return false;
    }
}

if (!function_exists('mkdirs')) {

    /**
     * 递归创建目录
     * @param string $dir 需要创建的目录路径
     * @param int $mode 权限值
     * @return bool 返回结果true或false
     * @author 陌上花开
     * @date 2022-01-19
     */
    function mkdirs($dir, $mode = 0777)
    {
        if (is_dir($dir) || mkdir($dir, $mode, true)) {
            return true;
        }
        if (!mkdirs(dirname($dir), $mode)) {
            return false;
        }
        return mkdir($dir, $mode, true);
    }
}

if (!function_exists('rmdirs')) {

    /**
     * 删除文件夹
     * @param string $dir 文件夹路径
     * @param bool $rmself 是否删除本身true或false
     * @return bool 返回删除结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function rmdirs($dir, $rmself = true)
    {
        if (is_link($dir) || !is_dir($dir)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $success = true;
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->isLink()) {
                if (!@unlink($path)) {
                    $success = false;
                }
                continue;
            }
            $todo = ($file->isDir() ? 'rmdir' : 'unlink');
            if (!@$todo($path)) {
                $success = false;
            }
        }
        if ($rmself) {
            if (!@rmdir($dir)) {
                $success = false;
            }
        }

        return $success;
    }
}

if (!function_exists('copydirs')) {

    /**
     * 复制文件夹
     * @param string $source 原文件夹路径
     * @param string $dest 目的文件夹路径
     * @author 陌上花开
     * @date 2022-01-19
     */
    function copydirs($source, $dest)
    {
        if (is_link($source) || !is_dir($source)) {
            throw new RuntimeException('复制源目录无效');
        }
        if (is_link($dest)) {
            throw new RuntimeException('复制目标目录不能是符号链接');
        }
        if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
            throw new RuntimeException('无法创建复制目标目录');
        }
        $source = realpath($source);
        $dest = realpath($dest);
        if ($source === false || $dest === false) {
            throw new RuntimeException('无法解析复制目录');
        }
        $destPrefix = rtrim($dest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('复制目录不能包含符号链接');
            }
            $target = $destPrefix . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (is_link($target)) {
                    throw new RuntimeException('复制目标不能经过符号链接');
                }
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new RuntimeException('无法创建目标子目录');
                }
            } else {
                $parent = realpath(dirname($target));
                if ($parent === false || ($parent . DIRECTORY_SEPARATOR !== $destPrefix
                    && !str_starts_with($parent . DIRECTORY_SEPARATOR, $destPrefix))) {
                    throw new RuntimeException('复制目标超出允许目录');
                }
                if (is_link($target) || !copy($item->getPathname(), $target)) {
                    throw new RuntimeException('复制文件失败');
                }
            }
        }
    }
}

if (!function_exists('mbsubstr')) {
    /**
     * 字符串截取，支持中文和其他编码
     * @param string $str 需要转换的字符串
     * @param int $start 开始位置
     * @param int $length 截取长度
     * @param string $encoding 编码格式
     * @param string $suffix 截断显示字符
     * @return false|mixed|string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function mbsubstr($str, $start = 0, $length = null, $encoding = "utf-8", $suffix = '...')
    {
        if (function_exists("mb_substr")) {
            $slice = mb_substr($str, $start, $length, $encoding);
        } elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $encoding);
            if (false === $slice) {
                $slice = '';
            }
        } else {
            $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$encoding], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
        }
        return $suffix ? $slice . $suffix : $slice;
    }
}

if (!function_exists('result')) {

    /**
     * 消息数组
     * @param string $msg 提示文字
     * @param bool $success 是否成功true或false
     * @param array $data 结果数据
     * @param int $code 编码
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function result($msg = "系统繁忙，请稍候再试", $success = true, $data = [], $code = 0)
    {
        $result = ['msg' => $msg, 'data' => $data, 'success' => $success];
        if ($success) {
            // 成功统一返回0
            $result['code'] = 0;
        } else {
            // 失败状态(可配置常用状态码)
            $result['code'] = $code ? $code : -1;
        }
        return $result;
    }
}

if (!function_exists('json_message')) {

    /**
     * 消息数组
     * @param string $msg 提示文字
     * @param bool $success 是否成功true或false
     * @param array $data 结果数据
     * @param int $code 编码
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function json_message($msg = "系统繁忙，请稍候再试", $success = true, $data = [], $code = 0)
    {
        $result = ['msg' => $msg, 'data' => $data, 'success' => $success];
        if ($success) {
            // 成功统一返回0
            $result['code'] = 0;
        } else {
            // 失败状态(可配置常用状态码)
            $result['code'] = $code ? $code : -1;
        }
        return json_encode($result);
    }
}

if (!function_exists('message')) {

    /**
     * 消息数组
     * @param string $msg 提示文字
     * @param bool $success 是否成功true或false
     * @param array $data 结果数据
     * @param int $code 编码
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function message($msg = "系统繁忙，请稍候再试", $success = true, $data = [], $code = 0)
    {
        // Allow callers to pass i18n keys without changing the response schema.
        if (is_string($msg) && preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/i', $msg)) {
            $translated = t($msg);
            $msg = $translated === $msg ? $msg : $translated;
        }
        $result = ['msg' => $msg, 'data' => $data, 'success' => $success];
        if ($success) {
            // 成功统一返回0
            $result['code'] = 0;
        } else {
            // 失败状态(可配置常用状态码)
            $result['code'] = $code ? $code : -1;
        }
        return $result;
    }
}

if (!function_exists('plugin_category_label')) {
    function plugin_category_label($key) {
        $map = ['feature'=>'功能增强','security'=>'安全防护','content'=>'内容管理','ui'=>'界面美化','payment'=>'支付集成','dev'=>'开发工具','analytics'=>'数据分析','social'=>'社交互动','other'=>'其他'];
        return $map[$key] ?? '其他';
    }
}

if (!function_exists('format_num')) {

    /**
     * 格式化阅读量等数字单位
     * @param $num
     * @return string
     * @author 陌上花开
     * @date 2022-01-19
     */
    function format_num($num)
    {
        if ($num >= 10000) {
            $num = round($num / 10000 * 100) / 100 . 'W';
        } elseif ($num >= 1000) {
            $num = round($num / 1000 * 100) / 100 . 'K';
        } else {
            $num = $num;
        }
        return $num;
    }
}

if (!function_exists('object_array')) {

    /**
     * 对象转数组
     * @param $object 对象
     * @return mixed 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function object_array($object)
    {
        //先编码成json字符串，再解码成数组
        return json_decode(json_encode($object), true);
    }
}
if (!function_exists('parse_attr')) {

    /**
     * 配置值解析成数组
     * @param string $value 参数值
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function parse_attr($value = '')
    {
        if (is_array($value)) {
            return $value;
        }
        $array = preg_split('/[,;\r\n]+/', trim($value, ",;\r\n"));
        if (strpos($value, ':')) {
            $value = array();
            foreach ($array as $val) {
                list($k, $v) = explode(':', $val);
                $value[$k] = $v;
            }
        } else {
            $value = $array;
        }
        return $value;
    }
}

if (!function_exists('strip_html_tags')) {

    /**
     * 去除HTML标签、图像等 仅保留文本
     * @param string $str 字符串
     * @param int $length 长度
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function strip_html_tags($str, $length = 0)
    {
        // 把一些预定义的 HTML 实体转换为字符
        $str = htmlspecialchars_decode($str);
        // 将空格替换成空
        $str = str_replace("&nbsp;", "", $str);
        // 函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
        $str = strip_tags($str);
        $str = str_replace(array("\n", "\r\n", "\r"), ' ', $str);
        $preg = '/<script[\s\S]*?<\/script>/i';
        // 剥离JS代码
        $str = preg_replace($preg, "", $str, -1);
        if ($length == 2) {
            // 返回字符串中的前100字符串长度的字符
            $str = mb_substr($str, 0, $length, "utf-8");
        }
        return $str;
    }

}

if (!function_exists('strip_html_tags2')) {

    /**
     * 去除指定HTML标签
     * @param string $str 字符串
     * @param $tags 指定的标签
     * @param int $content 是否删除标签内的内容 0保留内容 1不保留内容
     * @return string 返回结果
     * 示例：echo strip_html_tags($str, array('a','img'))
     * @author 陌上花开
     * @date 2022-01-19
     */
    function strip_html_tags2($str, $tags, $content = 0)
    {
        if ($content) {
            $html = array();
            foreach ($tags as $tag) {
                $html[] = '/(<' . $tag . '.*?>[\s|\S]*?<\/' . $tag . '>)/';
            }
            $result = preg_replace($html, '', $str);
        } else {
            $html = array();
            foreach ($tags as $tag) {
                $html[] = "/(<(?:\/" . $tag . "|" . $tag . ")[^>]*>)/i";
            }
            $result = preg_replace($html, '', $str);
        }
        return $result;
    }

}

if (!function_exists('sub_str')) {

    /**
     * 字符串截取
     * @param string $str 需要截取的字符串
     * @param int $start 开始位置
     * @param int $length 截取长度
     * @param bool $suffix 截断显示字符
     * @param string $charset 编码格式
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function sub_str($str, $start = 0, $length = 10, $suffix = true, $charset = "utf-8")
    {
        if (function_exists("mb_substr")) {
            $slice = mb_substr($str, $start, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $charset);
        } else {
            $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
        }
        $omit = mb_strlen($str) >= $length ? '...' : '';
        return $suffix ? $slice . $omit : $slice;
    }

}

if (!function_exists('cutstr_html')) {

    /**
     * 提取纯文本
     * @param $str 原字符串
     * @return string 过滤后的字符串
     * @author 陌上花开
     * @date 2022-01-19
     */
    function cutstr_html($str)
    {
        $str = trim(strip_tags($str)); //清除字符串两边的空格
        $str = preg_replace("/\t/", "", $str); //使用正则表达式替换内容，如：空格，换行，并将替换为空。
        $str = preg_replace("/\r\n/", "", $str);
        $str = preg_replace("/\r/", "", $str);
        $str = preg_replace("/\n/", "", $str);
        $str = preg_replace("/ /", "", $str);
        $str = preg_replace("/  /", "", $str);  //匹配html中的空格
        return trim($str); //返回字符串
    }
}

if (!function_exists('save_image')) {

    /**
     * 保存图片
     * @param string $img_url 网络图片地址
     * @param string $save_dir 图片保存目录
     * @return string 返回路径
     * @author 陌上花开
     * @date 2022-01-19
     */
    function save_image($img_url, $save_dir = '/')
    {
        if (!$img_url) {
            return false;
        }
        $save_dir = trim($save_dir, "/");
        $imgExt = pathinfo($img_url, PATHINFO_EXTENSION);
        // 是否是本站图片
        if (strpos($img_url, IMG_URL) !== false) {
            // 是否是临时文件
            if (strpos($img_url, 'temp') === false) {
                return str_replace(IMG_URL, "", $img_url);
            }
            $new_path = create_image_path($save_dir, $imgExt);
            $old_path = str_replace(IMG_URL, ATTACHMENT_PATH, $img_url);
            if (!file_exists($old_path)) {
                return false;
            }
            rename($old_path, IMG_PATH . $new_path);
            return str_replace(ATTACHMENT_PATH, "", IMG_PATH) . $new_path;
        } else {
            // 保存远程图片
            $new_path = save_remote_image($img_url, $save_dir);
        }
        return $new_path;
    }
}

if (!function_exists('create_image_path')) {

    /**
     * 创建图片存储目录
     * @param string $save_dir 存储目录
     * @param string $image_ext 图片后缀
     * @param string $image_root 图片存储根目录路径
     * @return string 返回文件目录
     * @author 陌上花开
     * @date 2022-01-19
     */
    function create_image_path($save_dir = "", $image_ext = "", $image_root = IMG_PATH)
    {
        $image_dir = date("/Ymd/");
        if ($image_dir) {
            $image_dir = ($save_dir ? "/" : '') . $save_dir . $image_dir;
        }
        // 未指定后缀默认使用JPG
        if (!$image_ext) {
            $image_ext = "jpg";
        }
        $image_path = $image_root . $image_dir;
        if (!is_dir($image_path)) {
            // 创建目录并赋予权限
            mkdir($image_path, 0777, true);
        }
        $file_name = substr(md5(time() . rand(0, 999999)), 8, 16) . rand(100, 999) . ".{$image_ext}";
        $file_path = $image_dir . $file_name;
        return $file_path;
    }
}

if (!function_exists('save_remote_image')) {

    /**
     * 保存网络图片到本地
     * @param string $img_url 网络图片地址
     * @param string $save_dir 保存目录
     * @return bool|string 图片路径
     * @author 陌上花开
     * @date 2022-01-19
     */
    function save_remote_image($img_url, $save_dir = '/')
    {
        $content = file_get_contents($img_url);
        if (!$content) {
            return false;
        }
        if ($content[0] . $content[1] == "\xff\xd8") {
            $image_ext = 'jpg';
        } elseif ($content[0] . $content[1] . $content[2] == "\x47\x49\x46") {
            $image_ext = 'gif';
        } elseif ($content[0] . $content[1] . $content[2] == "\x89\x50\x4e") {
            $image_ext = 'png';
        } else {
            // 不是有效图片
            return false;
        }
        $save_path = create_image_path($save_dir, $image_ext);
        return file_put_contents(IMG_PATH . $save_path, $content) ? str_replace(ATTACHMENT_PATH, "", IMG_PATH) . $save_path : false;
    }
}

if (!function_exists('save_image_content')) {

    /**
     * 富文本信息处理
     * @param string $content 富文本内容
     * @param bool $title 标题
     * @param string $path 图片存储路径
     * @return bool|int 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function save_image_content(&$content, $title = false, $path = 'article')
    {
        // 图片处理
        preg_match_all("/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i", str_ireplace("\\", "", $content), $match);
        if ($match[1]) {
            foreach ($match[1] as $id => $val) {
                $save_image = save_image($val, $path);
                if ($save_image) {
                    $content = str_replace($val, "[IMG_URL]" . $save_image, $content);
                }
            }
        }
        // 视频处理
        preg_match_all("/<embed .*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i", str_ireplace("\\", "", $content), $match2);
        if ($match2[1]) {
            foreach ($match2[1] as $vo) {
                $save_video = save_image($vo, $path);
                if ($save_video) {
                    $content = str_replace($vo, "[IMG_URL]" . str_replace(ATTACHMENT_PATH, "", IMG_PATH) . $save_video, $content);
                }
            }
        }
        // 提示标签替换
        if ((strpos($content, 'alt=\"\"') !== false) && $title) {
            $content = str_replace('alt=\"\"', 'alt=\"' . $title . '\"', $content);
        }
        return true;
    }
}
if (!function_exists('rich_text_has_content')) {

    /**
     * Determine whether rich text contains something users can actually see.
     * Editors commonly submit placeholders such as <p><br></p> after clearing.
     */
    function rich_text_has_content($content)
    {
        if ($content === null || trim((string)$content) === '') {
            return false;
        }

        $content = html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])?[^>\s"\']+/i', $content)) {
            return true;
        }

        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text);
        return $text !== '';
    }
}

if (!function_exists('clean_rich_text')) {

    if (!function_exists('sf_safe_url')) {
        /**
         * Accept an HTTP(S) URL or a site-root relative URL and reject
         * executable schemes, protocol-relative URLs and attribute breakers.
         */
        function sf_safe_url($url, $allowRelative = true)
        {
            $url = trim(html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '' || preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $url)) {
                return '';
            }
            if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                return $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return '';
            }
            $parts = parse_url($url);
            if (!is_array($parts)
                || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                return '';
            }
            return $url;
        }
    }

    if (!function_exists('sf_plain_text')) {
        function sf_plain_text($value, $maxLength = 0)
        {
            $value = trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $value = preg_replace('/[\x00-\x20\x7F]+/u', ' ', $value);
            $value = trim((string)preg_replace('/\s{2,}/u', ' ', $value));
            if ((int)$maxLength > 0 && mb_strlen($value, 'UTF-8') > (int)$maxLength) {
                $value = mb_substr($value, 0, (int)$maxLength, 'UTF-8');
            }
            return $value;
        }
    }

    if (!function_exists('sf_public_exception_message')) {
        /**
         * Preserve useful domain errors while preventing SQL, filesystem and
         * runtime internals from being reflected to public API clients.
         */
        function sf_public_exception_message($error, $fallback = '操作失败，请稍后重试')
        {
            if (!($error instanceof \Throwable)) {
                return (string)$fallback;
            }
            $class = strtolower(get_class($error));
            $message = sf_plain_text($error->getMessage(), 200);
            $internal = $error instanceof \Error
                || $error instanceof \PDOException
                || str_contains($class, 'think\\db\\exception')
                || preg_match('#(?:SQLSTATE|PDOException|Stack trace|/www/|/Users/|[A-Za-z]:\\\\|vendor/|app/[A-Za-z])#i', $message);
            if ($internal || $message === '') {
                try {
                    \think\facade\Log::error('[SF-PUBLIC-ERROR] ' . get_class($error) . ': ' . $error->getMessage());
                } catch (\Throwable $ignore) {
                }
                return (string)$fallback;
            }
            return $message;
        }
    }

    /**
     * Clean user-provided rich text while preserving common formatting tags.
     */
    function clean_rich_text($content)
    {
        if ($content === null || $content === '') {
            return '';
        }

        $content = html_entity_decode(stripslashes((string)$content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace("\0", '', $content);
        $content = preg_replace('/<\s*(script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base|svg|math)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $content);
        $content = preg_replace('/<\s*\/?\s*(script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base|svg|math)[^>]*>/is', '', $content);

        if (!class_exists('DOMDocument')) {
            $content = preg_replace('/\s+(?:on[a-z]+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content);
            $content = preg_replace('/\s+(href|src)\s*=\s*(?:([\'"])(?:(?!\2).)*\2|[^\s>]+)/i', '', $content);
            $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><span><div><blockquote><pre><code><ul><ol><li><table><thead><tbody><tr><th><td><h1><h2><h3><h4><h5><h6><a><img><hr>');
            return rich_text_has_content($content) ? trim($content) : '';
        }

        $allowedTags = array_fill_keys([
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'div',
            'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'table', 'thead',
            'tbody', 'tr', 'th', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'a', 'img', 'hr',
        ], true);
        $dropEntirely = array_fill_keys(['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true);
        $globalAttributes = ['class' => true, 'style' => true, 'title' => true];
        $tagAttributes = [
            'a' => ['href' => true, 'target' => true, 'rel' => true],
            'img' => ['src' => true, 'alt' => true, 'width' => true, 'height' => true],
            'td' => ['colspan' => true, 'rowspan' => true],
            'th' => ['colspan' => true, 'rowspan' => true],
            'ol' => ['start' => true],
        ];

        $sanitizeStyle = static function (string $style): string {
            if (preg_match('/(?:url\s*\(|expression\s*\(|@import|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding|[<>])/i', $style)) {
                return '';
            }
            $allowed = '/^(?:text-align|color|background-color|font-size|font-weight|font-style|text-decoration|line-height|width|height|max-width|min-width|margin(?:-(?:top|right|bottom|left))?|padding(?:-(?:top|right|bottom|left))?|border(?:-(?:top|right|bottom|left|color|style|width))?)$/i';
            $clean = [];
            foreach (explode(';', $style) as $declaration) {
                if (!str_contains($declaration, ':')) {
                    continue;
                }
                [$property, $value] = array_map('trim', explode(':', $declaration, 2));
                if (preg_match($allowed, $property) && $value !== '' && !preg_match('/[{}\\]/', $value)) {
                    $clean[] = strtolower($property) . ': ' . $value;
                }
            }
            return implode('; ', $clean);
        };

        $safeRichUrl = static function (string $url, bool $image = false): string {
            $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $compact = preg_replace('/[\x00-\x20\x7f]+/', '', $url);
            if ($url === '' || $compact !== $url || str_starts_with($url, '//')) {
                return '';
            }
            if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                return $url;
            }
            if (!$image && (str_starts_with($url, '#') || preg_match('/^(?:mailto|tel):/i', $url))) {
                return $url;
            }
            return sf_safe_url($url, false);
        };

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="sf-clean-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return '';
        }

        $cleanNode = function (\DOMNode $node) use (&$cleanNode, $allowedTags, $dropEntirely, $globalAttributes, $tagAttributes, $sanitizeStyle, $safeRichUrl): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if (!($child instanceof \DOMElement)) {
                    continue;
                }
                $tag = strtolower($child->tagName);
                if (!isset($allowedTags[$tag])) {
                    if (isset($dropEntirely[$tag])) {
                        $node->removeChild($child);
                    } else {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                    }
                    continue;
                }

                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (!isset($globalAttributes[$name]) && !isset($tagAttributes[$tag][$name])) {
                        $child->removeAttributeNode($attribute);
                        continue;
                    }
                    $value = (string)$attribute->value;
                    if ($name === 'href' || $name === 'src') {
                        $value = $safeRichUrl($value, $name === 'src');
                    } elseif ($name === 'style') {
                        $value = $sanitizeStyle($value);
                    } elseif ($name === 'target') {
                        $value = $value === '_blank' ? '_blank' : '';
                    } elseif (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'start'], true)) {
                        $value = preg_match('/^\d{1,4}$/D', $value) ? $value : '';
                    } elseif ($name === 'class') {
                        $value = preg_match('/^[A-Za-z0-9 _-]{1,200}$/D', $value) ? $value : '';
                    }
                    if ($value === '') {
                        $child->removeAttribute($name);
                    } else {
                        $child->setAttribute($name, $value);
                    }
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                $cleanNode($child);
            }
        };

        $root = $dom->getElementById('sf-clean-root');
        if (!$root) {
            return '';
        }
        $cleanNode($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return rich_text_has_content($result) ? trim($result) : '';
    }
}
if (!function_exists('sysmsg')) {

    /**
     * 提示页面
     * @param $type 1为成功，2为失败
     * @param $msg 提示信息
     * @param $url 跳转地址（留空则不跳转）
     * @param $time 跳转时间（默认：3s）
     * @param $die True为不继续执行，False为继续执行
     * @author 陌上花开
     * @date 2021-12-26
     */
    function sysmsg($msg = '', $type = 0, $url = '', $time = 3, $die = true)
    {
        if (empty($msg)) {
            $msg = t('common.unknown_error');
        }
        if ($type == 1) {
            $type = 'success';
        } else {
            $type = 'error';
        }
        $goBackLabel = t('common.go_back');
        $goHomeLabel = t('common.go_home');
        $jumpNowLabel = t('common.jump_now');
        $pageTitle = t('common.nice_tips');
        $redirectMsg = t('common.page_auto_redirect', ['time' => $time]);

        if (empty($url)) {
            $url = '<p class="clearfix">
                <a href="javascript:window.history.go(-1);" class="btn btn-grey">' . $goBackLabel . '</a>
                <a href="/" class="btn btn-primary">' . $goHomeLabel . '</a>
            </p>
            </div>';
        } else {
            $url = '<p class="jump">
                ' . $redirectMsg . '</p>
            <p class="clearfix">
                <a href="javascript:window.history.go(-1);" class="btn btn-grey">' . $goBackLabel . '</a>
                <a href="' . $url . '" class="btn btn-primary">' . $jumpNowLabel . '</a>
            </p>
        </div>
        <script type="text/javascript">
            (function () {
                var wait = document.getElementById(\'wait\');
                var interval = setInterval(function () {
                    var time = --wait.innerHTML;
                    if (time <= 0) {
                        location.href = "' . $url . '";
                        clearInterval(interval);
                    }
                }, 1000);
            })();
        </script>';
        }
        echo '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>' . $pageTitle . '</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="shortcut icon" href="/favicon.ico" />
            <style type="text/css">
                *{box-sizing:border-box;margin:0;padding:0;font-family:Lantinghei SC,Open Sans,Arial,Hiragino Sans GB,Microsoft YaHei,"微软雅黑",STHeiti,WenQuanYi Micro Hei,SimSun,sans-serif;-webkit-font-smoothing:antialiased}
                body{padding:70px 0;background:#edf1f4;font-weight:400;font-size:1pc;-webkit-text-size-adjust:none;color:#333}
                a{outline:0;color:#3498db;text-decoration:none;cursor:pointer}
                .system-message{margin:20px 5%;padding:40px 20px;background:#fff;box-shadow:1px 1px 1px hsla(0,0%,39%,.1);text-align:center}
                .system-message h1{margin:0;margin-bottom:9pt;color:#444;font-weight:400;font-size:40px}
                .system-message .jump,.system-message .image{margin:20px 0;padding:0;padding:10px 0;font-weight:400}
                .system-message .jump{font-size:14px}
                .system-message .jump a{color:#333}
                .system-message p{font-size:9pt;line-height:20px}
                .system-message .btn{display:inline-block;margin-right:10px;width:138px;height:2pc;border:1px solid #44a0e8;border-radius:30px;color:#44a0e8;text-align:center;font-size:1pc;line-height:2pc;margin-bottom:5px;}
                .success .btn{border-color:#69bf4e;color:#69bf4e}
                .error .btn{border-color:#ff8992;color:#ff8992}
                .info .btn{border-color:#3498db;color:#3498db}
                .copyright p{width:100%;color:#919191;text-align:center;font-size:10px}
                .system-message .btn-grey{border-color:#bbb;color:#bbb}
                .clearfix:after{clear:both;display:block;visibility:hidden;height:0;content:"."}
                @media (max-width:768px){body {padding:20px 0;}}
                @media (max-width:480px){.system-message h1{font-size:30px;}}
            </style>
        </head>
        <body>
        <div class="system-message ' . $type . '">
            <div class="image">
                <img src="/Assets/img/' . $type . '.svg" alt="" width="150" />
            </div>
            <h1>' . $msg . '</h1>
            
            ' . $url . '
        </body>
        </html>';

        if ($die == true) {
            exit ;
        }
    }
}
if (!function_exists('sf_store_managed_upload')) {
    /**
     * Return a browser-safe URL. OSS mode never silently falls back to local
     * storage, otherwise a partial outage would scatter uploads across disks.
     */
    function sf_store_managed_upload($file, string $saveDir, string $type, array $allowedExt, int $maxBytes): string
    {
        $storage = new PluginStorageService();
        if ($storage->isOssEnabled()) {
            $stored = $storage->storeUploadedFile($file, $type, $allowedExt, $maxBytes);
            if (empty($stored['url'])) {
                throw new \RuntimeException('云存储未返回可访问地址');
            }
            return (string)$stored['url'];
        }
        $saveName = (new LocalFilesystemService())->putFile($saveDir, $file);
        return str_replace('\\', '/', '/' . $saveName);
    }
}

if (!function_exists('upload_image')) {

    /**
     * 上传单张图片
     * @param string $form_name 文件表单名
     * @param string $save_dir 保存文件夹名
     * @param string $error 错误信息
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function upload_image($form_name = 'file', $save_dir = "", &$error = '')
    {
        // 获取文件对象
        $files = \request()->file($form_name);
        // 判断是否有上传的文件
        if (!$files) {
            $error = t('upload.please_select_image');
            return false;
        }

        try {
            // 允许上传的后缀
            $allowext = 'gif,GIF,jpg,JPG,jpeg,JPEG,png,PNG,bmp,BMP';
            // 上传路径
            $save_dir = empty($save_dir) ? 'temp' : $save_dir;
            if (is_array($files)) {
                $data = [];
                foreach ($files as $file) {
                    // 使用验证器验证上传的文件
                    validate(['file' => [
                        // 限制文件大小(单位b)，这里限制为4M
                        'filesize' => 10 * 1024 * 1024,
                        // 限制文件后缀，多个后缀以英文逗号分割
                        'fileExt' => $allowext,
                    ]])->check(['file' => $file]);
                    $path = sf_store_managed_upload(
                        $file,
                        $save_dir,
                        'image',
                        ['gif', 'jpg', 'jpeg', 'png', 'bmp'],
                        10 * 1024 * 1024
                    );
                    if ($path !== '') {
//                        $data[] = [
//                            'filepath' => $path,
//                            'filename' => $file->getOriginalName(),
//                            'fileext' => $file->extension(),
//                            'filesize' => $file->getSize(),
//                        ];
                        $data[] = $path;
                    }
                }
                return $data;
            } else {
                // 使用验证器验证上传的文件
                validate(['file' => [
                    // 限制文件大小(单位b)，这里限制为4M
                    'filesize' => 10 * 1024 * 1024,
                    // 限制文件后缀，多个后缀以英文逗号分割
                    'fileExt' => $allowext,
                ]])->check(['file' => $files]);
                $path = sf_store_managed_upload(
                    $files,
                    $save_dir,
                    'image',
                    ['gif', 'jpg', 'jpeg', 'png', 'bmp'],
                    10 * 1024 * 1024
                );
                if ($path !== '') {
//                    $data = [
//                        'filepath' => $path,
//                        'filename' => $files->getOriginalName(),
//                        'fileext' => $files->extension(),
//                        'filesize' => $files->getSize(),
//                    ];
                    return $path;
                }
            }
        } catch (ValidateException $e) {
            // 上传校验失败
            $error = $e->getMessage();
        } catch (Exception $e) {
            // 上传异常
            $error = $e->getMessage();
        }
        return false;
    }
}

if (!function_exists('formUpload')) {

    /**
     * 表单提交图片(多图上传)
     * @param $name
     * @param string $dir
     * @param int $width
     * @param int $height
     * @param int $tooSmall
     * @return array
     * @author 陌上花开
     * @date 2022-01-19
     */
    function formUpload($name, $dir = "", $width = 0, $height = 0, &$tooSmall = 0)
    {
        $allowedExts = array("jpg", "JPG", "jpeg", "JPEG", "gif", "GIF", "png", "PNG", "bmp", "BMP", "tif", "TIF", "svg", "SVG");
        $fileData = $_FILES[$name];
        $fileList = $fileData['tmp_name'];
        if (!$fileList) {
            return array();
        }
        if (!is_array($fileList)) {
            $fileList = array($fileList);
            $tempData = $fileData;
            $fileData = array();
            $fileData['error'][0] = $tempData['error'];
            $fileData['name'][0] = $tempData['name'];
        }
        $images = array();
        foreach ($fileList as $key => $row) {
            if ($fileData['error'][$key] !== 0) {
                continue;
            }
            $tempFile = $row;
            $filename = $fileData['name'][$key];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array($ext, $allowedExts)) {
                continue;
            }
            $imgPath = create_image_path($dir, $ext);
            $rs = @move_uploaded_file($tempFile, IMG_PATH . $imgPath);
            if ($rs) {
                $images[] = $imgPath;
            } else {
                $realPath = IMG_PATH . $imgPath;
                if ($width || $height) {
                    $imageInfo = getimagesize($realPath);
                    $imageWidth = $imageInfo['width'];
                    $heightWidth = $imageInfo['height'];
                }
                if ($width && $imageWidth < $width) {
                    $tooSmall = 1;
                }
                if ($height && $heightWidth < $height) {
                    $tooSmall = 1;
                }
            }
        }
        return $images;
    }
}

if (!function_exists('upload_file')) {

    /**
     * 上传单个文件
     * @param string $form_name 文件表单名
     * @param string $save_dir 存储文件夹名
     * @param string $error 错误信息
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function upload_file($form_name = 'file', $save_dir = "", &$error = '')
    {
        // 获取文件对象
        $files = \request()->file($form_name);
        // 判断是否有上传的文件
        if (!$files) {
            $error = t('upload.please_select_file');
            return false;
        }

        try {
            // 允许上传的后缀
            $allowext = 'xls,xlsx,doc,docx,ppt,pptx,zip,rar,mp3,txt,pdf,sql,js,css,chm,';
            // 上传路径
            $save_dir = empty($save_dir) ? 'temp' : $save_dir;
            if (is_array($files)) {
                foreach ($files as $file) {
                    $data = [];
                    foreach ($files as $file) {
                        // 使用验证器验证上传的文件
                        validate(['file' => [
                            // 限制文件大小(单位b)，这里限制为4M
                            'filesize' => 10 * 1024 * 1024,
                            // 限制文件后缀，多个后缀以英文逗号分割
                            'fileExt' => $allowext,
                        ]])->check(['file' => $file]);
                        $path = sf_store_managed_upload(
                            $file,
                            $save_dir,
                            'file',
                            ['xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'mp3', 'txt', 'pdf', 'sql', 'js', 'css', 'chm'],
                            10 * 1024 * 1024
                        );
                        if ($path !== '') {
                            $data[] = [
                                'fileName' => $file->getOriginalName(),
                                'filePath' => $path,
                            ];
                        }
                    }
                    return $data;
                }
            } else {
                // 使用验证器验证上传的文件
                validate(['file' => [
                    // 限制文件大小(单位b)，这里限制为4M
                    'filesize' => 10 * 1024 * 1024,
                    // 限制文件后缀，多个后缀以英文逗号分割
                    'fileExt' => $allowext,
                ]])->check(['file' => $files]);
                $path = sf_store_managed_upload(
                    $files,
                    $save_dir,
                    'file',
                    ['xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'mp3', 'txt', 'pdf', 'sql', 'js', 'css', 'chm'],
                    10 * 1024 * 1024
                );
                if ($path !== '') {
                    $result = [
                        'fileName' => $files->getOriginalName(),
                        'filePath' => $path,
                    ];
                    return $result;
                }
            }
        } catch (ValidateException $e) {
            // 上传校验失败
            $error = $e->getMessage();
        } catch (Exception $e) {
            // 上传异常
            $error = $e->getMessage();
        }
    }
}
if (!function_exists('zip_file')) {

    /**
     * 循环获取目录下所有文件
     * @param string $dir 目录路径
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function scan_dir($dir)
    {
        $file_list = [];
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file == '..' || $file == '.') continue;

                if (is_file($dir . '/' . $file)) {
                    $file_list[] = $dir . '/' . $file;
                    continue;
                }
                $file_list[$file] = scan_dir($dir . '/' . $file);
                foreach ($file_list[$file] as $infile) {
                    $file_list[] = $infile;
                }
                unset($file_list[$file]);
            }
            closedir($handle);

            return $file_list;
        }
    }
}
if (!function_exists('getDirContent')) {
    function getDirContent($path){
        if(!is_dir($path)){
            return false;
        }
        //scandir⽅法
        $arr = array();
        $data = scandir($path);
        foreach ($data as $value){
            if($value != '.' && $value != '..'){
                $arr[] = $value;
            }
        }
        return $arr;
    }
}
if (!function_exists('get_folder_list')) {
    /**
     * 获取目录下所有文件夹
     * @param string $path 目录路径
     * @return array 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function get_folder_list($path)
    {
        $folderList = [];   //最终返回的数组
        $keysValue = [];    //二维数组需要排序的值
        //扫描目录内的所有目录和文件并返回数组
        $data = scandir($path);
        $k = 0;
        foreach ($data as $value) {
            //判断如果不是文件夹则进入下一次循环
            if (!is_dir($path . "/" . $value)) {
                continue;
            }
            if ($value != '.' && $value != '..') {
                $folderList[$k] = array(
                    "name" => $value,
                );
                $keysValue[$k] = $value;   //记录排序值
                $k++;
            }
        }
        //对二维数组进行排序
        array_multisort($keysValue, SORT_DESC, $folderList);
        return $folderList;
    }
}

if (!function_exists('zip_file')) {

    /**
     * 打包压缩文件及文件夹
     * @param array $files 文件
     * @param string $zipName 压缩包名称
     * @param bool $isDown 压缩后是否下载true或false
     * @return string 返回结果
     * @author 陌上花开
     * @date 2022-01-19
     */
    function zip_file($files = [], $zipName = '', $isDown = true)
    {
        // 文件名为空则生成文件名
        if (empty($zipName)) {
            $zipName = date('YmdHis') . '.zip';
        }

        // 实例化类,使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
        $zip = new \ZipArchive;
        /*
         * 通过ZipArchive的对象处理zip文件
         * $zip->open这个方法如果对zip文件对象操作成功，$zip->open这个方法会返回TRUE
         * $zip->open这个方法第一个参数表示处理的zip文件名。
         * 这里重点说下第二个参数，它表示处理模式
         * ZipArchive::OVERWRITE 总是以一个新的压缩包开始，此模式下如果已经存在则会被覆盖。
         * ZipArchive::OVERWRITE 不会新建，只有当前存在这个压缩包的时候，它才有效
         * */
        if ($zip->open($zipName, \ZIPARCHIVE::OVERWRITE | \ZIPARCHIVE::CREATE) !== true) {
            exit(t('upload.cannot_open_zip'));
        }

        // 打包处理
        if (is_string($files)) {
            // 文件夹整体打包
            addFileToZip($files, $zip);
        } else {
            // 文件打包
            foreach ($files as $val) {
                if (file_exists($val)) {
                    // 添加文件
                    $zip->addFile($val, basename($val));
                }
            }
        }
        // 关闭
        $zip->close();

        // 验证文件是否存在
        if (!file_exists($zipName)) {
            exit(t('version.file_not_exist'));
        }

        if ($isDown) {
            // 下载压缩包
            header("Cache-Control: public");
            header("Content-Description: File Transfer");
            header('Content-disposition: attachment; filename=' . basename($zipName)); //文件名
            header("Content-Type: application/zip"); //zip格式的
            header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
            header('Content-Length: ' . filesize($zipName)); //告诉浏览器，文件大小
            @readfile($zipName);
        } else {
            // 直接返回压缩包地址
            return $zipName;
        }
    }
}

if (!function_exists('addFileToZip')) {

    /**
     * 添加文件至压缩包
     * @param string $path 文件夹路径
     * @param $zip zip对象
     * @author 陌上花开
     * @date 2022-01-19
     */
    function addFileToZip($path, $zip)
    {
        // 打开文件夹
        $handler = opendir($path);
        while (($filename = readdir($handler)) !== false) {
            if ($filename != "." && $filename != "..") {
                // 编码转换
                $filename = iconv('gb2312', 'utf-8', $filename);
                // 文件夹文件名字为'.'和‘..’，不要对他们进行操作
                if (is_dir($path . "/" . $filename)) {
                    // 如果读取的某个对象是文件夹，则递归
                    addFileToZip($path . "/" . $filename, $zip);
                } else {
                    // 将文件加入zip对象
                    $file_path = $path . "/" . $filename;
                    $zip->addFile($file_path, basename($file_path));
                }
            }
        }
        // 关闭文件夹
        @closedir($path);
    }
}

if (!function_exists('unzip_file')) {

    /**
     * 压缩文件解压
     * @param string $file 被解压的文件
     * @param $dirname 解压目录
     * @return bool 返回结果true或false
     * @author 陌上花开
     * @date 2022-01-19
     */
    function unzip_file($file, $dirname)
    {
        if (!is_file($file) || is_link($file) || is_link($dirname)) {
            return false;
        }
        $problem = \app\common\service\SafeZipService::validate($file, [], 10000, 536870912);
        if ($problem !== null) {
            return false;
        }
        if (!is_dir($dirname) && !mkdir($dirname, 0755, true) && !is_dir($dirname)) {
            return false;
        }
        // zip实例化对象
        $zipArc = new ZipArchive();
        // 打开文件
        if (!$zipArc->open($file)) {
            return false;
        }
        // 解压文件
        if (!$zipArc->extractTo($dirname)) {
            // 关闭
            $zipArc->close();
            return false;
        }
        return $zipArc->close();
    }
}

if (!function_exists('checkWords')) {
    /**
     * 检查敏感词
     * @param $list
     * @param $str
     * @return string
     * @author 陌上花开
     * @date 2022-01-19
     */
    function checkWords($list, $str, $flag = false)
    {
        $count = 0; //违规词的个数
        $sensitiveWord = '';  //违规词
        $stringAfter = $str;  //替换后的内容
        $pattern = "/" . implode("|", $list) . "/i"; //定义正则表达式
        if (preg_match_all($pattern, $str, $matches)) { //匹配到了结果
            $patternList = $matches[0];  //匹配到的数组
            $count = count($patternList);
            $sensitiveWord = implode(',', $patternList); //敏感词数组转字符串
//            $replaceArray = array_combine($patternList, array_fill(0, count($patternList), '***')); //把匹配到的数组进行合并，替换使用
//            $stringAfter = strtr($str, $replaceArray); //结果替换

            // 临时解决方案
            $itemArr = [];
            if (!empty($patternList)) {
                foreach ($patternList as $val) {
                    if (!$val) {
                        continue;
                    }
                    $itemArr[] = str_pad("", mb_strlen($val), "*", STR_PAD_LEFT);
                }
            }
            $replaceArray = array_combine($patternList, $itemArr); //把匹配到的数组进行合并，替换使用
            $stringAfter = strtr($str, $replaceArray); //结果替换
        }
        $log = "原句为 [ {$str} ]<br/>";
        if ($count == 0) {
            $log .= t('sensitive.none_found');
        } else {
            $log .= t('sensitive.found_count', ['count' => $count, 'words' => $sensitiveWord]) . '<br/>' .
                t('sensitive.replaced', ['text' => $stringAfter]);
        }
        if (!$flag) {
            return $stringAfter;
        } else {
            return $count;
        }
    }
}

if (!function_exists('move_temp_images_in_content')) {

    /**
     * 将HTML内容中的临时图片移动到正式目录
     * @param string $content 富文本HTML内容
     * @return string 处理后的HTML内容（临时路径替换为正式路径）
     */
    function move_temp_images_in_content($content)
    {
        if (empty($content)) {
            return $content;
        }
        $pattern = '/\/upload\/temp\/[\d]{8}\/[a-f0-9]+\.(?:jpg|jpeg|png|gif|bmp|webp)/i';
        if (!preg_match_all($pattern, $content, $matches)) {
            return $content;
        }
        $baseDir = PUBLIC_UPLOAD_PATH . DS;
        $tempDir = PUBLIC_UPLOAD_TEMP . DS;
        $permanentDir = PUBLIC_UPLOAD_PATH . DS;
        $replaceMap = [];
        foreach ($matches[0] as $url) {
            $relativePath = ltrim($url, '/');
            $tempFile = $baseDir . str_replace('/', DS, $relativePath);
            if (!file_exists($tempFile)) {
                continue;
            }
            $filename = basename($relativePath);
            $dateDir = date('Ymd');
            $destDir = $permanentDir . $dateDir;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $destFile = $destDir . DS . $filename;
            $newUrl = '/upload/' . $dateDir . '/' . $filename;
            if (rename($tempFile, $destFile)) {
                $replaceMap[$url] = $newUrl;
            }
        }
        if (!empty($replaceMap)) {
            $content = str_replace(array_keys($replaceMap), array_values($replaceMap), $content);
        }
        return $content;
    }
}

if (!function_exists('clean_temp_uploads')) {

    /**
     * 清理过期的临时上传图片
     * @param int $maxAgeSeconds 超过此时间的文件将被删除（默认24小时）
     * @return int 删除的文件数量
     */
    function clean_temp_uploads($maxAgeSeconds = 86400)
    {
        $tempDir = PUBLIC_UPLOAD_TEMP;
        if (!is_dir($tempDir)) {
            return 0;
        }
        $cutoff = time() - $maxAgeSeconds;
        $deleted = 0;
        $dateDirs = glob($tempDir . DS . '*', GLOB_ONLYDIR);
        if (empty($dateDirs)) {
            return 0;
        }
        foreach ($dateDirs as $dateDir) {
            $files = glob($dateDir . DS . '*');
            if (empty($files)) {
                continue;
            }
            $dirEmpty = true;
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff) {
                    @unlink($file);
                    $deleted++;
                } elseif (is_file($file)) {
                    $dirEmpty = false;
                }
            }
            if ($dirEmpty) {
                @rmdir($dateDir);
            }
        }
        return $deleted;
    }
}

if (!function_exists('get_addon_list')) {
    /**
     * 获取本地已安装插件列表。
     *
     * 这些兼容函数曾只存在于 vendor 的本地改动中，执行生产依赖安装后会丢失。
     * 放在项目公共函数文件中，并用 function_exists 保护，兼容依赖包后续原生提供。
     */
    function get_addon_list()
    {
        $addonsPath = app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
        if (!is_dir($addonsPath)) {
            return [];
        }

        $list = [];
        foreach (glob($addonsPath . '*') ?: [] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $infoFile = $dir . DIRECTORY_SEPARATOR . 'info.ini';
            if (!is_file($infoFile)) {
                continue;
            }
            $info = parse_ini_file($infoFile, true, INI_SCANNER_TYPED) ?: [];
            $info['name'] = basename($dir);
            $list[] = $info;
        }
        return $list;
    }
}

if (!function_exists('get_addons_fullconfig')) {
    /**
     * 获取插件完整配置（包含配置项元数据）。
     */
    function get_addons_fullconfig($name)
    {
        $addon = get_addons_instance($name);
        if ($addon) {
            return $addon->getConfig(true);
        }
        $configFile = app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR
            . $name . DIRECTORY_SEPARATOR . 'config.php';
        return is_file($configFile) ? (array) include $configFile : [];
    }
}

if (!function_exists('set_addons_fullconfig')) {
    /**
     * 保存插件完整配置。
     */
    function set_addons_fullconfig($name, $config)
    {
        $configFile = app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR
            . $name . DIRECTORY_SEPARATOR . 'config.php';
        if (!is_file($configFile)) {
            return false;
        }
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        return file_put_contents($configFile, $content) !== false;
    }
}

if (!function_exists('get_addons_tables')) {
    /**
     * 获取插件安装脚本声明的关联表。
     */
    function get_addons_tables($name)
    {
        $sqlFile = app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR
            . $name . DIRECTORY_SEPARATOR . 'install.sql';
        if (!is_file($sqlFile)) {
            return [];
        }
        $content = file_get_contents($sqlFile);
        if ($content !== false && preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i',
            $content,
            $matches
        )) {
            return $matches[1];
        }
        return [];
    }
}

if (!function_exists('addon_url')) {
    /**
     * 生成插件内 URL(兼容 think-addons v2)
     * 用法: {:addon_url('AddCode/index')}
     * 规则:访问入口(根域名) + /addons/<plugin>/<controller>/<action>.html
     * @param string $url  形如 'AddCode/index' 或 'AddCode/index/someArg'
     * @param array  $vars 额外查询参数
     * @param bool   $full 是否返回完整 URL
     * @return string
     */
    function addon_url($url = '', array $vars = [], $full = false)
    {
        $url = (string)$url;
        $url = ltrim($url, '/');
        // 默认入口:从当前请求推断
        $script = request()->baseFile(true);
        // 默认带 .php 后缀则替换为 addons.php 不存在,所以保留当前入口
        // 实际访问: 域名/addons/<plugin>/<ctrl>/<action>.html
        $base = preg_replace('/\\.php$/', '', $script);
        $fullUrl = $base . '/addons/' . $url;
        if (!str_ends_with($fullUrl, '.html')) {
            $fullUrl .= '.html';
        }
        if (!empty($vars)) {
            $fullUrl .= (strpos($fullUrl, '?') === false ? '?' : '&') . http_build_query($vars);
        }
        if ($full) {
            $host = request()->host(true);
            $proto = request()->scheme();
            return $proto . '://' . $host . $fullUrl;
        }
        return $fullUrl;
    }
}
