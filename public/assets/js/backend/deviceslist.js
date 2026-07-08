define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'deviceslist/index' + location.search,
                    add_url: 'deviceslist/add',
                    edit_url: 'deviceslist/edit',
                    del_url: 'deviceslist/del',
                    multi_url: 'deviceslist/multi',
                    table: 'deviceslist',
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
                        {field: 'chi', title: __('池'), searchList: {0: '公共', 1: '独立'}, formatter: Table.api.formatter.flag},
                        {field: 'shouhou', title: __('类型'), searchList: {0: '新购', 1: '售后'}, formatter: Table.api.formatter.flag},
                        {field: 'type', title: __('类别'), searchList: {0: '实时', 1: '预约', 2: '导入'}, formatter: Table.api.formatter.flag},
                        {field: 'shtype', title: __('版本'), searchList: {0: '躺平版', 1: '标准版', 2: '加强版', 3: '稳定版', 4: '摆烂版'}, formatter: Table.api.formatter.flag},
                        {field: 'udid', title: __('UDID')},
                        {field: 'model', title: __('设备'), formatter: Table.api.formatter.flag},
                        {field: 'kid', title: __('证书编号')},
                        {field: 'pname', title: __('证书名称'), formatter: Table.api.formatter.flag},
                        {field: 'zspt', title: __('证书来源'), searchList: {1: '本站', 2: '华阳', 3: '柠萌签', 4: '优速测', 5: '华阳摆'}, formatter: Table.api.formatter.flag},
                        {field: 'deviceid', title: __('第三方ID')},
                        {field: 'user', title: __('使用用户'), formatter: Table.api.formatter.flag},
                        {field: 'zt', title: __('状态'), operate: false, formatter: Table.api.formatter.status},
                        {field: 'tjtime', title: __('添加时间'), formatter: Table.api.formatter.datetime},
                        {field: 'kid', title: __('下载证书'), formatter: Table.api.formatter.downall},
                        {field: 'cost', title: __('成本'), formatter: Table.api.formatter.amount},
                        {field: 'price', title: __('售价'), formatter: Table.api.formatter.amount},
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