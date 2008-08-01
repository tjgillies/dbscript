<?php

  // send an e-mail when content changes
after_filter( 'send_email_notice', 'insert_from_post' );
after_filter( 'send_email_notice', 'update_from_post' );
after_filter( 'send_email_notice', 'delete_from_post' );


function send_email_notice( &$model, &$rec ) {
  
  global $db;
  global $request;
  
  // get data modesl for 3 tables
  $Entry =& $db->get_table('entries');
  $Group =& $db->get_table( 'groups' );
  $Person =& $db->get_table( 'people' );
  
  // load the first 20 records from the groups table
  $Group->find();
  
  // keep a list of people we have notified
  $sent_to = array();
  
  // get the name of the table from the data model reference we received
  $notify_table = $model->table;
  
  // get the primary key value of the record reference we received
  $notify_id = $rec->id;
  
  // if the table that was modified is a metadata table (comments, reviews)
  // notify about the "target" table being modified
  if (array_key_exists( 'target_id', $model->field_array )) {
    $e = $Entry->find($rec->attributes['target_id']);
    if ($e) {
      $notify_table = $e->resource;
      $notify_id = $e->record_id;
    }
  }
  
  // get the data model we are notifying about
  $datamodel =& $db->get_table($notify_table);
  
  // get the profile data for the current user
  $profile = get_profile();
  
  // loop over each group
  while ($g = $Group->MoveNext()) {
    
    // if the GROUP has READ or CREATE then do notify its members
    if ($rec->id && (in_array($g->name,$datamodel->access_list['read']['id']) ||
      in_array($g->name,$datamodel->access_list['create'][$notify_table]))) {
      
      // loop over each member in the group
      while ( $m = $g->NextChild( 'memberships' ) ) {
        
        // get a person activerecord object for the member's person_id
        $p = $Person->find( $m->person_id );
        
        if ($p) {
          
          // get an identities activerecord object for the person's first identity
          // this is an example of traversing the result dataset without re-querying
          $i = $p->FirstChild( 'identities' );
          
          // if we haven't already sent this person a message
          if (is_email($i->email_value) && !(in_array($i->email_value, $sent_to))) {
            
            // a token may be set to allow the notify-ee to "EXPRESS" register as a new site user
            // it fills in some of the "new user" form info such as e-mail address for them
            if (isset($i->token) && strlen($i->token) > 0)
              $addr = $request->url_for(array('resource'=>$notify_table,'id'=>$notify_id,'ident'=>$i->token));
            else
              $addr = $request->url_for(array('resource'=>$notify_table,'id'=>$notify_id));
            
            // this is the HTML content of the e-mail
            $html = ' 
            <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\"> 
            <html> 
            <body> 
            <br> 
            <b><u><i>Click on this link:</i></u></b><br> 
            <br>
            <font color="red"><a href="'
            .$addr.
            '">'.$addr.'</a></font> 
            </body> 
            </html>';
            
            // oh wait, we are not going to send the HTML it is just wasting space for now
            // comment this out to try some HTML for yourself
            $html = false;
            
            // change the e-mail subject line depending on what action took place
            if ($request->action == 'post')
              $actionmessage = " created a new ";
            elseif ($request->action == 'put')
              $actionmessage = " updated a ";
            elseif ($request->action == 'delete')
              $actionmessage = " deleted a ";
            
            // set the e-mail subject to the current user's first name
            // classify() converts a table name "nerds" to "Nerd"
            // the converse is tableize()
            $subject = $profile->given_name.$actionmessage.classify($request->resource);
            
            // this is the actual body of the e-mail we are using
            $text = 'Content was updated at the following location:'."\r\n\r\n".$addr."\r\n\r\n";
            
            // this sends e-mail using the xpertmailer package
            // the environment() function reads a value from the config.yml file
            send_email( $i->email_value, $subject, $text, environment('email_from'), environment('email_name'), $html );
            
            // add a new entry to the list of successful (more like woeful) recipients
            $sent_to[] = $i->email_value;
            
          }
          
        }
        
      }
      
    }
    
  }
  
}



?>