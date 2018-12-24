// JavaScript Document

var turnplate = {
    restaraunts: [], //大转盘奖品名称
    colors: [], //大转盘奖品区块对应背景颜色
    figure:[],//图片
    outsideRadius: 239, //大转盘外圆的半径
    textRadius: 165, //大转盘奖品位置距离圆心的距离
    insideRadius: 65, //大转盘内圆的半径
    startAngle: 0, //开始角度
    bRotate: false //false:停止;ture:旋转
};



$(document).ready(function() {

    turnplate.restaraunts = $('.canvasImg sapn');
    turnplate.colors = ["#FFF4D6", "#F7FBED", "#FFF4D6", "#F7FBED","#FFF4D6", "#F7FBED","#FFF4D6", "#F7FBED"];

    //旋转转盘 item:奖品位置; txt：提示语;
    var rotateFn = function(item, txt, data) {
        var angles = item * (360 / turnplate.restaraunts.length) - (360 / (turnplate.restaraunts.length * 2));
        if (angles < 270) {
            angles = 270 - angles;
        } else {
            angles = 360 - angles + 270;
        }
        // $('#wheelcanvas').stopRotate();
        $('#wheelcanvas').rotate({
            angle: 0,
            animateTo: angles + 1800,
            duration: 6000,
            callback: function() {
                //中奖页面与谢谢参与页面弹窗
                $("#popus").fadeIn();
                $('.mask').show();
                // $('.xxcy_text').html(turnplate.restaraunts[item - 1])
                num = 2
            }
        });
    };

    /********弹窗页面控制**********/
    var num = 1;
    $('.close_xxcy').click(function() {
        $('#popus').fadeOut();
        num = 1
    });

    /********抽奖开始**********/
    $('.tupBtn').click(function() {
        lotteryStart()
    });

    function lotteryStart() {
        if (num == 1) {
            var item = 1;
            var data = null;
            rotateFn(item, turnplate.restaraunts[item - 1], data);
            num = num + 1
        }else{
            alert('对不起您的次数已经用完')
        }
    }
});

//页面所有元素加载完毕后执行drawRouletteWheel()方法对转盘进行渲染
window.onload = function() {
    drawRouletteWheel();
};

function drawRouletteWheel() {
    var canvas = document.getElementById("wheelcanvas");
    if (canvas.getContext) {
        //根据奖品个数计算圆周角度
        var arc = Math.PI / (turnplate.restaraunts.length / 2);
        var ctx = canvas.getContext("2d");
        //在给定矩形内清空一个矩形
        ctx.clearRect(0, 0, 516, 516);
        //strokeStyle 属性设置或返回用于笔触的颜色、渐变或模式  
        ctx.strokeStyle = "#FFBE04";
        //font 属性设置或返回画布上文本内容的当前字体属性 
        ctx.font = 'bold 22px Microsoft YaHei';

        for (var i = 0; i < turnplate.restaraunts.length; i++) {
            var angle = turnplate.startAngle + i * arc;
            var id = 'indexRain'+i;
            var img = document.getElementById(id);
            // 图片放到画布上
            
            ctx.fillStyle = turnplate.colors[i];

            ctx.beginPath();
            //arc(x,y,r,起始角,结束角,绘制方向) 方法创建弧/曲线（用于创建圆或部分圆）    
            ctx.arc(258, 258, turnplate.outsideRadius, angle, angle + arc, false);
            ctx.arc(258, 258, turnplate.insideRadius, angle + arc, angle, true);
            
            ctx.stroke();
            ctx.fill();
            //锁画布(为了保存之前的画布状态)
            ctx.save();

            //----绘制奖品开始----
            ctx.fillStyle = "#E83800";
            //ctx.fillStyle = turnplate.fontcolors[i];
            var text = turnplate.restaraunts[i];
            var line_height = 30;
            //translate方法重新映射画布上的 (0,0) 位置
            ctx.translate(258 + Math.cos(angle + arc / 2) * turnplate.textRadius, 258 + Math.sin(angle + arc / 2) * turnplate.textRadius);
            
            //rotate方法旋转当前的绘图
            ctx.rotate(angle + arc / 2 + Math.PI / 2);

            ctx.drawImage(img,-50, -50,100,100);
            //把当前画布返回（调整）到上一个save()状态之前 
            ctx.restore();
            //----绘制奖品结束----
        }
    }

}

//提示抽奖结束
function theEnd() {
    $('#tupBtn').unbind('click'); //提交成功解除点击事件。   
    return 2;
}