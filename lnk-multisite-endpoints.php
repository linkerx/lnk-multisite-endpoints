<?php

/**
 * Plugin Name: LNK-MULTISITE-ENDPOINTS
 * Plugin URI: https://github.com/linkerx/lnk-multisite-endpoint
 * Description: Endpoints varios para Wordpress Multisite
 * Version: 0.1
 * Author: Diego Martinez Diaz
 * Author URI: https://github.com/linkerx
 * License: GPLv3
 */

/**
 * Registra los endpoints para la ruta lnk/v1 de la rest-api de Wordpress
 *
 * /sites: Lista de sitios
 * /sites/(?P<name>[a-zA-Z0-9-]+): Datos de un sitio en particular
 * /sites-posts:
 */
function lnk_sites_register_route(){

  $route = 'lnk/v1';

  // Endpoint: Lista de Sitios
  register_rest_route( $route, '/sites', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'lnk_get_sites',
  ));

  // Endpoint: Sitio Unico
  register_rest_route( $route, '/sites/(?P<name>[a-zA-Z0-9-]+)', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'lnk_get_site',
  ));

  // Endpoint: Posts de Sitio Unico
  register_rest_route( $route, '/site-posts/(?P<name>[a-zA-Z0-9-]+)', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'lnk_get_site_posts',
  ));

  // Endpoint: Ultimos Post de Todos Los Sitios
  register_rest_route( $route, '/sites-posts', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'lnk_get_sites_posts',
  ));

  // Endpoint: Posts Destacados de Todos Los Sitios
  register_rest_route( $route, '/sites-featured-posts', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'lnk_get_sites_featured_posts',
  ));
}
add_action( 'rest_api_init', 'lnk_sites_register_route');

/**
 * Lista de sitios
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response $sites
 */
function lnk_get_sites(WP_REST_Request $request) {
  $args = array(
    'public' => 1 // para ocultar el sitio principal
  );
  $sites = get_sites($args);
  if(is_array($sites))
  foreach($sites as $key => $site){
    switch_to_blog($site->blog_id);
    $sites[$key]->blog_name = get_bloginfo('name');
    $sites[$key]->blog_description = get_bloginfo('description');
    $sites[$key]->wpurl = get_bloginfo('wpurl');
     // info delegacion
  $sites[$key]->info_direccion = get_option('delegacion_info_direccion','Calle XXX, Localidad, Río Negro');
  $sites[$key]->info_telefono = get_option('delegacion_info_telefono','+54 XXX - XXX XXXX');
  $sites[$key]->info_email = get_option('delegacion_info_email','email_delegacion@upcn-rionegro.com.ar');
  $sites[$key]->info_imagen = get_option('delegacion_info_imagen','http://back.upcn-rionegro.com.ar/wp-content/uploads/2003/04/logo_upcn.jpg');

    restore_current_blog();
  }
  return new WP_REST_Response($sites, 200 );
}

/**
 * Sitio unico
 *
 * @param WP_REST_Request $request Id del sitio
 * @return WP_REST_Response $sites Datos del sitio
 */
function lnk_get_site(WP_REST_Request $request){

  $sites_args = array(
    'path' => '/'.$request['name'].'/' // los posts tb solo publicos?
  );
  $sites = get_sites($sites_args);
  if(count($sites) != 1){
    return new WP_REST_Response('no existe el área', 404 );
  }
  $site = $sites[0];

  switch_to_blog($site->blog_id);

  $site->frontpage = get_option('page_on_front');
  $site->barra_izq = get_option('curza_barra_izq_abierta',0);
  $site->barra_der = get_option('curza_barra_der_abierta',0);
  
  if($site->frontpage != 0){
    $site->page = get_post($site->frontpage);
  }

  $site->blog_name = get_bloginfo('name');
  $site->blog_description = get_bloginfo('description');
  $site->wpurl = get_bloginfo('wpurl');

  restore_current_blog();

  return new WP_REST_Response($site, 200 );
}

/**
 * Home Posts de Sitio
 *
 * @param WP_REST_Request $request Id del sitio
 * @return WP_REST_Response $sites Datos del sitio
 */
function lnk_get_site_posts(WP_REST_Request $request){

  $sites_args = array(
    'path' => '/'.$request['name'].'/' // los posts tb solo publicos?
  );
  $sites = get_sites($sites_args);
  if(count($sites) != 1){
    return new WP_REST_Response('no existe el área', 404 );
  }
  $site = $sites[0];

  switch_to_blog($site->blog_id);

  $posts = get_posts($posts_args);

  foreach($posts as $post_key => $post){
    $posts[$post_key]->blog = array(
      'blog_id' => $site->blog_id,
      'blog_name' => get_bloginfo('name'),
      'blog_url' => $site->path
    );

    $terms = wp_get_post_categories($post->ID);
    if(is_array($terms)){
      $posts[$post_key]->the_term = get_term($terms[0])->slug;
    }

    $posts[$post_key]->thumbnail = get_the_post_thumbnail_url($post->ID);
  }

  restore_current_blog();

  return new WP_REST_Response($posts, 200 );
}


/**
 * Lista de posts de todos los sitios
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response: Lista de posts
 */
 function lnk_get_sites_posts(WP_REST_Request $request){

   $count = $request->get_param("count");
   $agenda = $request->get_param("agenda");
   $dateFormat = $request->get_param("format");

   $sites_args = array(
     'public' => 1 // los posts tb solo publicos?
   );
   $sites = get_sites($sites_args);
   $allPosts = array();
   if(is_array($sites))
   foreach($sites as $site_key => $site){
    switch_to_blog($site->blog_id);

    if($agenda == '1') {
      $posts_args = array(
        'numberposts' => $count,
        'meta_query' => array(
          'relation' => 'AND',
          array(
            'key' => 'lnk_onagenda',
            'compare' => '=',
            'value' => '1'
          ),
          array(
            'key' => 'lnk_agenda',
            'compare' => '>=',
            'value' => date('Y-m-d')
          )                    
        )
      );
    } else {
      $posts_args = array(
        'numberposts' => $count,
        'meta_query' => array(
          'relation' => 'OR',
          array(
            'key' => 'lnk_onhome',
            'compare' => '=',
            'value' => '1'
          )
        )
      );
    }

    $posts = get_posts($posts_args);

     foreach($posts as $post_key => $post){
       $posts[$post_key]->blog = array(
         'blog_id' => $site->blog_id,
         'blog_name' => get_bloginfo('name'),
         'blog_url' => $site->path
       );

       $terms = wp_get_post_categories($post->ID);
       if(is_array($terms)){
         $posts[$post_key]->the_term = get_term($terms[0])->slug;
       }
       $posts[$post_key]->lnk_onagenda = get_post_meta($post->ID,'lnk_onagenda',true);
       $dateAgenda = get_post_meta($post->ID,'lnk_agenda',true);
       $posts[$post_key]->lnk_agenda = date($dateFormat,strtotime($dateAgenda));
       $posts[$post_key]->lnk_agenda_unformatted = $dateAgenda;
       $posts[$post_key]->thumbnail = get_the_post_thumbnail_url($post->ID,'thumbnail');
     }

     $allPosts = array_merge($allPosts,$posts);
     restore_current_blog();
   }

   if($agenda == '1') {
    usort($allPosts,'lnk_compare_by_lnk_agenda');
   } else {
    usort($allPosts,'lnk_compare_by_date');
  }

   $allPosts = array_slice($allPosts,0,$count);
   return new WP_REST_Response($allPosts, 200 );
 }

/**
 * Lista de posts destacados de todos los sitios
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response: Lista de posts
 */
function lnk_get_sites_featured_posts(WP_REST_Request $request){

  $count = $request->get_param("count");

  $sites_args = array(
    'public' => 1 // los posts tb solo publicos?
  );
  $sites = get_sites($sites_args);
  $allPosts = array();
  if(is_array($sites))
  foreach($sites as $site_key => $site){
    switch_to_blog($site->blog_id);

    $posts_args = array(
     'numberposts' => '-1',
     'meta_query' => array(
       array(
        'key' => 'lnk_featured',
        'compare' => '=',
        'value' => '1'
      )
   )
  );
    $posts = get_posts($posts_args);

    foreach($posts as $post_key => $post){
      $posts[$post_key]->blog = array(
        'blog_id' => $site->blog_id,
        'blog_name' => get_bloginfo('name'),
        'blog_url' => $site->path
      );


      $terms = wp_get_post_categories($post->ID);
      if(is_array($terms)){
        $posts[$post_key]->the_term = get_term($terms[0])->slug;
      }

      $posts[$post_key]->thumbnail = get_the_post_thumbnail_url($post->ID);
      $posts[$post_key]->lnk_featured_mode = get_post_meta($post->ID,'lnk_featured_mode',true);
    }

    $allPosts = array_merge($allPosts,$posts);
    restore_current_blog();
  }
  usort($allPosts,'lnk_compare_by_date');
  return new WP_REST_Response($allPosts, 200 );
}


 /**
  * Compara 2 objetos WP_Post para ordenar decrecientemente
  */
 function lnk_compare_by_date($post1, $post2){
   if($post1->post_date == $post2->post_date) {
     return 0;
   } else if ($post1->post_date > $post2->post_date) {
     return -1;
   } else {
     return 1;
   }
 }

 function lnk_compare_by_lnk_agenda($post1, $post2){
  if($post1->lnk_agenda_unformatted == $post2->lnk_agenda_unformatted) {
    return 0;
  } else if ($post1->lnk_agenda_unformatted > $post2->lnk_agenda_unformatted) {
    return 1;
  } else {
    return -1;
  }
}
