   $(".showsettings").on("click", function(){
      $(".adm").hide();
      $(".closeset").hide();
      $(this).next().next(".adm").load("/?act=settings&type="+$(this).data("type")).show();
      $(this).next(".closeset").show();
      $(".closeset").on("click", function(){
      $(this).hide();
      $(this).next(".adm").hide();
       });
   });

   $(".stab").on("click", function(){
      //alert($(this).data("id"));
       $("#content-" + $(this).data("id")).load("/?act=admin", { method: "POST", cat: $(this).data("cat") });
   });

   $("#searchtex").keypress(
       function(event){
           if (event.which == '13') {
               event.preventDefault();
           }
       });