(function() {
    var addr = "/?act=userlist";
    $.getJSON( addr, {
        type: "search",
        searchtext: '000',
        mode: "json"
    })
        .done(function( data ) {
            $.each( data, function( i, item ) {
                $.html("<li>item.fio</li>").appendTo( "#suserbox" );
            });
        });
})();