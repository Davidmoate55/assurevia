(function ($) {
  let iti;
  var $rowPart  = $('#row-part');
  var $rowTmi  = $('#row-tmi');
  var $tmiOui   = $('#tmi-oui');
  var $tmiNon   = $('#tmi-non');

  const partsRange = document.getElementById('parts-range');
  const partsOutput = document.getElementById('parts-output');
  const partsInput  = document.getElementById('part');

  function updatePartsFill(el){
    const min = +el.min || 1, max = +el.max || 10, val = +el.value || 1;
    el.style.setProperty('--fill', ((val - min) / (max - min) * 100) + '%');
  }

  if (partsRange) {
    // Init : si pas de value sur le range, force sur 1
    if (!partsRange.value) partsRange.value = partsRange.min || 1;
    updatePartsFill(partsRange);
    if (partsOutput) partsOutput.textContent = partsRange.value;
    if (partsInput)  partsInput.value       = partsRange.value; // <-- sync vers l’input
    partsRange.addEventListener('input', function(){
      updatePartsFill(this);
      if (partsOutput) partsOutput.textContent = this.value;
      if (partsInput)  partsInput.value       = this.value;     // <-- sync en live
    });
  }

  // Elements requis
  const range  = document.getElementById('tmi-range');
  const output = document.getElementById('tmi-output');
  const tmiInput = document.getElementById('tmi_valeur');
  const ticksC = document.querySelector('.tmi-ticks');

  if(!range) return; // sécurité

  // Valeurs autorisées (TMI)
  const TMI = [0, 11, 30, 41, 45];

  // Max du slider (doit être 45 si on reste en TMI pur)
  const max = parseInt(range.max || '45', 10);

  // Render des ticks
  if (ticksC){
    ticksC.innerHTML = '';
    TMI.forEach(v => {
      const pct = (v / max) * 100;
      const tick = document.createElement('div');
      tick.className = 'tick';
      tick.style.left = pct + '%';
      tick.innerHTML = '<div class="dot"></div><div class="label">'+v+'%</div>';
      ticksC.appendChild(tick);
    });
  }

  // Mise à jour remplissage (WebKit via --fill ; Firefox gère ::-moz-range-progress)
  function updateFill(el){
    const min = +el.min || 0, maxv = +el.max || 100, val = +el.value || 0;
    const pct = ((val - min) / (maxv - min)) * 100;
    el.style.setProperty('--fill', pct + '%');
  }

  // Trouver la valeur autorisée la plus proche
  function snap(val){
    return TMI.reduce((best, v) =>
      Math.abs(v - val) < Math.abs(best - val) ? v : best
    , TMI[0]);
  }

  // Mise à jour affichage texte
  function updateOutput(val){
    if (output) output.textContent = val + '%';
    if (tmiInput)  tmiInput.value       = val;
  }


  // Init : snap la valeur de départ, remplit la piste, maj texte
  (function init(){
    const start = snap(parseInt(range.value || '0', 10));
    range.value = start;
    updateFill(range);
    updateOutput(start);
  })();

  // Pendant le drag : remplissage fluide + texte fluide
  range.addEventListener('input', function(){
    updateFill(this);
    updateOutput(parseInt(this.value || '0', 10));
  });

  // En fin de drag : snap sur {0,11,30,41,45} + sync fill/texte
  ['change','mouseup','touchend','keyup','blur'].forEach(evt => {
    range.addEventListener(evt, function(){
      const snapped = snap(parseInt(this.value || '0', 10));
      this.value = snapped;
      updateFill(this);
      updateOutput(snapped);
    });
  });

  function showError($field, message) {
    const $formGroup = $field.closest('.form-group');
    if ($formGroup.find('.error-message').length === 0) {
      $formGroup.append('<div class="error-message">' + message + '</div>');
    }
    $formGroup.addClass('has-error');
    $field.addClass('input-error');
  }

  function majAffichagePart() {
    if ($tmiOui.is(':checked')) {
      $rowPart.hide();
      $rowTmi.show();
    } else {
      $rowPart.show();
      $rowTmi.hide();
    }
  }
  function toggleDeclarantUI() {
    const val = $('input[name="nb_personne"]:checked').val();
    if (val === '2') {
      $('#bloc-declarant-2, #label-pour-moi').stop(true, true).fadeIn(200);
    } else {
      $('#bloc-declarant-2, #label-pour-moi').stop(true, true).fadeOut(200);
    }
  }

  // Au chargement (pour l'état initial) :
  toggleDeclarantUI();

  // À chaque changement de radio :
  $(document).on('change', 'input[name="nb_personne"]', toggleDeclarantUI);
  // Au chargement initial
  majAffichagePart();

  // Au clic / changement sur chacune des radios
  $tmiOui.on('change', majAffichagePart);
  $tmiNon.on('change', majAffichagePart);

  const initInternationalPhone = () => {
    const input = document.querySelector("#telephone");
    if (input && typeof window.intlTelInput !== 'undefined') {
      iti = window.intlTelInput(input, {
        initialCountry: "fr",
        separateDialCode: false,
        nationalMode: false,
        autoPlaceholder: "polite",
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js"
      });
    }
  };

  const handlePERForm = () => {
    $('#form-avec-avis').on('submit', function (e) {
      e.preventDefault();

      // Supprimer anciens messages d'erreur et classes
      $('.form-group').removeClass('has-error');
      $('.error-message').remove();
      $('#calendly-inline-widget').hide();

      let valid = true;

      // Champs
      const $age1 = $('#age1');
      const $versement1 = $('#versement1');
      const $age2 = $('#age2');
      const $versement2 = $('#versement2');
      const $fileAvis = $('#fileAvis');
      const $form = $(this);

      // Récupération valeurs
      const age1 = $age1.val() !== "" ? parseInt($age1.val(), 10) : null;
      const versement1 = $versement1.val() !== "" ? parseFloat($versement1.val()) : null;
      const age2 = $age2.val() !== "" ? parseInt($age2.val(), 10) : null;
      const versement2 = $versement2.val() !== "" ? parseFloat($versement2.val()) : null;

      let declarant1OK = false;
      let declarant2OK = false;

      // Déclarant 1 complet ?
      if ($age1.val() || $versement1.val()) {
        if (!$age1.val()) {
          showError($age1, "L'âge du déclarant 1 est obligatoire si un montant est saisi.");
          valid = false;
        } else if (age1 < 18) {
          showError($age1, "Le déclarant 1 doit être majeur (au moins 18 ans).");
          valid = false;
        } else if (age1 > 64) {
          showError($age1, "Le déclarant 1 a dépassé l'âge de la retraite (max 64 ans).");
          valid = false;
        }

        if (!$versement1.val()) {
          showError($versement1, "Le montant d'épargne mensuelle est obligatoire si un âge est saisi.");
          valid = false;
        } else if (versement1 < 50) {
          showError($versement1, "Le versement mensuel doit être au moins de 50 €.");
          valid = false;
        }

        // Les deux sont remplis et valides
        if (
            $age1.val() && $versement1.val() &&
            age1 >= 18 && age1 <= 64 &&
            versement1 >= 50
          ) {
            declarant1OK = true;
          }
        }

        // Déclarant 2 complet ?
        if ($age2.val() || $versement2.val()) {
          if (!$age2.val()) {
            showError($age2, "L'âge du déclarant 2 est obligatoire si un montant est saisi.");
            valid = false;
          } else if (age2 < 18) {
            showError($age2, "Le déclarant 2 doit être majeur (au moins 18 ans).");
            valid = false;
          } else if (age2 > 64) {
            showError($age2, "Le déclarant 2 a dépassé l'âge de la retraite (max 64 ans).");
            valid = false;
          }

          if (!$versement2.val()) {
            showError($versement2, "Le montant d'épargne mensuelle est obligatoire si un âge est saisi.");
            valid = false;
          } else if (versement2 < 50) {
            showError($versement2, "Le versement mensuel doit être au moins de 50 €.");
            valid = false;
          }

          // Les deux sont remplis et valides
          if (
            $age2.val() && $versement2.val() &&
            age2 >= 18 && age2 <= 64 &&
            versement2 >= 50
          ) {
            declarant2OK = true;
          }
        }

        // Il faut au moins un déclarant complet
        if (!declarant1OK && !declarant2OK) {
          // Affiche une erreur générale ou sous le champ du premier déclarant
          showError($age1, "Veuillez renseigner au moins un déclarant complet (âge et versement).");
          valid = false;
        }
      // Stoppe si non valide
      if (!valid) return;

      // --- Appel AJAX comme avant ---
      const formData = new FormData(this);
      var $btn = $('#btn_simul_avis');
      var originalText = $btn.text();
      $('#per-simu-cards').html('');
      // Loader "3 points" animé
      $btn
        .addClass('btn-loader')
        .prop('disabled', true)
        .html('<span class="dot-loader"><span></span><span></span><span></span></span> <span>Chargement de la simulation</span>');

      $.ajax({
        url: '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function () {
          console.log('Envoi en cours...');
        },
        success: function (response) {
          if (response.success) {
            const d1 = response.data.declarant1;
            const d2 = response.data.declarant2;
            const tmi = response.data.tmi;
            const message             = response.data.message;
            const is_avis_imposition = response.data.is_avis_imposition;
            const dernier_avis_imposition = response.data.dernier_avis_imposition;

            if (!is_avis_imposition) {
              showError($fileAvis, "Ce n'est pas un avis d'imposition.");
              $('html, body').animate({
                scrollTop: $('#form-avec-avis').offset().top - 100
              }, 400);
            }else{
              showPERPopupAfterResult();
              let html = '';
              if (d1 && d1.versements_mensuel && d1.prenom) html += createDeclarantCard('Déclarant 1', d1, tmi, message);
              if (d2 && d2.versements_mensuel && d2.prenom) html += createDeclarantCard('Déclarant 2', d2, tmi, message);
              $('#per-simu-cards').html(html);

              // Scroll automatique sur la div résultat
              $('html, body').animate({
                scrollTop: $('.container-resultat-simulateur-per').offset().top - 100
              }, 400);
            }
          } else {
            $('#per-simu-cards').html('<div class="alert alert-warning">Erreur : Données non disponibles.</div>');
          }
        },
        error: function (xhr, status, error) {
          console.error('Erreur AJAX :', error);
        },
        complete: function () {
          // Restaure le bouton à la fin de la requête (succès ou échec)
          $btn
            .removeClass('btn-loader')
            .prop('disabled', false)
            .text(originalText);
        }
      });
    });
  };

  const handlePERFormSansAvis = () => {
    $('#form-sans-avis').on('submit', function(e){
      e.preventDefault();
      // réinitialise erreurs
      $('.form-group').removeClass('has-error');
      $('.error-message').remove();

      let valid = true;
      const $form        = jQuery(this);


      // D1
      const $salaires    = jQuery('#salaires');
      const $revAct      = jQuery('#revenu_activite');
      const $age         = jQuery('#age');
      const $versement   = jQuery('#versement');

      // TMI/parts
      const $tmiOui      = jQuery('#tmi-oui');
      const $tmiField    = jQuery('#tmi_valeur');
      const $partField   = jQuery('#part');

      // D2
      const $salaires2   = jQuery('#salaires2');
      const $revAct2     = jQuery('#revenu_activite2');
      const $age2        = jQuery('#age_2');
      const $versement2  = jQuery('#versement_2');

      // valeurs
      const salaires   = parseFloat($salaires.val()) || 0;
      const revAct     = parseFloat($revAct.val())   || 0;

      const age        = $age.val()       ? parseInt($age.val(), 10)        : null;
      const versement  = $versement.val() ? parseFloat($versement.val())    : null;

      const knowsTMI   = $tmiOui.is(':checked');
      const tmi        = $tmiField.val()  ? parseFloat($tmiField.val())     : null;
      const parts      = ($partField.val() || '').trim();

      // D2 values
      const isCouple   = jQuery('input[name="nb_personne"]:checked').val() === '2';
      const salaires2  = parseFloat($salaires2.val()) || 0;
      const revAct2Val = parseFloat($revAct2.val())   || 0;
      const age2Val    = $age2.val()       ? parseInt($age2.val(), 10)     : null;
      const vers2Val   = $versement2.val() ? parseFloat($versement2.val()) : null;

      // ---------------- RÈGLES "POUR MOI" (toujours appliquées à D1) ----------------

      // 1) Au moins un revenu (salaires OU revAct)
      if (salaires <= 0 && revAct <= 0) {
        showError($salaires, "Renseignez au moins un des deux : salaires ou revenu d’activité.");
        showError($revAct,  "Renseignez au moins un des deux : salaires ou revenu d’activité.");
        valid = false;
      }

      // 2) Âge D1 obligatoire + bornes
      if (!$age.val()) {
        showError($age, "Votre âge est obligatoire.");
        valid = false;
      } else if (age < 18) {
        showError($age, "Vous devez être majeur (≥ 18 ans).");
        valid = false;
      } else if (age > 64) {
        showError($age, "Âge maximal pour la retraite : 64 ans.");
        valid = false;
      }

      // 3) Versement D1 obligatoire + seuil
      if (!$versement.val()) {
        showError($versement, "Votre versement mensuel est obligatoire.");
        valid = false;
      } else if (versement < 50) {
        showError($versement, "Le versement doit être ≥ 50 €.");
        valid = false;
      }

      // 4) TMI vs parts
      if (knowsTMI) {
        if (!$tmiField.val()) {
          showError($tmiField, "Veuillez saisir votre TMI.");
          valid = false;
        }
      } else {
        if (!parts) {
          showError($partField, "Le nombre de parts est obligatoire si vous ne connaissez pas votre TMI.");
          valid = false;
        }
      }

      // ---------------- RÈGLES "NOUS DEUX" (appliquées en plus à D2) ----------------
      if (isCouple) {
        // Âge D2 obligatoire + bornes
        if (!$age2.val()) {
          showError($age2, "L’âge du deuxième déclarant est obligatoire.");
          valid = false;
        } else if (age2Val < 18) {
          showError($age2, "Le deuxième déclarant doit être majeur (≥ 18 ans).");
          valid = false;
        } else if (age2Val > 64) {
          showError($age2, "Âge maximal : 64 ans pour le deuxième déclarant.");
          valid = false;
        }

        // Versement D2 obligatoire + seuil
        if (!$versement2.val()) {
          showError($versement2, "Le versement mensuel du deuxième déclarant est obligatoire.");
          valid = false;
        } else if (vers2Val < 50) {
          showError($versement2, "Le versement du deuxième déclarant doit être ≥ 50 €.");
          valid = false;
        }

        // D2 : au moins un des deux salaires2/revAct2 (> 0)
        if (salaires2 <= 0 && revAct2Val <= 0) {
          showError($salaires2, "Renseignez au moins un des deux : salaires ou revenu d’activité (2).");
          showError($revAct2,   "Renseignez au moins un des deux : salaires ou revenu d’activité (2).");
          valid = false;
        }
      }

      if (!valid) return;
      // loader bouton (idem handlePERForm)
      const $btn = $form.find('button[type=submit]');
      const origText = $btn.text();
      $btn
        .addClass('btn-loader')
        .prop('disabled', true)
        .html('<span class="dot-loader"><span></span><span></span><span></span></span> <span>Chargement de la simulation</span>');

      // envoi AJAX
      const formData = new FormData(this);
      $.ajax({
        url: '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success(response){
          if (response.success) {
            const d1                  = response.data.declarant1;
            const d2                  = response.data.declarant2;
            const message             = response.data.message;
            const tmi                 = response.data.tmi;
            const plafondsDetails     = response.data.plafondsDetails;
            showPERPopupAfterResult();
            let html = '';
            if (d1 && d1.versements_mensuel && d1.prenom) html += createDeclarantCard('Déclarant 1', d1, tmi, message, plafondsDetails.declarant1);
            if (d2 && d2.versements_mensuel && d2.prenom) html += createDeclarantCard('Déclarant 2', d2, tmi, message, plafondsDetails.declarant2);
            $('#per-simu-cards').html(html);
            // Scroll automatique sur la div résultat
            $('html, body').animate({
              scrollTop: $('.container-resultat-simulateur-per').offset().top - 100
            }, 400);
          } else {
            $('#per-simu-cards').html('<div class="alert alert-warning">Erreur : Données non disponibles.</div>');
          }
        },
        error(){
          //alert('Erreur réseau, réessayez.');
        },
        complete(){
          $btn
            .removeClass('btn-loader')
            .prop('disabled', false)
            .text(origText);
        }
      });

    });
  };

  const animateSimulateurButton = () => {
    setInterval(function () {
      const $btn = $('#btn_simul_avis');
      $btn.addClass('bounce-horizontal');
      setTimeout(function () {
        $btn.removeClass('bounce-horizontal');
      }, 300);
    }, 3000);
  };

  $(document).ready(function () {
    // Fermeture par le bouton "X"
    $("#close-popup").click(function () {
      $("#popup-simulateur-per").fadeOut(400);
    });
    $(".show-calendly-popup").click(function (e) {
      e.preventDefault();
      $("#popup-simulateur-per").fadeOut(400);
      $('#calendly-inline-widget').show();
      Calendly.initInlineWidget({
        url: 'https://calendly.com/david-moate-assurevia/30min',
        parentElement: document.getElementById('calendly-inline-widget'),
        prefill: {},
        utm: {}
      });
      // Scroll automatique sur la div Calendly
      $('html, body').animate({
        scrollTop: $('#calendly-inline-widget').offset().top - 20
      }, 400);
    });

    // Optionnel : fermeture en cliquant sur le fond gris
    $("#popup-simulateur-per").on('click', function(e) {
      if ($(e.target).is('#popup-simulateur-per')) {
        $(this).fadeOut(400);
      }
    });
    // Fermer la popup (croix ou clic hors popup)
    $(document).on('click', '.per-popup-close, .per-popup-overlay', function(e) {
      if (e.target.classList.contains('per-popup-close') || e.target.classList.contains('per-popup-overlay')) {
        $('#popup-simulateur-per').fadeOut();
      }
    });
    $('.per-popup-modal').on('click', function(e) {
      e.stopPropagation(); // Pour ne pas fermer si on clique dans la modal
    });

    $(document).on('click', '.show-calendly', function(e) {
      e.preventDefault();
      console.log('calendly-inline-widget')
      $('#calendly-inline-widget').show();
      Calendly.initInlineWidget({
        url: 'https://calendly.com/david-moate-assurevia/30min',
        parentElement: document.getElementById('calendly-inline-widget'),
        prefill: {},
        utm: {}
      });
      // Scroll automatique sur la div Calendly
      $('html, body').animate({
        scrollTop: $('#calendly-inline-widget').offset().top - 20
      }, 400);
    });
      // 1) Déplacer la tooltip au niveau de .form-group (pleine largeur, au-dessus)
      $('.tooltip-wrapper').each(function(){
        const $wrap  = $(this);
        const $tip   = $wrap.find('.tooltip');
        const $group = $wrap.closest('.form-group');
        if ($tip.length && $group.length && !$tip.parent().is($group)) {
          $tip.attr('tabindex', '-1');          // ne prend jamais le focus
          $tip.appendTo($group);
        }
      });

      const $allTips = $('.form-group .tooltip');

      function closeAllTips(){ $allTips.removeClass('is-open').attr('aria-hidden','true'); }

      function openTip($tip){
        if (!$tip || !$tip.length) return;
        closeAllTips(); // OK : on ferme les autres à l’ouverture
        $tip.addClass('is-open').attr('aria-hidden','false');
      }

      // 2) Hover icône : ouvre. Quitter l’icône : ferme si aucun input du groupe n’est focus
      $(document).on('mouseenter', '.tooltip-wrapper', function(){
        const $tip = $(this).closest('.form-group').find('.tooltip');
        openTip($tip);
      });
      $(document).on('mouseleave', '.tooltip-wrapper', function(){
        const $group = $(this).closest('.form-group');
        if ($group.find(':input').is(':focus')) return; // si un champ est focus, on laisse ouvert
        $group.find('.tooltip').removeClass('is-open').attr('aria-hidden','true'); // ferme seulement celle du groupe
      });

      // 3) Focus gestion — utiliser focusin/focusout (bubblent) pour FIABILISER les <input type="text">
      $(document).on('focusin', '.form-group :input', function(){
        const $tip = $(this).closest('.form-group').find('.tooltip');
        openTip($tip);
      });

      // 3) Focus gestion — fermer uniquement la tooltip du groupe qui perd le focus
      $(document).on('focusout', '.form-group :input', function(){
        const $group = $(this).closest('.form-group');
        // très court délai pour laisser le temps au nouvel input de prendre le focus
        setTimeout(function(){
          const active = document.activeElement;
          const focusIsInsideSameGroup = $group.has(active).length > 0;
          const hoverIcon = $group.find('.tooltip-wrapper:hover').length > 0;

          if (!focusIsInsideSameGroup && !hoverIcon) {
            // ⬇️ on ferme SEULEMENT la tooltip de CE groupe
            $group.find('.tooltip').removeClass('is-open').attr('aria-hidden','true');
          }
        }, 0); // délai minimal pour éviter la course avec focusin
      });

      $(document).on('pointerdown', function(e){
        // On ne ferme que si on clique VRAIMENT en dehors du formulaire
        if ($(e.target).closest('.form-group').length === 0) {
          closeAllTips();
        }
      });

      // 5) ESC pour fermer
      $(document).on('keydown', function(e){
        if (e.key === 'Escape' || e.keyCode === 27) closeAllTips();
      });

    // Textes courts par profil (valeurs 3, 6, 9)
      const PROFIL_TEXT = {
        3: "<strong>Profil prudent</strong> capital sécurisé rendement limité adapté si horizon court ou aversion au risque",
        6: "<strong>Profil équilibré</strong> actions et obligations pour équilibre performance et sécurité horizon 5 à 8 ans",
        9: "<strong>Profil dynamique</strong> forte part actions rendement élevé long terme horizon 10 ans et plus"
      };

      const $tip = $('#profil-tooltip-content')
      const $tip2 = $('#profil2-tooltip-content');

      const $radiosProfil1 = $('input[name="profil"]');
      const $radiosProfil2 = $('input[name="profil2"]');

      function majTooltipProfil(){
        const val = $radiosProfil1.filter(':checked').val();
        if ($tip.length && val && PROFIL_TEXT[val]) {
          $tip.html(PROFIL_TEXT[val]);
        }
        const val2 = $radiosProfil2.filter(':checked').val();
        if ($tip2.length && val2 && PROFIL_TEXT[val2]) {
          $tip2.html(PROFIL_TEXT[val2]);
        }
      }

      // Initialisation + mise à jour au changement
      majTooltipProfil();
      $radiosProfil1.on('change', majTooltipProfil);

      // Initialisation + mise à jour au changement
      majTooltipProfil();
      $radiosProfil2.on('change', majTooltipProfil);

    initInternationalPhone();
    handlePERForm();
    handlePERFormSansAvis();
    animateSimulateurButton();
  });
  function formatEuro(val) {
    return val ? (Math.round(val).toLocaleString('fr-FR') + ' €') : '0€';
  }
  function showPERPopupAfterResult() {
    setTimeout(function() {
      $('#popup-simulateur-per').fadeIn();
    }, 50000); // 8 secondes
  }
  function createDeclarantCard(label, d, tmi, message, plafondsDetails) {
    // Récup des détails du bon déclarant
    const key = /2/.test(label) ? 'declarant2' : 'declarant1';
    const pd  = plafondsDetails;

    // Helpers sûrs
    const euro = (n) => (typeof n === 'number' ? n : 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' €';
    const sum  = (obj) => Object.values(obj || {}).reduce((a,b)=>a+(+b||0),0);

    // suppose euro(n) et sum(obj) existent déjà

    const chipsReportable = (() => {
      const mapObj  = pd?.plafond_non_utilise_restant_par_annee || {};
      const entries = Object.entries(mapObj);

      // (optionnel) trier par année croissante
      const sorted = entries.sort((a, b) => parseInt(a[0], 10) - parseInt(b[0], 10));

      // Puces historiques (N-4, N-3, N-2, ...)
      const chipsHistorique = sorted.map(([an, val]) => `
        <div class="chip ${(+val > 0 ? 'chip-on' : 'chip-off')}">
          <span class="chip-badge">${an}</span>
          <span class="chip-val">${euro(+val || 0)}</span>
        </div>
      `).join('');

      // Dernière année + 1 (fallback: année courante si aucune entrée)
      const years    = sorted.map(([an]) => parseInt(an, 10)).filter(n => !Number.isNaN(n));
      const nextYear = years.length ? Math.max(...years) + 1 : (new Date().getFullYear());

      // Puce "plafond_actuel_restant"
      const plafondActuel = +pd?.plafond_actuel_restant || 0;
      const chipActuel = `
        <div class="chip-last chip ${(plafondActuel > 0 ? 'chip-on' : 'chip-off')}">
          <span class="chip-badge">${nextYear}</span>
          <span class="chip-val">${euro(plafondActuel)}</span>
        </div>
      `;

      return chipsHistorique + chipActuel;
    })();

    // MAJ primeUniquePossible
    const primeUniquePossible = euro(
      (sum(pd?.plafond_non_utilise_restant || {})) + (+pd?.plafond_actuel_restant || 0)
    );


    // Transferts (si présents)
    const transfertRecu  = pd.transferts?.recu ?? pd.transfert_total_recu ?? 0;
    const transfertDonne = pd.transferts?.donne ?? 0;
    const transfertRows = (transfertRecu>0 || transfertDonne>0) ? `
    <div class="panel">
      <div class="panel-title">Transferts de plafond</div>
      <div class="transfer-row">
        <div class="transfer-item">
          <div class="transfer-dot in"></div>
          <div class="transfer-content">
            <div class="transfer-label">Transfert reçu</div>
            <div class="transfer-val">${euro(transfertRecu)}</div>
          </div>
        </div>
        <div class="transfer-item">
          <div class="transfer-dot out"></div>
          <div class="transfer-content">
            <div class="transfer-label">Transfert donné</div>
            <div class="transfer-val">${euro(transfertDonne)}</div>
          </div>
        </div>
        <div class="panel-note">Le transfert de plafond permet à un déclarant d’utiliser le plafond non consommé de l’autre au sein du foyer fiscal et réciproquement.</div>
      </div>
    </div>
    ` : '';

    // Projection N+1 (plafonds reportables la prochaine année)
    const chipsNext = (pd.plafond_non_utilise_prochaine_annee?.par_annee
      ? Object.entries(pd.plafond_non_utilise_prochaine_annee.par_annee)
          .map(([an, val]) => `
            <div class="chip chip-next">
              <span class="chip-badge">${parseInt(an) + 1}</span>
              <span class="chip-val">${euro(+val)}</span>
            </div>
          `).join('')
      : ''
    );

    // Carte
    return `
      <div class="card">
        <div class="heading">
          <div>
            <div class="badge">PER - ${label}</div>
            <h1 class="name">${d.prenom ? d.prenom + ' ' + d.nom : ''}</h1>
            <div class="sub">TMI ${Math.round((tmi ?? d.tmi)*100)}% · Retraite à 64 ans</div>
          </div>
          <div><a class="btn show-calendly" href="#">Prendre un rendez-vous</a></div>
        </div>

        <!-- Stats existantes -->
        <div class="grid two-col" style="margin-top:1.5rem;">
          <div class="stat"><h4>Épargne mensuelle</h4><div class="value">${formatEuro(d.versements_mensuel)}</div><div class="small">Versée chaque mois</div></div>
          <div class="stat"><h4>Épargne annuelle</h4><div class="value">${formatEuro(d.versements_annuel)}</div><div class="small">Total par an</div></div>
          <div class="stat"><h4>Épargne réalisée</h4><div class="value">${formatEuro(d.versements_cumules)}</div><div class="small">Sur toute la période</div></div>
          <div class="stat"><h4>Plus-value</h4><div class="value">${formatEuro(d.plus_value)}</div><div class="small">Gain estimé grâce aux intérêts composés</div></div>
          <div class="stat"><h4>Capital à la retraite</h4><div class="value">${formatEuro(d.capital_final)}</div><div class="small">Capital estimé avec la plus value</div></div>
          <div class="stat"><h4>Plafond non utilisé pour le PER</h4><div class="value">${formatEuro(d.plafonds?.non_utilise)}</div><div class="small">Disponible pour cette année</div></div>
          <div class="stat"><h4>Plafond actuel pour le PER</h4><div class="value">${formatEuro(d.plafonds?.actuel)}</div><div class="small">Disponible pour cette année</div></div>
          ${(d.plafonds?.transfere_recu ?? 0) > 0 ? `<div class="stat"><h4>Plafond transféré reçu</h4><div class="value">${formatEuro(d.plafonds.transfere_recu)}</div><div class="small">Utilisé depuis l’autre déclarant</div></div>` : ''}
          <div class="stat"><h4>Économie d’impôt cette année</h4><div class="value">${formatEuro(d.economie_annee1)}</div><div class="small">Sous réserve d’impôt à payer</div></div>
          <div class="stat"><h4>Nombre d’années de déduction</h4><div class="value">${d.economie?.nb_annees}</div><div class="small">Années restantes</div></div>
          <div class="stat"><h4>Taux marginal d’imposition (TMI)</h4><div class="value">${Math.round((tmi ?? d.tmi)*100)}%</div><div class="small">Votre tranche</div></div>
        </div>

        <!-- NOUVEAU : Plafonds & transferts -->
        <div class="panel-group">
          <div class="panel">
            <div class="panel-title">Plafonds reportables restants</div>
            <div class="chips">${chipsReportable}</div>
            <div class="panel-note">Plafonds des 3 années précédentes + plafond de cette année.</div>
          </div>

          <div class="panel">
            <div class="panel-title">Versement possible cette année</div>
            <div class="big-number">${primeUniquePossible}</div>
            <div class="panel-note">C'est le montant que vous pouvez encore verser sur votre PER cette année, déductible sous réserve d’avoir encore un impôt à payer.</div>
          </div>

          ${transfertRows}

          ${chipsNext ? `
            <div class="panel">
              <div class="panel-title">Projection de vos plafonds reportables pour l’année prochaine</div>
              <div class="chips">${chipsNext}</div>
              <div class="panel-note">C'est une estimation si vous n’utilisez pas le reliquat cette année.</div>
            </div>
          ` : ''}
        </div>

        <div class="advice">
          <div class="advice-icon">✔</div>
          <div>
            <div style="font-weight:600;">Astuce :</div>
            <div>${d.astuce || '-'}</div>
          </div>
        </div>

        <div class="grid" style="margin-top:2rem;">
          <div class="stat" style="flex:1;">
            <h4>Conseil personnalisé</h4>
            <p style="margin:6px 0 0; line-height:1.3;">${d.conseil_personnalise || '-'}</p>
          </div>
        </div>

        <div class="footer-line">${message || ''}</div>
      </div>
    `;
  }



})(jQuery);
