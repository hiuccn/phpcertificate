<?php

namespace app\admin\model;

use think\Model;


class Website extends Model
{

    

    

    // 表名
    protected $name = 'website';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'app_type_text',
        'endtime_text',
        'apiurl1_text',
        'apiurl2_text',
        'apiurl3_text'
    ];
    

    
    public function getAppTypeList()
    {
        return ['0' => __('App_type 0'), '1' => __('App_type 1'), '2' => __('App_type 2')];
    }

    public function getApiurl1List()
    {
        return ['developer.iksq.cn' => __('Apiurl1 developer.iksq.cn'), 'cer.52tzs.com' => __('Apiurl1 cer.52tzs.com'), 'cert.4yun.cn' => __('Apiurl1 cert.4yun.cn'), 'cer.ihyys.cn' => __('Apiurl1 cer.ihyys.cn'), 'cert.xiayian.cn' => __('Apiurl1 cert.xiayian.cn'), 'sign.getp12.com' => __('Apiurl1 sign.getp12.com'), 'www.jikeq.com' => __('Apiurl1 www.jikeq.com')];
    }

    public function getApiurl2List()
    {
        return ['0' => __('Apiurl2 0'), 'developer.iksq.cn' => __('Apiurl2 developer.iksq.cn'), 'cer.52tzs.com' => __('Apiurl2 cer.52tzs.com'), 'cert.4yun.cn' => __('Apiurl2 cert.4yun.cn'), 'cer.ihyys.cn' => __('Apiurl2 cer.ihyys.cn'), 'cert.xiayian.cn' => __('Apiurl2 cert.xiayian.cn'), 'sign.getp12.com' => __('Apiurl2 sign.getp12.com')];
    }

    public function getApiurl3List()
    {
        return ['0' => __('Apiurl3 0'), 'developer.iksq.cn' => __('Apiurl3 developer.iksq.cn'), 'cer.52tzs.com' => __('Apiurl3 cer.52tzs.com'), 'cert.4yun.cn' => __('Apiurl3 cert.4yun.cn'), 'cer.ihyys.cn' => __('Apiurl3 cer.ihyys.cn'), 'cert.xiayian.cn' => __('Apiurl3 cert.xiayian.cn'), 'sign.getp12.com' => __('Apiurl3 sign.getp12.com')];
    }


    public function getAppTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['app_type']) ? $data['app_type'] : '');
        $list = $this->getAppTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getApiurl1TextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['apiurl1']) ? $data['apiurl1'] : '');
        $list = $this->getApiurl1List();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getApiurl2TextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['apiurl2']) ? $data['apiurl2'] : '');
        $list = $this->getApiurl2List();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getApiurl3TextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['apiurl3']) ? $data['apiurl3'] : '');
        $list = $this->getApiurl3List();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setEndtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
