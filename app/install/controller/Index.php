<?php
declare (strict_types = 1);

namespace app\install\controller;

use app\common\controller\Install;
class Index extends Install
{
    public function initialize()
    {
        parent::initialize();
    }

    public function index()
    {
        return $this->render();
    }

    public function author()
    {
        return $this->authorInfo();
    }

    public function main()
    {
        return $this->render();
    }

    public function userAgreen()
    {
        return $this->render();
    }

    public function updateLog()
    {
        return $this->render();
    }

    public function binding()
    {
        return $this->render();
    }
}
