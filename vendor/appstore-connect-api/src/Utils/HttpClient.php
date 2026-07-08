<?php

namespace MingYuanYun\AppStore\Utils;


use Curl\Curl;

trait HttpClient
{
    /**
     * @var Curl
     */
    private $curl;
    
    
    protected function getFastestProxy(array $proxies, $testUrl)
    {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $responseTimes = [];
    
        // 初始化每个代理的 CURL 请求
        foreach ($proxies as $proxy) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 增加超时时间
            curl_setopt($ch, CURLOPT_HEADER, true); // 请求头部
            curl_setopt($ch, CURLOPT_NOBODY, false); // 不仅请求头部
            curl_setopt($ch, CURLOPT_VERBOSE, true); // 输出详细信息
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use ($proxy) {
                // 记录请求头部（可选）
                return strlen($header);
            });
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$proxy] = $ch;
        }
    
        $fastestProxy = null;
        $fastestTime = PHP_INT_MAX;
    
        // 并行执行 CURL 请求
        do {
            $status = curl_multi_exec($multiHandle, $active);
            $logContent = sprintf("[%s] 请求状态: %d, 活跃请求数: %d\n", date('Y-m-d H:i:s'), $status, $active);
            $logFile = __DIR__ . '/proxy_debug_log.txt';
            file_put_contents($logFile, $logContent, FILE_APPEND);
    
            if ($status === CURLM_OK) {
                // 使用 curl_multi_select 等待直到有一个请求完成
                $selectResult = curl_multi_select($multiHandle);
                $logContent = sprintf("[%s] curl_multi_select 返回: %d\n", date('Y-m-d H:i:s'), $selectResult);
                file_put_contents($logFile, $logContent, FILE_APPEND);
    
                if ($selectResult !== -1) {
                    do {
                        $info = curl_multi_info_read($multiHandle);
                        if ($info) {
                            $handle = $info['handle'];
                            $proxy = array_search($handle, $curlHandles);
                            $logContent = sprintf("[%s] 有一个请求已经完成: 代理: %s\n", date('Y-m-d H:i:s'), $proxy);
                            file_put_contents($logFile, $logContent, FILE_APPEND);
    
                            if ($proxy !== false) {
                                $start = microtime(true);
                                $content = curl_multi_getcontent($handle); // 强制读取内容
                                $responseTime = round((microtime(true) - $start) * 1000); // 毫秒
                                $responseTimes[$proxy] = $responseTime;
    
                                if ($responseTime < $fastestTime) {
                                    $fastestTime = $responseTime;
                                    $fastestProxy = $proxy;
                                }
    
                                // 释放其他句柄并返回最快的代理
                                foreach ($curlHandles as $handle) {
                                    curl_multi_remove_handle($multiHandle, $handle);
                                    curl_close($handle);
                                }
                                curl_multi_close($multiHandle);
    
                                // 记录调试信息
                                $this->logDebugInfo($responseTimes, $fastestProxy);
    
                                return $fastestProxy;
                            }
                        }
                    } while ($info);
                }
            }
        } while ($active);
    
        // 如果没有代理返回成功的结果
        curl_multi_close($multiHandle);
        return null;
    }
    
    protected function logDebugInfo(array $responseTimes, $fastestProxy)
    {
        $logFile = __DIR__ . '/proxy_debug_log.txt';
        $logContent = "[" . date('Y-m-d H:i:s') . "] Response Times:\n";
        foreach ($responseTimes as $proxy => $time) {
            $logContent .= sprintf("Proxy: %s, Time: %d ms\n", $proxy, $time);
        }
        $logContent .= sprintf("[%s] Fastest Proxy: %s\n", date('Y-m-d H:i:s'), $fastestProxy);
        file_put_contents($logFile, $logContent, FILE_APPEND);
    }



    protected function getCurl()
    {
        $proxies = [
            //"122.51.206.195:8333",
            "106.52.221.240:8899"
            //"175.178.58.202:8086",
        ];
        
        $testUrl = "https://www.baidu.com";  // 你选择的测试URL
        $startTime = microtime(true);
        $fastestProxy = $this->getFastestProxy($proxies, $testUrl);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000); // 毫秒
        
        // 准备日志内容
        $logContent = sprintf("[%s] Fastest Proxy: %s\n", date('Y-m-d H:i:s'), $fastestProxy);
        $logContent .= sprintf("[%s] Test Duration: %s\n", date('Y-m-d H:i:s'), $duration);
    
        // 将日志写入到文件
        $logFile = __DIR__ . '/proxy_test_log.txt';
        file_put_contents($logFile, $logContent, FILE_APPEND);

        $this->curl = new Curl();
        $this->curl->setProxy($fastestProxy);
        return $this;
    }

    public function get($url, array $params = [], array $headers = [])
    {
        $this->getCurl();
        foreach ($headers as $key => $value) {
            $this->curl->setHeader($key, $value);
        }
        $this->curl->get($url, $params);
        return $this->wrapContent($this->curl->getResponse());
    }

    public function postJson($url, array $body = [], array $headers = [])
    {
        $this->getCurl();
        foreach ($headers as $key => $value) {
            $this->curl->setHeader($key, $value);
        }
        $this->curl->setHeader('Content-Type', 'application/json');
        $this->curl->post($url, $body);
        return $this->wrapContent($this->curl->getResponse());
    }

    public function delete($url, array $params = [], array $headers = [])
    {
        $this->getCurl();
        foreach ($headers as $key => $value) {
            $this->curl->setHeader($key, $value);
        }
        $this->curl->delete($url, $params);
        return $this->wrapContent($this->curl->getResponse());
    }

    protected function wrapContent($content)
    {
        if (is_string($content)) {
            $content = json_decode(implode('', explode(PHP_EOL, $content)));
        }
        return json_decode(json_encode($content), true);
    }
}