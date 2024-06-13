tinymce.init({
      selector: '#note',
      plugins: 'autoresize autolink lists media table link charmap codesample image imagetools media nonbreaking paste quickbars',
      menubar:'',
      toolbar: 'undo redo | bold italic | alignleft aligncentre alignright alignjustify | bullist numlist | paste pastetext link image media table | casechange | charmap codesample nonbreaking | indent outdent | formatselect',
      language_url : '/inc/tmce-ru.js',
      language: 'ru',
      toolbar_mode: 'floating',
      images_upload_url: '/?act=imageupload',
      paste_data_images: true,
      image_uploadtab: true,
      automatic_uploads: true,
      block_unsupported_drop: true,
      file_picker_types: 'image',
      images_file_types: 'jpeg,jpg,jpe,jfi,jif,jfif,png,gif,bmp,webp',
      images_reuse_filename: true,
      resize: 'both',
      width: '100%',
      max_width: 600,
      min_height: 300,
      height: 300
    });

    $("form#addnewpredmet").submit(function() {// обрабатываем отправку формы   
        $('#predmetlist').load('/?act=addnewpredmet', { predmet: $("#predmetname").val() });
        $("#predmetname").val('');
        return false;
    });//submit

$("#curatorphone").mask("+7(999)999-99-99");

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

$( ".date" ).datepicker();
$( ".date" ).datepicker( "option", "changeYear", true ).datepicker( "option", "changeMonth", true );
$( ".date" ).datepicker( "option", "yearRange", "2022:2050" )
                            
                        
/*                        if($('#fio').prop( "checked" ))
                                $('#dfio').show();
                        $('#fio').on('click',function(){
                            if($('#fio').prop( "checked" )){
                                $('#dfio').show();
                                $('#dfio .field :input').addClass('rfield');
                            } else {
                                $('#dfio').hide();
                                    $('#dfio .field :input').prop('class', '');
                            }
                        });*/
                        
function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);
        return pattern.test(emailAddress);
}//isValidEmailAddress(emailAddress)================================================================


//-------------------------------------------------------------------------------------------

    $("form#addcourse").submit(function() {// обрабатываем отправку формы   
        var error=0; // индекс ошибки

        var email = $("#curatoremail").val();
        if(!isValidEmailAddress(email)){
            error=2;
        }
        
        $(".rfield").each(function() {// проверяем каждое поле в форме
                    if(!$(this).val()){// если в поле пустое
                        //alert($(this).object);
                        $(this).css('border', 'red 2px solid').css('background-color','#DDAAAA');// устанавливаем рамку красного цвета
                        error=1;// определяем индекс ошибки      
                    }
                    else{
                        $(this).css('border', 'gray 1px solid').css('background-color','');// устанавливаем рамку обычного цвета
                    }
        })
        //alert(error);
        if(error==0){ // если ошибок нет то отправляем данные
            $("#messenger").hide();
            return true;
        }
        else{
        if(error==1) var err_text = "Не все обязательные поля заполнены!1";
        if(error==2)  var err_text="Введен некорректный e-mail!";

        $("#messenger").html(err_text);
        $("#messenger").fadeIn("slow");
        return false; //если в форме встретились ошибки , не  позволяем отослать данные на сервер.
        }
    });

//-------------------------------------------------------------------------------------------

$('.js-select2').select2({
		placeholder: "Выберите категорию слушателей",
		language: "ru"
	});
/** Костыль для автофокуса поля ввода в выпадающем списке 
 *  перестало работатть на новой jQuery
 *  когда разрабы пофиксят баг, можно убрать */
jQuery(document).on('select2:open', function (e) {
        window.setTimeout(function() {
            jQuery(".select2-container--open .select2-search__field").get(0).focus();
        }, 200);
    });
/* конец костыля ... */    

document.querySelector("#cover").addEventListener("change", function () {
  if (this.files[0]) {
    var fr = new FileReader();
    var file_name = this.value.replace(/\\/g, '/').replace(/.*\//, '')

    fr.addEventListener("load", function () {
        document.querySelector("#coverimg").style.backgroundImage = "url(" + fr.result + ")";
        var image = new Image();
        image.src = fr.result; 
        image.onload = function(){
          $('#imgsize').text(this.width+'x'+this.height+'px');
          $('#imgname').text(file_name+', ');
        }
    }, false);

    fr.readAsDataURL(this.files[0]);
  }
});

