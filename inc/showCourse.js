/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Other/javascript.js to edit this template
 */
$('.copylink').click(function (e) {
    e.preventDefault();
    value = $(this).data('link');
    var $temp = $("<input>");
    $('body').append($temp);
    $temp.val(value).select();
    document.execCommand("copy");
    $(this).animate({backgroundColor: "#0A0"}, 300).animate({backgroundColor: "transparent"}, 1000);
    $temp.remove();
});


$('.open_modal.ci').on('click', function (){
    if ($(this).data('act') != null)
        $('.modalcontent').load('/?act=showcourse', { cat: $(this).data('cat'), id: $(this).data('id')});
    else
        $('.modalcontent').load('/?act=showcourse', { cat: $(this).data('cat'), id: $(this).data('id'), hidectrl: 1});
});

$('.open_modal.au').on('click', function (){
    let course = $(this).data('course');
    $('.modalcontent').load('/?act=userlist',
                            { method: "post", hideadm: 1 },
                            function (){
                                $('#suserform').append('<input type="hidden" id="courseid" name="course" value="' + course + '">');
                                $('#suserform').append('<input type="submit" id="rectocourse" name="rectocourse" value="Зачислить на курс">');
                                $('#suserform').attr('action', '/?act=recmoretocourse&courseid=' +  $('#courseid').val());
                                $('#searchtext').data('hideadm', 1);
                                //вырубаем отправку формы
                                /*$('#suserform').on('submit', (e) => {
                                    e.preventDefault();
                                });*/
                                $('#rectocourse').click(function () {
                                    $('input[name^=user]:checkbox:not(:checked)').each(function () {
                                        $(this).next('input:hidden').remove();
                                        $(this).next('input:hidden').remove();
                                        $(this).next('input:hidden').remove();
                                    });
                                });

    });
});

$('.open_modal.co').on('click', function (){
    let course = $(this).data('course');
    $('.modalcontent').load('/?act=userlist',
        { method: "post", hideadm: 1 , group: 3},
        function (){
            $('#suserform').append('<input type="hidden" id="courseid" name="course" value="' + course + '">');
            $('#suserform').append('<input type="hidden" id="group" name="group" value="3">');
            $('#suserform').append('<input type="submit" id="changemoder" name="changemoder" value="Сменить модератора">');
            $('#suserform').attr('action', '/?act=chmoder&courseid=' +  $('#courseid').val());
            $('#searchtext').data('hideadm', 1);
            //вырубаем отправку формы
            /*$('#suserform').on('submit', (e) => {
                e.preventDefault();
            });*/
            $('#rectocourse').click(function () {
                $('input[name^=user]:checkbox:not(:checked)').each(function () {
                    $(this).next('input:hidden').remove();
                    $(this).next('input:hidden').remove();
                    $(this).next('input:hidden').remove();
                });
            });

        });
});