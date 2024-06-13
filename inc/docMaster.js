$(".open_modal").on("click", function(){ 
    var group = $(this).data("group");
    var type = Number($(this).data("type"));
    // $("#group").val($(this).data("group"));
    // $("#type").val($(this).data("type"));
    switch(type) {
        case 2:
            $.post("/?act=getorderaddnumber", { group: group } , function(data){
                var jData = JSON.parse(data);
                $("#pnumber").val(jData.pNumber);
                $("#date").val(jData.date);
                $("#group").val(group);
                $("#type").val(type);
                $(".warn").hide();
                $("#pnumber").prop("disabled",false);
                $("#date").prop("disabled", false);
                if (jData.saved) {
                    $(".warn").show();
                    $("#rnblock").hide();
                    $("#pnumber").prop("disabled", true);
                    $("#date").prop("disabled", true);
                }
            });
            break;
        case 3:
            $.post("/?act=getorderexpnumber", { group: group } , function(data){
                var jData = JSON.parse(data);
                if (jData.error != ''){
                    $(".modal_div").hide();
                    $("#overlay").hide();
                    //alert(jData.error);
                    return false;
                }
                $("#epnumber").val(jData.pNumber);
                $("#edate").val(jData.date);
                $("#egroup").val(group);
                $("#etype").val(type);
                $("#reason").val(jData.reason);
                $("#reason").prop("disabled", false);
                $("#epnumber").prop("disabled", false);
                $("#edate").prop("disabled", false);
                $(".warn").hide()
                if (jData.saved) {
                    $(".warn").show();
                    $("#ernblock").hide();
                    $("#epnumber").prop("disabled", true);
                    $("#edate").prop("disabled", true);
                    $("#reason").prop("disabled", true);
                }
            });
            break;
        case 4:
            $.post("/?act=getorderendnumber", { group: group } , function(data){
                var jData = JSON.parse(data);
                if (jData.error != ''){
                    $(".modal_div").hide();
                    $("#overlay").hide();
                    alert('!');
                    return false;
                }
                $("#endpnumber").val(jData.pNumber);
                $("#enddate").val(jData.date);
                $("#veddate").val(jData.veddate);
                $("#endgroup").val(group);
                $("#endtype").val(type);
                $(".warn").hide()
                $("#endpnumber").prop("disabled", false);
                $("#veddate").prop("disabled", false);
                $("#enddate").prop("disabled", false);
                if (jData.saved) {
                    $(".warn").show();
                    $("#endrnblock").hide();
                    $("#endpnumber").prop("disabled", true);
                    $("#veddate").prop("disabled", true);
                    $("#enddate").prop("disabled", true);
                }
            });
            break;
        default:
            break;
    }


});

//Приказ о зачислении на курс
$("#pozprint").on("click", function(){
    var save = 0;
    if ($("#save").is(":checked")) save = 1;
    location.href = "/?act=printpoz&doctype=2&date=" + $("#date").val() + "&group=" + $("#group").val() + "&pnumber=" + $("#pnumber").val() + "&save=" + save;
    return false;
});
//Приказ об отчислении с курса
$("#pooprint").on("click", function(){
    var save = 0;
    if ($("#esave").is(":checked")) save = 1;
    location.href = "/?act=printpoo&doctype=3&date=" + $("#edate").val() + "&group=" + $("#egroup").val() + "&pnumber=" + $("#epnumber").val() + "&reason=" + $("#reason").val() + "&save=" + save;
    return false;
});
//Приказ об окончании курса
$("#poeprint").on("click", function(){
    var save = 0;
    if ($("#endsave").is(":checked")) save = 1;
    location.href = "/?act=printpoe&doctype=4&date=" + $("#enddate").val() + "&group=" + $("#endgroup").val() + "&pnumber=" + $("#epnumber").val() + "&vdate=" + $("#veddate").val() + "&save=" + save;
    return false;
});