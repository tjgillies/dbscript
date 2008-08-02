<?php
$user      = get_userdata( $current_user->ID );
$first_name    = attribute_escape( $user->first_name );
?>

<div id="postbox">
  <form enctype="multipart/form-data" id="new_post" name="new_post" method="post" action="<?php bloginfo( 'url' ); ?>">
    <input type="hidden" name="action" value="post" />
    <input type="hidden" name="profile_id" value="<?php echo get_profile_id(); ?>" />
    <?php wp_nonce_field( 'new-post' ); ?>

    <?php echo prologue_get_avatar( $user->ID, $user->user_email, 48 ); ?>

    <label for="posttext">Hi, <?php echo $first_name; ?>. Whatcha up to?</label>
    <textarea name="posttext" id="posttext" rows="3" cols="60"></textarea>

    <label for="link">Hyperlink</label>
    <input id="link" name="link[href]" />

    <label for="postfile">Attachment</label>
    <input id="postfile" type="file" name="post[attachment]" />
    
    <label for="tags">Tag it</label>
    <input type="text" name="tags" id="tags" autocomplete="off" />
    <input type="hidden" name="post[local]" value="1" />
    <input id="submit" type="submit" value="Post it" />
  </form>
</div> <!-- // postbox -->
