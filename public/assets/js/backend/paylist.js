define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'paylist/index' + location.search,
                    del_url: 'paylist/del',
                    multi_url: 'paylist/multi',
                    table: 'paylist',
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
                        {field: 'id', title: __('ID')},
                         {field: 'username', title: __('用户'),  formatter: Table.api.formatter.flag},
                        {field: 'num', title: __('数量')},
                         {field: 'fee', title: __('金额')},
                        {field: 'zt', title: __('状态'),searchList: {1: '已支付', 0: '未支付'},formatter: Table.api.formatter.flag},
                        {field: 'fktime', title: __('支付时间'),formatter: Table.api.formatter.datetime},
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