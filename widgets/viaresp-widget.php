<?php
namespace Elementor;

if (!defined('ABSPATH')) {
    exit; // Empêche l'accès direct
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class ViaResp_Widget extends Widget_Base {
    public function get_name() { return 'viaresp_widget'; }
    public function get_title() { return 'ViaResp Widget'; }
    public function get_icon() { return 'eicon-search'; }
    public function get_categories() { return ['basic']; }

    protected function _register_controls() {
        $this->start_controls_section('content_section', [
            'label' => __('Contenu', 'viaresp'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);
        $this->end_controls_section();
    }

    public function render() {
        echo '<div class="tf-search-wrap style1">';
        echo '    <div class="search-properties-form">';
        echo '        <div class="tf-search-status-tab">';
        echo '            <a href="/" data-value="for-rent" class="btn-status-filter active">Tester nos simulateurs</a>';
        echo '            <a href="/" data-value="for-sale" class="btn-status-filter">Lancer mon bilan fiscal</a>';
        echo '        </div>';
        echo '    </div>';
        echo '</div>';
    }
}
