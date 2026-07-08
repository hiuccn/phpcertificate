<?php

namespace tools;
class Checker
{
    public $p12path = '';
    public $mppath = '';
    public $password = '';

    public function __construct($p12,$pw,$mp)
    {

        $this->p12path = $_SERVER['DOCUMENT_ROOT'].'/'.$p12;
        $this->mppath  = $_SERVER['DOCUMENT_ROOT'].'/'.$mp;
        $this->password = $pw;
    }

    function contents(){
        $path = dirname(__FILE__);
        $p12path = $this->p12path;
        $mppath = $this->mppath;
        $password = $this->password;
        $res = shell_exec("$path/cert_check.sh $p12path $password $mppath 2>&1");

        if (!strstr($res, 'P12错误')) {
            $data['code'] = 1;
            $data['msg'] = '成功';

        }else{
            $data['code'] = 0;
            $data['msg'] = '错误';
        }
        $Serial=$this->getSubstr($res,'P12证书序列号:','wb');
        $LocalEndDate=$this->getSubstr($res,'P12证书有效期:','wb');
        $CertificationName = $this->getSubstr($res,'P12证书名称:','wb');
        $CertificationName = $this->getSubstr($CertificationName,': ','(');
        $ProvisionUUID = $this->getSubstr($res,'描述文件UUID=','wb');
        if (strstr($res, '证书一致')) {
            $data['same'] = true;
        } else {
            $data['same'] = false;
        }
        $data['CertificationName'] = $CertificationName;
        $data['LocalEndDate'] =  strtotime($LocalEndDate);
        if(strlen($Serial)==30)$Serial="00".$Serial;
        $data['Serial'] = $Serial;
        $data['ProvisionUUID'] = $res;
        header("Content-Type:application/json; charset=utf-8");
        return json_encode($data);
    }

    /*   取文本中间  */
    function getSubstr($str, $leftStr, $rightStr)
    {
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr,$left);
        //echo '<br>右边:'.$right;
        if($left < 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));

    }
}