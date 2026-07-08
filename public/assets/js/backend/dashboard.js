define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {

        initEchatTotal: function () {
            var myChartTotal = Echarts.init(document.getElementById('echart-total'), 'walden');
            // 指定图表的配置项和数据
            var option = {
                title: {
                    text: '',
                    subtext: ''
                },
                color: [
                    "#18d1b1",
                    "#3fb1e3",
                    "#626c91",
                    "#a0a7e6",
                    "#c4ebad",
                    "#96dee8"
                ],
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: [__('Register user')]
                },
                toolbox: {
                    show: false,
                    feature: {
                        magicType: {show: true, type: ['stack', 'tiled']},
                        saveAsImage: {show: true}
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: Config.column
                },
                yAxis: { type:'value'},
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '10',
                    bottom: 30
                }],
                series: [{
                    name: __('新增秒出'),
                    type: 'bar',
                    smooth: true,
                    areaStyle: {
                        normal: {}
                    },
                    barWidth: 40,
                    itemStyle:{
                        color:'#39cccc'  // 将柱子颜色改为绿色
                    },
                    data: Config.userdata,
                    itemStyle: {
                        normal: {
                            label: {
                                show: true, //开启显示
                                position: 'top', //内部显示
                                formatter:function (params) {
                                    // dataIndex是当前柱状图的索引

                                    return Config.userdata[params.dataIndex];
                                },
                                textStyle: {
                                    //数值样式
                                    color: 'black',
                                    fontSize: 12,
                                },
                            },
                        },
                    },
                },
                    {
                        name: __('新增预约'),
                        type: 'bar',
                        smooth: true,
                        areaStyle: {
                            normal: {}
                        },
                        barWidth: 40,
                        data: Config.userdata1,
                        itemStyle: {
                            normal: {
                                label: {
                                    show: true, //开启显示
                                    position: 'top', //内部显示
                                    formatter:function (params) {
                                        // dataIndex是当前柱状图的索引

                                        return Config.userdata1[params.dataIndex];
                                    },
                                    textStyle: {
                                        //数值样式
                                        color: 'black',
                                        fontSize: 12,
                                    },
                                },
                            },
                        },

                    }

                ]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChartTotal.setOption(option);
            return myChartTotal;
        },

        initEchatDetail: function () {
            var myChartDetail = Echarts.init(document.getElementById('echart-detail'), 'walden');
            // 提取所有的属性名（除了'user'）
            const keys = Object.keys(Config.userdata2[0]).filter(key => key == '秒出' || key == '审核');

            // 构建 series 数组
            const series = keys.map(key => ({
                name: key,
                type: 'bar',
                stack: 'total',
                label: {
                    show: true
                },
                emphasis: {
                    focus: 'series'
                },
                data: Config.userdata2.map(item => item[key])  // 提取每个对象中对应属性的值作为 data
            }));
            series.push({
                // 添加一个新的系列来显示总数
                name: '总数',
                type: 'bar',
                stack: 'total',
                label: {
                    show: true,
                    formatter: function (params) {
                        let total = 0;
                        for (let i = 0; i < option.series.length - 1; i++) {
                            total += parseInt(option.series[i].data[params.dataIndex]);
                        }
                        return total;
                    },
                    fontWeight: 'bold',
                    fontSize: 16
                },
                data: Config.userdata2.map(() => 0)  // 生成一个全 0 的数组
            });
            var option = {
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        // Use axis to trigger tooltip
                        type: 'shadow' // 'shadow' as default; can also be 'line' or 'shadow'
                    }
                },
                legend: {},
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'value'
                },
                yAxis: {
                    type: 'category',
                    data: Config.userdata2.map(item => item.user)
                },
                series: series,
            };
            myChartDetail.setOption(option);
            return myChartDetail;
        },

        index: function () {
            const myChartTotal = this.initEchatTotal();
            const myChartDetail = this.initEchatDetail();

            $(window).resize(function () {
                myChartTotal.resize();
                myChartDetail.resize();
            });

            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    myChartTotal.resize();
                    myChartDetail.resize();
                }, 0);
            });
        }
    };

    return Controller;
});
