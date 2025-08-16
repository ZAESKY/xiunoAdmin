<?php

namespace app\common\extend;

class Template{
    static public function getList($type){
        switch ($type){
            case 'home':
                $dir = HOME_TEMPLATE_PATH;
                break;
            case 'login':
                $dir = LOGIN_TEMPLATE_PATH;
                break;
            case 'maintain':
                $dir = MAINTAIN_TEMPLATE_PATH;
                break;
            case 'notice':
                $dir = NOTICE_TEMPLATE_PATH;
                break;
        }
        $template_list = [];
        if (false != ($handle = opendir($dir))) {
            while (false !== ($name = readdir($handle))) {
                if ($name == '.' || $name == '..') continue;
                $path = $dir. DS . $name . DS;
                if (@is_dir($path) && is_file($path . 'info.ini')) {
                    $info = parse_ini_file($path . 'info.ini', true, INI_SCANNER_TYPED) ?: [];//获取模板配置信息
                    $info['name'] = $name;
                    $info['is_use'] = 0;
                    if (config('self_template')[$type]['name'] == $info['name']) {
                        $info['is_use'] = 1;
                    }

                    $template_list[] = $info;
                }
            }
            closedir($handle);
        }
        return $template_list;
    }
}
