<?php


namespace MingYuanYun\AppStore\Api;


class Device extends AbstractApi
{
    public function all(array $params = [])
    {
        return $this->get('/devices', $params);
    }

    public function register($name, $platform, $udid)
    {
        $data = [
            'data' => [
                'type' => 'devices',
                'attributes' => [
                    'name' => $name,
                    'platform' => strtoupper($platform),
                    'udid' => $udid,
                ]
            ]
        ];
        return $this->postJson('/devices', $data);
    }
    
    
    
    
    public function devicesort()
    {
        $params = [
            'filter[platform]' => 'IOS',
            'fields[devices]' => 'deviceClass',
            'limit' => 200
        ];
        
        $ipad=0;
        $iphone=0;
       
        
       $datas= $this->get('/devices', $params);
       
       foreach ($datas as $data) {
           foreach ($data as $device) {
               $type= isset($device['attributes']['deviceClass']) ?$device['attributes']['deviceClass'] : '';
                if($type=='IPHONE')$iphone+=1;
                if($type=='IPAD')$ipad+=1;
            
           }
        }
        
        $params = [
            'filter[platform]' => 'MAC_OS',
           'fields[devices]' => 'deviceClass',
        ];
        $datas1= $this->get('/devices', $params);
        
        $zt=isset($datas1['errors'])?$datas1['errors']:0;
        if($zt!=0)return ['code'=>1001,'msg'=>json_encode($datas1['errors'][0]['detail']),'data'=>[]];
        
        $mac=$datas1['meta']['paging']['total'];
        $ab= $this->get('/users');
        
        $email= $ab['data'][0]['attributes']['username'];
        return ['code'=>1,'msg'=>'ok','data'=> ['IPHONE' =>100-$iphone,'IPAD'=>100-$ipad,'MAC'=>100-$mac,'email'=>$email]];
       
    }


}