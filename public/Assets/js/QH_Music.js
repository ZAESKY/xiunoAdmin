/*
@ name: web music player js
@ author: 陌上花开
@ link: https://www.tjit.net
@ date: 2019-10-20
@ version: 1.0.0.1
@ requires: Jquery APlayer
*/
$("head").append("<link>");
var css = $("head").children(":last");
css.attr({
    rel: "stylesheet",
    type: "text/css",
    href: "https://lib.baomitu.com/aplayer/1.10.1/APlayer.min.css"
});
document.write('<div id="aplayer"></div>');
$.getScript('https://lib.baomitu.com/aplayer/1.10.1/APlayer.min.js', function () {
    $.ajax({
        type: "GET",
        url: '/api.php/MusicAnalysis',
        dataType: 'json',
        success: function (result) {
            var ap = new APlayer({
                element: document.getElementById('aplayer'),
                lrcType: 3,
                volume: 1,
                mutex: true,
                fixed: true,
                theme: '#cfd9df',
                autoplay: true,
                order: 'list',
                //loop: 'none',
                //mini: false,
                //listFolded: false,
                //listMaxHeight: -,
                audio: result.Body,
            });
        }
    });
});
