<?php
add_action('init', 'viaresp_register_post_type');

function viaresp_register_post_type() {
    register_post_type('simulation', [
        'labels' => [
            'name' => 'Simulations',
            'singular_name' => 'Simulation',
            'add_new' => 'Ajouter une simulation',
            'add_new_item' => 'Ajouter une nouvelle simulation',
            'edit_item' => 'Modifier la simulation',
            'new_item' => 'Nouvelle simulation',
            'view_item' => 'Voir la simulation',
            'search_items' => 'Rechercher une simulation',
            'not_found' => 'Aucune simulation trouvée',
            'not_found_in_trash' => 'Aucune simulation dans la corbeille',
            'menu_name' => 'Simulations'
        ],
        'public' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-analytics',
        'has_archive' => false,
        'supports' => ['title'],
        'capability_type' => 'post',
        'show_in_rest' => true // pour éditeur Gutenberg ou API
    ]);
}
