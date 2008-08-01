<?php

global $request,$oauth_functions,$db;


define( OMB_VERSION, 'http://openmicroblogging.org/protocol/0.1' );
define( OAUTH_VERSION, 'http://oauth.net/core/1.0' );

$oauth_functions = array(
  'local_subscribe',
  'local_unsubscribe',
  'oauth_omb_post',
  'oauth_omb_update',
  'oauth_omb_subscribe',
  'oauth_omb_finish_subscribe',
  'access_token',
  'request_token',
  'oauth_authorize'
);

foreach ($oauth_functions as $func)
  $request->connect( $func );


$request->connect(
  ':nickname',
  array(
    'resource'=>'identities',
    'action'=>'entry',
    'requirements' => array ( '[A-Za-z0-9_.]+' )
  )
);

// YUCK!!!!! re-fac-tored as :nickname route

//$Identity =& $db->Model('Identity');
//$Identity->find();
//while ( $i = $Identity->MoveNext() ) {
//  if (!empty($i->nickname)) {
//    $nick = $i->nickname;
//    $request->connect(
//      $nick,
//      array(
//        'id'         => $i->id,
//        'resource'   => 'identities',
//        'action' => 'entry'
//      )
//    );
//  }
//}


$request->connect(
  ':resource/by/:byid/:page',
  array(
    'requirements' => array ( '[A-Za-z0-9_.]+', '[0-9]+', '[0-9]+' )
  )
);

before_filter( 'omb_filter_posts', 'get_query' );

function omb_filter_posts( &$model, &$db ) {
  global $request;
  if (isset($request->params['byid']) && $model->table == 'posts'){
    $model->has_many( 'profile_id:subscriptions.subscribed' );
    $where = array(
      'op'=>'OR',
      'profile_id'=>$request->params['byid'],
      'subscriptions.subscriber'=>$request->params['byid']
    );
    $model->set_param( 'find_by', $where );
  }
}


// this is a filter to handle posts from the prologue theme

before_filter( 'wp_set_post_fields', 'insert_from_post' );

function wp_set_post_fields( &$model, &$rec ) {
  
  if ( !(isset($_POST['posttext'])) || !(isset($_POST['tags'])) )
    return;
  
  global $db,$request;
  
  //$request->set_param('resource','posts');
  
  if ( isset( $_POST['posttext'] ))
    $request->set_param( array( 'post', 'title' ), $_POST['posttext'] );
  
  if ( isset( $_POST['profile_id'] ))
    $request->set_param( array( 'post', 'profile_id' ), $_POST['profile_id'] );
  
  $Category =& $db->model('Category');
  
  $Category->find();
  
  $tags = split( ' ', $_POST['tags'] );
  
  $cats = array();
  
  while ( $c = $Category->MoveNext() )
    $cats[strtolower($c->name)] = $c->id;
  
  foreach ( $tags as $t ) {
    $t = strtolower( trim( $t ));
    if (array_key_exists( $t, $cats )) {
      $request->set_param( "category".$cats[$t], $t );
    }
  }
  
}


after_filter('set_identity_from_nick','routematch');

function set_identity_from_nick(&$request,&$route) {
  
  if (!(isset($request->params['nickname'])))
    return;
  
  global $db;
  $nick = $db->escape_string(urldecode($request->params['nickname']));
  $nick = split( '\.', $nick );
  
  if (is_array($nick)) {
    if (isset($nick[1]))
      $request->set('client_wants',$nick[1]);
    $nick = trim($nick[0]);
  } else {
    $nick = trim($nick);
  }
  
  if (substr($nick,0,2) == '__') {
    $request->set_param('id',substr($nick,2));
    $request->set_param('resource','posts');
    $request->set_param('action','entry');
    return;
  }
  
  if ($db->table_exists($nick)) {
    $request->set_param('resource',$nick);
    if (!(isset($_POST['method'])))
      $request->set_param('action','index');
    return;
  }
  
  $Identity =& $db->model('Identity');
  
  if (substr($nick,0,1) == '_')
    $Member = $Identity->find(substr($nick,1));
  else
    $Member = $Identity->find_by('nickname',$nick);
  
  if ($Member)
    $request->set_param('id',$Member->id);
  else
    trigger_error("Sorry, the person named ".$nick." could not be found.", E_USER_ERROR);
}


before_filter( 'omb_request_munger', 'routematch' );

function omb_request_munger( &$request, &$route ) {
  
  global $oauth_functions;
  
  // look for a dbscript omb Route in the POST/GET params
  $params = array_merge($_GET,$_POST);
  foreach($oauth_functions as $func) {
    if (array_key_exists($func,$params)) {
        // if found, lie to the mapper about the URI
        $request->set('uri',$request->base."?".$func);
        $request->set('params', array($func));
    }
  }
}


global $omb_services;

$omb_services = array(
  'http://oauth.net/discovery/1.0',
  OMB_VERSION,
  OAUTH_VERSION . '/endpoint/request',
  OAUTH_VERSION . '/endpoint/authorize',
  OAUTH_VERSION . '/endpoint/access',
  OMB_VERSION   . '/postNotice',
  OMB_VERSION   . '/updateProfile'
);
  
  
function filter_MatchesAnyOMBType(&$service)
{
  global $omb_services;

  $uris = $service->getTypes();
  
  foreach ($uris as $uri) {
      if (in_array($uri, $omb_services)) {
          return true;
      }
  }

  return false;
}


// subscribe step 1 (remote service)

function oauth_omb_subscribe( &$vars ) {
  
  extract($vars);

  $db->create_openid_tables();

  wp_plugin_include(array(
    'wp-oauth'
  ));

  $key = $request->base;
  $secret = '';
  
  $wp_plugins = "wp-plugins" . DIRECTORY_SEPARATOR . "plugins" . DIRECTORY_SEPARATOR . "enabled";
  $path = plugin_path() . $wp_plugins . DIRECTORY_SEPARATOR . 'wp-openid' . DIRECTORY_SEPARATOR;
  add_include_path( $path ); 
  require_once "Auth/Yadis/Yadis.php";
  
  $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
  $yadis = Auth_Yadis_Yadis::discover($request->listener_uri, $fetcher);

  if (!$yadis || $yadis->failed)
    trigger_error("Sorry but the Yadis doc was not found at the profile URL", E_USER_ERROR);
  
  $xrds =& Auth_Yadis_XRDS::parseXRDS($yadis->response_text);

  if (!$xrds)
    trigger_error("Sorry but the XRDS data was not found in the Yadis doc", E_USER_ERROR);
  
  $yadis_services = $xrds->services(array('filter_MatchesAnyOMBType'));


  foreach ($yadis_services as $service) {
    $type_uris = $service->getTypes();
    $uris = $service->getURIs();
    if ($type_uris && $uris) {
      foreach ($uris as $uri) {
        $xrd = xrdends($uri,$xrds);
        $ends = $xrd->services(array('filter_MatchesAnyOMBType'));
        foreach($ends as $serv) {
          $typ = $serv->getTypes();
          global $omb_services;
          $end = "";
          foreach($typ as $t) {
            if (in_array($t,$omb_services))
              $end = $t;
          }
          $req = $serv->getURIs();
          $endpoints[$end] = $req[0];
        }  
      }
    }
  }
  
  $_SESSION['subscriber_request_token_url'] = $endpoints[OAUTH_VERSION . '/endpoint/request'];
  $_SESSION['subscriber_access_token_url'] = $endpoints[OAUTH_VERSION . '/endpoint/access'];
  $_SESSION['subscriber_authorize_url'] = $endpoints[OAUTH_VERSION . '/endpoint/authorize'];
  $_SESSION['subscriber_notice_url'] = $endpoints[OMB_VERSION . '/postNotice'];
  $_SESSION['subscriber_update_url'] = $endpoints[OMB_VERSION . '/updateProfile'];

  $_SESSION['listenee_id'] = $request->listenee_id;
  $_SESSION['listener_uri'] = $request->listener_uri;
  
  // need the Oauth Request Token URL for the subscriber's host
  
  $sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
  $consumer = new OAuthConsumer($key, $secret, NULL);
  
  // GETTING REQUEST TOKEN
  
  $rtoken = OAuthRequest::from_consumer_and_token($consumer, NULL, 'POST', $_SESSION['subscriber_request_token_url'], array());
  $rtoken->sign_request($sha1_method, $consumer, NULL);
  
  $curl = curl_init($_SESSION['subscriber_request_token_url']);
  
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $rtoken->to_postdata());
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $rtoken = curl_exec($curl);
  curl_close($curl);

  preg_match('/oauth_token=([^&]*)&oauth_token_secret=([^&]*)/', $rtoken, $rtoken);
  $rtoken_secret = $rtoken[2];
  $rtoken = $rtoken[1];
  if ( !$rtoken ) { echo "Sorry, an invalid request token was returned: $rtoken"; exit; }
  $_SESSION['rtoken'] = $rtoken;
  $_SESSION['rtoken_secret'] = $rtoken_secret;

  // finish_subscribe saves the profile
  $callback_url = $request->url_for( 'oauth_omb_finish_subscribe' );
  //echo 
  $con = new OAuthConsumer($key, $secret, NULL);
   $tok = new OAuthToken($rtoken, $rtoken_secret);
  $url = $_SESSION['subscriber_authorize_url'];
  $parsed = parse_url($url);
  $params = array();
  parse_str($parsed['query'], $params);
  $req = OAuthRequest::from_consumer_and_token($con, $tok, 'GET', $url, $params);

  $omb_subscribe = array();
  $Person =& $db->model('Person');

  $p = $Person->find( $_SESSION['listenee_id'] );
  if ($p) {
    // get an identities activerecord object 
    // this is an example of traversing a result set without re-querying
    $i = $p->FirstChild( 'identities' );
    if (!(isset($i->nickname)))
      trigger_error('the identity does not have a nickname', E_USER_ERROR);
    $omb_subscribe = array(
      'omb_listener'          => $_SESSION['listener_uri'],
      'omb_listenee'          => $i->profile,
      'omb_version'           => OMB_VERSION,
      'omb_listenee_profile'  => $i->profile,
      'omb_listenee_nickname' => $i->nickname,
      'omb_listenee_license'  => $i->license,
      'omb_listenee_fullname' => $i->fullname,
      'omb_listenee_homepage' => $i->url,
      'omb_listenee_bio'      => $i->bio,
      'omb_listenee_location' => $i->locality,
      'omb_listenee_avatar'   => $i->avatar
    );
  } else {
    trigger_error('Unable to find the listenee, sorry', E_USER_ERROR);
  }
  
  foreach($omb_subscribe as $k=>$v)
    $req->set_parameter($k, $v);

  $req->set_parameter('oauth_callback', $callback_url);

  $req->sign_request($sha1_method, $con, $tok);

  header('Location: '.$req->to_url(),true,303);
  exit;  
}


// subscribe step 2 (local service)

function oauth_authorize( &$vars ) {
  
  extract($vars);

  $db->create_openid_tables();

  wp_plugin_include(array(
    'wp-oauth'
  ));

  global $wpdb;
  global $userdata;
  
  if(!$_REQUEST['oauth_token'] && !$_POST['authorize']) die('No token passed');
  
  $NO_oauth = true;
  //require_once dirname(__FILE__).'/common.inc.php';
  $store = new OAuthWordpressStore();
  
  if(!$_POST['authorize']) {
    $token = $wpdb->escape($_REQUEST['oauth_token']);
    $consumer_key = $store->lookup_token('','request',$token);//verify token
    if(!$consumer_key) die('Invalid token passed');
  }//end if ! POST authorize

  get_currentuserinfo();
  
  if(!$userdata->ID) {
    redirect_to($request->url_for('openid_login'));
  }//end if ! userdata->ID
  

  $listenee_params = array(
    //'omb_listenee'  => '',
    'omb_listenee_fullname'  => 'fullname',
    'omb_listenee_profile'   => 'profile',
    'omb_listenee_nickname'  => 'nickname',
    'omb_listenee_license'   => 'license',
    'omb_listenee_homepage'  => 'url',
    'omb_listenee_bio'       => 'bio',
    'omb_listenee_location'  => 'locality',
    'omb_listenee_avatar'    => 'avatar'
  );
  
  $Identity =& $db->get_table( 'identities' );
  $Person =& $db->get_table( 'people' );
  
  $i = $Identity->find_by( 'profile', urldecode($_GET['omb_listenee_profile']) );
  if ($i) {
    // found an existing identity
  } else {
    // need to create the identity (and person?) because it was not found
    $p = $Person->base();
    $p->save();
    $i = $Identity->base();
    $i->set_value( 'label', 'profile 1' );
    $i->set_value( 'person_id', $p->id );
    foreach($listenee_params as $k=>$v ) {
      if (isset($_GET[$k])) {
        $i->set_value( $v, urldecode($_GET[$k]) );
      }
    }

    $i->save_changes();
    $i->set_etag($p->id);
  }
  
  $_SESSION['listenee_id'] = $i->id;
  
  if($_POST['authorize']) {
    session_start();
    $_REQUEST['oauth_callback'] = $_SESSION['oauth_callback']; unset($_SESSION['oauth_callback']);
    $token = $_SESSION['oauth_token']; unset($_SESSION['oauth_token']);
    $consumer_key = $_SESSION['oauth_consumer_key']; unset($_SESSION['oauth_consumer_key']);
    if($_POST['authorize'] != 'Ok') {
      if($_GET['oauth_callback']) {
        header('Location: '.urldecode($_GET['oauth_callback']),true,303);
      } else {
        //get_header();
        echo '<h2 style="text-align:center;">You chose to cancel authorization.  You may now close this window.</h2>';
        //get_footer();
      }//end if-else callback
      exit;
    }//cancel authorize
    $consumers = $userdata->oauth_consumers ? $userdata->oauth_consumers : array();
    $services = get_option('oauth_services');
    $yeservices = array();
    foreach($services as $k => $v)
      if(in_array($k, array_keys($_REQUEST['services'])))
        $yeservices[$k] = $v;
    $consumers[$consumer_key] = array_merge(array('authorized' => true), $yeservices);//it's an array so that more granular data about permissions could go in here
    $userdata->oauth_consumers = $consumers;
    update_usermeta($userdata->ID, 'oauth_consumers', $consumers);
  }//end if authorize
  
  if($userdata->oauth_consumers && in_array($consumer_key,array_keys($userdata->oauth_consumers))) {
    $store->authorize_request_token($consumer_key, $token, $userdata->ID);
    if($_GET['oauth_callback']) {
      
      $Subscription =& $db->model('Subscription');
  
      $sub = $Subscription->find_by( array(
        'subscribed'=>$_SESSION['listenee_id'],
        'subscriber'=>get_profile_id()
      ));
  
      
      if (!$sub) {
        $s = $Subscription->base();
        $s->set_value( 'subscriber', get_profile_id() );
        $s->set_value( 'subscribed', $_SESSION['listenee_id'] );
        $s->save_changes();
        $s->set_etag(get_person_id());
      }

      // response to omb remote service

      $i = get_profile();
      
      $omb_subscriber = array(
        'omb_version'           => OMB_VERSION,
        'omb_listener_profile'  => $i->profile,
        'omb_listener_nickname' => $i->nickname,
        'omb_listener_license'  => $i->license,
        'omb_listener_fullname' => $i->fullname,
        'omb_listener_homepage' => $i->url,
        'omb_listener_bio'      => $i->bio,
        'omb_listener_location' => $i->locality,
        'omb_listener_avatar'   => $i->avatar
      );
      
      $profileparams = "";
  
      foreach($omb_subscriber as $key=>$item)
        $profileparams .= "&".$key."=".urlencode($item);
      
      $profileparams .= "&oauth_token=".$token;
      
      header('Location: '.urldecode($_GET['oauth_callback']).$profileparams,true,303);
    } else {
      //get_header();
      echo '<h2 style="text-align:center;">Authorized!  You may now close this window.</h2>';
      //get_footer();
    }//end if-else callback
    exit;
  } else {
    session_start();//use a session to prevent the consumer from tricking the user into posting the Yes answer
    $_SESSION['oauth_token'] = $token;
    $_SESSION['oauth_callback'] = $_REQUEST['oauth_callback'];
    $_SESSION['oauth_consumer_key'] = $consumer_key;
    //get_header();
    $description = $store->lookup_consumer_description($consumer_key);
    if($description) $description = 'Allow '.$description.' to post notices to your account?';
      else $description = 'Allow the service you came from to access your account and...';
    ?>
    <div style="text-align:center;">
      <h2><?php echo $description; ?></h2>
      <form method="post" action=""><div>
        <div style="text-align:left;width:15em;margin:0 auto;">
          <ul style="padding:0px;">
        <?php
          $services = get_option('oauth_services');
          foreach($services as $k => $v)
            echo '<li><input type="checkbox" checked="checked" name="services['.htmlentities($k).']" /> '.$k.'</li>';
        ?>
          </ul>
          <br />
          <input type="submit" name="authorize" value="Ok" />
          <input type="submit" name="authorize" value="No" />
        </div>
      </div></form>
    </div>
    <?php
    //get_footer();
    exit;
  }//end if user has authorized this consumer
  
}

// subscribe step 3 (remote service)

function oauth_omb_finish_subscribe( &$vars ) {

  extract($vars);
  
  $db->create_openid_tables();

  wp_plugin_include(array(
    'wp-oauth'
  ));
  
  $req = OAuthRequest::from_request();

  $token = $req->get_parameter('oauth_token');

  if ($token != $_SESSION['rtoken'])
    trigger_error('Sorry the subscription failed', E_USER_ERROR);
  
  $listener_params = array(
    'omb_listener_fullname'  => 'fullname',
    'omb_listener_profile'   => 'profile',
    'omb_listener_nickname'  => 'nickname',
    'omb_listener_license'   => 'license',
    'omb_listener_homepage'  => 'url',
    'omb_listener_bio'       => 'bio',
    'omb_listener_location'  => 'locality',
    'omb_listener_avatar'    => 'avatar'
  );
  
  $Identity =& $db->get_table( 'identities' );
  $Person =& $db->get_table( 'people' );
  $i = $Identity->find_by( 'url', $_GET['omb_listener_profile'] );
  if ($i) {
    // found an existing identity
  } else {
    // need to create the identity (and person?) because it was not found
    $p = $Person->base();
    $p->save();
    $i = $Identity->base();
    $i->set_value( 'url', $_GET['omb_listener_profile'] );
    $i->set_value( 'label', 'profile 1' );
    $i->set_value( 'person_id', $p->id );
    foreach($listener_params as $k=>$v ) {

      if (isset($_GET[$k])) {
        $i->set_value( $v, urldecode($_GET[$k]) );
      }
    }
    $i->set_value( 'update_profile', $_SESSION['subscriber_update_url'] );
    $i->set_value( 'post_notice', $_SESSION['subscriber_notice_url'] );
    $i->save_changes();
    $i->set_etag($p->id);
  }
  
  $url = $_SESSION['subscriber_access_token_url'];
  $parsed = parse_url($url);
  $params = array();
  parse_str($parsed['query'], $params);
  $sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
  $consumer = new OAuthConsumer($request->base, '', NULL);
  $token = new OAuthToken($_SESSION['rtoken'], $_SESSION['rtoken_secret']);
  $req = OAuthRequest::from_consumer_and_token($consumer, $token, "POST", $url, $params);
  $req->set_parameter('omb_version', OMB_VERSION);

  $req->sign_request($sha1_method, $consumer, $token);
  //$route = 'access_token';
  //$atoken = str_replace("?oauth_version","?".$route."&oauth_version",$atoken->to_url());

  //print_r($part);
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $req->to_postdata());
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $atoken = curl_exec($curl);
  curl_close($curl);

 
  parse_str($atoken, $result);

  if (!(isset($result['oauth_token']) && isset($result['oauth_token_secret'])))
    trigger_error( 'could not find the access token!',E_USER_ERROR);
  $Subscription =& $db->model( 'Subscription' );
  $s = $Subscription->base();
  $s->set_value( 'subscriber', $i->id );
  $s->set_value( 'subscribed', $_SESSION['listenee_id'] );
  $s->set_value( 'token', $result['oauth_token'] );
  $s->set_value( 'secret', $result['oauth_token_secret'] );
  $s->save();
  $p = $i->FirstChild('people');
  $s->set_etag($p->id);

  redirect_to(array(
        'resource'=>'identities',
        'id'=>$_SESSION['listenee_id'] ));
}




// subscribe step 4 (local service)

function access_token( &$vars ) {
  
  extract($vars);

  $db->create_openid_tables();

  wp_plugin_include(array(
    'wp-oauth'
  ));
  
  $store = new OAuthWordpressStore();
  $server = new OAuthServer($store);
  $sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
  $plaintext_method = new OAuthSignatureMethod_PLAINTEXT();
  $server->add_signature_method($sha1_method);
  $server->add_signature_method($plaintext_method);
  
  $req = OAuthRequest::from_request();
  $token = $server->fetch_access_token($req);
  
  header( 'Status: 200 OK' );
  print $token->to_string().'&xoauth_token_expires='.urlencode($store->token_expires($token));
  exit;
  
}




function request_token( &$vars ) {
  
  extract($vars);
  
  $db->create_openid_tables();

  wp_plugin_include(array(
    'wp-oauth'
  ));
  
  $consumer_result = $db->get_result("SELECT consumer_key FROM oauth_consumers WHERE consumer_key like '".$_POST['oauth_consumer_key']."'");
  
  if (!$db->num_rows($consumer_result)>0)
    $result = $db->get_result("INSERT INTO oauth_consumers (consumer_key, secret, description) VALUES ('".$_POST['oauth_consumer_key']."', '', 'Unidentified Consumer')");
  
  $store = new OAuthWordpressStore();
  $server = new OAuthServer($store);
  $sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
  $plaintext_method = new OAuthSignatureMethod_PLAINTEXT();
  $server->add_signature_method($sha1_method);
  $server->add_signature_method($plaintext_method);
  $params = array();
  foreach($_POST as $key=>$val) {
    if (!($key == 'request_token'))
      $params[$key] = $val;
  }
  $req = OAuthRequest::from_request();
  $token = $server->fetch_request_token($req);
  header( 'Status: 200 OK' );
  print $token->to_string().'&xoauth_token_expires='.urlencode($store->token_expires($token));
  exit;
}


function oauth_omb_post( &$vars ) {
  
  extract($vars);
  
  $db->create_openid_tables();

  wp_plugin_include(array(
    'wp-oauth'
  ));
  $store = new OAuthWordpressStore();
  $server = new OAuthServer($store);
  $sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
  $plaintext_method = new OAuthSignatureMethod_PLAINTEXT();
  $server->add_signature_method($sha1_method);
  $server->add_signature_method($plaintext_method);
  $req = OAuthRequest::from_request();
  //$token = $server->fetch_access_token($req);
  list($consumer, $token) = $server->verify_request($req);

  $version = $req->get_parameter('omb_version');

  if ($version != OMB_VERSION)
    trigger_error('invalid omb version', E_USER_ERROR);

  $listenee = $req->get_parameter('omb_listenee');

  $Identity =& $db->model('Identity');
  $sender = $Identity->find_by('profile',$listenee);
  
  $Subscription =& $db->model('Subscription');
  $sub = $Subscription->find_by('token', $token->key);
  
  $content = $req->get_parameter('omb_notice_content');
  
  $notice_uri = $req->get_parameter('omb_notice');
  
  $notice_url = $req->get_parameter('omb_notice_url');
  
  $Post =& $db->model('Post');
  $p = $Post->find_by('uri',$notice_uri);
  
  if (!$p) {
    $p = $Post->base();
    //$notice->profile_id = $sender->id;
    $p->set_value( 'profile_id', $sender->id );
    $p->set_value( 'uri', $notice_uri );
    $p->set_value( 'title', $content );
    $p->save_changes();
    $p->set_etag($sender->person_id);
  }
  
  print "omb_version=".OMB_VERSION;
  exit;
  
}


function oauth_post_content_type() {
  return "application/x-www-form-urlencoded";
  //application/atom+xml;type=entry
}


function local_subscribe( &$vars ) {
  
  extract($vars);
  
  $Subscription =& $db->model('Subscription');
  
  $sub = $Subscription->find_by( array(
    'subscribed'=>$request->listenee_id,
    'subscriber'=>get_profile_id()
  ));
  
  if (!$sub) {
    $sub = $Subscription->base();
    $sub->set_value('subscribed',$request->listenee_id);
    $sub->set_value('subscriber',get_profile_id());
    $sub->save_changes();
  }
  
  redirect_to( array(
    'resource'=>$request->listenee_nick
  ));
  
}


function local_unsubscribe( &$vars ) {
  
  extract($vars);
  
  $Subscription =& $db->model('Subscription');
  
  $sub = $Subscription->find_by( array(
    'subscribed'=>$request->listenee_id,
    'subscriber'=>get_profile_id()
  ));
  
  if ( $sub )
    $db->delete_record( $sub );
  
  redirect_to( array(
    'resource'=>$request->listenee_nick
  ));
  
}


function xrdends( $uri, $xrds ) {
  
  if (!(substr($uri,0,1)) == '#')
    return;
  
  $xmlid = substr( $uri, 1 );
  
  $n = $xrds->allXrdNodes;
  
  $p = $xrds->parser;
  
  foreach ( $n as $nd ) {
    
    $a = $p->attributes( $nd );
    
    if ( isset($a['xml:id']) && $a['xml:id'] == $xmlid ) {
      $skip = array( $nd );
      return new Auth_Yadis_XRDS( $p, $skip );

    }
  }
}


function oauth_omb_update( &$vars ) {
  
  // update profile code goes here! XXX
  
}


function oauth_omb_register_services() {
  
  global $request;
  global $db;
  $Identity =& $db->model('Identity');
  $i = $Identity->find($request->id);
  
  //register_xrd_service('main', 'OAuth Dummy Service', array(
  //  'Type' => array( array('content' => 'http://oauth.net/discovery/1.0') ),
  //  'URI' => array( array('content' => '#oauth' ) ),
  //) );
  
  //register_xrd_service('main', 'OMB Dummy Service', array(
  //  'Type' => array( array('content' => 'http://openmicroblogging.org/protocol/0.1') ),
  //  'URI' => array( array('content' => '#omb' ) ),
  //) );
  
  register_xrd('oauth');
  
  register_xrd('omb');
  
  register_xrd_service( 'omb', 'OMB Post Notice', array(
    'Type' => array( 
      array('content' => OMB_VERSION . '/postNotice')
    ),
    'URI' => array( array('content' => $request->url_for( 'oauth_omb_post' ) ) ),
  ) );
  
  register_xrd_service( 'omb', 'OMB Update Profile', array(
    'Type' => array( 
      array('content' => OMB_VERSION . '/updateProfile')
    ),
    'URI' => array( array('content' => $request->url_for( 'oauth_omb_update' ) ) ),
  ) );
  
  register_xrd_service('oauth', 'OAuth Request Token', array(
    'Type' => array( 
    
      array('content' => OAUTH_VERSION . '/endpoint/request'),
      array('content' => OAUTH_VERSION . '/parameters/auth-header'),
      array('content' => OAUTH_VERSION . '/parameters/post-body'),
      array('content' => OAUTH_VERSION . '/signature/HMAC-SHA1'),
    ),
    'URI' => array( array('content' => $request->url_for( 'request_token' ) ) ),
    'LocalID' => array('content' => $i->profile )
  ) );
  
  register_xrd_service('oauth', 'OAuth Authorize Token', array(
    'Type' => array( 
      array('content' => OAUTH_VERSION . '/endpoint/authorize'),
      array('content' => OAUTH_VERSION . '/parameters/auth-header'),
      array('content' => OAUTH_VERSION . '/parameters/post-body'),
      array('content' => OAUTH_VERSION . '/signature/HMAC-SHA1'),
    ),
    'URI' => array( array('content' => $request->url_for( 'oauth_authorize' ) ) ),
  ) );
  
  register_xrd_service('oauth', 'OAuth Access Token', array(
    'Type' => array( 
      array('content' => OAUTH_VERSION . '/endpoint/access'),
      array('content' => OAUTH_VERSION . '/parameters/auth-header'),
      array('content' => OAUTH_VERSION . '/parameters/post-body'),
      array('content' => OAUTH_VERSION . '/signature/HMAC-SHA1'),
    ),
    'URI' => array( array('content' => $request->url_for( 'access_token' ) ) ),
  ) );
  
  register_xrd_service('oauth', 'OAuth Resources', array(
    'Type' => array( 
      array('content' => OAUTH_VERSION . '/endpoint/resource'),
      array('content' => OAUTH_VERSION . '/parameters/auth-header'),
      array('content' => OAUTH_VERSION . '/parameters/post-body'),
      array('content' => OAUTH_VERSION . '/signature/HMAC-SHA1'),
    ),
  ) );
  
  //register_xrd_service('oauth', 'OAuth Static Token', array(
  //  'Type' => array( 
  //    array('content' => 'http://oauth.net/discovery/1.0/consumer-identity/static'),
  //  ),
  //  'LocalID' => array( array('content' => $request->url_for(array('resource'=>'identities','id'=>$request->id )))),
  //) );
  
  
  
}










?>