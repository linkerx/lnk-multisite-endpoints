<?php

/**
 * Plugin Name: LNK REST API Multisite Endpoints
 * Plugin URI: https://github.com/linkerx/lnk-rest-api-sites-endpoint
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

  // Endpoint: Ultimos Post de Todos Los Sitios
  register_rest_route( $route, '/sites-posts', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'lnk_get_sites_posts',
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
    $info = get_bloginfo('name');
    $sites[$key]->blog_name = $info;
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
  $site->blog_name = get_bloginfo('name');
  $site->blog_description = get_bloginfo('description');
  $site->wpurl = get_bloginfo('wpurl');
  restore_current_blog();

  return new WP_REST_Response($site, 200 );
}

/**
 * Lista de posts de todos los sitios
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response: Lista de posts
 */
 function lnk_get_sites_posts(WP_REST_Request $request){
   $sites_args = array(
     'public' => 1 // los posts tb solo publicos?
   );
   $sites = get_sites($sites_args);
   $allPosts = array();
   if(is_array($sites))
   foreach($sites as $site_key => $site){
     switch_to_blog($site->blog_id);

     $posts_args = array(
        'numberposts' => 12
     );
     $posts = get_posts($posts_args);

     foreach($posts as $post_key => $post){
       $posts[$post_key]->blog = array(
         'blog_id' => $site->blog_id,
         'blog_name' => get_bloginfo('name'),
         'blog_url' => $site->path
       );

       $posts[$post_key]->thumbnail = get_the_post_thumbnail_url($post->ID,'thumbnail');
     }

     $allPosts = array_merge($allPosts,$posts);
     restore_current_blog();
   }
   usort($allPosts,'lnk_compare_by_date');
   $allPosts = array_slice($allPosts,0,12);
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
