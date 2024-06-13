function formatDate(date) {
  var dd = date.getDate();
  if (dd < 10) dd = '0' + dd;
  var mm = date.getMonth() + 1;
  if (mm < 10) mm = '0' + mm;
  var yy = date.getFullYear();
  return dd + '.' + mm + '.' + yy;
}

( function( factory ) {
                            "use strict";

                            if ( typeof define === "function" && define.amd ) {

                                    // AMD. Register as an anonymous module.
                                    define( [ "../widgets/datepicker" ], factory );
                            } else {

                                    // Browser globals
                                            factory( jQuery.datepicker );
                                    }
                            } )
( function( datepicker ) {
                            "use strict";

                            datepicker.regional.ru = {
                                    closeText: "Закрыть",
                                    prevText: "&#x3C;Пред",
                                    nextText: "След&#x3E;",
                                    currentText: "Сегодня",
                                    monthNames: [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
                                    "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ],
                                    monthNamesShort: [ "Янв", "Фев", "Мар", "Апр", "Май", "Июн",
                                    "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек" ],
                                    dayNames: [ "воскресенье", "понедельник", "вторник", "среда", "четверг", "пятница", "суббота" ],
                                    dayNamesShort: [ "вск", "пнд", "втр", "срд", "чтв", "птн", "сбт" ],
                                    dayNamesMin: [ "Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб" ],
                                    weekHeader: "Нед",
                                    dateFormat: "dd.mm.yy",
                                    firstDay: 1,
                                    isRTL: false,
                                    showMonthAfterYear: false,
                                    yearSuffix: "" };
                            datepicker.setDefaults( datepicker.regional.ru );

                            return datepicker.regional.ru;

                            } );

$( "#date" ).datepicker();
$( "#date" ).datepicker( "option", "changeYear", true ).datepicker( "option", "changeMonth", true );
$( "#date" ).datepicker( "option", "yearRange", "1930:2050" );
$( "#edate" ).datepicker();
$( "#edate" ).datepicker( "option", "changeYear", true ).datepicker( "option", "changeMonth", true );
$( "#edate" ).datepicker( "option", "yearRange", "1930:2050" );
$( "#enddate" ).datepicker();
$( "#enddate" ).datepicker( "option", "changeYear", true ).datepicker( "option", "changeMonth", true );
$( "#enddate" ).datepicker( "option", "yearRange", "1930:2050" );
$( "#veddate" ).datepicker();
$( "#veddate" ).datepicker( "option", "changeYear", true ).datepicker( "option", "changeMonth", true );
$( "#veddate" ).datepicker( "option", "yearRange", "1930:2050" );

$(".selectall").on("change", function(){ 
    $(this).parent().parent().parent("div[id^='content-']").children("span").children("input.studentcheck").prop("checked", $(this).is(":checked"));
    $(".studentcheck").change();
});

$("input[name=tab-btn]").on("change", function(){ 
    $(this).parent("div.tabs").children("div").children("span").children("input.studentcheck").prop("checked", false); 
    $("input.selectall").prop("checked", false); 
});

$(".studentcheck").on("change", function(){ 
    $(".studentcheck").parent().css("background-color", "");
    $(".studentcheck:checked").parent().css("background-color", "#929ebc77"); 
});

$(".delgroup").on("click", function(){ 
    if ($(".itab").length == 1)
        return false;
    if ($(".itab:checked").data("count") != 0) { 
        $("#error_messages").html("Нельзя удалить непустую группу!<br>Сначала переведите всех слушателей в другую группу.").show(); 
        return false; 
    } else { 
        return true; 
    } 
});

$(".editgroup").on("click", function(){ 
    $("#groupid").val($(".itab:checked").val()); 
    $("#grouprename").val($(".itab:checked").data("gname")); 
});

$("#grenamesubmit").on("click", function(){ 
    $(".itab:checked").data("gname", $("#grouprename").val());
    $(".itab:checked").next("label").text($("#grouprename").val()); 
    $.post("/?act=renamegroup", { id: $(".itab:checked").val(), groupname: $("#grouprename").val() }); 
    return false; 
});

$(".crossgroup").on("click", function(){ 
    $(this).children(".selectgroup").html(""); 
    if ($(".studentcheck:checked").length == 0)
        return false;
    var count = 0;
    $(".itab").each(function(){ 
        if (!$(this).is(":checked")) {
            if ($(this).data("gname") !== undefined){
                $(".selectgroup").append("<span class=\"cross\" data-group=\"" + $(this).val() + "\">" + $(this).data("gname") + "</span>"); 
                count++;
            }
        }
    }); 
    $(".selectgroup").append('\<script\>$(".cross").on("click", function(){ var students = [];$(".studentcheck:checked").each(function(){ students.push( $(this).val() ) });$.post("/?act=crossgroup", { oldgroup: $(".itab:checked").val(),  newgroup: $(this).data("group"), student: JSON.stringify(students) });setTimeout(function() {window.location.reload();}, 1000); });\</script\>');
    if (count > 0) $(this).children(".selectgroup").toggle(); 
});

$(".printstudent").on("click", function(){
    if ($(".studentcheck:checked").length == 0)
        $(".printzop").hide();
    else
        $(".printzop").show();
    $(".printzop").on("click", function(){
        var students = [];
        $(".studentcheck:checked").each(function(){
            students.push( $(this).val() )
        });
        location.href = "/?act=printdoc&doctype=1&student=" + JSON.stringify(students);
    });

    $(this).children(".printctrl").toggle();
});

$(".printall").on("click", function () {
    var students = [];
    $(".studentcheck").each(function(){
        students.push( $(this).val() )
    });
    location.href = "/?act=printdoc&doctype=6&student=" + JSON.stringify(students);
});

    // печатаем приказ о зачислении
    $("#pozprint").on("click", function(){
        var students = [];
        //alert("#content-"+$(".itab:checked").val());
        $("#content-"+$(".itab:checked").val()).children(".userlist").children(".studentcheck").each(function(){ 
            students.push( $(this).val() ) 
        });
        var save = 0;
        if ($('#save').is(':checked')) save = 1;
        location.href = "/?act=printdoc&doctype=2&date=" + $("#date").val() + "&group=" + $(".itab:checked").val() + "&pnumber=" + $("#pnumber").val() + "&save=" + save + "&student=" + JSON.stringify(students);
        return false;
    });
    
    // открываем окно параметров приказа о зачислении
    $(".printpoz").on('click', function(event){
        event.preventDefault(); // вырубaем стaндaртнoе пoведение
        $('#date').val(formatDate(new Date()));
        var pNumber = $.post("/?act=getorderaddnumber", { group: $(".itab:checked").val() } , function(data){
            var jData = JSON.parse(data);
            $("#pnumber").val(jData.pNumber);
            $("#date").val(jData.date);
            //$('#regnumber').val(jData.regnumber);
            if (jData.saved) {
                $(".warn").show();
                $("#rnblock").hide();
                $("#pnumber").prop("disabled", true);
                $("#date").prop("disabled", true);
                $("#save").prop("checked", false);
            }
        });
        var div = '#modal3'; 
        $('#overlay').fadeIn(400, //пoкaзывaем oверлэй
        function(){ // пoсле oкoнчaния пoкaзывaния oверлэя
            $(div) // берем стрoку с селектoрoм и делaем из нее jquery oбъект
            .css('display', 'block')
            .animate({opacity: 1, top: '30%'}, 200); // плaвнo пoкaзывaем
        });
    });
    
        $(".printpoo").on('click', function(event){
        event.preventDefault(); // вырубaем стaндaртнoе пoведение
        $('#edate').val(formatDate(new Date()));
        var pNumber = $.post("/?act=getorderexpnumber", { group: $(".itab:checked").val() } , function(data){
            var jData = JSON.parse(data);
            if (jData.error != '') {
                $("#pooprint").prop("disabled", true);
                $("#experror").html(jData.error);
                $("#experror").show();
            } else {
                $("#pooprint").prop("disabled", false);
                $("#experror").html('');
                $("#experror").hide();
            }
            $("#epnumber").val(jData.pNumber);
            $("#edate").val(jData.date);
            //$('#regnumber').val(jData.regnumber);
            if (jData.saved) {
                $(".warn").show();
                $("#ernblock").hide();
                $("#epnumber").prop("disabled", true);
                $("#edate").prop("disabled", true);
                $("#reason").prop("disabled", true);
                $("#esave").prop("checked", false);
            }
        });
        var div = '#modal4'; 
        $('#overlay').fadeIn(400, //пoкaзывaем oверлэй
        function(){ // пoсле oкoнчaния пoкaзывaния oверлэя
            $(div) // берем стрoку с селектoрoм и делaем из нее jquery oбъект
            .css('display', 'block')
            .animate({opacity: 1, top: '30%'}, 200); // плaвнo пoкaзывaем
        });
    });


$(".expulse").on("click", function(){
    if ($(".studentcheck:checked").length == 0) 
        return false;
    if (!confirm("Эту операцию нельзя будет отменить!\nВы уверены, что слушателя нужно отчислить?"))
        return false;
    var students = [];
    $(".studentcheck:checked").each(function(){
        students.push( $(this).val() ) 
    });
    $.post("/?act=expulsestudent", { student: JSON.stringify(students) });
    setTimeout(function () {
        location.href = window.location;
    }, 500);
});

// печатаем приказ об отчислении
$("#pooprint").on("click", function(){
    var students = [];
    //alert("#content-"+$(".itab:checked").val());
     $("#content-"+$(".itab:checked").val()).children(".wrong").children(".studentcheck").each(function(){
         students.push( $(this).val() )
     });
     console.log(students);
     var save = 0;
     if ($('#save').is(':checked')) save = 1;
     location.href = "/?act=printdoc&doctype=3&date=" + $("#edate").val() + "&group=" + $(".itab:checked").val() + "&pnumber=" + $("#epnumber").val() + "&save=" + save + "&reason=" + $("#reason").val() + "&student=" + JSON.stringify(students);
    return false;
});

// открываем окно параметров приказа о зачислении
$(".printpoz").on('click', function(event){
    event.preventDefault(); // вырубaем стaндaртнoе пoведение
    $('#date').val(formatDate(new Date()));
    var pNumber = $.post("/?act=getorderaddnumber", { group: $(".itab:checked").val() } , function(data){
        var jData = JSON.parse(data);
        $("#pnumber").val(jData.pNumber);
        $("#date").val(jData.date);
        //$('#regnumber').val(jData.regnumber);
        if (jData.saved) {
            $(".warn").show();
            $("#rnblock").hide();
            $("#pnumber").prop("disabled", true);
            $("#date").prop("disabled", true);
            $("#save").prop("checked", false);
        }
    });
    var div = '#modal3';
    $('#overlay').fadeIn(400, //пoкaзывaем oверлэй
        function(){ // пoсле oкoнчaния пoкaзывaния oверлэя
            $(div) // берем стрoку с селектoрoм и делaем из нее jquery oбъект
                .css('display', 'block')
                .animate({opacity: 1, top: '30%'}, 200); // плaвнo пoкaзывaем
        });
});

// открываем окно параметров приказа об окончании курса
$(".printpoe").on('click', function(event){
    event.preventDefault(); // вырубaем стaндaртнoе пoведение
    $("#poeprint").prop("disabled", true);
    $('#enddate').val(formatDate(new Date()));
    var pNumber = $.post("/?act=getorderendnumber", { group: $(".itab:checked").val() } , function(data){
        var jData = JSON.parse(data);
        $("#endpnumber").val(jData.pNumber);
        $("#enddate").val(jData.date);
        $("#veddate").val(jData.veddate);
        //$('#regnumber').val(jData.regnumber);
        if (jData.saved) {
            $(".warn").show();
            $("#endrnblock").hide();
            $("#endpnumber").prop("disabled", true);
            $("#veddate").prop("disabled", true);
            $("#enddate").prop("disabled", true);
            $("#endsave").prop("checked", false);
        }
        $("#poeprint").prop("disabled", false);
    });
    var div = '#modal5';
    $('#overlay').fadeIn(400, //пoкaзывaем oверлэй
        function(){ // пoсле oкoнчaния пoкaзывaния oверлэя
            $(div) // берем стрoку с селектoрoм и делaем из нее jquery oбъект
                .css('display', 'block')
                .animate({opacity: 1, top: '30%'}, 200); // плaвнo пoкaзывaем
        });
});

$("#poeprint").on("click", function(){
    var students = [];
    //$("#veddate").val(String(formatDate(new Date())));
    //alert("#content-"+$(".itab:checked").val());
    $("#content-"+$(".itab:checked").val()).children(".userlist").children(".studentcheck").each(function(){
        if ( !$(this).parent(".userlist").hasClass("wrong") )
        students.push( $(this).val() )
    });
    var save = 0;
    if ($('#endsave').is(':checked')) save = 1;
    location.href = "/?act=printdoc&doctype=4&date=" + $("#enddate").val() + "&vdate=" + $("#veddate").val() +  "&group=" + $(".itab:checked").val() + "&pnumber=" + $("#endpnumber").val() + "&save=" + save + "&student=" + JSON.stringify(students);
    return false;
});