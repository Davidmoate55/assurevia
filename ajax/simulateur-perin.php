<?php
use Smalot\PdfParser\Parser;
add_action('wp_ajax_simulateur_perin_avec_avis', 'handle_simulation_perin_avec_avis');
add_action('wp_ajax_nopriv_simulateur_perin_avec_avis', 'handle_simulation_perin_avec_avis');

add_action('wp_ajax_simulateur_perin_sans_avis', 'handle_simulation_perin_sans_avis');
add_action('wp_ajax_nopriv_simulateur_perin_sans_avis', 'handle_simulation_perin_sans_avis');

function handle_simulation_perin_avec_avis() {
    session_start();
    $apiKey = CLE_CHAT_GPT;
    if (!isset($_FILES['avis_imposition']) || empty($_FILES['avis_imposition']['tmp_name'])) {
        wp_send_json_error(['message' => 'Fichier manquant']);
    }

    $age1               = sanitize_text_field($_POST['age1']);
    $age2               = sanitize_text_field($_POST['age2']);
    $taux_profil        = sanitize_text_field($_POST['profil']);
    $versementMensuel1  = sanitize_text_field($_POST['versement1']);
    $versementMensuel2  = sanitize_text_field($_POST['versement2']);

    $pdf_path   = $_FILES['avis_imposition']['tmp_name'];
    try {
        // Utilisation de PDF Parser
        $parser = new Parser();
        $pdf                  = $parser->parseFile($pdf_path);
        $text                 = $pdf->getText();
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Erreur lors de la lecture du PDF : ' . $e->getMessage()]);
    }
    $extractionPlafonds   =  extraireInfosFiscalesPdf($text);
    if (empty($text) || mb_strlen($text, 'UTF-8') <= 800) {
      $jsonKeyPath        = GOOGLE_CREDENTIALS_JSON;
      $imageFiles         = pdfToImages($pdf_path);
      $text               = ocrFromImagesViaGoogleVision( $imageFiles, $jsonKeyPath);
      $extractionPlafonds = extraireInfosFiscalesImages($text);
    }

    if (!isset($_FILES['avis_imposition']) || empty($_FILES['avis_imposition']['tmp_name'])) {
        wp_send_json_error(['message' => 'Fichier manquant']);
    }

    $pdf_name = $_FILES['avis_imposition']['name'];
    $pdf_size = $_FILES['avis_imposition']['size'];


    $session_key = 'perin_simulation_result';

    $interetComposeDeclarant1 = calculInteretsComposes($versementMensuel1, $taux_profil, $age1, 64);
    $interetComposeDeclarant2 = calculInteretsComposes($versementMensuel2, $taux_profil, $age2, 64);

    $data = [];
    $data['declarant1'] = $interetComposeDeclarant1;
    $data['declarant2'] = $interetComposeDeclarant2;
    $data['plafonds']   = $extractionPlafonds;

    $montantAnnuelDeclarant1  = (floatval($versementMensuel1) > 0) ? floatval($versementMensuel1) * 12 : 0;
    $montantAnnuelDeclarant2  = (floatval($versementMensuel2) > 0) ? floatval($versementMensuel2) * 12 : 0;

    $plafond_non_utilise1                     = $data['plafonds']["plafond_non_utilise_declarant1"];
    $plafond_non_utilise2                     = $data['plafonds']["plafond_non_utilise_declarant2"];
    $plafond_revenus_declarant1               = $data['plafonds']["plafond_revenus_declarant1"];
    $plafond_revenus_declarant2               = $data['plafonds']["plafond_revenus_declarant2"];

    // Vérifier la session
    if (
        isset($_SESSION[$session_key]['pdf_name'], $_SESSION[$session_key]['pdf_size'])
        && $_SESSION[$session_key]['pdf_name'] === $pdf_name
        && $_SESSION[$session_key]['pdf_size'] === $pdf_size
    ) {
        $responseChatGpt = $_SESSION[$session_key]['responseChatGpt'];
    }else {
        // Envoi à ChatGPTs
        $prompt = getPrompt($age1, $age2,  $text, $data, $versementMensuel1, $versementMensuel2);
        $responseChatGpt = query_chatgpt_text($prompt);
        $_SESSION[$session_key] = [
          'pdf_name'        => $pdf_name,
          'pdf_size'        => $pdf_size,
          'responseChatGpt' => $responseChatGpt
        ];
    }
    $tmi = $responseChatGpt['tmi'] ?? 0.0;
    if(isset($extractionPlafonds['tmi']) && !empty($extractionPlafonds['tmi'])){
        $tmi = $extractionPlafonds['tmi'];
    }

    $economiesImpotsPluriannuelles = calculEconomiesImpotsPluriannuelles($tmi, $montantAnnuelDeclarant1, $montantAnnuelDeclarant2, $plafond_non_utilise1, $plafond_non_utilise2, $plafond_revenus_declarant1, $plafond_revenus_declarant2, $age1, $age2, $age_retraite = 64);
    $resultatSimulateur = [
        'message'                 =>'Résumé basé sur les données extraites de votre avis d’imposition. L’avantage fiscal est calculé en fonction de vos plafonds et de votre TMI. Investir comporte des risques de perte en capital.',
        'tmi'                     => $responseChatGpt['tmi'] ?? 0.0,
        'is_avis_imposition'      => $responseChatGpt['is_avis_imposition'] ?? '',
        'dernier_avis_imposition' => $responseChatGpt['dernier_avis_imposition'] ?? '',
        'declarant1' => [
            'nom'      => $responseChatGpt['nom_declarant1'] ?? '',
            'prenom'   => $responseChatGpt['prenom_declarant1'] ?? '',
            'versements_mensuel'  => $versementMensuel1 ?? '',
            'versements_annuel'   => $montantAnnuelDeclarant1 ?? '',
            'versements_cumules'  => $interetComposeDeclarant1['versements_cumules'] ?? 0.0,
            'capital_final'       => $interetComposeDeclarant1['capital_final'] ?? 0.0,
            'plus_value'          => $interetComposeDeclarant1['plus_value'] ?? 0.0,
            'plafonds' => [
                'non_utilise' => $plafond_non_utilise1,
                'actuel'       => $plafond_revenus_declarant1,
            ],
            'economie' => [
                'totale'    => $economiesImpotsPluriannuelles['declarant1']['economie_totale'] ?? 0.0,
                'nb_annees' => $economiesImpotsPluriannuelles['declarant1']['nb_annees'] ?? 0,
            ],
            'astuce'  => $responseChatGpt['astuce_declarant1'] ?? '',
            'conseil_personnalise' => $responseChatGpt['conseil_personnalise_declarant1'] ?? '',
        ],
        'declarant2' => [
            'nom'      => $responseChatGpt['nom_declarant2'] ?? '',
            'prenom'   => $responseChatGpt['prenom_declarant2'] ?? '',
            'versements_mensuel'  => $versementMensuel2 ?? '',
            'versements_annuel'   => $montantAnnuelDeclarant2 ?? '',
            'versements_cumules' => $interetComposeDeclarant2['versements_cumules'] ?? 0.0,
            'capital_final'      => $interetComposeDeclarant2['capital_final'] ?? 0.0,
            'plus_value'         => $interetComposeDeclarant2['plus_value'] ?? 0.0,
            'plafonds' => [
                'non_utilise' => $plafond_non_utilise2,
                'actuel'       => $plafond_revenus_declarant2,
            ],
            'economie' => [
                'totale'    => $economiesImpotsPluriannuelles['declarant2']['economie_totale'] ?? 0.0,
                'nb_annees' => $economiesImpotsPluriannuelles['declarant2']['nb_annees'] ?? 0,
            ],
            'astuce'  => $responseChatGpt['astuce_declarant2'] ?? '',
            'conseil_personnalise' => $responseChatGpt['conseil_personnalise_declarant2'] ?? '',
        ]
    ];
    wp_send_json_success($resultatSimulateur);
}

function handle_simulation_perin_sans_avis() {
    // 1) Lecture des champs POST
    $resultat         = [];
    $reponse_tmi      = isset($_POST['tmi']) ? $_POST['tmi'] : 'non';
    $connait_tmi      = ($reponse_tmi === 'oui');
    $tmi              = ($connait_tmi && isset($_POST['tmi_valeur'])) ? ((float)$_POST['tmi_valeur'] / 100.0) : null;
    $nb_personne      = isset($_POST['nb_personne']) ? intval($_POST['nb_personne']) : 1;
    $parts            = (!$connait_tmi && isset($_POST['part'])) ? (int)$_POST['part'] : 1;
    $taux_profil      = sanitize_text_field($_POST['profil2']);

    // Déclarant 1
    $salaire              = isset($_POST['salaires'])        ? floatval($_POST['salaires'])        : 0;
    $revenu_activite      = isset($_POST['revenu_activite']) ? floatval($_POST['revenu_activite']) : 0;
    $age                  = isset($_POST['age'])             ? intval($_POST['age'])               : null;
    $versement            = isset($_POST['versement'])       ? floatval($_POST['versement'])       : null;


    // Déclarant 2 (si couple)
    $salaire2             = isset($_POST['salaires2'])        ? floatval($_POST['salaires2'])        : 0;
    $revenu_activite2     = isset($_POST['revenu_activite2']) ? floatval($_POST['revenu_activite2']) : 0;
    $age2                 = isset($_POST['age_2'])             ? intval($_POST['age_2'])               : null;
    $versement2           = isset($_POST['versement_2'])       ? floatval($_POST['versement_2'])       : null;

    $PASS             = array_map('floatval', load_config(__DIR__ . '/../config.ini', 'PASS'));
    $tmi_section      = load_config(__DIR__ . '/../config.ini', 'TMI');
    $TRANCHES_TMI     = charger_tranches_tmi($tmi_section);
    $anneeCotisation  = (int)date('Y');

    $plafondsDeclarant1 = calcul_plafonds_structures($anneeCotisation, $PASS, $salaire, $revenu_activite, 'declarant1');
    if($nb_personne == 2){
        $plafondsDeclarant2 = calcul_plafonds_structures($anneeCotisation, $PASS, $salaire2, $revenu_activite2, 'declarant2');
    }

    // Contexte pour la fonction
    $contexte = [
        'nb_personne'  => $nb_personne,
        'salaires_1'   => $salaire,
        'revenu_act_1' => $revenu_activite,
        'salaires_2'   => $salaire2,
        'revenu_act_2' => $revenu_activite2,
        'connait_tmi'  => $connait_tmi,
        'tmi_valeur'   => $tmi_valeur,
        'parts'        => $parts,
    ];
    if(!$connait_tmi){
        $tmi = calculer_tmi($contexte, $TRANCHES_TMI);
    }
    $montantAnnuelDeclarant1  = (floatval($versement) > 0) ? floatval($versement) * 12 : 0;
    $montantAnnuelDeclarant2  = (floatval($versement2) > 0) ? floatval($versement2) * 12 : 0;

    $plafond_non_utilise1                     = $plafondsDeclarant1["plafond_non_utilise_declarant1"];
    $plafond_non_utilise2                     = $plafondsDeclarant2["plafond_non_utilise_declarant2"];
    $plafond_revenus_declarant1               = $plafondsDeclarant1["plafond_revenus_declarant1"];
    $plafond_revenus_declarant2               = $plafondsDeclarant2["plafond_revenus_declarant2"];

    $economiesImpotsPluriannuelles  = calculEconomiesImpotsPluriannuelles($tmi,$montantAnnuelDeclarant1,$montantAnnuelDeclarant2,$plafond_non_utilise1,$plafond_non_utilise2,$plafond_revenus_declarant1,$plafond_revenus_declarant2,$age,$age2,$age_retraite = 64);
    $interetComposeDeclarant1       = calculInteretsComposes($versement, $taux_profil, $age, 64);
    $interetComposeDeclarant2       = calculInteretsComposes($versement2, $taux_profil, $age2, 64);
    $data = [];
    $data['declarant1'] = $interetComposeDeclarant1;
    $data['declarant2'] = $interetComposeDeclarant2;
    $data['plafonds']["plafond_non_utilise_declarant1"]   = $plafond_non_utilise1;
    $data['plafonds']["plafond_non_utilise_declarant2"]   = $plafond_non_utilise2;
    $data['plafonds']["plafond_revenus_declarant1"]       = $plafond_revenus_declarant1;
    $data['plafonds']["plafond_revenus_declarant2"]       = $plafond_revenus_declarant2;
    $data['tmi']                                          = $tmi * 100;
    $prompt = getPromptSansAvis($age, $age2, $data, $versement, $versement2);
    $responseChatGpt = query_chatgpt_text($prompt);
    $resultatSimulateur = [
        'message'                 => "Les résultats fournis par ce simulateur sont des estimations à titre indicatif. Pour une évaluation précise et complète de vos économies d'impôts, veuillez vous référer à votre dernier avis d'impôt. Investir comporte des risques de perte en capital.",
        'tmi'                     => $tmi,
        'declarant1' => [
            'nom'      => 'de ma simulation PER',
            'prenom'   => 'Résultat',
            'versements_mensuel'  => $versement ?? '',
            'versements_annuel'   => $montantAnnuelDeclarant1 ?? '',
            'versements_cumules'  => $interetComposeDeclarant1['versements_cumules'] ?? 0.0,
            'capital_final'       => $interetComposeDeclarant1['capital_final'] ?? 0.0,
            'plus_value'          => $interetComposeDeclarant1['plus_value'] ?? 0.0,
            'plafonds' => [
                'non_utilise' => $plafond_non_utilise1,
                'actuel'       => $plafond_revenus_declarant1,
            ],
            'economie' => [
                'totale'    => $economiesImpotsPluriannuelles['declarant1']['economie_totale'] ?? 0.0,
                'nb_annees' => $economiesImpotsPluriannuelles['declarant1']['nb_annees'] ?? 0,
            ],
            'astuce'  => $responseChatGpt['astuce_declarant1'] ?? '',
            'conseil_personnalise' => $responseChatGpt['conseil_personnalise_declarant1'] ?? '',
        ],
        'declarant2' => [
            'nom'      => 'du 2ème déclarant',
            'prenom'   => 'Résultat',
            'versements_mensuel'  => $versement2 ?? '',
            'versements_annuel'   => $montantAnnuelDeclarant2 ?? '',
            'versements_cumules' => $interetComposeDeclarant2['versements_cumules'] ?? 0.0,
            'capital_final'      => $interetComposeDeclarant2['capital_final'] ?? 0.0,
            'plus_value'         => $interetComposeDeclarant2['plus_value'] ?? 0.0,
            'plafonds' => [
                'non_utilise' => $plafond_non_utilise2,
                'actuel'       => $plafond_revenus_declarant2,
            ],
            'economie' => [
                'totale'    => $economiesImpotsPluriannuelles['declarant2']['economie_totale'] ?? 0.0,
                'nb_annees' => $economiesImpotsPluriannuelles['declarant2']['nb_annees'] ?? 0,
            ],
            'astuce'  => $responseChatGpt['astuce_declarant2'] ?? '',
            'conseil_personnalise' => $responseChatGpt['conseil_personnalise_declarant2'] ?? '',
        ]
    ];
    wp_send_json_success($resultatSimulateur);
}
