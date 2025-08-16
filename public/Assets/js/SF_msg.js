/*
Template Name: Color Admin - Responsive Admin Dashboard Template build with Twitter Bootstrap 3.3.7 & Bootstrap 4.0.0-Alpha 6
Version: 3.0.0
Author: Sean Ngu
Website: http://www.seantheme.com/color-admin-v3.0/admin/apple/
*/
var blue = "#007aff",
blueLight = "#409bff",
blueDark = "#005bbf",
aqua = "#5AC8FA",
aquaLight = "#83d6fb",
aquaDark = "#4396bb",
green = "#4CD964",
greenLight = "#79e38b",
greenDark = "#39a34b",
orange = "#FF9500",
orangeLight = "#ffb040",
orangeDark = "#bf7000",
dark = "#222222",
grey = "#bbbbbb",
purple = "#5856D6",
purpleLight = "#8280e0",
purpleDark = "#4240a0",
red = "#FF3B30",
handleDashboardDatepicker = function() {
    "use strict";
    $("#datepicker-inline").datepicker({
    	language:"zh-CN",
        todayHighlight: !0
    })
},
handleDashboardTodolist = function() {
    "use strict";
    $("[data-click=todolist]").click(function() {
        var e = $(this).closest("li");
        $(e).hasClass("active") ? $(e).removeClass("active") : $(e).addClass("active")
    })
},
handleDashboardGritterNotification = function() {
    $(window).load(function() {
        setTimeout(function() {
            $.gritter.add({
                title: "欢迎使用SF授权系统!",
                text: "2021全新定义，倾情设计，全力打造，全网最牛逼的授权系统！<br>By：陌上花开",
                image: "/assets/images/logo.png",
                sticky: !0,
                time: "",
                class_name: "my-sticky-class"
            })
        },
        1e3)
    })
},
Dashboard = function() {
    "use strict";
    return {
        init: function() {
            handleDashboardGritterNotification(),
            handleDashboardTodolist(),
            handleDashboardDatepicker()
        }
    }
} ();