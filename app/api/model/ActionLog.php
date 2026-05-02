<?php
namespace app\api\model;

use app\common\model\BaseModel;

/**
 * 行为日志-模型
 * @author 陌上花开
 * @since 2022/7/3
 * Class ActionLog
 * @package app\api\model
 */
class ActionLog extends BaseModel
{
    // 设置数据表
    protected $name = 'log';
    // 自定义日志标题
    protected static $title = '';
    // 自定义日志内容
    protected static $content = '';

    /**
     * 设置标题
     * @param string $title 标题
     * @author 陌上花开
     * @date 2022/4/4
     */
    public static function setTitle($title)
    {
        self::$title = $title;
    }

    /**
     * 设置内容
     * @param string $content 内容
     * @author 陌上花开
     * @date 2022/4/4
     */
    public static function setContent($content)
    {
        self::$content = $content;
    }

    /**
     * 记录行为日志
     * @author 陌上花开
     * @date 2022/4/4
     */
    public static function record()
    {

//        if (!self::$title) {
//            // 操作控制器名
//            $menuMod = new Menu();
//            $url = str_replace("/admin.php/","",request()->url());
//            $url = str_replace(".html","",$url);
//            $info = $menuMod->getOne([['url', '=', $url],['status', '=', 1]]);
//            if ($info) {
////                if ($info['type'] == 4) {
////                    $menuInfo = $menuMod->getInfo($info['pid']);
////                    self::$title = $menuInfo['name'];
////                } else {
//                self::$title = $info['name'];
//                //}
//            }else{
//                self::$title = '未知操作';
//            }
//        }
        // 日志数据
        $data = [
            'username' => empty(session('userId')) ? 0 : session('userId'),
            'is_admin' => 0,
            'module' => app('http')->getName(),
            'action' => request()->url(),
            'method' => request()->method(),
            'url' => request()->url(true), // 获取完成URL
            'param' => !empty(request()->param()) ? json_encode(request()->param()) : '',
            'title' => !empty(self::$title) ? self::$title : '操作API',
            'content' => !empty(self::$content) ? self::$content : '操作 '.CONTROLLER_NAME.'/'.ACTION_NAME.' API',
            'ip' => request()->ip(),
            'user_agent' => request()->server('HTTP_USER_AGENT'),
            'create_user' => empty(session('userId')) ? 0 : session('userId'),
            'create_time' => datetime(),
        ];
        // 日志入库
        $action = new ActionLog();
        $action->insert($data);
    }
    public function insert($data){
        $result = ActionLog::strict(false)->insert($data);
        if($result){
            return true;
        }else{
            return false;
        }
    }
}