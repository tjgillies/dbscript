<?php

class Post extends Model {
  
  function Post() {
    
    // fields
    
    $this->char_field( 'title' );
    
    $this->text_field( 'body' );
    
    $this->text_field( 'summary' );
    
    $this->text_field( 'contributor' );
    $this->text_field( 'rights' );
    $this->text_field( 'source' );
    
    $this->char_field( 'uri' );
    
    $this->file_field( 'attachment' );
    
    $this->int_field( 'parent_id' );
    $this->int_field( 'profile_id' );
    
    $this->time_field( 'created' );
    $this->time_field( 'modified' );
    
    $this->int_field( 'entry_id' );
    
    $this->auto_field( 'id' );
    
    // relationships
    
    // each record in posts HAS ONE record in entries
    
    $this->has_one( 'entry' );

    //$this->has_many( 'comments' );

    //$this->has_many( 'reviews' );
    
    $this->set_limit(10);
    
    // permissions
    
    //$this->let_access( 'all:always' );
    
    $this->let_read( 'all:always' );
    $this->let_create( 'all:members' );
    $this->let_access( 'all:administrators' );
    
  }
  
}

?>