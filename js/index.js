$(function () {
    setInterval(function () {
        $.getJSON("index.php", {api: "1"}, function (data) {

            //次のバスが取れない時はテーブル非表示
            if (data[1] === undefined) {
                $("#bus_next").css("display", "none");
            } else {
                $("#bus_next").css("display", "");
            }

            if (data[0] !== undefined) {
                $("#departure").html(data[0].departure);
                $("#arrival").html(data[0].arrival);
                $("#min_left").html(data[0].time + "分");
                $("#note").html(data[0].text);
            }

            if (data[1] !== undefined) {
                $("#next_departure").html(data[1].departure);
                $("#next_arrival").html(data[1].arrival);
                $("#next_min_left").html(data[1].time + "分");
                $("#next_note").html(data[1].text);
            }
        });
    }, 15000);
});