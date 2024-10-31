<?php
/*
Plugin Name: Posts from Images
Plugin URI: http://wordpress.org/extend/plugins/posts-from-images/
Description: Makes a post for every image in your library and optionally sets it as the the post thumbnail, adds the image and/or gallery to the post body.
Version: 1.1.1
Author: Davey IJzermans
Author URI: http://daveyyzermans.nl/
License: GPL3
*/

/*
Copyright (C) 2011 Davey IJzermans

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

add_action('admin_menu', 'posts_from_images_menu');

function posts_from_images_menu() {
  add_management_page( __( "Posts from Images", 'pfi'), __( "Posts from Images" , 'pfi'), 'edit_posts', 'posts-from-images', 'posts_from_images_page' );
}

add_action( 'init', 'load_post_from_images_locale' );

function load_post_from_images_locale() {
  
  load_plugin_textdomain( 'pfi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
  
}

function posts_from_images_page() {
	
	if ( ! current_user_can( 'edit_posts' ) )  {
		
		wp_die( __('You do not have sufficient permissions to access this page.', 'pfi') );
		
	}
	
  /* the magic */
  
  $arr_parameters = array( 'include', 'exclude' );
  $image_sizes = get_intermediate_image_sizes();
  $log = array();
  
  if( isset( $_POST['posts_from_images']['submit'] ) ) {
    
    $time = explode ( ' ',microtime() );
    $start_time = (double) $time[0] + $time[1];
    
    $sanitized = array();
    
    foreach( $arr_parameters as $a ) { //yup, I'm lazy
	    
	    if( isset( $_POST['posts_from_images'][$a] ) ) {
	      
	      $to_exclude = trim( $_POST['posts_from_images'][$a] );
	      $explode = explode( ',', $to_exclude );
	      $sanitized[$a] = array();
	      
	      foreach( $explode as $e ) {
	        
	        if( ctype_digit($e) ) {
	          
	          $sanitized[$a][] = $e;
	          
	        }
	        
	      }
	      
	    }
	    
    }
    
    $attachments = get_posts( array(
      
      'numberposts' => -1,
      'post_type' => 'attachment',
      'exclude' => $sanitized['exclude'],
      'include' => $sanitized['include']
      
    ) );
    
    $make_post_thumbnails = ( isset( $_POST['posts_from_images']['thumbnail'] ) ? true : false );
    $title_format = $_POST['posts_from_images']['title_format'];
    $content_format = $_POST['posts_from_images']['content_format'];
    $date_format = $_POST['posts_from_images']['date_format'];
    $post_post_type = $_POST['posts_from_images']['cpt'];
    
    $i = 0;
    foreach($attachments as $attachment) {
      
      $i++;
      $att_ID = $attachment->ID;
      $att_post_title = $attachment->post_title;
      $att_post_date = $attachment->post_date;
      
      $post_post_title = $title_format;
      $post_post_title = str_ireplace('[title]', $att_post_title, $post_post_title);
      $post_post_title = str_ireplace('[date]', mysql2date($date_format, $att_post_date), $post_post_title);
      $post_post_title = str_ireplace('[ID]', $att_ID, $post_post_title);
      $post_post_title = str_ireplace('[i]', $i, $post_post_title);
      
      $post_post_content = $content_format;
      foreach( $image_sizes as $size ) {
        
        $att_url = wp_get_attachment_image_src( $att_ID, $size );
        if($att_url[0] == '') {
          $log[] = '<strong style="color:orange">' .sprintf( __( 'Failed to make a post for attachment \'%1$s\', because it is not an image. (ID: %2$s)' , 'pfi'), $att_post_title, $att_ID ). '</strong>';
          break;
        }
        $post_post_content = str_ireplace('[url=' .$size. ']', $att_url[0], $post_post_content);
        
      }
      $post_post_content = str_ireplace('[title]', $att_post_title, $post_post_content);
      $post_post_content = str_ireplace('[date]', $att_post_date, $post_post_content);
      $post_post_content = str_ireplace('[ID]', $att_ID, $post_post_content);
      $post_post_content = str_ireplace('[i]', $i, $post_post_content);
      $post_post_content .= ( isset( $_POST['posts_from_images']['gallery'] ) ? '[gallery]' : '' );
      
      $post = array(
		    
		    'post_title' => $post_post_title,
		    'post_content' => $post_post_content,
		    'post_status' => 'publish',
		    'post_type' => $post_post_type
		    
			);
			
			$the_new_post = wp_insert_post( $post );
			
			$fail = ( is_wp_error($the_new_post) ? true : ( $the_new_post === 0 ? true : false ) );
			
			if( $fail === true ) {
				
				$log[] = '<strong style="color:darkred">' .sprintf( __( 'Failed to make a post for attachment \'%1$s\'. (ID: %2$s)' , 'pfi'), $att_post_title, $att_ID ). '</strong>';
				
			} else {
			  
			  if( $make_post_thumbnails === true ) { update_post_meta( $the_new_post,'_thumbnail_id', $att_ID); }
			  wp_update_post( array ( 'ID' => $att_ID, 'post_parent' => $the_new_post ) );
			  $log[] = sprintf( __( 'Post made for attachment \'%1$s\'. (ID: %2$s, post ID: %3$s)' , 'pfi'), $att_post_title, $att_ID , $the_new_post );
			  
			}
      
    }
    
    if( ! empty( $log ) ) {
      
      foreach( $log as $l ) {
        
        echo( $l. '<br/>' );
        
      }
      
      $time = explode ( ' ',microtime() );
	    $end_time = (double) $time[0] + $time[1];
      echo '<div class="updated fade" id="message"><p>' .sprintf( __( "Finished in %s seconds" , 'pfi'), number_format( ( $end_time - $start_time ), 2 ) ). '</p></div>';
      
    }
    
  }


?>
	
<div class="wrap">

<h2><?php _e( "Posts from Images" , 'pfi'); ?></h2>
<?php _e( "By" , 'pfi');?> <a href="http://daveyyzermans.nl/" target="_blank">Davey IJzermans</a>.

<form method="post" action="">
	
	<table class="form-table">
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[include]"><?php _e( "Include these attachment IDs:" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[include]" id="posts_from_images_include" type="text" class="input" value="<?php echo ( isset( $_POST['posts_from_images']['include'] ) ? $_POST['posts_from_images']['include'] : '' ); ?>" /><br/>
			<?php _e( "Enter comma-seperated attachment IDs to include for import." , 'pfi'); ?>
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[exclude]"><?php _e( "Exclude these attachment IDs:" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[exclude]" id="posts_from_images_exclude" type="text" class="input" value="<?php echo ( isset( $_POST['posts_from_images']['exclude'] ) ? $_POST['posts_from_images']['exclude'] : '' ); ?>" /><br/>
			<?php _e( "Enter comma-seperated attachment IDs to exclude from import." , 'pfi'); ?>
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[cpt]"><?php _e ("(Custom) Post Type:" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[cpt]" id="posts_from_images_cpt" type="text" class="input" value="<?php echo ( isset( $_POST['posts_from_images']['cpt'] ) ? esc_attr( stripslashes( $_POST['posts_from_images']['cpt'] ) ) : 'post' ); ?>" /><br/>
			<?php _e( "Make sure to enter it correctly, or just use 'post'." , 'pfi'); ?>
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[thumbnail]"><?php _e( "Set image as post thumbnail" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[thumbnail]" id="posts_from_images_thumbnail" type="checkbox" class="check"<?php echo ( isset( $_POST['posts_from_images']['thumbnail'] ) ? ' checked="checked"' : '' ); ?> /><br/>
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[title_format]"><?php _e( "Title Format" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[title_format]" id="posts_from_images_title_format" type="text" class="input" value="<?php echo ( isset( $_POST['posts_from_images']['title_format'] ) ? esc_attr( stripslashes( $_POST['posts_from_images']['title_format'] ) ) : '[title]' ); ?>" /><br/>
			<?php _e( "Use [title] for image title, [date] for image date, [ID] for attachment id and [i] for a sequential number." , 'pfi'); ?><br/>
			<?php _e( "Don't forget your seperators!" , 'pfi'); ?>
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[content_format]"><?php _e( "Content Format" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[content_format]" id="posts_from_images_content_format" type="text" class="input" size="60" value="<?php echo ( isset( $_POST['posts_from_images']['content_format'] ) ? esc_attr( stripslashes( $_POST['posts_from_images']['content_format'] ) ) : esc_attr( '<img src="[url=' .$image_sizes[1]. ']" alt="[title]" /&gt;' ) ); ?>" /><br/>
			<?php _e( "Use [url=*image_size*] for image url, [title] for image title, [date] for image date, [ID] for attachment id and [i] for a sequential number." , 'pfi'); ?><br/>
			<?php _e( "Available image sizes:" , 'pfi'); ?> <?php $i = 0; foreach( $image_sizes as $size ) { echo $size. ( $i == count( $image_sizes ) - 1 ? '' : ', ' ); $i++; } ?>.
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[date_format]"><?php _e( "Date Format" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[date_format]" id="posts_from_images_date_format" type="text" class="input" size="60" value="<?php echo ( isset( $_POST['posts_from_images']['date_format'] ) ? esc_attr( stripslashes( $_POST['posts_from_images']['date_format'] ) ) : get_option('date_format') .' '. get_option('time_format') ); ?>" /><br/>
			<?php _e( "Use PHP date() formatting." , 'pfi'); ?>
		</td>
	</tr>
	
	<tr valign="top">
		<th scope="row"><label for="posts_from_images[gallery]"><?php _e( "Include [gallery] tag in post content?" , 'pfi'); ?></label></th>
		<td>
			<input name="posts_from_images[gallery]" id="posts_from_images_gallery" type="checkbox" class="check"<?php echo ( isset( $_POST['posts_from_images']['gallery'] ) ? ' checked="checked"' : '' ); ?> /><br/>
		</td>
	</tr>
	
	</table>
	
  <?php submit_button( __( "Go!" , 'pfi'), 'primary', 'posts_from_images[submit]' ); ?>
	
</form>

</div>
	
<?php

}

?>