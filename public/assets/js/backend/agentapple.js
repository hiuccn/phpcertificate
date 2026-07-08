define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'agentapple/index' + location.search,
                     add_url: 'agentapple/add',
                    edit_url: 'agentapple/edit',
                    del_url: 'agentapple/del',
                    multi_url: 'agentapple/multi',
                      dragsort_url: 'ajax/weigh',
                    table: 'agentapple',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                //sortOrder: "asc",    
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('ID')},
                        {field: 'uid', title: __('UID')},
                        {field: 'iss', title: __('ISS')},
                        //  {field: 'kid', title: __('KID')},
                        //  {field: 'cid', title: __('CID')},
                        // {field: 'bid', title: __('BID')},
                         {field: 'devname', title: __('证书名称')},
                        {field: 'iphone', title: __('iPhone余量')},
                        {field: 'ipad', title: __('iPad余量')},
                        {field: 'mac', title: __('Mac余量')},
                        {field: 'id', title: __('刷新余量'), formatter: Table.api.formatter.myyl},
                        {field: 'id', title: __('下载P12'), formatter: Table.api.formatter.downmyp12},
                         {field: 'dqtime', title: __('到期时间')},
                          {field: 'zt', title: __('状态'), formatter:Table.api.formatter.toggle},
                           {field: 'yy', title: __('预约'), formatter:Table.api.formatter.toggle},
					    {field: 'user', title: __('用户'),formatter: Table.api.formatter.flag},
					     {field: 'zszt', title: __('证书状态'),searchList: {1: '正常', 0: '掉签'},formatter: Table.api.formatter.flag},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});