define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'appledev/index' + location.search,
                    add_url: 'appledev/add',
                    edit_url: 'appledev/edit',
                    del_url: 'appledev/del',
                    multi_url: 'appledev/multi',
                    dragsort_url: 'ajax/weigh',
                    table: 'appleidlist',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortOrder: "desc",
                //sortName: 'weigh',

                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('ID')},
                        {field: 'email', title: __('苹果账号')},
                        {field: 'iss', title: __('ISS')},
                        // {field: 'kid', title: __('KID')},
                        // {field: 'cid', title: __('CID')},
                        // {field: 'bid', title: __('BID')},
                        {field: 'devname', title: __('证书名称')},
                        {field: 'iphone', title: __('iPhone余量')},
                        {field: 'ipad', title: __('iPad余量')},
                        {field: 'mac', title: __('Mac余量')},
                        {field: 'id', title: __('刷新余量'), formatter: Table.api.formatter.yl},
                        {field: 'id', title: __('下载P12'), formatter: Table.api.formatter.downp12},
                        {field: 'id', title: __('下载描述'),formatter: actionFormatter},
                        {field: 'dqtime', title: __('到期时间')},
                        {field: 'yz', title: __('优质'), formatter:Table.api.formatter.toggle},
                        {field: 'zt', title: __('状态'), formatter:Table.api.formatter.toggle},
                        {field: 'yy', title: __('预约'), formatter:Table.api.formatter.toggle},
                        {field: 'sh', title: __('售后'), formatter:Table.api.formatter.toggle},
                        {field: 'open_ipad', title: __('IPAD'), formatter:Table.api.formatter.toggle},

                        {field: 'open0', title: __('摆烂'), formatter:Table.api.formatter.toggle},
                        {field: 'open1', title: __('躺平'), formatter:Table.api.formatter.toggle},
                        {field: 'open2', title: __('标准'), formatter:Table.api.formatter.toggle},
                        {field: 'open3', title: __('加强'), formatter:Table.api.formatter.toggle},
                        {field: 'open4', title: __('稳定'), formatter:Table.api.formatter.toggle},

                        {field: 'beizhu', title: __('备注')},
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


//操作栏的格式化
function actionFormatter(value, row, index) {
    var id = value;
    var result = "";
    result += '<span class="input-group-btn input-group-sm"><a onclick="down(' +row['id'] + ')" target="_blank" class="btn btn-default btn-sm"><i class="fa fa-download"></i></a></span>';

    return result;
}


function down(id) {


    layer.msg(`正在处理中..`, {icon: 16,shade: 0.3,time: false});
    $.ajax({
        type: 'POST',
        url: "appledev/downmp",
        data: {
            id:id,
        },
        dataType: "json",
        success: function(result, textStatus, jqXHR) {
            layer.closeAll();
            if(result['code']==1){


                location.href=result['url'];


            }else{
                layer.msg(result['msg'])
            }

        },
        error: function(response) {
            layer.closeAll();
            msg('请求失败');

        }});
}