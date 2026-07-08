<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Exception;

/**
 * 充值
 */
class Website extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['epay'];
    protected $noNeedRight = ['*'];


    public function website()
    {
        
        $sitelist = Website::where(['user_id' => $this->auth->id])
            ->order('id desc')
            ->paginate(10);

        $this->view->assign('title', '网站列表');
        $this->view->assign('sitelist', $sitelist);
        return $this->view->fetch();
    }


    
}
