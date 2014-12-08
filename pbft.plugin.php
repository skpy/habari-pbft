<?php
class PBFT extends Plugin
{
  public function action_plugin_activation( $file ='' )
  {
    // Was this plugin activated?
    if ( Plugins::id_from_file( $file ) == Plugins::id_from_file( __FILE__ ) ) {
      CronTab::add_cron( array(
        'name'        => 'pbft',
        'callback'    => array( __CLASS__, 'go'), 
        'increment'   => 600,
        'description' => 'Create posts from tagged Flickr photos'
      ) );
    }
  }

  public function action_plugin_deactivation( $file )
  {
    // Was this plugin deactivated?
    if ( Plugins::id_from_file( $file ) == Plugins::id_from_file( __FILE__ ) ) {
      CronTab::delete_cronjob( 'pbft' );
    }
  }

  public static function go()
  {
    $pbft_users = Users::get_by_info( 'pbft_active', 1);
    if ( empty( $pbft_users ) ) { return; }
    foreach ( $pbft_users as $u ) {
      if ( $u->info->pbft_user && $u->info->pbft_tag ) {
        self::go_for_user( $u->id, $u->info->pbft_user, $u->info->pbft_tag );
      }
    }
  }

  public static function go_for_user( $huid, $fuid, $tag )
  {
    $url = 'https://api.flickr.com/services/feeds/photos_public.gne?id=' . $fuid . '&tags=' . $tag . '&format=php_serial';
    $call = new RemoteRequest( $url );
    $call->set_timeout( 5 );
    $result = $call->execute( );
    if ( Error::is_error( $result ) ) {
      throw Error::raise( _t( 'Unable to contact Flickr.', 'pbft' ) );
    }
    $feed = unserialize( $call->get_response_body() );
    foreach (array_reverse( $feed['items'] ) as $image) {
      # get the photo ID from the GUID
      # and cast it as an integer
      $guid = (int) substr($image['guid'], 7);
      # make sure we haven't created a post for this photo before
      $post = Post::get( array( 'info' => array( 'guid' => $guid )));
      if ( $post ) { continue; }
      # save the photo to our local storage
      # create a new post
      $content = '<p><img src="' . $image['l_url'] . '" /></p>';
      $content .= '<p>' . $image['description_raw'] . '</p>';
      $tags = str_replace(' ', ',', trim(str_replace($tag, '', $image['tags'])));
      $postdata = array(
        'user_id'      => $huid,
        'content_type' => Post::type( 'entry' ),
        'status'       => Post::status( 'published' ),
        'pubdate'      => HabariDateTime::date_create ($image['date']),
        'title'        => $image['title'],
        'content'      => $content, 
      );
      $post = new Post( $postdata );
      $post->info->guid = $guid;
      $post->insert();
    }
  }

  public function action_form_user( $form, $edit_user )
  {
    $pbft = $form->insert('page_controls', 'wrapper','pbft', _t('Flickr Tag!', 'pbft'));
    $pbft->class = 'container settings';
    $pbft->append( 'static', 'pbft', '<h2>' . htmlentities( _t( 'Flickr Tag!', 'pbft' ), ENT_COMPAT, 'UTF-8' ) . '</h2>' );

    $pbft_active = $form->pbft->append( 'select', 'pbft_active', 'null:null', _t('Enable post by Flickr Tag: ', 'pbft') );
    $pbft_active->class[] = 'item clear';
    $pbft_active->options = array( 1 => 'Yes', 0 => 'No' );
    $pbft_active->template = 'optionscontrol_select';
    $pbft_active->value = $edit_user->info->pbft_active;

    $pbft_user = $form->pbft->append( 'text', 'pbft_user', 'null:null', _t('Flickr User ID: ', 'pbft'), 'optionscontrol_text' );
    $pbft_user->value = $edit_user->info->pbft_user;
    $pbft_user->class[] = 'item clear';

    $pbft_tag = $form->pbft->append( 'text', 'pbft_tag', 'null:null', _t('Flickr Tag: ', 'pbft'), 'optionscontrol_text' );
    $pbft_tag->value = $edit_user->info->pbft_tag;
    $pbft_tag->class[] = 'item clear';
  }

  public function filter_adminhandler_post_user_fields( $fields )
  {
    $fields['pbft_active'] = 'pbft_active';
    $fields['pbft_user'] = 'pbft_user';
    $fields['pbft_tag'] = 'pbft_tag';
    return $fields;
  }
}
?>
