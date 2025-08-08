<?php
/**
 * Plugin Name: ViaResp - Elementor Custom Widgets
 * Description: Ajoute un élément personnalisé à Elementor.
 * Version: 1.0
 * Author: Votre Nom
 */
 require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
 require_once plugin_dir_path(__FILE__) . 'helper.php';
 require_once plugin_dir_path(__FILE__) . 'ajax/simulateur-perin.php';

 use libphonenumber\PhoneNumberUtil;
 use libphonenumber\PhoneNumberFormat;
 use libphonenumber\NumberParseException;

if (!defined('ABSPATH')) {
    exit; // Empêche l'accès direct
}

// Vérifier si Elementor est activé
function viaresp_elementor_widgets_init() {
    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p>Elementor doit être activé pour utiliser le plugin ViaResp.</p></div>';
        });
        return;
    }

    // Ajouter l'action pour enregistrer les widgets lorsque Elementor est prêt
    add_action('elementor/widgets/widgets_registered', function() {
        require_once plugin_dir_path(__FILE__) . 'widgets/viaresp-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/viaresp-simulator-widget.php';

        // Enregistrer les widgets
        \Elementor\Plugin::instance()->widgets_manager->register( new \Elementor\ViaResp_Widget() );
        \Elementor\Plugin::instance()->widgets_manager->register( new \Elementor\ViaResp_Simulator_Widget() );
    });

    // Ajouter les styles CSS
    add_action('wp_enqueue_scripts', function() {
        wp_enqueue_style('viaresp-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
        $sim_css_version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/simulator.css');
        wp_enqueue_style('viaresp-simulator-style', plugin_dir_url(__FILE__) . 'assets/css/simulator.css', [], $sim_css_version);
        wp_enqueue_style('viaresp-tabs-style', '/wp-content/plugins/themesflat-core/assets/css/tabs/tf-tabs.css');
    });

    // Ajouter les scripts JS (jQuery UI, Touch Punch, CanvasJS, etc.)
    add_action('wp_enqueue_scripts', function() {
        $sim_js_version = time(); // Force le rechargement du fichier JS

        // Charger jQuery
        wp_enqueue_script('jquery');

        // Charger jQuery UI
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-slider');

        // Charger jQuery UI Touch Punch
        wp_enqueue_script('jquery-ui-touch-punch', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js', ['jquery', 'jquery-ui-widget', 'jquery-ui-mouse'], null, true);

        // Charger CanvasJS
        wp_enqueue_script('canvasjs', 'https://canvasjs.com/assets/script/canvasjs.min.js', [], null, true);

        // Calendly Widget
        wp_enqueue_style('calendly-widget-style', 'https://assets.calendly.com/assets/external/widget.css');
        wp_enqueue_script('calendly-widget-script', 'https://assets.calendly.com/assets/external/widget.js', [], null, true);
        wp_enqueue_script('pdf-lib', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js', [], null, true);

        wp_enqueue_script(
          'viaresp-simulator-script',
          plugin_dir_url(__FILE__) . 'assets/js/simulator.js',
          ['jquery'],
          time(),
          true
        );

        wp_enqueue_style(
            'calendly-widget',
            'https://assets.calendly.com/assets/external/widget.css',
            [],
            null
        );

        // Calendly JS
        wp_enqueue_script(
            'calendly-widget',
            'https://assets.calendly.com/assets/external/widget.js',
            [],
            null,
            true
        );

        // Script du simulateur (dépend maintenant de canvasjs aussi)
        wp_enqueue_script('viaresp-simulator-script', 'assets/js/simulator.js?v=2', ['jquery', 'jquery-ui-touch-punch', 'canvasjs'], $sim_js_version, true);
        wp_enqueue_script('viaresp-tabs-script', '/wp-content/plugins/themesflat-core/assets/js/tabs/tf-tabs.js', ['jquery', 'jquery-ui-touch-punch', 'canvasjs'], $sim_js_version, true);
        wp_add_inline_script('viaresp-simulator-script', 'var viaresp_ajax_url = "' . plugin_dir_url(__FILE__) . 'ajax/";', 'before');
        wp_add_inline_script('viaresp-simulator-script', 'var simulator_ajax_url = "' . admin_url('admin-ajax.php') . '";', 'before');

    });
}
function viaresp_enqueue_phone_lib() {
    wp_enqueue_style('intl-tel-input-css', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/css/intlTelInput.min.css');
    wp_enqueue_script('intl-tel-input-js', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/intlTelInput.min.js', [], null, true);
    wp_enqueue_script('intl-tel-utils', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js', [], null, true);
}

add_action('wp_enqueue_scripts', 'viaresp_enqueue_phone_lib');
add_action('plugins_loaded', 'viaresp_elementor_widgets_init');
