<?php

echo "<h2>Test</h2><br />";
?>
<style>
.ui-autocomplete-loading {
  background: white url("./img/ui-anim_basic_16x16.gif") right center no-repeat;
}
</style>

<div class="ui-widget">
  <label for="ppl">Display Name: </label>
  <input id="ppl">
</div>

<div class="ui-widget" style="margin-top:2em; font-family:Arial">
  Result:
  <div id="log" style="height: 200px; width: 300px; overflow: auto;" class="ui-widget-content"></div>
</div>

<script>
  $(function() {
    function log( message ) {
      $( "<div>" ).text( message ).prependTo( "#log" );
      $( "#log" ).scrollTop( 0 );
    }

    $( "#ppl" ).autocomplete({
      source: function( request, response ) {
        $.ajax( {
          method: "POST",
          url: "http://localhost/test/PHPelelep/api",
          data: {
            s: request.term
          },
          success: function( data ) {
            data = $.parseJSON(data);
            response($.map(data, function (el) {
                   return {
                       id: el.sciper,
                       value: el.displayname,
                       units: el.units
                   };
               }));
          }
        });
      },
      minLength: 2,
      select: function( event, ui ) {
        log( "Selected: " + ui.item.value + " (#" + ui.item.id + "), units: " + ui.item.units);
      }
    });
  });
</script>
