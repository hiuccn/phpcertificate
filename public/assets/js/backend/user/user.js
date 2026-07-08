define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('ID'), operate: 'LIKE'},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},

                        {field: 'money', title: __('余额'), operate: 'BETWEEN', sortable: true,formatter:edit_money},
                        {field: 'score', title: __('Score'), operate: 'BETWEEN', sortable: true,formatter:edit_score},
                        {field: 'mac', title: __('Mac权限'), formatter:Table.api.formatter.toggle},
                        {field: 'price', title: __('单独价格'), formatter:edit_price},
                        {field: 'yy_price', title: __('预约单独价格'), formatter:edit_yy_price},
                        {field: 'gender', title: __('Gender'), visible: false, searchList: {1: __('Male'), 0: __('Female')}},

                        {field: 'successions', title: __('Successions'), visible: false, operate: 'BETWEEN', sortable: true},

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


function edit_money(value, row, index) {

    return ` <a href="javascript:;" class="btn btn-success btn-sm" onclick="set('${row.username} - 增加余额','money',${row.id},${value},'plus')" ><i class="fa fa-plus"></i></a> <input readonly style="  padding: 5px;margin: 1px;border: 1px solid #ccc;border-radius: 4px;max-width: 80px; top:1px;"  type="text"  value="${value}"> <a href="javascript:;" onclick="set('${row.username} - 扣除余额','money',${row.id},${value},'minus')" class="btn btn-warning btn-sm"  ><i class="fa fa-minus"></i></a> `;

}


function edit_score(value, row, index) {

    return ` <a href="javascript:;" onclick="set('${row.username} - 增加设备数','score',${row.id},${value},'plus')" class="btn btn-success btn-sm"  ><i class="fa fa-plus"></i></a> <input readonly style="  padding: 5px;margin: 1px;border: 1px solid #ccc;border-radius: 4px;max-width: 80px; top:1px;"  type="text"  value="${value}"> <a  onclick="set('${row.username} - 扣除设备数','score',${row.id},${value},'minus')" href="javascript:;" class="btn btn-warning btn-sm"  ><i class="fa fa-minus"></i></a> `;

}

function edit_price(value, row, index) {
    return  ` <input readonly style=" padding: 5px;margin: 1px;border: 1px solid #ccc;border-radius: 4px;max-width: 100px;" name="edit-${row.id}"  type="text" value="${value}"> <a href="javascript:;" onclick="change(${row.id},'${value}', 'price')" class="btn btn-info
      btn-sm"  ><i class="fa fa-edit"></i></a> `;
}

function edit_yy_price(value, row, index) {
    return  ` <input readonly style=" padding: 5px;margin: 1px;border: 1px solid #ccc;border-radius: 4px;max-width: 100px;" name="edit-${row.id}"  type="text" value="${value}"> <a href="javascript:;" onclick="change(${row.id},'${value}', 'yy_price')" class="btn btn-info
      btn-sm"  ><i class="fa fa-edit"></i></a> `;
}


function change(id,str,type){

    let arr= str.split(",");
    var tp = arr[0];
    var jq = arr[1];
    var wd = arr[2];
    var bl = arr[3];
    var bz = arr[4];

    layer.open({
        title: '设置单独价格',
        btnAlign: 'c',
        closeBtn:'1',//右上角的关闭
        content: `  <div style="padding-top:2px;padding-right: 5px;padding-left:5px;">
                <p>填0则表示按后台全局价格</p>
               躺平版：<br>
               <input id="tp" type="Number" class="form-control"   value="${tp}" oninput="if(!/^[0-9]+$/.test(value)) value=value.replace(/\D/g,'');if(value>100)value=100;if(value<0)value=0" v-model='testNum'>
                加强版：<br>
               <input id="jq" type="Number" class="form-control" value="${jq}" oninput="if(!/^[0-9]+$/.test(value)) value=value.replace(/\D/g,'');if(value>100)value=100;if(value<0)value=0" v-model='testNum'>
                稳定版：<br>
               <input id="wd" type="Number" class="form-control" value="${wd}" oninput="if(!/^[0-9]+$/.test(value)) value=value.replace(/\D/g,'');if(value>100)value=100;if(value<0)value=0" v-model='testNum'>
                摆烂版：<br>
               <input id="bl" type="Number" class="form-control" value="${bl}" oninput="if(!/^[0-9]+$/.test(value)) value=value.replace(/\D/g,'');if(value>100)value=100;if(value<0)value=0" v-model='testNum'>
                标准版：<br>
               <input id="bz" type="Number" class="form-control" value="${bz}" oninput="if(!/^[0-9]+$/.test(value)) value=value.replace(/\D/g,'');if(value>100)value=100;if(value<0)value=0" v-model='testNum'>
                </div>`,
        btn:['确认','取消'],
        yes: function (index, layero) {

            var a = $('#tp').val();
            var b = $('#jq').val();
            var c = $('#wd').val();
            var d = $('#bl').val();
            var e = $('#bz').val();
            var before = a +',' + b +',' + c + ','+ d + ','+ e;
            layer.msg(`正在处理中..`, {
                icon: 16
                ,shade: 0.3
                ,time: false
            });

            $.ajax({
                type: 'POST',
                url: "user/user/change",
                data: {
                    type:type,
                    id:id,
                    after:before,
                    before:before
                },
                dataType: "json",
                success: function(result, textStatus, jqXHR) {
                    layer.closeAll();
                    if(result['code']!=1){
                        layer.msg(result['msg']);
                    }else{
                        layer.msg('修改成功');
                        $('#table').bootstrapTable('refresh', {silent: true});
                    }
                },
                error: function(response) {
                    layer.msg('修改失败');
                }});
        },
        no:function(index)
        {
            layer.close(index);
            return false;//点击按钮按钮不想让弹层关闭就返回false
        }
    });
}

function set(msg,type,id,after,act){
    layer.prompt({title:msg,btn: ['确定', '取消'],offset: '25%',},
        function(value,index,elem){
            if (!/^-?\d+(\.\d{1,2})?$/.test(value)) {
                layer.msg('请输入整数或小数，可带有两位小数', {time: 1000, icon: 5});
                $(this).val('');
                return false;
            }
            if(act =='plus'){before = after + parseFloat(value);}
            if(act =='minus'){before = after - parseFloat(value);}

            layer.msg(`正在处理中..`, {
                icon: 16
                ,shade: 0.3
                ,time: false
            });
            $.ajax({
                type: 'POST',
                url: "user/user/change",
                data: {
                    type:type,
                    id:id,
                    after:after,
                    before:before
                },
                dataType: "json",
                success: function(result, textStatus, jqXHR) {
                    layer.closeAll();
                    if(result['code']!=1){

                        layer.msg(result['msg'])
                    }else{
                        layer.msg('修改成功');
                        $('#table').bootstrapTable('refresh', {silent: true});
                    }
                },
                error: function(response) {
                    layer.msg('修改失败');
                }});

        });

}