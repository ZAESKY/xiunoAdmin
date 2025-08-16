<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use Metowolf\Meting;
use think\facade\Cache;

class MusicAnalysis extends Frontend
{
    private $api_uri = '';
    // 设置中文歌词
    const TLYRIC = true;
    // 设置歌单文件缓存及时间
    const CACHE = true;
    const CACHE_TIME = 86400;
    // 设置AUTH密钥-更改'meting-secret'
    const AUTH = false;
    const AUTH_SECRET = 'meting-secret';

    public function initialize()
    {
        parent::initialize();
        $this->api_uri = self::api_uri();
    }

    private static function api_uri() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    }

    public function index(){
        if (conf('site_background_music') == 2) {
            $MusicArr = explode("\n", conf('diy_background_music_id'));
            //baidu xiami kugou tencent netease
            $musicdata = array();
            foreach($MusicArr as $res){
                $Music = explode("|", $res);
                $id = !empty($Music[0])?$Music[0]:null;
                $server = !empty($Music[1])?$Music[1]:null;
                $type = !empty($Music[2])?$Music[2]:'song';
                if (empty($id) || empty($server)){
                    continue;
                }
                if ($type == 'playlist') {
                    $data = json_decode($this->playList($server ,$id),true);
                    if($data['Code'] == 'NO'){
                        continue;
                    }
                    foreach ($data['Body'] as $res){
                        $musicdata[] = $res;
                    }
                } else {
                    $data = json_decode($this->song($server ,$id),true);
                    if($data['Code'] == 'NO'){
                        continue;
                    }
                    $musicdata[] = $data['Body'];

                }
            }
            $result = array(
                "Code" => "OK",
                "Body" => $musicdata
            );
            return json($result);
        }elseif (conf('site_background_music') == 4){
            $toplist = conf('netease_music_toplist');
            $server = 'netease';
            switch ($toplist) {
                case 0:
                    $id = '19723756';
                    break;
                case 1:
                    $id = '3779629';
                    break;
                case 2:
                    $id = '2884035';
                    break;
                case 3:
                    $id = '3778678';
                    break;
                case 4:
                    $id = '991319590';
                    break;
                case 5:
                    $id = '1978921795';
                    break;
                case 6:
                    $id = '2250011882';
                    break;
                case 7:
                    $id = '2617766278';
                    break;
                case 8:
                    $id = '71385702';
                    break;
                case 9:
                    $id = '11641012';
                    break;
                case 10:
                    $id = '5059644681';
                    break;
                case 11:
                    $id = '5059661515';
                    break;
                case 12:
                    $id = '5059633707';
                    break;
                default:
                    $id = '19723756';
            }
            $data = json_decode($this->playList($server ,$id),true);
            $result = array(
                "Code" => "OK",
                "Msg" => $data['Msg'],
                "Body" => $data['Body']
            );
            return json($result);
        }else{
            $info = array(
                "Code" => "NO",
                "Msg" => '获取失败！'
            );
            return json($info, 320);
        }
    }

    private function playList($server ,$id){
        if (self::CACHE) {
            $cache = Cache::get($server.'_'.$id.'_playlist',null);
            if (!empty($cache)) {
                $result = array(
                    "Code" => "OK",
                    "Msg" => "获取成功",
                    "Body" => $cache
                );
                return json_encode($result);
            }
        }

        $api = new Meting($server);
        $api->format(true);
        $data = $api->playlist($id);

        if ($data == '[]') {
            $result = array(
                "Code" => "NO",
                "Msg" => "不存在此歌单",
                "Body" => ""
            );
            return json_encode($result);
        }
        $data = json_decode($data);
        $playlist = array();
        foreach ($data as $song) {
            $playlist[] = array(
                'title'   => $song->name,
                'author' => implode('/', $song->artist),
                'url'    => $this->api_uri . '/song2?server=' . $song->source . '&type=url&id=' . $song->url_id . (self::AUTH ? '&auth=' . $this->auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => $this->api_uri . '/song2?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (self::AUTH ? '&auth=' . $this->auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => $this->api_uri . '/song2?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (self::AUTH ? '&auth=' . $this->auth($song->source . 'lrc' . $song->lyric_id) : '')
            );
        }

        if (self::CACHE) {
            Cache::set($server.'_'.$id.'_playlist', $playlist,self::CACHE_TIME);
        }
        $result = array(
            "Code" => "OK",
            "Msg" => "获取成功",
            "Body" => $playlist
        );
        return json_encode($result);
    }

    private function song($server, $id ,$type = 'song'){

        if (self::CACHE) {
            $type_key = $server . $type . $id;
            if (Cache::get($type_key)) {
                $result = array(
                    "Code" => "OK",
                    "Msg" => "获取成功",
                    "Body" => json_decode($this->return_data($type, Cache::get($type_key)),true)
                );
                return json_encode($result);
            }
            $song_id_key = $server . 'song_id' . $id;
            if (Cache::get($song_id_key)) {
                $song = Cache::get($song_id_key);
            }

        }

        $api = new Meting($server);
        $api->format(true);

        if (!isset($song)) $song = $api->song($id);
        if ($song == '[]') {
            $result = array(
                "Code" => "NO",
                "Msg" => "不存在此歌曲",
                "Body" => ""
            );
            return json_encode($result);
        }
        if (self::CACHE) {
            Cache::set($song_id_key, $song,self::CACHE_TIME);
        }
        $data = $this->song2data($api, json_decode($song)[0], $type, $id);

        if (self::CACHE) {
            Cache::set($type_key, $data,self::CACHE_TIME);
        }
        $result = array(
            "Code" => "OK",
            "Msg" => "获取成功",
            "Body" => json_decode($this->return_data($type, $data),true)
        );
        return json_encode($result);
    }

    public function song2($id, $server = 'netease', $type = 'url'){
        $need_song = !in_array($type, ['url', 'pic', 'lrc']);
        if ($need_song && !in_array($type, ['name', 'artist', 'song'])) {
            $result = array(
                "Code" => "NO",
                "Msg" => "不存在此类型",
                "Body" => ""
            );
            return json_encode($result);
        }
        $api = new Meting($server);
        $api->format(true);
        $data = $this->song2data($api, null, $type, $id);
        return $this->return_data($type, $data);
    }

    private function song2data($api, $song, $type, $id)
    {
        $data = '';
        switch ($type) {
            case 'name':
                $data = $song->name;
                break;
            case 'artist':
                $data = implode('/', $song->artist);
                break;
            case 'url':
                $m_url = json_decode($api->url($id, 320))->url;
                if ($m_url == '') break;
                if ($api->server == 'netease') {
                    if ($m_url[4] != 's') $m_url = str_replace('http', 'https', $m_url);
                }
                $data = $m_url;
                break;
            case 'pic':
                $data = json_decode($api->pic($id, 90))->url;
                break;
            case 'lrc':
                $lrc_data = json_decode($api->lyric($id));
                if ($lrc_data->lyric == '') {
                    $lrc = '[00:00.00]这似乎是一首纯音乐呢，请尽情欣赏它吧！';
                } else if ($lrc_data->tlyric == '') {
                    $lrc = $lrc_data->lyric;
                } else if (self::TLYRIC) { // lyric_cn
                    $lrc_arr = explode("\n", $lrc_data->lyric);
                    $lrc_cn_arr = explode("\n", $lrc_data->tlyric);
                    $lrc_cn_map = array();
                    foreach ($lrc_cn_arr as $i => $v) {
                        if ($v == '') continue;
                        $line = explode(']', $v, 2);
                        // 格式化处理
                        $line[1] = trim(preg_replace('/\s\s+/', ' ', $line[1]));
                        $lrc_cn_map[$line[0]] = $line[1];
                        unset($lrc_cn_arr[$i]);
                    }
                    foreach ($lrc_arr as $i => $v) {
                        if ($v == '') continue;
                        $key = explode(']', $v, 2)[0];
                        if (!empty($lrc_cn_map[$key]) && $lrc_cn_map[$key] != '//') {
                            $lrc_arr[$i] .= ' (' . $lrc_cn_map[$key] . ')';
                            unset($lrc_cn_map[$key]);
                        }
                    }
                    $lrc = implode("\n", $lrc_arr);
                } else {
                    $lrc = $lrc_data->lyric;
                }
                $data = $lrc;
                break;
            case 'song':
                $data = json_encode(array(
                    'name'   => $song->name,
                    'artist' => implode('/', $song->artist),
                    'url'    => $this->api_uri . '/song2?server=' . $song->source . '&type=url&id=' . $song->url_id . (self::AUTH ? '&auth=' . $this->auth($song->source . 'url' . $song->url_id) : ''),
                    'pic'    => $this->api_uri . '/song2?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (self::AUTH ? '&auth=' . $this->auth($song->source . 'pic' . $song->pic_id) : ''),
                    'lrc'    => $this->api_uri . '/song2?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (self::AUTH ? '&auth=' . $this->auth($song->source . 'lrc' . $song->lyric_id) : '')
                ));
                break;
        }
        if ($data == '') return null;
        return $data;
    }

    private function return_data($type, $data)
    {
        if (in_array($type, ['url', 'pic'])) {
            header('Location: ' . $data);
        } else {
            return $data;
        }
    }

    private function auth($name)
    {
        return hash_hmac('sha1', $name, self::AUTH_SECRET);
    }

}