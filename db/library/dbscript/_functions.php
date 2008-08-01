<?php

  /** 
   * dbscript -- restful openid framework
   * @version 0.5.0 -- 17-July-2008
   * @author Brian Hendrickson <brian@dbscript.net>
   * @link http://dbscript.net/
   * @copyright Copyright 2008 Brian Hendrickson
   * @package dbscript
   * @license http://www.opensource.org/licenses/mit-license.php MIT License
   */


  /**
   * classify
   * 
   * takes a table/resource name ('entries')
   * makes it singular & capitalized ('Entry')
   *
   * @access public
   * @param string $resource
   * @return string
   */

function classify( $resource ) {
  
  $n = strtolower(str_replace( '_', ' ', $resource ));
  
  switch( $n ) {
    
    // renaming handlers
    
    case ( $n == 'person' || $n == 'people' ) :
      $n = 'Person';
      break;
      
    case ( substr( $n, -3 ) == 'ies' ) :
      $n = substr( $n, 0, -3 ) . "y";
      break;
      
    case ( substr( $n, -1 ) == 's' ) :
      $n = substr( $n, 0, -1 );
      break;
      
  }
  
  $n = ucwords($n);
  
  $n = str_replace( ' ', '', $n );
  
  return $n;
  
}


  /**
   * tableize
   * 
   * takes a (CamelCase or not) name ('DbSession')
   * makes it lower_case and plural ('db_sessions')
   * 
   * @access public
   * @param string $table
   * @return string
   */
   
function tableize( $object ) {

  $n = preg_split( '//', $object );
  
    // replace uppercase Chars with _ and lowercase char
  foreach ( $n as $idx=>$char ) {
    if ( ctype_upper( $char ) && ( $idx > 1 ) && $char )
      $n[$idx] = "_" . strtolower($char);
    elseif ( ctype_upper( $char ) )
      $n[$idx] = strtolower($char);
  }

  $n = implode($n);

  switch( strtolower($n) ) {
    
    // renaming handlers

    case ( $n == 'person' || $n == 'people' ) :
      $n = 'people';
      break;

    case ( substr( $n, -1 ) == 'y' ) :
      $n = substr( $n, 0, -1 ) . "ies";
      break;
      
    case ( substr( $n, -1 ) != 's' ) :
      $n = $n . "s";
      break;
      
  }
  
  return $n;
  
}


  /**
   * Error
   * 
   * custom Error handling per-client-type
   * 
   * @author Brian Hendrickson <brian@dbscript.net>
   * @access public
   * @param integer $errno
   * @param string $errstr
   * @param string $errfile
   * @param integer $errline
   * @todo return based on content-negotiation status
   */

function dbscript_error( $errno, $errstr, $errfile, $errline ) {
  if ( !error_reporting() || $errno == 2048 )
    return;
  switch ($errno) {
    case E_USER_ERROR:
      global $request;
      $req =& $request;
      if (isset($_GET['dbscript_xml_error_continue'])) {
        $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n";
        $xml .= "<root>\n";
        $xml .= "  <dbscript_error>Fatal error in line $errline of file $errfile<br>: $errstr</dbscript_error>\n";
        $xml .= "</root>\n";
        print $xml;
      } elseif (isset($req->error)) {
        $req->handle_error( $errstr );
        print "<b>ERROR</b> [$errno] $errstr<br>\n";
        print "  Fatal error in line $errline of file $errfile<br>\n";
        print "Aborting...<br>\n";
      } else {
        print "<br /><br />$errstr<br /><br />\n";
        if (environment('debug_enabled'))
          print "  Fatal error in line $errline of file $errfile<br>\n";
      }
      exit(1);
    case E_USER_WARNING:
      print "<b>WARNING</b> [$errno] $errstr<br>\n";
      break;
    case E_USER_NOTICE:
      print "<b>NOTICE</b> [$errno] $errstr<br>\n";
  }
}


function microtime_float() {
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}


  /**
   * Trigger Before
   * 
   * trip before filters for a function
   * 
   * @access public
   * @param string $func
   * @param object $obj_a
   * @param object $obj_b
   */

function trigger_before( $func, &$obj_a, &$obj_b ) {
  if (environment('show_timer')) {
    global $exec_time;
    $time_end = microtime_float();
    $time = $time_end - $exec_time;
    $diff = substr($time,1,5);
    echo "$diff seconds <BR>$func ";
  }
  if ( isset( $GLOBALS['ASPECTS']['before'][$func] ) ) {
    foreach( $GLOBALS['ASPECTS']['before'][$func] as $callback ) {
      call_user_func_array( $callback, array( $obj_a, $obj_b ) );
    }
  }
}


  /**
   * Trigger After
   * 
   * trip after filters for a function
   * 
   * @access public
   * @param string $func
   * @param object $obj_a
   * @param object $obj_b
   */

function trigger_after( $func, &$obj_a, &$obj_b ) {
  if ( isset( $GLOBALS['ASPECTS']['after'][$func] ) ) {
    foreach( $GLOBALS['ASPECTS']['after'][$func] as $callback ) {
      call_user_func_array( $callback, array( $obj_a, $obj_b ) );
    }
  }
}


  /**
   * aspect_join_functions
   * 
   * add trigger function name pairs to GLOBALS
   * 
   * @access public
   * @param string $func
   * @param string $callback
   * @param string $type
   */
   
function aspect_join_functions( $func, $callback, $type = 'after' ) {
  $GLOBALS['ASPECTS'][$type][$func][] = $callback;
}


  /**
   * Before Filter
   * 
   * set an aspect function to trigger before another function
   * 
   * @access public
   * @param string $name
   * @param string $func
   * @param string $when
   */

function before_filter( $name, $func, $when = 'before' ) {
  aspect_join_functions( $func, $name, $when );
}


  /**
   * After Filter
   * 
   * set an aspect function to trigger after another function
   * 
   * @access public
   * @param string $name
   * @param string $func
   * @param string $when
   */

function after_filter( $name, $func, $when = 'after' ) {
  aspect_join_functions( $func, $name, $when );
}


  /**
   * Never
   * 
   * returns false
   * 
   * @access public
   * @return boolean false
   */

function never() {
  return false;
}


  /**
   * Always
   * 
   * returns true
   * 
   * @access public
   * @return boolean true
   */

function always() {
  return true;
}


  /**
   * model_path
   * 
   * path to data models
   * 
   * @access public
   * @return string
   */
  
function model_path() {
  return $GLOBALS['PATH']['models'];
}


  /**
   * types_path
   * 
   * path to renderers
   * 
   * @access public
   * @return string
   */
  
function types_path() {
  return $GLOBALS['PATH']['types'];
}



  /**
   * library_path
   * 
   * path to libraries
   * 
   * @access public
   * @return string
   */
  
function library_path() {
  return $GLOBALS['PATH']['library'];
}


  /**
   * dbscript_path
   * 
   * path to library/dbscript
   * 
   * @access public
   * @return string
   */
  
function dbscript_path() {
  return $GLOBALS['PATH']['dbscript'];
}


  /**
   * ignore_errors
   * 
   * returns value of ignore_errors environment variable, if set
   * 
   * @access public
   * @return boolean
   */
  
function ignore_errors() {
  global $env;
  if ( isset( $env['ignore_errors'] ) && $env['ignore_errors'] )
    return true;
  return false;
}


  /**
   * plugin_path
   * 
   * path to data models
   * 
   * @access public
   * @return string
   */
  
function plugin_path() {
  return $GLOBALS['PATH']['plugins'];
}


  /**
   * controller_path
   * 
   * path to controllers
   * 
   * @access public
   * @return string
   */
  
function controller_path() {
  return $GLOBALS['PATH']['controllers'];
}


function load( $loader ) {
  $loader =& loader();
  $loader->add_loader($loader);
}


function session_started() {
  if(isset($_SESSION)) {  
    return true; 
  } else { 
    return false;
  }
}


  /**
   * db_object
   * 
   * get global Database object
   * 
   * @access public
   * @return string
   */
  
function &db_object() {
  global $db;
  return $db;
}


  /**
   * loader
   * 
   * get global BootLoader object
   * 
   * @access public
   * @return string
   */
  
function &loader() {
  global $loader;
  return $loader;
}



function environment($name=NULL) {
  global $env;
  if (!($name == NULL) && isset( $env[$name] ))
    return $env[$name];
  if (!($name == NULL))
    return false;
  return $env;
}


function introspect_tables() {

  global $db;
  
  $arr = array();
  
  $tables = $db->get_tables();

  foreach ($tables as $t) {
    if (!(in_array($t, array( 'db_sessions', 'entries', 'categories_entries' )))
    && $t != classify($t)) {
      //$m =& $db->get_table($t);
      //if (!$m->hidden)
      $arr[] = $t;
    }
  }

  return $arr;
  
}


function read_cache_blob( &$request, $value ) {
  
  if ( $coll = environment('collection_cache') ) {
    
    if (isset($coll[$request->resource])) {
      
      if ($coll[$request->resource]['duration'] > 0) {
        
        $cacheFile = $coll[$request->resource]['location'].DIRECTORY_SEPARATOR.$request->resource.$request->id;
        
        if (!(is_dir($coll[$request->resource]['location'].DIRECTORY_SEPARATOR)))
          return;
        
        if ( file_exists( $cacheFile ) && filemtime( $cacheFile ) > ( time() - $coll[$request->resource]['duration'] ) ) {
          // read cacheFile
          if ( !$fp = fopen( $coll[$request->resource]['location'].DIRECTORY_SEPARATOR.'hits', 'a' ) ) {
            trigger_error( 'Error opening hits file', E_USER_ERROR );
          }
          if ( !flock( $fp, LOCK_EX ) ) {
            trigger_error( 'Unable to lock hits file', E_USER_ERROR );
          }
          if( !fwrite( $fp, time_of(timestamp())." hit ".$cacheFile."\n" ) ) {
            trigger_error( 'Error writing to cache file', E_USER_ERROR );
          }
          flock( $fp, LOCK_UN );
          fclose( $fp );
          unset( $fp );
          
          print file_get_contents( $cacheFile );
          
          exit;
          
        } else {
          // write cacheFile
          if ( !$fp = fopen( $coll[$request->resource]['location'].DIRECTORY_SEPARATOR.'hits', 'a' ) )
            trigger_error( 'Error opening hits file', E_USER_ERROR );
          
          if ( !flock( $fp, LOCK_EX ) )
            trigger_error( 'Unable to lock hits file', E_USER_ERROR );
          
          if( !fwrite( $fp, time_of(timestamp())." ".'write '.$cacheFile."\n" ) )
            trigger_error( 'Error writing to cache file', E_USER_ERROR );
          
          flock( $fp, LOCK_UN );
          
          fclose( $fp );
          
          unset( $fp );
          
          if ( !$fp = fopen( $cacheFile, 'w' ) )
            trigger_error( 'Error opening cache file', E_USER_ERROR );
          
          if ( !flock( $fp, LOCK_EX ) )
            trigger_error( 'Unable to lock cache file', E_USER_ERROR );
          
          if( !fwrite( $fp, fetch_blob($value, true) ) )
            trigger_error( 'Error writing to cache file', E_USER_ERROR );
          
          flock( $fp, LOCK_UN );
          
          fclose( $fp );
          
          unset( $fp );
          
          return;
          
        }
      }
    }
  }
}



function render_blob( $value ) {
  
  global $request;
  $req =& $request;
  global $db;
  
  read_cache_blob($req,$value);
  
  fetch_blob($value, false);
  
}


function fetch_blob( $value, $return ) {
  
  global $request;
  $req =& $request;
  global $db;
  
  if (is_array( $value )) {

    return $db->large_object_fetch( 

      $value['t'],
      $value['f'],
      $value['k'],
      $value['i'],
      $return

    );

  } else {

    return $db->large_object_fetch( $value, $return );

  }

}


  /**
   * template_exists
   * 
   * find a template during content-negotiation
   * 
   * @access public
   * @param Mapper $request
   * @param string $extension
   * @return boolean
   */
  
function template_exists( &$request, $extension, $template ) {
  #if ($template == 'introspection') print 'ye';
  #print "template_exists $template ".$extension."<br>";
  
  $view = $request->get_template_path( $extension, $template );
  
  if ( file_exists( $view ) )
    return true;
  
  return false;
  
}


  /**
   * Form For
   * 
   * generate a form action string
   * 
   * @access public
   * @param string $template
   * @todo implement
   */

function form_for( &$resource, &$member, $url ) {

  $request =& request_object();

  if (is_object($resource)) {
    
    if ( isset( $resource->table )) {
      
       // remote_form_for :entry, @new_entry, :url => entries_url(:project_id => @project.id )
       
       return "<form method=\"post\" >";
      
    }
    
  }
  
}


  /**
   * URL For
   * 
   * generate a url from a Route
   * 
   * @access public
   * @param array $params
   * @param array $altparams
   */

function url_for( $params, $altparams = NULL ) {

  $request =& request_object();
  
  print $request->url_for( $params, $altparams );
  
}


function base_url() {

  $request =& request_object();
  
  print $request->base;
  
}

  /**
   * Redirect To
   * 
   * redirect the browser via Routes
   * 
   * @access public
   * @param string $template
   */

function redirect_to( $param, $altparam = NULL ) {
  
  $request =& request_object();
  
  // check for qooxdoo "POST" and bail before redirect
  if (strstr($request->uri,"&method="))
    echo "OK";
  
  $request->redirect_to( $param, $altparam );
  
}


function type_of( $file ) {
  
  $types = mime_types();
  
  if (eregi('\.?([a-z0-9]+)$', $file, $match) && isset($types[strtolower($match[1])]))
    return $types[strtolower($match[1])];
  
  return "text/html";
  
}


function extension_for( $type ) {
  
  $types = mime_types();
  
  foreach($types as $key=>$val) {
    if ($val == $type)
      return $key;
  }
  
  return "html";
  
}


function is_blob( $field ) {
  
  $spleet = split( "\.", $field );
  
  global $db;
  
  if (empty($spleet[0])) return false;
  
  $model =& $db->get_table($spleet[0]);
  
  if ($model && !empty($spleet[1]))
    if ($model->is_blob($spleet[1]))
      return true;
  
  return false;
  
}


function mime_types() {
  
  return array (
    
    'aif'   => 'audio/x-aiff',
    'aiff'  => 'audio/x-aiff',
    'aifc'  => 'audio/x-aiff',
    'm3u'   => 'audio/x-mpegurl',
    'mp3'   => 'audio/mp3',
    'ra'    => 'audio/x-realaudio',
    'ram'   => 'audio/x-pn-realaudio',
    'rm'    => 'audio/x-pn-realaudio',
    'wav'   => 'audio/wav',
    
    'avi'   => 'video/x-ms-wm',
    'mp4'   => 'video/mp4',
    'mpeg'  => 'video/mpeg',
    'mpe'   => 'video/mpeg',
    'mpg'   => 'video/mpeg',
    'mov'   => 'video/quicktime',
    'movie' => 'video/x-sgi-movie',
    'qt'    => 'video/quicktime',
    'swa'   => 'application/x-director',
    'swf'   => 'application/x-shockwave-flash',
    'swfl'  => 'application/x-shockwave-flash',
    'wmv'   => 'video/x-ms-wmv',
    'asf'   => 'video/x-ms-asf',
    
    'sit'   => 'application/x-stuffit',
    'zip'   => 'application/zip',
    'tgz'   => 'application/g-zip',
    'gz'    => 'application/g-zip',
    'gzip'  => 'application/g-zip',
    'hqx'   => 'application/mac-binhex40',
    
    'ico'   => 'image/vnd.microsoft.icon', 
    'bmp'   => 'image/bmp',
    'gif'   => 'image/gif',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'jpe'   => 'image/jpeg',
    'pct'   => 'image/pict',
    'pic'   => 'image/pict',
    'pict'  => 'image/pict',
    'png'   => 'image/png',
    'svg'   => 'image/svg+xml',
    'svgz'  => 'image/svg+xml',
    'tif'   => 'image/tiff',
    'tiff'  => 'image/tiff',
    
    'rtf'   => 'text/rtf',
    'pdf'   => 'application/pdf',
    'xdp'   => 'application/pdf',
    'xfd'   => 'application/pdf',
    'xfdf'  => 'application/pdf',
    
  );
  
}


  /**
   * Render
   * 
   * render template or blob using content-negotiation
   * 
   * @access public
   * @param string $template
   */

function render( $param, $value ) {
  
  global $db;
  $response =& response_object();
  $request =& request_object();

  if ( $param == 'action' && !(strpos($value,".") === false ) ) {
    $spleet = split( "\.", $value );
    $value = $spleet[0];
    $request->set( 'client_wants', $spleet[1] );
  }
  
  $request->set_param( $param, $value );
  
  $response->render( $request );
  
  exit;
  
}


  /**
   * Member Of
   * 
   * check group membership for a Person
   * 
   * @access public
   * @param string $template
   */

function member_of( $group ) {
  
  global $memberships;

  if ( $group == 'everyone' )
    return true;
  
  if (!( get_person_id() ))
    return false;

  if ( $group == 'members' )
    return true;
  
  if (!is_array($memberships)) {
    $memberships = array();

    $request =& request_object();
  

  
    global $db;
  
    $Person =& $db->model('Person');
    $Group =& $db->model('Group');
  
    $p = $Person->find( get_person_id() );
  
    while ( $m = $p->NextChild( 'memberships' )) {
      $g = $Group->find( $m->group_id );
      $memberships[] = $g->name;
    
      if (!$g)
        trigger_error( "the Group with id ".$m->group_id." does not exist", E_USER_ERROR );
      if ( $g->name == $group )
        return true;
    }
  
  } else {
    
    if (in_array($group,$memberships))
      return true;
    
  }
  
  return false;
  
}


// add_include_path by ricardo dot ferro at gmail dot com

function add_include_path($path) {
    foreach (func_get_args() AS $path)
    {
        if (!file_exists($path) OR (file_exists($path) && filetype($path) !== 'dir'))
        {
            trigger_error("Include path '{$path}' not exists", E_USER_WARNING);
            continue;
        }
        
        $paths = explode(PATH_SEPARATOR, get_include_path());
        
        if (array_search($path, $paths) === false)
            array_push($paths, $path);
        
        set_include_path(implode(PATH_SEPARATOR, $paths));
    }
}


function send_email( $sendto, $subject, $content, $fromemail="", $fromname="", $html=false ) {
  
  if ($fromemail == 'root@localhost' && $fromname == 'Notifier')
    return;
  
  require_once(library_path().'xpertmailer'.DIRECTORY_SEPARATOR.'MAIL.php'); 
  
  $mail = new MAIL;
  
  $mail->From($fromemail, $fromname ); 

  $mail->AddTo($sendto);
  
  $mail->Subject($subject);
  
  if ($html)
    $mail->HTML($html);
  
  $mail->Text($content);
  
  $c = $mail->Connect(environment('email_server'), environment('email_port'), environment('email_user'), environment('email_pass'));
  
  $send = $mail->Send( $c ); 
  
  $mail->Disconnect();
  
}


function is_upload($table,$field) {
  return (isset($_FILES[strtolower(classify($table))]['name'][$field])
   && !empty($_FILES[strtolower(classify($table))]['name'][$field]));
}


function is_email($email) {
  return preg_match('/^([a-z0-9])(([-a-z0-9._])*([a-z0-9]))*\@([a-z0-9])([-a-z0-9_])+([a-z0-9])*(\.([a-z0-9])([-a-z0-9_-])([a-z0-9])+)*$/i',$email);
}


  /**
   * Render Partial
   * 
   * render a _template using content-negotiation
   * 
   * @access public
   * @param string $template
   */

function render_partial( $template ) {
  
  $request =& request_object();
  
  $response =& response_object();
  
  if (!(strpos($template,".") === false)) {
    $spleet = split("\.",$template);
    $template = $spleet[0];
    $request->set( 'client_wants', $spleet[1] );
  }
  
  $response->render_partial( $request, $template );
  
}


function render_theme( $theme ) {
  
  global $request;
  
  $folder = $GLOBALS['PATH']['themes'] . $theme . DIRECTORY_SEPARATOR;
  
  add_include_path($folder);
  
  if (isset($request->action) && !($request->action == 'index')) {
    get_header();
    show_prologue_nav();
    echo '<div id="main">'."\n";
    content_for_layout();
    echo '</div>'."\n";
    get_footer();
    exit;
  }
  
  if ( file_exists( $folder . "index.php" ))
    require_once( $folder . "index.php" );
  else
    require_once( $folder . "index.html" );
}

function theme_path() {
  
  return $GLOBALS['PATH']['themes'] . environment('theme') . DIRECTORY_SEPARATOR;
  
}


  /**
   * content_for_layout
   * 
   * render a _template using content-negotiation
   * 
   * @access public
   * @param string $template
   */

function content_for_layout() {
  
  $request =& request_object();
  
  render_partial( $request->action );
  
}


function breadcrumbs() {
  $request =& request_object();
  echo $request->breadcrumbs();
}


  /**
   * request_object
   * 
   * get global Mapper object
   * 
   * @access public
   * @return Mapper
   */
  
function &request_object() {
  global $request;
  return $request;
}


function register_type( $arr ) {
  global $variants;
  $variants[] = $arr;
}

function photoCreateCropThumb ($p_thumb_file, $p_photo_file, $p_max_size, $p_quality = 100) {
  
  $pic = imagecreatefromjpeg($p_photo_file);
  
  if ($pic) {
    $thumb = imagecreatetruecolor ($p_max_size, $p_max_size);
    if (!$thumb)
      trigger_error('Sorry, the thumbnail photo could not be created', E_USER_ERROR);
    $width = imagesx($pic);
    $height = imagesy($pic);
    if ($width < $height) {
      $twidth = $p_max_size;
      $theight = $twidth * $height / $width; 
      imagecopyresized($thumb, $pic, 0, 0, 0, ($height/2)-($width/2), $twidth, $theight, $width, $height); 
    } else {
      $theight = $p_max_size;
      $twidth = $theight * $width / $height; 
      imagecopyresized($thumb, $pic, 0, 0, ($width/2)-($height/2), 0, $twidth, $theight, $width, $height); 
    }
    
    imagejpeg($thumb, $p_thumb_file, $p_quality);
  }
  
}


function resize_jpeg($file,$dest,$size) {
  $new_w = $size;
  $new_h = $new_w;
  $src_img = imagecreatefromjpeg("$file");
  $old_x=imageSX($src_img);
	$old_y=imageSY($src_img);
	if ($old_x > $old_y) 
	{
		$thumb_w=$new_w;
		$thumb_h=$old_y*($new_h/$old_x);
	}
	if ($old_x < $old_y) 
	{
		$thumb_w=$old_x*($new_w/$old_y);
		$thumb_h=$new_h;
	}
	if ($old_x == $old_y) 
	{
		$thumb_w=$new_w;
		$thumb_h=$new_h;
	}
	$dst_img=ImageCreateTrueColor($thumb_w,$thumb_h);
	imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y); 
	imagejpeg($dst_img,$dest);
}
	

            


function content_types() {
  global $env;
  $variants = array(
    array(
      'id' => 'html',
      'qs' => 1.000,
      'type' => 'text/html',
      'encoding' => null,
      'charset' => 'iso-8859-1',
      'language' => 'en',
      'size' => 3000
    )
  );
  if (isset($env['content_types']))
    return $env['content_types'];
  else
    return $variants;
}


  /**
   * response_object
   * 
   * get global View object
   * 
   * @access public
   * @return View
   */
  
function &response_object() {
  global $response;
  return $response;
}


  /**
   * db_include
   * 
   * include a dbscript file
   * 
   * @access public
   */

function db_include( $file ) {
  if (is_array($file)) {
    foreach($file as $f)
      require_once dbscript_path() . $f . ".php";
  } else {
    require_once dbscript_path() . $file . ".php";
  }  
}


function wp_plugin_include( $file ) {
  $wp_plugins = "wp-plugins" . DIRECTORY_SEPARATOR . "plugins" . DIRECTORY_SEPARATOR . "enabled";
  if (is_array($file)) {
    foreach($file as $f) {
      if (file_exists(plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR . 'plugin.php' ))
        require_once plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR . 'plugin.php';
      elseif (file_exists(plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR . 'core.php' ))
        require_once plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR . 'core.php';
      elseif (file_exists(plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $f . ".php"))
        require_once plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $f . ".php";
    }
  } else {
    if (file_exists(plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'plugin.php' ))
      require_once plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'plugin.php';
    elseif (file_exists(plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'core.php' ))
      require_once plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . 'core.php';
    elseif (file_exists(plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $file . ".php"))
      require_once plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . $file . ".php";
  }
}


  /**
   * lib_include
   * 
   * include a library file
   * 
   * @access public
   */

function lib_include( $file ) {
  if (is_array($file)) {
    foreach($file as $f) {
      if (file_exists(library_path() . $f . ".php"))
        require_once library_path() . $f . ".php";
    }
  } else {
    if (file_exists(library_path() . $file . ".php"))
      require_once library_path() . $file . ".php";
  }
}


function load_plugin( $plugin ) {
  
  if ( file_exists( $GLOBALS['PATH']['plugins'] . $plugin . '.php' ) )
    include $GLOBALS['PATH']['plugins'] . $plugin . '.php';
  
  if ( file_exists( $GLOBALS['PATH']['plugins'] . $plugin . DIRECTORY_SEPARATOR . 'plugin.php' ) )
    include $plugin . DIRECTORY_SEPARATOR . 'plugin.php';
  
  if ( file_exists( $GLOBALS['PATH']['plugins'] . $plugin . DIRECTORY_SEPARATOR . 'core.php' ) )
    include $plugin . DIRECTORY_SEPARATOR . 'core.php';
  
  $init = $plugin . "_init";
  
  if ( function_exists( $init ) )
    $init();
  
}

  /**
   * version
   * 
   * get dbscript version
   * 
   * @access public
   * @return string
   */
  
function version() {
  global $version;
  return $version;
}


function timestamp() {
  return date( "Y-m-d H:i:s", strtotime( "now" ));
}


  /**
   * magic_quotes_stripslashes_b
   * 
   * @access public
   * @param array $a
   * @return array $r
   */

function magic_quotes_stripslashes_b($a) { // Back
   $r=array();
   foreach ($a as $k=>$v) {
       if (!is_array($v)) $r[stripslashes($k)] = stripslashes($v);
       else $r[stripslashes($k)] = magic_quotes_stripslashes_b($v);
   }
   return $r;
}


  /**
   * magic_quotes_stripslashes
   * 
   * @access public
   * @param array $a
   * @return array $r
   */

function magic_quotes_stripslashes($a) { // Top
   $r=array();
   foreach ($a as $k=>$v) {
       if (!is_array($v)) $r[$k] = stripslashes($v);
       else $r[$k] = magic_quotes_stripslashes_b($v);
   }
   return $r;
}


  /**
   * magic_quotes_stripquotes_b
   * 
   * @access public
   * @param array $a
   * @return array $r
   */

function magic_quotes_stripquotes_b($a) { // Back
   $r=array();
   foreach ($a as $k=>$v) {
       if (!is_array($v)) $r[str_replace('\'\'','\'',$k)] = str_replace('\'\'','\'',$v);
       else $r[str_replace('\'\'','\'',$k)] = magic_quotes_stripquotes_b($v);
   }
   return $r;
}


  /**
   * magic_quotes_stripquotes
   * 
   * @access public
   * @param array $a
   * @return array $r
   */

function magic_quotes_stripquotes($a) { // Top
   $r=array();
   foreach ($a as $k=>$v) {
     if (!is_array($v)) $r[$k] = str_replace('\'\'','\'',$v);
     else $r[$k] = magic_quotes_stripquotes_b($v);
   }
   return $r;
}


  /**
   * header_status
   * 
   * @access public
   * @param string $status
   */
   
function header_status( $status ) {
  if (!headers_sent($filename, $linenum)) {
    // this is disabled because it breaks on Dreamhost
    #header( "Status: ".$status );
    // THIS "works" on DH but it's not up to HTTP spec
    #header( "HTTP/1.0 Status: ".$status );
  } else {
    echo "Headers already sent in $filename on line $linenum\nCannot set HTTP status\n";
    exit;
  }
}

  /**
   * set_cookie
   * 
   * make a fresh Cookie
   * 
   * @access public
   * @param integer $userid
   */

function set_cookie($userid) {
  $cookie = new Cookie();
  $cookie->userid = $userid;
  $cookie->set();
}


  /**
   * unset_cookie
   * 
   * throw the Cookie away? noo..
   * 
   * @access public
   */

function unset_cookie() {
  $cookie = new Cookie();
  $cookie->logout();
}


  /**
   * check_cookie
   * 
   * is the Cookie fit for consumption?
   * 
   * @access public
   * @return boolean
   */

function check_cookie() {
  $cookie = new Cookie();
  if ($cookie->validate()) {
    return true;
  } else {
    return false;
  }
}


function print_email( $mail ) {
    if ($mail=='') return '';
    $mail = str_replace(array('@',':','.'), array('&#064;','&#058;','&#046;'), $mail);
    $mail = '<a href=mailto&#058;'.$mail.'>'.$mail.'</a>';
    $len = strlen($mail);
    $i=0;
    while($i<$len)
        {
        $c = mt_rand(1,4);
        $par[] = (substr($mail, $i, $c));
        $i += $c;
        }
    $join = implode('"+ "', $par);

    return '<script language=javascript>
    <!--
    document.write("'.$join.'")
    //-->
    </script>';
}


function signed_in() {
  
  return member_of('members');
  
}


function public_resource() {
  
  global $db;
  global $request;
  $req =& $request;
  
  if ( $req->resource == 'introspection' )
    return true;  
  
  $datamodel =& $db->get_table($req->resource);
  
  if (!(isset($datamodel->access_list['read']['id'])))
    return false;
  
  if (in_array('always',$datamodel->access_list['read']['id']))
    return true;
    
  if (isset($req->client_wants))
    if (in_array($req->action.".".$req->client_wants,$datamodel->access_list['read']['id']))
      return true;
    else
      return false;

  if (in_array($req->action,$datamodel->access_list['read']['id']))
    return true;

//      if ((!(file_exists($this->template_path . $resource . "_" . $action . "." . $ext)))
  
  return false;
  
}

function can_read( $resource ) {
  if (!(isset($this->access_list['read'][$resource]))) return false;
  foreach ( $this->access_list['read'][$resource] as $callback ) {
    if ( function_exists( $callback ) ) {
      if ($callback())
        return true;
    } else {
      if ( member_of( $callback ))
        return true;
    }
  }
  return false;
}

function can_edit( $post ) {
  global $db;
  $p = get_profile();
  $e = $post->FirstChild('entries');
  $m =& $db->get_table($post->table);
  return (($p->id == $e->person_id) || $m->can_superuser($post->table));
}


  /**
   * get_profile
   * 
   * get the Identity of a person
   * 
   * @access public
   * @return integer
   */

function get_profile($id=NULL) {
  
  global $db;
  
  $Identity =& $db->get_table( 'identities' );
  $Person =& $db->get_table( 'people' );
  
  $view =& response_object();
  
  if (!($id == NULL))
    return $Identity->find($id);
  
  if (isset($view->named_vars['profile']))
    return $view->named_vars['profile'];
  
  if ($id == NULL)
    $id = get_person_id();
  
  $p = $Person->find($id);
  
  if ($p) {
    $i = $p->FirstChild('identities');
    if ($i)
      return $i;
  }
  
  return false;
}

function get_profile_id() {
  
  global $db;
  
  $Identity =& $db->get_table( 'identities' );
  
  $view =& response_object();
  
  if (isset($view->named_vars['profile']))
    $i =& $view->named_vars['profile'];
  else
    $i = $Identity->find( get_person_id() );
  
  if ($i)
    return $i->id;
  
  return false;
}


  /**
   * get_person_id
   * 
   * get the person_id of the profile owner
   * 
   * @access public
   * @return integer
   */

function get_person_id() {
  
  $view =& response_object();
  
  if (isset($view->named_vars['profile'])) {
    $i = $view->named_vars['profile'];
    if ($i)
      return $i->person_id;
  }
  
  $p = get_cookie_id();
  
  if ($p)
    return $p;
  
  return 0;
  
}



  /**
   * get_cookie_id
   * 
   * get the person_id of the cookie owner
   * 
   * @access public
   * @return integer
   */

function get_cookie_id() {
  
  $cookie = new Cookie();
  
  if ($cookie->validate())
    return $cookie->userid;
  
  return 0;
  
}


  /**
   * Vars
   * 
   * mutator function makes an array of local variables extractable
   * 
   * @access public
   * @param string $var
   * @return string
   */
  
  function vars($varios, $scope=false, $prefix='unique', $suffix='value') {
    if ( $scope )
      $vals = $scope;
    else
      $vals = $GLOBALS;
    $i = 0;
    foreach ($varios as $orig) {
      $var =& $varios[$i];
      $old = $var;
      $var = $new = $prefix . rand() . $suffix;
      $vname = FALSE;
      foreach( $vals as $key => $val ) {
        if ( $val === $new ) $vname = $key;
      }
      $var = $old;
      if ($vname) {
        $varios[$vname] = $var;
      }
      $i++;
    }
    return $varios;
  }


  /**
   * Randomstring
   * 
   * give it a string length and you will receive a random string!
   * 
   * @access public
   * @param integer $len
   * @return string
   */

function randomstring($len) {
   srand(date("s"));
   $i = 0;
   $str = "";
   while($i<$len)
     {
       $str.=chr((rand()%26)+97);
       $i++;
   }
   return $str;
}


function getEtag($id) {
  return "ci-".dechex(crc32($id.microtime()));
}


  /**
   * drop_array_element
   * 
   * returns the array minus the named element
   * 
   * @access public
   * @param string $str
   */

function drop_array_element($array_with_elements, $key_name) {
  $key_index = array_keys(array_keys($array_with_elements), $key_name);
  if (count($key_index) != '') {
    array_splice($array_with_elements, $key_index[0], 1);
  }
  return $array_with_elements;
}


  /**
   * dictionary_parse
   * 
   * Parses an xml dictionary.
   * First argument is file name OR xml data.
   * Second argument must be 'true' if the first arg is file name.
   *
   * <code>
   * $array = dictionary_parse( $file_name, true );
   * </code>
   *
   * @author Brian Hendrickson <brian@dbscript.net>
   * @access public
   * @param string $data
   * @return array
   */

function dictionary_parse( $data ) { /* parse XML dictionaries (lookup tables) */
  $dict = array();
  $xml_parser = xml_parser_create();
  xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 1);
  xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 0);
  if (!$xml_parser) {
    trigger_error("error creating xml parser (xml_parser_create) in dictionary_parse", E_USER_ERROR );
  }
  $func_args = func_get_args();
  if (count($func_args) > 1) {
    if ($func_args[1] == true) {
      $data = file_get_contents($data);
      if (!$data) {
        trigger_error("error reading file contents in dictionary_parse, file name was: ".$func_args[0], E_USER_ERROR );
      }
    }
  }
  $int = xml_parse_into_struct($xml_parser, $data, $values, $tags);
  if (!($int > 0)) {
    trigger_error("error parsing XML in dictionary_parse: ".xml_error_string(xml_get_error_code($xml_parser)), E_USER_ERROR )." ". $data;
  }
  foreach ($tags as $tagname=>$valuelocations) {
    if ($tagname == "KEY") {
      $i = 0;
      foreach ($valuelocations as $valkey=>$valloc) {
        $map[$i] = $values[$valloc]['value'];
        $dict[$values[$valloc]['value']] = "";
        $i++;
      }
    }
    if ($tagname == "STRING") {
      $i = 0;
      foreach ($valuelocations as $valkey=>$valloc) {
        if (isset($values[$valloc]['value'])) {
          $dict[$map[$i]] = $values[$valloc]['value'];
        } else {
          $dict[$map[$i]] = "";
        }
        $i++;
      }
    }
  }
  xml_parser_free($xml_parser);
  return $dict;
}


function load_model( &$model, &$model2 ) {
  global $db;
  $tab = tableize(get_class($model));
  if ($tab == 'models') return;
  if (!($db->models[$tab]->exists))
    $model->register($tab);
}
  /**
   * in_string
   * 
   * search for a substring
   * 
   * @access public
   * @param string $needle
   * @param array $haystack
   * @param integer $insensitive
   * @return boolean
   */

function in_string($needle, $haystack, $insensitive = 0) {
  if ($insensitive) {
    return (false !== stristr($haystack, $needle)) ? true : false;
  } else {
    return (false !== strpos($haystack, $needle))  ? true : false;
  }
} 
  

  /**
   * get_script_name
   * 
   * the name of the current script
   * 
   * @access public
   * @return string
   */

function get_script_name() {
  if (!empty($_SERVER['PHP_SELF'])) {
    $strScript = $_SERVER['PHP_SELF'];
  } else if (!empty($_SERVER['SCRIPT_NAME'])) {
    $strScript = @$_SERVER['SCRIPT_NAME'];
  } else {
    trigger_error("error reading script name in get_script_name", E_USER_ERROR );
  }
  $intLastSlash = strrpos($strScript, "/");
  if (strrpos($strScript, "\\")>$intLastSlash) {
    $intLastSlash = strrpos($strScript, "\\");
  }
  return substr($strScript, $intLastSlash+1, strlen($strScript));
}


function dircopy($srcdir, $dstdir, $verbose = false) {
  $num = 0;
  if(!is_dir($dstdir)) mkdir($dstdir);
  if($curdir = opendir($srcdir)) {
   while($file = readdir($curdir)) {
     if($file != '.' && $file != '..') {
       $srcfile = $srcdir . DIRECTORY_SEPARATOR . $file;
       $dstfile = $dstdir . DIRECTORY_SEPARATOR . $file;
       if(is_file($srcfile)) {
         if(is_file($dstfile)) $ow = filemtime($srcfile) - filemtime($dstfile); else $ow = 1;
         if($ow > 0) {
           if($verbose) echo "Copying '$srcfile' to '$dstfile'...";
           if(copy($srcfile, $dstfile)) {
             touch($dstfile, filemtime($srcfile)); $num++;
             if($verbose) echo "OK\n";
           }
           else echo "Error: File '$srcfile' could not be copied!\n";
         }                  
       }
       else if(is_dir($srcfile)) {
         $num += dircopy($srcfile, $dstfile, $verbose);
       }
     }
   }
   closedir($curdir);
  }
  return $num;
}


function unzip($dir, $file, $verbose = 0) {

   $dir_path = "$dir$file";
   $zip_path = "$dir$file.zip";
   
   $ERROR_MSGS[0] = "OK";
   $ERROR_MSGS[1] = "Zip path $zip_path doesn't exists.";
   $ERROR_MSGS[2] = "Directory $dir_path for unzip the pack already exists, impossible continue.";
   $ERROR_MSGS[3] = "Error while opening the $zip_path file.";
   
   $ERROR = 0;
   
   if (file_exists($zip_path)) {
   
         if (!file_exists($dir_path)) {
             
           mkdir($dir_path);    
         
         if (($link = zip_open($zip_path))) {
             
           while (($zip_entry = zip_read($link)) && (!$ERROR)) {
               
               if (zip_entry_open($link, $zip_entry, "r")) {
           
                 $data = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                 $dir_name = dirname(zip_entry_name($zip_entry));
                 $name = zip_entry_name($zip_entry);
                 
                 if ($name[strlen($name)-1] == '/') {
                         
                       $base = "$dir_path/";

                     foreach ( explode("/", $name) as $k) {
                         
                       $base .= "$k/";
                           
                       if (!file_exists($base))
                           mkdir($base);
                           
                     }    
                       
                 }
                 else { 
                 
                     $name = "$dir_path/$name"; 
                     
                     if ($verbose)
                       echo "extracting: $name<br>";
                       
                   $stream = fopen($name, "w");
                   fwrite($stream, $data);
                   
                 }  
                 
                 zip_entry_close($zip_entry);
                 
               }
               else
                 $ERROR = 4;    
  
             }
             
             zip_close($link);  
             
           }
           else
             $ERROR = "3";
       }
       else 
         $ERROR = 2;
   }
   else 
       $ERROR = 1;
     
   return $ERROR_MSGS[$ERROR];        
   
}    

#---

#example:

#$error = unzip("d:/www/dir/", "zipname", 1);


  /**
   * is_obj
   * 
   * more flexible is_object
   * 
   * @access public
   * @param object $object
   * @param boolean $check
   * @param boolean $strict
   * @return boolean
   */

function is_obj( &$object, $check=null, $strict=true ) {
  if (is_object($object)) {
    if ($check == null) {
      return true;
    } else {
      $object_name = get_class($object);
      return ($strict === true)? 
      ( $object_name == $check ):
      ( strtolower($object_name) == strtolower($check) );
    }    
  } else {
    return false;
  }
}

function resource_group_members( $gid=NULL ) {
  
  global $db;
  global $request;
  $req =& $request;
  $Person =& $db->model('Person');
  $Group =& $db->model('Group');
  $Group->find();
  $added = array();
  $result = array();
  $datamodel =& $db->get_table($req->resource);
  
  if ($gid == NULL) {
    
    while ( $g =& $Group->MoveNext() ) {
      if (in_array($g->name,$datamodel->access_list['read']['id'])) {
        while ( $m = $g->NextChild( 'memberships' )) {
          if (!(in_array($m->person_id,$added))) {
            $p =& $Person->find( $m->person_id );
            $result[] = $p->FirstChild('identities');
            $added[] = $m->person_id;
          }
        }
      }
    }
    
  } else {
    while ( $g =& $Group->MoveNext() ) {
      if (($g->id == $gid)) {
        while ( $m = $g->NextChild( 'memberships' )) {
          if (!(in_array($m->person_id,$added))) {
            $p =& $Person->find( $m->person_id );
            $result[] = $p->FirstChild('identities');
            $added[] = $m->person_id;
          }
        }
      }
    }
  }
  
  return $result;
  
}


function laconica_time($dt) {
  
  $t = strtotime($dt);
	$now = time();
	$diff = $now - $t;

	if ($diff < 60) {
		return _t('a few seconds ago');
	} else if ($diff < 92) {
		return _t('about a minute ago');
	} else if ($diff < 3300) {
		return _t('about ') . round($diff/60) . _t(' minutes ago');
	} else if ($diff < 5400) {
		return _t('about an hour ago');
	} else if ($diff < 22 * 3600) {
		return _t('about ') . round($diff/3600) . _t(' hours ago');
	} else if ($diff < 37 * 3600) {
		return _t('about a day ago');
	} else if ($diff < 24 * 24 * 3600) {
		return _t('about ') . round($diff/(24*3600)) . _t(' days ago');
	} else if ($diff < 46 * 24 * 3600) {
		return _t('about a month ago');
	} else if ($diff < 330 * 24 * 3600) {
		return _t('about ') . round($diff/(30*24*3600)) . _t(' months ago');
	} else if ($diff < 480 * 24 * 3600) {
		return _t('about a year ago');
	}
}

function time_3339($str) {
    $timestamp = strtotime($str);
    if (!$timestamp) {
        $timestamp = time();
    }
    $date = date('Y-m-d\TH:i:s', $timestamp);

    $matches = array();
    if (preg_match('/^([\-+])(\d{2})(\d{2})$/', date('O', $timestamp), $matches)) {
        $date .= $matches[1].$matches[2].':'.$matches[3];
    } else {
        $date .= 'Z';
    }
    return $date;
}

function time_2822($str) {
  return strftime ("%a, %d %b %Y %H:%M:%S %z", strtotime($str));
}

function time_of($str) {
  return strftime ("%a %b %e %I:%M %p", strtotime($str));
}


  /**
   * getLocaltime
   * 
   * echo ' Server Time: '.date("F d, Y - g:i A",time());;
   * echo " || GMT Time: " . gmdate("F d Y - g:i A", time());
   * echo ' ||  Localtime: '.getLocaltime('+5.5',0);
   *
   * @author http://techjunk.websewak.com/
   * @access public
   * @param string $GMT
   * @param string $dst
   * @return string
   */


function getLocaltime($GMT,$dst){
        if(preg_match('/-/i',$GMT))
        {
                $sign = "-";
        }
        else
        {
                $sign = "+";
        }

        $h = round((float)$GMT,2);

        $dst = "true";

        if ($dst)
                {
                $daylight_saving = date('I');
                if ($daylight_saving)
                                {
                   if ($sign == "-"){ $h=$h-1;  }
                   else { $h=$h+1; }
                }
        }

        // FIND DIFFERENCE FROM GMT
        $hm = $h * 60;
        $ms = $hm * 60;

        // SET CURRENT TIME
        if ($sign == "-"){ $timestamp = time()-($ms); }
        else { $timestamp = time()+($ms); }

        // SAMPLE OUTPUT
        $gmdate = gmdate("F d, Y - g:i A", $timestamp);
        return $gmdate;
}


function normalize_username($username) {
  $username = preg_replace('|[^https?://]?[^\/]+/(xri.net/([^@]!?)?)?/?|', '', $username);
  return trim($username);
}

?>