(function ($) {
  let iti;
  var $rowPart  = $('#row-part');
  var $rowTmi  = $('#row-tmi');
  var $tmiOui   = $('#tmi-oui');
  var $tmiNon   = $('#tmi-non');

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
              if (d1 && d1.versements_mensuel && d1.prenom) html += createDeclarantCard('Déclarant 1', d1, tmi);
              if (d2 && d2.versements_mensuel && d2.prenom) html += createDeclarantCard('Déclarant 2', d2, tmi);
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
      const $tmiField    = jQuery('#tmi');
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
            const d1      = response.data.declarant1;
            const d2      = response.data.declarant2;
            const message = response.data.message;
            const tmi     = response.data.tmi;
            showPERPopupAfterResult();
            let html = '';
            if (d1 && d1.versements_mensuel && d1.prenom) html += createDeclarantCard('Déclarant 1', d1, tmi, message);
            if (d2 && d2.versements_mensuel && d2.prenom) html += createDeclarantCard('Déclarant 2', d2, tmi, message);
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
    $('.tooltip-wrapper').each(function() {
      const $wrapper = $(this);
      const $tooltip = $wrapper.find('.tooltip');

      // Échap pour fermer
      $wrapper.on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
          if ($tooltip.length) {
            $tooltip.css({ opacity: 0, pointerEvents: 'none', transform: 'translate(-50%, 8px)' });
          }
        }
      });

      // Toggle au clic (utile sur mobile)
      $wrapper.on('click', function(e) {
        if (!$tooltip.length) return;
        const isVisible = parseFloat($tooltip.css('opacity')) === 1;
        if (isVisible) {
          $tooltip.css({ opacity: 0, pointerEvents: 'none', transform: 'translate(-50%, 8px)' });
        } else {
          $tooltip.css({ opacity: 1, pointerEvents: 'auto', transform: 'translate(-50%, 4px)' });
        }
        e.stopPropagation();
      });

      // Clic en dehors pour fermer
      $(document).on('click', function(e) {
        if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
          if ($tooltip.length) {
            $tooltip.css({ opacity: 0, pointerEvents: 'none', transform: 'translate(-50%, 8px)' });
          }
        }
      });
    });
    initInternationalPhone();
    handlePERForm();
    handlePERFormSansAvis();
    animateSimulateurButton();
  });
  function formatEuro(val) {
    return val ? (Math.round(val).toLocaleString('fr-FR') + ' €') : '-';
  }
  function showPERPopupAfterResult() {
    setTimeout(function() {
      $('#popup-simulateur-per').fadeIn();
    }, 50000); // 8 secondes
  }
  function createDeclarantCard(label, d, tmi, message) {
    return `
      <div class="card">
        <div class="heading">
          <div>
            <div class="badge">PER - ${label}</div>
            <h1 class="name">${d.prenom ? d.prenom.toUpperCase() + ' ' + d.nom.toUpperCase() : ''}</h1>
            <div class="sub">TMI ${Math.round((tmi ?? d.tmi)*100)}% · Retraite à 64 ans</div>
          </div>
          <div>
            <a class="btn show-calendly" href="#">Prendre un rendez-vous</a>
          </div>
        </div>
        <div class="grid two-col" style="margin-top:1.5rem;">
          <div class="stat"><h4>Épargne mensuelle</h4><div class="value">${formatEuro(d.versements_mensuel)}</div><div class="small">Versée chaque mois</div></div>
          <div class="stat"><h4>Épargne annuelle</h4><div class="value">${formatEuro(d.versements_annuel)}</div><div class="small">Total par an</div></div>
          <div class="stat"><h4>Épargne réalisée</h4><div class="value">${formatEuro(d.versements_cumules)}</div><div class="small">Sur toute la période</div></div>
          <div class="stat"><h4>Plus-value</h4><div class="value">${formatEuro(d.plus_value)}</div><div class="small">Gain estimé grâce aux intérêts composés</div></div>
          <div class="stat"><h4>Capital à la retraite</h4><div class="value">${formatEuro(d.capital_final)}</div><div class="small">Capital estimé avec la plus value</div></div>
          <div class="stat"><h4>Plafond non utilisé pour le PER</h4><div class="value">${formatEuro(d.plafonds?.non_utilise)}</div><div class="small">Disponible pour cette année</div></div>
          <div class="stat"><h4>Plafond actuel pour le PER</h4><div class="value">${formatEuro(d.plafonds?.actuel)}</div><div class="small">Disponible pour cette année</div></div>
          <div class="stat"><h4>Économie d’impôt totale</h4><div class="value">${formatEuro(d.economie?.totale)}</div><div class="small">Sur toute la période</div></div>
          <div class="stat"><h4>Nombre d’années de déduction</h4><div class="value">${d.economie?.nb_annees ?? '-'}</div><div class="small">Années restantes</div></div>
          <div class="stat"><h4>Taux marginal d’imposition (TMI)</h4><div class="value">${Math.round((tmi ?? d.tmi)*100)}%</div><div class="small">Votre tranche</div></div>
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
        <div class="footer-line">
          ${message}
        </div>
      </div>
    `;
  }

})(jQuery);
