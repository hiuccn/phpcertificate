<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\Deviceslist;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist =Db::name("deviceslist")
            ->where('chi', 0)
            ->where('shouhou', 0)
            ->where('type', 0)
            ->field('base64mp,base64p12',true)
            ->where('tjtime', 'between time', [$starttime, $endtime])
            ->field('tjtime, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(tjtime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        $joinlist1 =Db::name("deviceslist")
            ->where('chi', 0)
            ->where('shouhou', 0)
            ->where('type', 1)
            ->field('base64mp,base64p12',true)
            ->where('tjtime', 'between time', [$starttime, $endtime])
            ->field('tjtime, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(tjtime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        $userlist1 = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }
        foreach ($joinlist1 as $k => $v) {
            $userlist1[$v['join_date']] = $v['nums'];
        }
        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }
        $notice_list = Db::name('deviceslist')
            ->where('id','<>',0)

            ->field(['base64mp','base64p12'],true)
            ->where('chi', 0)
            ->select();

        $timezoneOffset = 0;

        $yesterdayStart = strtotime('yesterday') + $timezoneOffset;
        $yesterdayEnd = strtotime('today') + $timezoneOffset - 1;

        $cost = Db::table('fa_deviceslist')
            ->where('tjtime', '>=', $yesterdayStart)
            ->where('tjtime', '<=', $yesterdayEnd)
            ->field([
                'SUM(cost) AS total_cost',
                'SUM(price) AS total_price',
                'SUM(price - cost) AS total_profit'
            ])
            ->find();

        $todayStart = strtotime('today') + $timezoneOffset;
        $now = time() + $timezoneOffset;
        $today_cost = Db::table('fa_deviceslist')
            ->where('tjtime', '>=', $todayStart)
            ->where('tjtime', '<=', $now)
            ->field([
                'SUM(cost) AS total_cost',
                'SUM(price) AS total_price',
                'SUM(price - cost) AS total_profit'
            ])
            ->find();


        $this->view->assign([
            'totaluser'         => count($notice_list),
            'totaladdon'        => $totaladdon,
            'totaladmin'        => Admin::count(),
            'totalcategory'     => \app\common\model\Category::count(),
            'todayusersignup'   => Deviceslist::whereTime('tjtime', 'today')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'todayuserlogin'    => Deviceslist::whereTime('tjtime', '-365 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'sevendau'          => Deviceslist::whereTime('tjtime', '-90 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'thirtydau'         => Deviceslist::whereTime('tjtime', '-30 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'threednu'          => Deviceslist::whereTime('tjtime', '-3 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'zrxz'          => Deviceslist::whereTime('tjtime', '-1 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'byxz'          => Deviceslist::whereTime('tjtime', '-15 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'sevendnu'          => Deviceslist::whereTime('tjtime', '-7 days')->where('shouhou', 0)->field(['base64mp','base64p12'],true)->where('chi', 0)->count(),
            'dbtablenums'       => count($dbTableList),
            'dbsize'            => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'totalworkingaddon' => $totalworkingaddon,

            'cost_yesterday'  => (float)$cost['total_cost']/100,
            'amount_yesterday'  => (float)$cost['total_price']/100,
            'profit_yesterday'  => (float)$cost['total_profit']/100,

            'cost_today'  => (float)$today_cost['total_cost']/100,
            'amount_today'  => (float)$today_cost['total_price']/100,
            'profit_today'  => (float)$today_cost['total_profit']/100,

            'attachmentnums'    => Attachment::count(),
            'attachmentsize'    => Attachment::sum('filesize'),
            'picturenums'       => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'       => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));
        $this->assignconfig('userdata1', array_values($userlist1));
        $this->assignconfig('userdata2', array_values($this->getDeviceStats(Date::unixtime('day', -1), Date::unixtime('day', -1, 'end'))));

        return $this->view->fetch();
    }

    public function getDeviceStats($startTimestamp, $endTimestamp)
    {
        $result = Db::table('fa_deviceslist')
            ->whereBetween('tjtime', [$startTimestamp, $endTimestamp])
            ->where('chi', '<>', 1)
            ->where('shouhou', 0)
            ->field([
                'user',
                'COUNT(*) AS device_count',
                'MAX(tjtime) AS latest_submission',
                'GROUP_CONCAT(DISTINCT kid ORDER BY kid ASC) AS kids',
                'SUM(CASE WHEN type = 0 THEN 1 ELSE 0 END) AS 秒出',
                'SUM(CASE WHEN type = 1 THEN 1 ELSE 0 END) AS 审核',
                'SUM(CASE WHEN shtype = 0 AND type = 0 THEN 1 ELSE 0 END) AS 秒出躺平版',
                'SUM(CASE WHEN shtype = 0 AND type = 1 THEN 1 ELSE 0 END) AS 审核躺平版',
                'SUM(CASE WHEN shtype = 1 AND type = 0 THEN 1 ELSE 0 END) AS 秒出标准版',
                'SUM(CASE WHEN shtype = 1 AND type = 1 THEN 1 ELSE 0 END) AS 审核标准版',
                'SUM(CASE WHEN shtype = 2 AND type = 0 THEN 1 ELSE 0 END) AS 秒出加强版',
                'SUM(CASE WHEN shtype = 2 AND type = 1 THEN 1 ELSE 0 END) AS 审核加强版',
                'SUM(CASE WHEN shtype = 3 AND type = 0 THEN 1 ELSE 0 END) AS 秒出稳定版',
                'SUM(CASE WHEN shtype = 3 AND type = 1 THEN 1 ELSE 0 END) AS 审核稳定版',
                'SUM(CASE WHEN shtype = 4 AND type = 0 THEN 1 ELSE 0 END) AS 秒出摆烂版',
                'SUM(CASE WHEN shtype = 4 AND type = 1 THEN 1 ELSE 0 END) AS 审核摆烂版'
            ])
            ->group('user')
            ->order('device_count', 'asc')
            ->select();

        return $result;
    }
}