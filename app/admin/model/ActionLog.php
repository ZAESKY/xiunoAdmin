<?php
namespace app\admin\model;


use app\common\model\BaseModel;

/**
 * 行为日志-模型
 * @author 陌上花开
 * @since 2022/7/3
 * Class ActionLog
 * @package app\admin\model
 */
class ActionLog extends BaseModel
{
    // 设置数据表
    protected $name = 'log';
    // 自定义日志标题
    protected static $title = '';
    // 自定义日志内容
    protected static $content = '';
    // 自定义请求结果
    protected static $result = 1;

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
     * 设置请求结果
     * @param int $result 状态
     * @author 陌上花开
     * @date 2022/4/4
     */
    public static function setResult($result){
        self::$result = intval($result);
    }

    /**
     * 记录行为日志
     * @author 陌上花开
     * @date 2022/4/4
     */
    public static function record()
    {
        
        if (!self::$title) {
            // 操作控制器名
            $menuMod = new Menu();
            $url = request()->controller().'/'.request()->action();
            $info = $menuMod->getOne([['url', '=', $url],['status', '=', 1]]);
            if ($info) {
//                if ($info['type'] == 4) {
//                    $menuInfo = $menuMod->getInfo($info['pid']);
//                    self::$title = $menuInfo['name'];
//                } else {
                    self::$title = $info['name'];
                //}
            }else{
                self::$title = '未知操作';
            }
        }
        // 日志数据
        $data = [
            'username' => empty(session('adminId')) ? 0 : session('adminId'),
            'is_admin' => 1,
            'module' => app('http')->getName(),
            'action' => request()->url(),
            'method' => request()->method(),
            'url' => request()->url(true), // 获取完成URL
            'param' => !empty(request()->param()) ? qh_action_log_params(request()->param()) : '',
            'title' => !empty(self::$title) ? self::$title : '操作后台',
            'content' => !empty(self::$content) ? self::$content : '无',
            'ip' => request()->ip(),
            'user_agent' => request()->server('HTTP_USER_AGENT'),
            'create_user' => empty(session('adminId')) ? 0 : session('adminId'),
            'create_time' => datetime(),
        ];
        // 日志入库
        $action = new ActionLog();
        $action->insert($data);
    }

    public function insert($data){
        $result = self::strict(false)->insert($data);
        if($result){
            return true;
        }else{
            return false;
        }
    }
}
