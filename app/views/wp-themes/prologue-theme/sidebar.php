
<div id="sidebar">

<?php global $request; if ($request->action == 'index') : ?>
  <ul>

<?php 
if( !function_exists('dynamic_sidebar') || !dynamic_sidebar() ) { 
  $before = "<li><h2>Recent Projects</h2>\n";
  $after = "</li>\n";

  $num_to_show = 35;

  echo prologue_recent_projects( $num_to_show, $before, $after );
} // if dynamic_sidebar
?>
  
    <li class="credits">
      <p>Powered by <a href="http://dbscript.net/">dbscript</a></p>
      <p>Prologue theme by <a href="http://automattic.com/">Automattic</a></p>
    </li>
  </ul>

<?php endif; ?>
</div> <!-- // sidebar -->
