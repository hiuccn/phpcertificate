<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;

/**
 * Token接口
 */
class Token extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 检测Token是否过期
     *
     */
    public function check()
    {
        $token = $this->auth->getToken();
        $tokenInfo = \app\common\library\Token::get($token);
        $this->success('', ['token' => $tokenInfo['token'], 'expires_in' => $tokenInfo['expires_in']]);
    }
    

    public function add()
    {
        
        $row=Db::table('fa_config')->insert(array('name'=>'plqj','group'=>'basic','title'=>'批量区间','type'=>'string','value'=>'1,5,30,50,100,200,300,500','setting'=>'{"table":"","conditions":"","key":"","value":""}')); 
        
        if($row){
            echo '成功';
        }else{
            echo '失败';
        }
    }
    
    
    /**
     * 刷新Token
     *
     */
    public function refresh()
    {
        //删除源Token
        $token = $this->auth->getToken();
        \app\common\library\Token::delete($token);
        //创建新Token
        $token = Random::uuid();
        \app\common\library\Token::set($token, $this->auth->id, 2592000);
        $tokenInfo = \app\common\library\Token::get($token);
        $this->success('', ['token' => $tokenInfo['token'], 'expires_in' => $tokenInfo['expires_in']]);
    }
}
