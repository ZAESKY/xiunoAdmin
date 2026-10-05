<?php
declare (strict_types = 1);

namespace app\index\controller;

use app\common\controller\Frontend;
use app\api\controller\Background;
use think\facade\View;
class Index extends Frontend
{
    public function initialize()
    {
        parent::initialize();
        $this->background = new Background();
        View::config(['view_path' => app()->getRootPath() . 'public/template/modules/home/QH3.0/']);
        View::assign('user_login', parent::isUserLogin());
        View::assign('app_list', parent::getAppList());
        View::assign('background_image',$this->background->image());
        View::assign('background_notice',$this->background->notice('home'));
        View::assign('background_music',$this->background->music());
        View::assign('background_music',$this->background->music());
    }

    public function index(){
        return $this->render('index/index');
    }

    public function check(){
        return $this->render('index/check');
    }

    public function main(){
        return $this->render('index/main');
    }

    public function dashboard(){
        return $this->render('index/dashboard');
    }

    public function download(){
        View::config(['view_path' => '']);
        $mode = qh_download_mode();
        if($mode === '0'){
            return $this->render('/public/error', ['msg' => t('common_ui.download_disabled')]);
        }
        return $this->render($mode);
    }

    public function register(){
        return $this->render('index/register');
    }

    public function login(){
        return $this->render('index/login');
    }

    public function pluginMarket(){
        View::assign('app_list', parent::getAppList());
        return $this->render('index/plugin_market');
    }
}
