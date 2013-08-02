<div id="netmap"></div>
<div id="netmap_controls">
<input id="netmap_save" type="button" value="save" /> <a id="network_map_id"></a><br />
<input id="netmap_reload" type="button" value="reload" /><br />
force: <input id="netmap_force" type="button" value="off" />

<script>
$("#netmap_force").on("click", function() {
  if ($(this).val() == "off") {
    netmap.use_force();
    $(this).val("on");
  } else {
    netmap.deny_force();
    $(this).val("off");
  }
});

$("#netmap_save").on("click", function() {
  netmap.save_map(function(id) {
    $("#network_map_id").attr("href", "http://littlesis.org/map/" + id);
    $("#network_map_id").text(id);
  });
});

$("#netmap_reload").on("click", function() {
  netmap.reload_map();
});

</script>
</div>