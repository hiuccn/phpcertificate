<?php

namespace MingYuanYun\AppStore\Api;


class Certificates extends AbstractApi
{
    public function all(array $params = [])
    {
        return $this->get('/certificates', $params);
    }
  
    
    
    public function del($id)
    {
        return $this->delete('/certificates/'.$id);
    }
    
    
      public function create()
    {
        $data = [
            'data' => [
                'type' => 'certificates',
                
                'attributes' => [
                    'certificateType' => 'IOS_DISTRIBUTION', //'IOS_DEVELOPMENT',//
                    'csrContent' => 'MIICvDCCAaQCAQAwTzELMAkGA1UEBhMCQ0gxDDAKBgNVBAgMAyBBSDELMAkGA1UE
                                BwwCQVExCzAJBgNVBAoMAldCMQswCQYDVQQLDAJYSzELMAkGA1UEAwwCWEswggEi
                                MA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDJSQAT5AAfB4MIl4lH7bMmfsKh
                                KprbXJbiT3srcocFv09YyaTcDukFsfZXm+9umIqQV2oPTm2Itjf2B3Nv6Q/eT0O0
                                oO6yi5oV+9Cmt1ofrG5HWLkSt4V6yiJTye1Wp0+0vbMGAdfT9EMtDQj88z9CMHR6
                                7c1klG0EDyRh+w4i+7TcKE5JVv9yBRybTqOmCoRNgRPmeAHTnJrIjPIra+R5aZ6p
                                l1/EAotTkP9KiTX0lqjA26xSTZwSuDTNaNRLVUE+WCjIBoAgN7Rfnxe/oq0gX4+Y
                                x1i5KlJZqCSaTdZpTyIaeg1E+MXogVGT3PT2pBuvAjpCQu/OA/jEwIn0vwLxAgMB
                                AAGgKDARBgkqhkiG9w0BCQIxBAwCV0IwEwYJKoZIhvcNAQkHMQYMBDEyMzQwDQYJ
                                KoZIhvcNAQELBQADggEBAKSvmOPAzwLKz8jQfmMtGuFTcQgBxokAYJAxUuiXSxRg
                                hcZKempuVH+lO1uCSK6X+obTAS1W0BmThxoJ1iR0JTMzMYNA2S/x8vuFOXn0EydO
                                S6BFgiK53yHJXhSg9QoZwRCen16z67mlG0NOJrT/AoxUbzsNs1ItGnyFc9S07DAR
                                EaSd7G8Ien/C3bNDkq6Em159TEDs8M+Vp8EPCTpC5KTWQRJ687ToHTxUXREWVd0w
                                lIBfXCbiD1WGlsTgmZobTOk4PmRuGrCZhtaj/Uk64SCkgV/1Rq4HtJa9C3/QTfw1
                                olJWoJniJhlz9rjA0MQHenHWGnSxVBJAx2yG2Idnao8='
                ]
            ]
        ];
        
        return $this->postJson('/certificates', $data);
    }
    
}