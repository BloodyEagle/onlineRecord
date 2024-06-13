var stimer = null;
$('#searchtext').keyup(function () {
    if ($(this).val().length < 3) return null;
    clearTimeout(stimer);
    stimer = setTimeout(findUser, 1500, $(this).val(), $(this).data('hideadm'), $('#group').val());
});

$('form').keydown(function(event){
    if(event.keyCode == 13) {
        event.preventDefault();
        return false;
    }
});
function findUser(ftext, hideadm, fgroup = null) {
    if (hideadm) {
        if (fgroup === null) {
            $('#suserbox').load('/?act=searchuser', {text: ftext, hideadm: 1});
        } else {
            $('#suserbox').load('/?act=searchuser', {text: ftext, hideadm: 1, group: fgroup});
        }
    } else {
        if (fgroup === null) {
            $('#suserbox').load('/?act=searchuser', {text: ftext});
        } else {
            $('#suserbox').load('/?act=searchuser', {text: ftext, group: fgroup});
        }
    }
}

$(document).on('click', 'li#sortid', function(){
    tinysort('ul.userinfoblock>ul',{selector:'li.id'});
});

$(document).on('click', 'li#sortsnils', function(){
    tinysort('ul.userinfoblock>ul',{selector:'li.snils'});
});

$(document).on('click', 'li#sortphone', function(){
    tinysort('ul.userinfoblock>ul',{selector:'li.phone'});
});

$(document).on('click', 'li#sortemail', function(){
    tinysort('ul.userinfoblock>ul',{selector:'li.email'});
});

$(document).on('click', 'li#sortfio', function(){
    tinysort('ul.userinfoblock>ul',{selector:'li.fio'});
});

$(document).on('click', '.act', function(){
    if ($(this).is(':checked')) {
        $(this).parent('li').parent('ul').removeClass('orange');
        $.post('/?act=moderact', { id: $(this).val(), action: 1 });
    } else {
        $(this).parent('li').parent('ul').addClass('orange');
        $.post('/?act=moderact', { id: $(this).val(), action: 0 });
    }

});

$(document).on('click', '.ban', function(){
    if (window.confirm('Вы уверены, что хотите заблокировать/разблокировать пользователя?')==true)
    {
        if ($(this).is(':checked')) {
            $(this).parent('li').parent('ul').addClass('wrong');
            $.post('/?act=ban', { id: $(this).val(), action: 1 });
        } else {
            $(this).parent('li').parent('ul').removeClass('wrong');
            $.post('/?act=ban', { id: $(this).val(), action: 0 });
        }
    } else {
        $(this).prop('checked', false);
    }


});

$(document).on('change', '.group', function(){
    $.post('/?act=chugroup', { user: $(this).data('user'), group: $(this).val() });
});

$(document).on('click', '.resetpass', function(){
    if (confirm('Выслать ссылку на сброс пароля?')) {
        $.post('/?act=lostpass', { snils: $(this).data('snils'), email: $(this).data('email'), phone: $(this).data('phone') });
    }
});


$('#searchtext').focus();
