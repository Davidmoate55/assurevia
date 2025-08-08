<?php
namespace Elementor;

if (!defined('ABSPATH')) {
    exit; // Empêche l'accès direct
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class ViaResp_Simulator_Widget extends Widget_Base {
    public function get_name() { return 'assurevia_simulator_per'; }
    public function get_title() { return 'Assurevia Simulateur PER'; }
    public function get_icon() { return 'eicon-timer'; }
    public function get_categories() { return ['basic']; }

    protected function _register_controls() {
        $this->start_controls_section('content_section', [
            'label' => __('Paramètres du simulateur', 'Assurevia'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control(
            'taux_technique',
            [
                'label' => __('Taux technique (%)', 'viaresp'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 100, // Valeur maximale
                'step' => 0.25, // Incrémentation de 0.50
                'default' => 1.75, // Valeur par défaut
            ]
        );

        $this->end_controls_section();
    }

    public function render() {
        $settings = $this->get_settings_for_display();
        ?>
          <section class="section-simulateur">
            <div class="elementor-element elementor-element-92758ec elementor-widget elementor-widget-tftabs" data-id="92758ec" data-element_type="widget" data-widget_type="tftabs.default">
              <div class="elementor-widget-container">
                <div id="tf-tabs" class="tf-tabs" data-tabid="92758ec">
                  <div class="tf-tabnav">
                    <ul>
                      <li class="tablinks active" data-tab="tab-1">
                        <span class="tab-title-text">Simulateur PER avec avis d'imposition</span>
                      </li>
                      <li class="tablinks inactive" data-tab="tab-2">
                        <span class="tab-title-text">Simulateur PER sans avis d'imposition</span>
                      </li>
                    </ul>
                  </div>
                  <div class="tf-tabcontent">
                    <!-- Formulaire avec avis d'imposition -->
                    <div id="tab-1" class="tf-tabcontent-inner animated fadeIn active">
                      <div class="row align-items-start">
                        <!-- Colonne formulaire -->
                        <div class="col-xl-8 col-12">
                          <form id="form-avec-avis" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="simulateur_perin_avec_avis">
                            <div class="form-input">
                              <div class="row">
                              <div class="col-xl-6 col-lg-6 col-12">
                                <div class="form-group">
                                  <label for="fileAvis" class="label-with-tooltip">Insérer votre avis d’imposition PDF *</label>
                                  <input type="file" id="fileAvis" name="avis_imposition" accept=".pdf" required>
                                </div>
                              </div>
                              <div class="col-xl-6 col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="profil" class="label-with-tooltip">
                                      Votre profil d'épargne
                                      <span class="tooltip-wrapper" tabindex="0" aria-label="Informations sur les profils d'épargne">
                                        <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                          <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                          <text x="12.5" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                        </svg>
                                        <div class="tooltip" role="tooltip">
                                          <div class="tooltip-arrow" data-popper-arrow></div>
                                          <div class="tooltip-content">
                                            <strong>Valeurs indicatives :</strong> Le profil prudent concerne les indépendants soucieux de la pérennité de leur épargne, le profil équilibré correspond à un équilibre entre performance financière et épargne sécurisée, le profil dynamique s’adresse à des épargnants désireux d’obtenir des performances élevées.
                                          </div>
                                        </div>
                                      </span>
                                    </label>
                                    <div class="profil-slider" role="radiogroup" aria-label="Profil d'épargne">
                                      <input type="radio" name="profil" id="profil-prudent" value="3" checked>
                                      <label for="profil-prudent" class="option">Prudent</label>
                                      <input type="radio" name="profil" id="profil-equilibre" value="6">
                                      <label for="profil-equilibre" class="option">Équilibré</label>
                                      <input type="radio" name="profil" id="profil-dynamique" value="9">
                                      <label for="profil-dynamique" class="option">Dynamique</label>
                                    <div class="slider-track">
                                      <div class="slider-thumb"></div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                              <div class="row">
                              <div class="col-xl-6 col-lg-6 col-12">
                                <div class="form-group">
                                  <label for="age1">Votre âge (déclarant 1)</label>
                                  <span class="tooltip-wrapper" tabindex="0" aria-label="Âge du déclarant 1">
                                    <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                      <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                      <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                    </svg>
                                    <div class="tooltip" role="tooltip">
                                      <div class="tooltip-arrow" data-popper-arrow></div>
                                      <div class="tooltip-content">
                                        <strong>Déclarant 1&nbsp;:</strong> Correspond au premier nom inscrit sur votre avis d’imposition. Renseignez ici son âge actuel.
                                      </div>
                                    </div>
                                  </span>
                                  <input type="number" id="age1" name="age1">
                                </div>
                              </div>
                              <div class="col-xl-6 col-lg-6 col-12">
                                <div class="form-group">
                                  <label for="versement1">Votre épargne mensuel pour le PER</label>
                                  <span class="tooltip-wrapper" tabindex="0" aria-label="Épargne mensuelle PER déclarant 1">
                                    <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                      <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                      <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                    </svg>
                                    <div class="tooltip" role="tooltip">
                                      <div class="tooltip-arrow" data-popper-arrow></div>
                                      <div class="tooltip-content">
                                        <strong>Épargne mensuelle – Déclarant 1 :</strong> Saisissez le montant que le premier déclarant souhaite investir chaque mois sur son PER.
                                      </div>
                                    </div>
                                  </span>
                                  <input type="number" id="versement1" name="versement1">
                                </div>
                              </div>
                            </div>
                              <div class="row">
                              <div class="col-xl-6 col-lg-6 col-12">
                                <div class="form-group">
                                  <label for="age2">Votre âge (déclarant 2)</label>
                                  <span class="tooltip-wrapper" tabindex="0" aria-label="Âge du déclarant 2">
                                    <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                      <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                      <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                    </svg>
                                    <div class="tooltip" role="tooltip">
                                      <div class="tooltip-arrow" data-popper-arrow></div>
                                      <div class="tooltip-content">
                                        <strong>Déclarant 2&nbsp;:</strong> Correspond au second nom indiqué sur votre avis d’imposition. Indiquez son âge actuel.
                                      </div>
                                    </div>
                                  </span>
                                  <input type="number" id="age2" name="age2">
                                </div>
                              </div>
                              <div class="col-xl-6 col-lg-6 col-12">
                                <div class="form-group">
                                  <label for="versement2">Votre épargne mensuel pour le PER
                                  </label>
                                  <span class="tooltip-wrapper" tabindex="0" aria-label="Épargne mensuelle PER déclarant 2">
                                    <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                      <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                      <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                    </svg>
                                    <div class="tooltip" role="tooltip">
                                      <div class="tooltip-arrow" data-popper-arrow></div>
                                      <div class="tooltip-content">
                                        <strong>Épargne mensuelle – Déclarant 2 :</strong> Saisissez le montant que le second déclarant souhaite investir chaque mois sur son PER.
                                      </div>
                                    </div>
                                  </span>
                                  <input type="number" id="versement2" name="versement2">
                                </div>
                              </div>
                            </div>
                            </div>
                            <div class="message-rgpd">
                                <strong>* Conformité RGPD: </strong>Votre avis d’imposition sert uniquement à la simulation, n’est jamais stocké, et reste totalement confidentiel.
                            </div>
                            <div class="form-group bouton-simulateur">
                              <button id="btn_simul_avis" type="submit">Lancer la simulation</button>
                            </div>
                          </form>
                        </div>

                        <!-- Colonne image -->
                        <div class="col-xl-4 col-12 summary-col">
                          <!--img class="image-simulateur-per" src="/wp-content/plugins/viaresp-elementor/assets/img/PER simulateur.png" alt="Illustration simulation PER" style="width: 100%; height: auto;"-->
                          <div class="summary-panel simulateur-background">
                            <div class="summary-badge">
                              <div class="checkmark">✔</div>
                              <div class="summary-title">Simulation PER</div>
                            </div>
                            <div class="summary-main">
                              <div class="summary-box">
                                <div class="label-large">Calculez votre potentiel d'epargne retraite et l’impact sur votre impôt</div>
                                <!--div class="amount">88 644 €</div-->
                              </div>
                              <ul class="summary-list">
                                <li>Départ à la retraite prévu à <strong>64 ans</strong></li>
                                <li>Plafonds de versement encore disponibles</li>
                                <li>Épargne totale estimée à la retraite</li>
                                <li>Plus-value des intérêts composés</li>
                                <li>Économie d’impôt estimée</li>
                                <li>Recommandations personnalisées</li>
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>
                      </div>
                    <!-- Formulaire sans avis d'imposition -->
                    <div id="tab-2" class="tf-tabcontent-inner animated fadeIn inactive">
                      <div class="row align-items-start">
                        <!-- Colonne formulaire -->
                        <div class="col-xl-8 col-12">
                          <form id="form-sans-avis" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="simulateur_perin_sans_avis">
                            <div class="form-input">
                              <!-- Ligne 0 -->
                              <div class="row">
                                <div class="col-xl-12 col-lg-12 col-12">
                                  <div class="form-group">
                                    <label for="salaires">La simulation PER est pour</label>
                                    <div class="nb-personne-slider" role="radiogroup" aria-label="Nombre de personnes">
                                      <input type="radio" name="nb_personne" id="nbp-1" value="1" checked>
                                      <label for="nbp-1" class="nb-personne-option">Pour moi <div class="d-none d-md-inline">(simulation individuelle)</div></label>

                                      <input type="radio" name="nb_personne" id="nbp-2" value="2">
                                      <label for="nbp-2" class="nb-personne-option">Nous deux <div class="d-none d-md-inline">(couple marié ou pacsé)</div></label>

                                      <div class="nb-personne-track">
                                        <div class="nb-personne-thumb"></div>
                                      </div>
                                    </div>

                                  </div>
                                </div>
                              </div>
                              <!-- Ligne 1 -->
                              <div class="row">
                                <div class="col-xl-12 col-lg-12 col-12">
                                  <div class="form-group">
                                    <label for="profil" class="label-with-tooltip">
                                      Votre profil d'épargne
                                      <span class="tooltip-wrapper" tabindex="0" aria-label="Informations sur les profils d'épargne">
                                        <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                          <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                          <text x="12.5" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                        </svg>
                                        <div class="tooltip" role="tooltip">
                                          <div class="tooltip-arrow" data-popper-arrow></div>
                                          <div class="tooltip-content">
                                            <strong>Valeurs indicatives :</strong> Le profil prudent concerne les indépendants soucieux de la pérennité de leur épargne, le profil équilibré correspond à un équilibre entre performance financière et épargne sécurisée, le profil dynamique s’adresse à des épargnants désireux d’obtenir des performances élevées.
                                          </div>
                                        </div>
                                      </span>
                                    </label>

                                    <div class="profil-slider" role="radiogroup" aria-label="Profil d'épargne">
                                      <input type="radio" name="profil2" id="profil2-prudent" value="3" checked>
                                      <label for="profil2-prudent" class="option">Prudent</label>

                                      <input type="radio" name="profil2" id="profil2-equilibre" value="6">
                                      <label for="profil2-equilibre" class="option">Équilibré</label>

                                      <input type="radio" name="profil2" id="profil2-dynamique" value="9">
                                      <label for="profil2-dynamique" class="option">Dynamique</label>

                                      <div class="slider-track">
                                        <div class="slider-thumb"></div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <!-- Ligne 2 -->
                              <div class="row">
                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="tmi" class="label-with-tooltip">
                                      Connaissez vous votre TMI ?
                                      <span class="tooltip-wrapper" tabindex="0" aria-label="Connaissez vous votre TMI ?">
                                        <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                          <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                          <text x="12.5" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                        </svg>
                                        <div class="tooltip" role="tooltip">
                                          <div class="tooltip-arrow" data-popper-arrow></div>
                                          <div class="tooltip-content">
                                            <strong>Valeurs indicatives :</strong> Le profil prudent concerne les indépendants soucieux de la pérennité de leur épargne, le profil équilibré correspond à un équilibre entre performance financière et épargne sécurisée, le profil dynamique s’adresse à des épargnants désireux d’obtenir des performances élevées.
                                          </div>
                                        </div>
                                      </span>
                                    </label>
                                    <div class="tmi-slider" role="radiogroup" aria-label="TMI">
                                      <input type="radio" name="tmi" id="tmi-non"  value="non" checked>
                                      <label for="tmi-non"  class="tmi-option">Non</label>

                                      <input type="radio" name="tmi" id="tmi-oui" value="oui">
                                      <label for="tmi-oui" class="tmi-option">Oui</label>

                                      <div class="tmi-track">
                                        <div class="tmi-thumb"></div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-12" id="row-part">
                                  <div class="form-group">
                                    <label for="part">Nombre de parts fiscales</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Nombre de part">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Nombre de part</strong> Correspond ...
                                        </div>
                                      </div>
                                    </span>
                                    <input type="text" id="part" name="part">
                                  </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-12" id="row-tmi" style="display: none;">
                                  <div class="form-group">
                                    <label for="tmi">Votre taux marginal d'imposition (TMI)</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Votre taux marginal d'imposition (TMI)">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Votre taux marginal d'imposition</strong> Correspond ...
                                        </div>
                                      </div>
                                    </span>
                                    <input type="text" id="tmi" name="tmi">
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div class="form-input mt-4">
                              <label id="label-pour-moi" style="display:none;"><strong>Pour moi</strong></label>
                              <!-- Ligne 3 -->
                              <div class="row">
                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="salaires">Salaires nets imposables N-1</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Salaires nets imposables N-1">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Salaires nets imposables N-1</strong>
                                        </div>
                                      </div>
                                    </span>
                                    <input type="number" id="salaires" name="salaires">
                                  </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="revenu_activite">Revenu d’activité (Entrepreneur)</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Revenu d’activité (Entrepreneur)">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Revenus d’activité (Entrepreneur)</strong>
                                        </div>
                                      </div>
                                    </span>
                                    <input type="number" id="revenu_activite" name="revenu_activite">
                                  </div>
                                </div>
                              </div>
                              <!-- Ligne 4 -->
                              <div class="row">
                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="age">Votre âge</label>
                                    <input type="number" id="age" name="age">
                                  </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="versement">Votre épargne mensuelle pour le PER</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Épargne mensuelle PER">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Épargne mensuelle:</strong> Saisissez le montant que vous souhaitez investir chaque mois dans votre PER.
                                        </div>
                                      </div>
                                    </span>
                                    <input type="number" id="versement" name="versement">
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div class="form-input mt-4" id="bloc-declarant-2" style="display:none;">
                              <label><strong>Deuxième déclarant</strong></label>
                              <!-- Ligne 5 -->
                              <div class="row">
                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="salaires2">Salaires nets imposables N-1</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Salaires nets imposables N-1">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Salaires nets imposables N-1</strong>
                                        </div>
                                      </div>
                                    </span>
                                    <input type="number" id="salaires2" name="salaires2">
                                  </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="revenu_activite2">Revenu d’activité (Entrepreneur)</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Revenu d’activité (Entrepreneur)">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Revenus d’activité (Entrepreneur)</strong>
                                        </div>
                                      </div>
                                    </span>
                                    <input type="number" id="revenu_activite2" name="revenu_activite2">
                                  </div>
                                </div>
                              </div>
                              <!-- Ligne 6 -->
                              <div class="row">
                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="age_2">Votre âge</label>
                                    <input type="number" id="age_2" name="age_2">
                                  </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-12">
                                  <div class="form-group">
                                    <label for="versement_2">Votre épargne mensuelle pour le PER</label>
                                    <span class="tooltip-wrapper" tabindex="0" aria-label="Épargne mensuelle PER">
                                      <svg class="info-icon" width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <circle cx="12" cy="12" r="11" fill="#4fa79b" />
                                        <text x="12" y="16.5" text-anchor="middle" font-size="18" font-weight="700" fill="#fff" dy="0.1em" font-family="system-ui, sans-serif">i</text>
                                      </svg>
                                      <div class="tooltip" role="tooltip">
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                        <div class="tooltip-content">
                                          <strong>Épargne mensuelle:</strong> Saisissez le montant que vous souhaitez investir chaque mois dans votre PER.
                                        </div>
                                      </div>
                                    </span>
                                    <input type="number" id="versement_2" name="versement_2">
                                  </div>
                                </div>
                              </div>
                            </div>
                            <!-- /form-input -->
                            <div class="message-rgpd">
                              <strong>Les résultats fournis par ce simulateur</strong> sont des estimations à titre indicatif. Pour une évaluation précise et complète de vos économies d'impôts, veuillez vous référer à votre avis d'impôt 2025 sur les revenus de 2024. Investir comporte des risques de perte en capital.
                            </div>

                            <div class="form-group bouton-simulateur">
                              <button id="btn_simul_avis" type="submit">Lancer la simulation</button>
                            </div>
                          </form>
                        </div>

                        <!-- Colonne image -->
                        <div class="col-xl-4 col-12 summary-col">
                          <!--img class="image-simulateur-per" src="/wp-content/plugins/viaresp-elementor/assets/img/PER simulateur.png" alt="Illustration simulation PER" style="width: 100%; height: auto;"-->
                          <div class="summary-panel simulateur-background">
                            <div class="summary-badge">
                              <div class="checkmark">✔</div>
                              <div class="summary-title">Simulation PER</div>
                            </div>
                            <div class="summary-main">
                              <div class="summary-box">
                                <div class="label-large">Calculez votre potentiel d'epargne retraite et l’impact sur votre impôt</div>
                                <!--div class="amount">88 644 €</div-->
                              </div>
                              <ul class="summary-list">
                                <li>Départ à la retraite prévu à <strong>64 ans</strong></li>
                                <li>Plafonds de versement encore disponibles</li>
                                <li>Épargne totale estimée à la retraite</li>
                                <li>Plus-value des intérêts composés</li>
                                <li>Économie d’impôt estimée</li>
                                <li>Recommandations personnalisées</li>
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>
                      </div>
                  </div>
                </div>
                <div class="container container-resultat-simulateur-per">
                  <div id="calendly-inline-widget" style="display:none; min-width:320px; height:700px;"></div>
                  <div id="per-simu-cards" class="cards-wrapper">
                  </div>
                </div>
              </div>
            </div>
          </section>
          <div id="popup-simulateur-per" class="per-popup-overlay" style="display:none;">
            <div class="per-popup-modal">
              <button id="close-popup" class="close-btn" aria-label="Fermer">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                  <rect x="1" y="1" width="22" height="22" rx="4" ry="4" fill="none"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                  <line x1="18" y1="6" x2="6" y2="18"/>
                </svg>
              </button>
              <div class="per-popup-content">
                <div class="per-popup-message">

                  <p><strong>Attention, le PER ne supprime pas l’impôt,</strong><br>
                  il permet de le décaler à la retraite où votre TMI sera souvent plus faible.
                  Chez <b>Assurévia</b>, notre priorité, c’est la clarté, on vous explique tout, avantages et limites.</p>
                </div>
                <div class="per-popup-cta">
                  <p class="cta-title">
                    Profitez d’un rendez-vous offert, sans engagement, pour vous aider à choisir la meilleure stratégie selon votre profil.
                  </p>
                  <a href="#" class="show-calendly-popup btn btn-popup-rdv">Prendre un rendez-vous</a>
                </div>
              </div>
            </div>
          </div>

        <?php
    }
}
