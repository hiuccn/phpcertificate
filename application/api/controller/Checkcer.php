<?php

namespace app\api\controller;

use think\Db;
use app\common\controller\Api;
use app\common\controller\Checkp12;

class Checkcer extends Api
{
    protected $noNeedLogin = ["*"];
    protected $noNeedRight = ["*"];

    /**
     * 检查所有设备的证书
     */
    public function index()
    {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");

        // 存储唯一项目名称的数组
        $projectNames = [];

        // 获取所有正常设备并提取唯一项目名称
        $devices = Db::table("fa_deviceslist")->where("zt", "normal")->order("id desc")->select();
        foreach ($devices as $device) {
            $projectName = $device["pname"];
            if (!in_array($projectName, $projectNames)) {
                array_push($projectNames, $projectName);
            }
        }

        // 唯一项目名称的计数
        $totalProjects = count($projectNames);
        $normalCount = 0;
        $droppedCount = 0;

        // 遍历每个项目并检查证书
        foreach ($projectNames as $project) {
            $devices = Db::table("fa_deviceslist")->where("pname", $project)->where("zt", "normal")->order("id asc")->select();
            if ($devices) {
                $checkP12 = new Checkp12();
                try {
                    $result = $checkP12->loadp12($devices[0]["base64p12"]);
                } catch (\Exception $e) {
                    $droppedCount += 1;
                    echo "<font color=\"#f00\">证书: $project 检测失败</font><br>";
                    // foreach ($devices as $device) {
                    //     Db::table("fa_deviceslist")->where("id", $device["id"])->update(["zt" => "hidden"]);
                    // }
                    continue;
                }
                $result = json_decode($result->getContent(), true);
                if ($result["state"] == false) {
                    $droppedCount += 1;
                    echo "<font color=\"#f00\">证书: $project 掉签</font><br>";
                    foreach ($devices as $device) {
                        Db::table("fa_deviceslist")->where("id", $device["id"])->update(["zt" => "hidden"]);
                    }
                } else {
                    $normalCount += 1;
                    echo "证书: $project 正常<br>";
                }
            }
        }

        echo "本次检测: " . $totalProjects . "，正常 " . $normalCount . "，掉签 " . $droppedCount . "<br>";
    }

    public function index1()
    {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");

        // 存储唯一项目名称的数组
        $projectNames = Db::table("fa_deviceslist")->distinct(true)->where("zt", "normal")->column("pname");

        // 唯一项目名称的计数
        $totalProjects = count($projectNames);
        $normalCount = 0;
        $droppedCount = 0;

        // 创建一个生成器函数来分批处理设备数据
        function getDevicesByProject($project)
        {
            $offset = 0;
            $limit = 100;
            while (true) {
                $devices = Db::table("fa_deviceslist")
                    ->where("pname", $project)
                    ->where("zt", "normal")
                    ->order("id asc")
                    ->limit($offset, $limit)
                    ->select();
                if (empty($devices)) {
                    break;
                }
                foreach ($devices as $device) {
                    yield $device;
                }
                $offset += $limit;
            }
        }

        // 遍历每个项目并检查证书
        foreach ($projectNames as $project) {
            $deviceIterator = getDevicesByProject($project);
            $devices = iterator_to_array($deviceIterator);
            if ($devices) {
                $checkP12 = new Checkp12();
                try {
                    $result = $checkP12->loadp12($devices[0]["base64p12"]);
                } catch (\Exception $e) {
                    $droppedCount += 1;
                    echo "<font color=\"#f00\">证书: $project 检测失败</font><br>";
                    // foreach ($devices as $device) {
                    //     Db::table("fa_deviceslist")->where("id", $device["id"])->update(["zt" => "hidden"]);
                    // }
                    continue;
                }
                $result = json_decode($result->getContent(), true);
                if ($result["state"] == false) {
                    $droppedCount += 1;
                    echo "<font color=\"#f00\">证书: $project 掉签</font><br>";
                    foreach ($devices as $device) {
                        Db::table("fa_deviceslist")->where("id", $device["id"])->update(["zt" => "hidden"]);
                    }
                } else {
                    $normalCount += 1;
                    echo "证书: $project 正常<br>";
                }
            }
        }

        echo "本次检测: " . $totalProjects . "，正常 " . $normalCount . "，掉签 " . $droppedCount . "<br>";
    }
    
    public function index2()
    {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");
    
        // 存储唯一项目名称的数组
        $projectNames = Db::table("fa_deviceslist")->distinct(true)->where("zt", "normal")->column("pname");
    
        // 唯一项目名称的计数
        $totalProjects = count($projectNames);
        $normalCount = 0;
        $droppedCount = 0;
    
        // 子进程数量
        $maxProcesses = 5; // 减少并发数量
        $processes = [];
        $pipes = [];
        
        echo "开始检测: " . $totalProjects;
        
        // 遍历每个项目并创建子进程
        foreach ($projectNames as $project) {
            if (count($processes) >= $maxProcesses) {
                // 等待任意子进程结束
                $pid = pcntl_wait($status);
                unset($processes[$pid]);
            }
    
            // 创建管道
            $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pipe === false) {
                die('could not create pipe');
            }
    
            $pid = pcntl_fork();
            if ($pid == -1) {
                // 进程创建失败
                die('could not fork');
            } elseif ($pid) {
                // 父进程
                $processes[$pid] = $project;
                $pipes[$pid] = $pipe[0];
                fclose($pipe[1]);
            } else {
                // 子进程
                fclose($pipe[0]);
    
                // 在子进程中重新建立数据库连接
                // Db::connect();
    
                $output = '';
    
                // 子进程处理函数
                $devices = Db::table("fa_deviceslist")
                    ->where("pname", $project)
                    ->where("zt", "normal")
                    ->order("id asc")
                    ->select();
                if ($devices) {
                    $checkP12 = new Checkp12();
                    try {
                        $result = $checkP12->loadp12($devices[0]["base64p12"]);
                    } catch (\Exception $e) {
                        $output .= "<font color=\"#f00\">证书: $project 检测失败</font><br>";
                        foreach ($devices as $device) {
                            Db::table("fa_deviceslist")->where("id", $device["id"])->update(["zt" => "hidden"]);
                        }
                        fwrite($pipe[1], $output);
                        fclose($pipe[1]);
                        exit(1);
                    }
                    $result = json_decode($result->getContent(), true);
                    if ($result["state"] == false) {
                        $output .= "<font color=\"#f00\">证书: $project 掉签</font><br>";
                        foreach ($devices as $device) {
                            $db->table("fa_deviceslist")->where("id", $device["id"])->update(["zt" => "hidden"]);
                        }
                    } else {
                        $output .= "证书: $project 正常<br>";
                    }
                }
                fwrite($pipe[1], $output);
                fclose($pipe[1]);
                exit(0); // 子进程执行完后必须退出
            }
        }
        
        echo "开始获取输出";
        
        // 实时读取子进程输出
        foreach ($pipes as $pipe) {
            while (($buffer = fgets($pipe, 1024)) !== false) {
                echo "N";
                echo $buffer;
            }
            fclose($pipe);
        }
    
        // 等待所有子进程结束
        foreach ($processes as $pid => $project) {
            pcntl_waitpid($pid, $status);
        }
    
        echo "本次检测: " . $totalProjects . "，正常 " . $normalCount . "，掉签 " . $droppedCount . "<br>";
    }

    /**
     * 特定证书的统计信息
     */
    public function tj()
    {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header("X-Accel-Buffering: no");

        // 存储唯一用户的数组
        $users = [];

        // 从查询字符串中获取证书名称
        $certificateName = $_GET["zs"];

        // 获取与证书相关的所有设备并提取唯一用户
        $devices = Db::table("fa_deviceslist")->where("pname", $certificateName)->order("id desc")->select();
        foreach ($devices as $device) {
            $user = $device["user"];
            if (!in_array($user, $users)) {
                array_push($users, $user);
            }
        }

        echo "证书: $certificateName<br>";
        // 输出用户统计信息
        foreach ($users as $user) {
            $deviceCount = Db::table("fa_deviceslist")->where("user", $user)->where("pname", $certificateName)->count();
            echo "$user: $deviceCount<br>";
        }
    }
}
