<?php

namespace app\api\controller;

use think\facade\Cache;

class Background
{
    public function notice($type){
        $cacheKey = 'notice_' . $type;
        if(!empty(Cache::get($cacheKey))){
            return Cache::get($cacheKey);
        }
        if (conf('notice_' . $type) != 1) {
            Cache::tag('SF_Set')->set($cacheKey, '');
            return '';
        }
        $info = [];
        if (conf('notice_icon') != -1) {
            $info['icon'] = intval(conf('notice_icon'));
        }
        $info['anim'] = intval(conf('notice_anim'));
        $info['shade'] = conf('notice_shade');
        $info['time'] = intval(conf('notice_time'));
        $method = in_array(conf('notice_template'), ['default', 'alert', 'black'], true) ? conf('notice_template') : 'open';
        $notice_content = '';
        if ($type === 'home') {
            $notice_content = clean_rich_text(conf('notice_home'));
        } elseif ($type === 'user') {
            $notice_content = clean_rich_text(conf('notice_user'));
        }
        $content = json_encode($notice_content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $options = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $notice = '<script>if(!getCookie("gonggao")){layui.use([\'layer\'],function(){var layer=layui.layer;layer.ready(function(){setTimeout(function(){var content=' . $content . ';var options=' . $options . ';';
        if ($method === 'default') {
            $notice .= 'layer.msg(content,options);';
        } elseif ($method === 'alert') {
            $notice .= 'layer.alert(content,options);';
        } elseif ($method === 'black') {
            $notice .= 'layer.open(Object.assign({type:1,title:false,closeBtn:0,area:"300px",btn:["好的了解","<div style=\"color:#4FC3F7\">不再提醒</div>"],btnAlign:"c",moveOut:true,moveType:0,btn2:function(){setCookie("gonggao","SF2129876388",1);},content:"<div style=\"background-color:#393D49;color:#eeeeee;padding:0.5em\"><h2 style=\"text-align:center;padding-top:0.5em\">平台公告</h2><hr>"+content+"</div>"},options));';
        } else {
            $notice .= 'layer.open(Object.assign({type:1,title:false,area:["420px","auto"],content:\'<div class="layui-card layui-card-body">\'+content+\'</div>\'},options));';
        }
        $notice .= '},500);});});}</script>';
        Cache::tag('SF_Set')->set($cacheKey, $notice);
        return $notice;
        if(!empty(Cache::get('notice'))){
            return Cache::get('notice');
        }else {
            if (conf('notice_' . $type) != 1) {
                Cache::tag('SF_Set')->set('notice', '');
                return '';
            }
            $info = '';
            if (conf('notice_icon') != -1) {
                $info .= 'icon:' . conf('notice_icon') . ',';
            }
            $info .= 'anim:' . conf('notice_anim') . ',';
            $info .= 'shade:' . conf('notice_shade') . ',';
            $info .= 'time:' . conf('notice_time') . '';
            $star = '<script>if(!getCookie("gonggao")){layui.use([\'layer\'], function () { var layer = layui.layer;layer.ready(function(){ setTimeout(function (){';
            $end = '';
            switch (conf('notice_template')) {
                case 'default':
                    $star .= 'layer.msg("';
                    $end .= '",{' . $info . '});';
                    break;
                case 'alert':
                    $star .= 'layer.alert("';
                    $end .= '",{' . $info . '});';
                    break;
                case 'black':
                    $star .= 'layer.open({type: 1,title: false,closeBtn: 0,area: "300px",btn: ["好的了解", "<div style=\\"color:#4FC3F7\\">不再提醒</div>"],btnAlign: "c",moveOut: true,moveType: 0,btn2: function (layero, index) {setCookie("gonggao","SF2129876388",1);},content: "<div style=\\"background-color:#393D49;color:#eeeeee;padding:0.5em\\"><h2 style=\\"text-align:center;padding-top:0.5em\\">平台公告</h2><hr>';
                    $end .= '</div>",' . $info . '});';
                    break;
                default:
                    $notice = str_replace("[cookie]", 'setCookie("gonggao","SF2129876388",1);', conf('notice_diy'));
                    $notice = explode('[notice]', $notice);
                    $star .= !empty($notice[0]) ? $notice[0] : '';
                    $star .= '"';
                    $end .= '"';
                    $end .= '"' . !empty($notice[1]) ? $notice[1] : '';
                    break;
            }
            $end .= '},500);});});}</script>';
            switch ($type) {
                case 'home':
                    $notice_content = conf('notice_home');
                    break;
                case 'user':
                    $notice_content = conf('notice_user');
                    break;
            }
            $notice = $star . $notice_content . $end;
            Cache::tag('SF_Set')->set('notice', $notice);
            return $notice;
        }
    }
    public function css(){
        if(!empty(Cache::get('css'))){
            return Cache::get('css');
        }else{
            switch (conf('site_background_css')){
                case 1:
                    $css = conf('diy_site_background_css');
                    break;
                case 2:
                    $css = 'background-repeat:repeat;';
                    break;
                case 3:
                    $css = 'background-repeat:repeat-x; background-size:auto 100%;';
                    break;
                case 4:
                    $css = 'background-repeat:repeat-y; background-size:100% auto;';
                    break;
                case 5:
                    $css = 'background-repeat:no-repeat; background-size:100% 100%;';
                    break;
                default:
                    $css = '';
                    break;
            }
            Cache::tag('SF_Set')->set('css', $css);
            return $css;
        }
    }
    public function image(){
        if(!empty(Cache::get('image'))){
            return Cache::get('image');
        }else {
            $repeat = $this->css();
            switch (conf('site_background')) {
                case 10:
                    $site_background = conf('diy_background');
                    break;
                case 1:
                    $site_background = 'https://bing.ioliu.cn/v1/rand?w=1920&h=1080';
                    break;
                case 2:
                    $url = 'http://cn.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1';
                    $bing_data = curl_get($url);
                    $bing_arr = json_decode($bing_data, true);
                    $site_background = '//cn.bing.com' . $bing_arr['images'][0]['url'];
                    break;
                case 3:
                    $site_background = 'https://api.uomg.com/api/rand.img1?sort=美女&format=images';
                    break;
                case 4:
                    $site_background = 'https://api.ixiaowai.cn/api/api.php';
                    break;
                case 5:
                    $site_background = 'https://api.uomg.com/api/rand.img1?sort=汽车&format=images';
                    break;
                case 6:
                    $site_background = 'https://api.uomg.com/api/rand.img1?sort=动漫&format=images';
                    break;
                case 7:
                    $site_background = 'https://api.uomg.com/api/rand.img1?sort=背景&format=images';
                    break;
                case 8:
                    $site_background = 'https://api.ixiaowai.cn/gqapi/gqapi.php';
                    break;
                case 9:
                    $site_background = 'https://api.ixiaowai.cn/mcapi/mcapi.php';
                    break;
                default:
                    $site_background = '';
                    break;
            }
            $site_background = '<style>body{ background:#ecedf0 url("' . $site_background . '") fixed;' . $repeat . '}</style>';
            Cache::tag('SF_Set')->set('image', $site_background);
            return $site_background;
        }
    }
    public function music(){
        if(!empty(Cache::get('music'))){
            return Cache::get('music');
        }else {
            switch (conf('site_background_music')) {
                case 1:
                    $music = conf('diy_background_music');
                    break;
                case 2:
                case 4:
                case 5:
                    $music = '<script src="//lib.baomitu.com/jquery/1.12.4/jquery.min.js"></script><script type="text/javascript" src="/Assets/js/SF_Music.js"></script>';
                    break;
                case 3:
                    $music = $this->baiduSpeechSounds(conf('background_text'), conf('background_text_per'), conf('background_text_spd'), 1);
                    break;
                default:
                    $music = '';
                    break;
            }
            Cache::tag('SF_Set')->set('music', $music);
            return $music;
        }
    }
    public function baiduSpeechSounds($text = '', $per = '', $spd = '', $type = 0){
        if(!empty(Cache::get('baiduSpeechSounds'))){
            return Cache::get('baiduSpeechSounds');
        }else {
            switch ($type) {
                case 0:
                    $star = '<audio id="music" preload src="';
                    $end = '"></audio>';
                    break;
                case 1:
                    $star = '<embed src="';
                    $end = '" id="media" width="0" height="0" allowNetworking="all">';
                    break;
                default:
                    $star = '';
                    $end = '';
                    break;
            }
            $Speech_Sounds = $star . 'https://fanyi.sogou.com/reventondc/synthesis?text=' . $text . '&speed=' . $spd . '&lang=zh-CHS&from=translateweb&speaker=' . $per . $end;
            Cache::tag('SF_Set')->set('baiduSpeechSounds', $Speech_Sounds);
            return $Speech_Sounds;
        }
    }
}
