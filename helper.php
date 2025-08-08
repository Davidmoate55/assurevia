<?php
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;

function extraireInfosFiscalesImages(string $text): array {
    $tmi = null;
    $plafond_non_utilise_declarant1 = 0;
    $plafond_non_utilise_declarant2 = 0;
    $plafond_calcule_declarant1 = '';
    $plafond_calcule_declarant2 = '';

    // Extraire le TMI
    if (preg_match('/Taux marginal.*?(\d{1,2}),\d{2}/', $text, $match)) {
        $tmi = (int) $match[1];
    }

    // Plafond non utilisé pour les revenus de ...
    preg_match_all('/Plafond non utilisé pour les revenus de \d{4}[^0-9]*(\d{4,5})(?:[^0-9]+(\d{4,5}))?/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        if (isset($match[1])) {
            $plafond_non_utilise_declarant1 += (int) $match[1];
        }
        if (isset($match[2])) {
            $plafond_non_utilise_declarant2 += (int) $match[2];
        }
    }

    // Plafond calculé sur les revenus de ...
    if (preg_match('/Plafond calculé sur les revenus de \d{4}[^0-9]*(\d{4,5})(?:[^0-9]+(\d{4,5}))?/', $text, $match)) {
        $plafond_calcule_declarant1 = $match[1];
        $plafond_calcule_declarant2 = $match[2] ?? '';
    }

    return [
        'tmi' => ($tmi/100) ?? 0,
        'plafond_non_utilise_declarant1'    => strval($plafond_non_utilise_declarant1),
        'plafond_non_utilise_declarant2'     => $plafond_non_utilise_declarant2 > 0 ? strval($plafond_non_utilise_declarant2) : '',
        'plafond_revenus_declarant1' => $plafond_calcule_declarant1,
        'plafond_revenus_declarant2' => $plafond_calcule_declarant2
    ];
}

function calculEconomiesImpotsPluriannuelles($tmi,$montantAnnuelDeclarant1,$montantAnnuelDeclarant2,$plafond_non_utilise1,$plafond_non_utilise2,$plafond_revenus_declarant1,$plafond_revenus_declarant2,$age1,$age2,$age_retraite = 64) {
    // Cast et validation des paramètres
    $tmi                         = floatval($tmi);
    $montantAnnuelDeclarant1     = floatval($montantAnnuelDeclarant1);
    $montantAnnuelDeclarant2     = floatval($montantAnnuelDeclarant2);
    $plafond_non_utilise1        = floatval($plafond_non_utilise1);
    $plafond_non_utilise2        = floatval($plafond_non_utilise2);
    $plafond_revenus_declarant1  = floatval($plafond_revenus_declarant1);
    $plafond_revenus_declarant2  = floatval($plafond_revenus_declarant2);
    $age1                        = intval($age1);
    $age2                        = intval($age2);
    $age_retraite                = intval($age_retraite);

    // Si le TMI est invalide, on retourne à zéro
    if ($tmi <= 0) {
        return [
            'declarant1' => ['economie_totale' => 0, 'nb_annees' => 0],
            'declarant2' => ['economie_totale' => 0, 'nb_annees' => 0],
        ];
    }

    // Nombre d'années restantes avant retraite
    $nb_annees1 = max(0, $age_retraite - $age1);
    $nb_annees2 = max(0, $age_retraite - $age2);

    $economie_declarant1 = 0;
    $economie_declarant2 = 0;

    // ----- DÉCLARANT 1 -----
    if ($nb_annees1 > 0 && $montantAnnuelDeclarant1 > 0) {
        // 1ère année : plafond_revenus + plafond_non_utilise
        $plafond1_annee1 = $plafond_revenus_declarant1 + $plafond_non_utilise1;
        $deductible1 = min($montantAnnuelDeclarant1, $plafond1_annee1);
        $economie_declarant1 += $deductible1 * $tmi;

        // années suivantes : on utilise chaque année le seul plafond_non_utilise
        for ($an = 2; $an <= $nb_annees1; $an++) {
            $deductible = min($montantAnnuelDeclarant1, $plafond_non_utilise1);
            $economie_declarant1 += $deductible * $tmi;
        }
    }

    // ----- DÉCLARANT 2 -----
    if ($nb_annees2 > 0 && $montantAnnuelDeclarant2 > 0) {
        // 1ère année : plafond_revenus + plafond_non_utilise
        $plafond2_annee1 = $plafond_revenus_declarant2 + $plafond_non_utilise2;
        $deductible2 = min($montantAnnuelDeclarant2, $plafond2_annee1);
        $economie_declarant2 += $deductible2 * $tmi;

        // années suivantes : on utilise chaque année le seul plafond_non_utilise
        for ($an = 2; $an <= $nb_annees2; $an++) {
            $deductible = min($montantAnnuelDeclarant2, $plafond_non_utilise2);
            $economie_declarant2 += $deductible * $tmi;
        }
    }

    return [
        'declarant1' => [
            'economie_totale' => round($economie_declarant1, 2),
            'nb_annees'       => $nb_annees1
        ],
        'declarant2' => [
            'economie_totale' => round($economie_declarant2, 2),
            'nb_annees'       => $nb_annees2
        ]
    ];
}

function pdfToImages(string $pdfPath): array {
    $uploadDir = wp_upload_dir();
    $tmpDir    = trailingslashit($uploadDir['basedir']) . 'pdf_to_png_simulateur';

    if (!file_exists($tmpDir)) {
        wp_mkdir_p($tmpDir);
    }

    $uuid = wp_generate_uuid4(); // UUID unique pour cet appel

    $imagick = new Imagick();
    $imagick->setResolution(300, 300);
    $imagick->readImage($pdfPath); // obligatoire avant la plupart des autres opérations
    $imagick->setImageFormat('png');
    $imagick->setImageCompressionQuality(100);

    // Écriture des images
    $pattern = trailingslashit($tmpDir) . "pdfpage_%d_{$uuid}.png";
    $ok = $imagick->writeImages($pattern, true);

    if (!$ok) {
        error_log("Erreur writeImages pour $pdfPath");
        return [];
    }

    // On ne récupère que les fichiers de cet appel (via UUID)
    $globPattern = trailingslashit($tmpDir) . "pdfpage_*_{$uuid}.png";
    $files = glob($globPattern);

    if (empty($files)) {
        error_log("Aucun PNG généré (UUID $uuid) dans $tmpDir");
    } else {
        error_log("PNG générés (UUID $uuid) : " . implode(', ', $files));
    }

    $imagick->clear();
    $imagick->destroy();

    return $files;
}

function googleVisionOcr(string $filePath, string $jsonKeyPath): string {
     if (!file_exists($filePath) || !file_exists($jsonKeyPath)) {
         error_log("Fichier image ou clé JSON introuvable.");
         return '';
     }

     try {
         $client = new ImageAnnotatorClient([
             'credentials' => json_decode(file_get_contents($jsonKeyPath), true),
         ]);
         $imageData = file_get_contents($filePath);
         $image = (new Image())->setContent($imageData);
         $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

         $request = (new AnnotateImageRequest())
             ->setImage($image)
             ->setFeatures([$feature]);

         $batchRequest = (new BatchAnnotateImagesRequest())
             ->setRequests([$request]);
         $response = $client->batchAnnotateImages($batchRequest);

         $client->close();

         $outputLines = [];
         $yTolerance = 10;

         foreach ($response->getResponses() as $res) {
             if ($res->hasError()) {
                 error_log("Erreur API Vision : " . $res->getError()->getMessage());
                 continue;
             }

             foreach ($res->getFullTextAnnotation()->getPages() as $page) {
                 $lines = []; // Rassemble tous les mots de la page en lignes

                 foreach ($page->getBlocks() as $block) {
                     foreach ($block->getParagraphs() as $para) {
                         foreach ($para->getWords() as $word) {
                             $vertices = $word->getBoundingBox()->getVertices();
                             $x = $vertices[0]->getX();
                             $y = $vertices[0]->getY();

                             $text = '';
                             foreach ($word->getSymbols() as $symbol) {
                                 $text .= $symbol->getText();
                             }

                             // Regroupe par ligne en fonction de Y
                             $found = false;
                             foreach ($lines as &$line) {
                                 if (abs($line['y'] - $y) <= $yTolerance) {
                                     $line['words'][] = ['x' => $x, 'text' => $text];
                                     $found = true;
                                     break;
                                 }
                             }

                             if (!$found) {
                                 $lines[] = [
                                     'y' => $y,
                                     'words' => [['x' => $x, 'text' => $text]]
                                 ];
                             }
                         }
                     }
                 }

                 // Trie et reconstruit les lignes de texte
                 usort($lines, fn($a, $b) => $a['y'] <=> $b['y']);

                 foreach ($lines as $line) {
                     usort($line['words'], fn($a, $b) => $a['x'] <=> $b['x']);
                     $lineText = implode(' ', array_column($line['words'], 'text'));
                     $outputLines[] = $lineText;
                 }
             }
         }

         // Ou concaténer en un seul bloc de texte si nécessaire
         return trim(implode("\n", $outputLines));

     } catch (Exception $e) {
         error_log("Exception Vision API : " . $e->getMessage());
         return '';
     }
 }

function ocrFromImagesViaGoogleVision(array $imageFiles, string $jsonKeyPath): string {
    $fullText = '';
    foreach ($imageFiles as $img) {
        $text = googleVisionOcr($img, $jsonKeyPath);
        if (!empty($text)) {
            $fullText .= "\n" . $text;
        }

        if (file_exists($img)) {
            @unlink($img); // Suppression de l’image temporaire
        }
    }

    return trim($fullText);
}

function query_chatgpt_text($prompt) {
    $api_key = CLE_CHAT_GPT;
    $url = 'https://api.openai.com/v1/chat/completions';

    $headers = [
        'Content-Type: application/json',
        'Authorization: ' . 'Bearer ' . $api_key,
    ];

    $body = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.4,
        'max_tokens' => 3500,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    $decoded = json_decode($response, true);

    if (isset($decoded['error'])) {
        return ['success' => false, 'error' => $decoded['error']];
    }
    $dataAvisImposition = $decoded['choices'][0]['message']['content'];
    $cleanJson = trim($dataAvisImposition);
    $cleanJson = preg_replace('/^```json\s*/', '', $cleanJson); // supprime le début ```json
    $cleanJson = preg_replace('/```$/', '', $cleanJson);        // supprime la fin ```
    $cleanJson = preg_replace('/,(\s*[}\]])/', '$1', $cleanJson);
    // Conversion en tableau PHP
    $responseChatGpt = json_decode($cleanJson, true);

    // Vérification et affichage
    if (json_last_error() === JSON_ERROR_NONE) {
      //var_dump($responseChatGpt);
    } else {
     echo "Erreur JSON : " . json_last_error_msg();
    }
    return $responseChatGpt;
}

function extraireInfosFiscalesPdf($texte) {
    $res = [
        'annee_N' => null,
        'plafond_non_utilise_declarant1' => null,
        'plafond_non_utilise_declarant2' => null,
        'plafond_revenus_declarant1' => null,
        'plafond_revenus_declarant2' => null
    ];

    // Nettoyage du texte
    $texte = str_replace('Déclar.', "\nDéclar.", $texte);
    $lines = preg_split('/\r\n|\r|\n/', $texte);

    // Récupérer l'année N
    foreach ($lines as $line) {
        if (preg_match('/cotisations versées en (\d{4})/i', $line, $m)) {
            $res['annee_N'] = (int)$m[1];
            break;
        }
    }

    // Découper les blocs par déclarant
    $d1bloc = [];
    $d2bloc = [];
    $current = null;
    foreach ($lines as $line) {
        if (preg_match('/Déclar\.\s*1\b/', $line)) { $current = 1; continue; }
        if (preg_match('/Déclar\.\s*2\b/', $line)) { $current = 2; continue; }
        if ($current === 1) {
            if (preg_match('/Enfant|Déclar\./', $line)) $current = null;
            else $d1bloc[] = $line;
        } elseif ($current === 2) {
            if (preg_match('/Enfant|Déclar\./', $line)) $current = null;
            else $d2bloc[] = $line;
        }
    }

    // Extraction des plafonds
    foreach ([1, 2] as $who) {
        $bloc = ${"d{$who}bloc"};
        $blocstr = implode(' ', $bloc);

        // Plafond non utilisé (total =)
        if (preg_match('/=\s*(\d{4,5})/', $blocstr, $m)) {
            $res["plafond_non_utilise_declarant$who"] = (int)$m[1];
        }

        // Plafond actuel (calculé sur revenus de N)
        if (preg_match('/Plafond calculé sur les revenus de \d{4}\s*(\d{4,5})/', $blocstr, $m)) {
            $res["plafond_revenus_declarant$who"] = (int)$m[1];
        }

        // Fallback si pas trouvé : on prend le dernier "+" (souvent le plafond actuel)
        if (!$res["plafond_revenus_declarant$who"]) {
            if (preg_match_all('/\+\s*(\d{4,5})/', $blocstr, $plus) && !empty($plus[1])) {
                $res["plafond_revenus_declarant$who"] = (int)end($plus[1]);
            }
        }

        // Soustraction : on calcule la différence si les deux sont présents
        if ($res["plafond_non_utilise_declarant$who"] !== null && $res["plafond_revenus_declarant$who"] !== null) {
            $res["plafond_non_utilise_declarant$who"] -= $res["plafond_revenus_declarant$who"];
        }
    }

    return $res;
}

function calculInteretsComposes($prime_mensuelle, $taux_annuel, $age_debut, $age_retraite = 64) {
  // Convertir en float/int pour gérer les entrées chaînes numériques
  $prime_mensuelle = floatval($prime_mensuelle);
  $taux_annuel = floatval($taux_annuel);
  $age_debut = intval($age_debut);
  $age_retraite = intval($age_retraite);

  // Vérification des paramètres
  if (
      $prime_mensuelle <= 0 ||
      $taux_annuel <= 0 ||
      $age_debut < 0 ||
      $age_debut >= $age_retraite ||
      $age_retraite <= 0
  ) {
      return [
          'versements_cumules' => 0,
          'capital_final' => 0,
          'plus_value' => 0
      ];
  }

  $nb_annees = $age_retraite - $age_debut;
  $nb_mois = $nb_annees * 12;
  $taux_mensuel = $taux_annuel / 100 / 12;

  $capital_final = $prime_mensuelle * (pow(1 + $taux_mensuel, $nb_mois) - 1) / $taux_mensuel;
  $versements = $prime_mensuelle * $nb_mois;
  $plus_value = $capital_final - $versements;

  return [
      'versements_cumules' => round($versements),
      'capital_final' => round($capital_final),
      'plus_value' => round($plus_value)
  ];
}

function getPrompt($age1, $age2,  $text, $data, $versementMensuel1, $versementMensuel2){
  $plafond_non_utilise1                     = $data['plafonds']["plafond_non_utilise_declarant1"];
  $plafond_non_utilise2                     = $data['plafonds']["plafond_non_utilise_declarant2"];
  $plafond_revenus_declarant1               = $data['plafonds']["plafond_revenus_declarant1"];
  $plafond_revenus_declarant2               = $data['plafonds']["plafond_revenus_declarant2"];
  $epargneCumule1                           = $data['declarant1']['versements_cumules'];
  $capitalFinal1                            = $data['declarant1']['capital_final'];
  $plusValue1                               = $data['declarant1']['plus_value'];
  $epargneCumule2                           = $data['declarant1']['versements_cumules'];
  $capitalFinal2                            = $data['declarant1']['capital_final'];
  $plusValue2                               = $data['declarant1']['plus_value'];
  $declarant1 = "";
  $declarant2 = "";
  $declarationRule = "";
  if (empty($age1) && !empty($age2)) {
      // seul le déclarant 2 est renseigné
      $declarationRule = "Seul le déclarant 2 doit être mentionné dans le tableau ; ne remplir aucune variable pour le déclarant 1 les valeur doit rester vide ";
  } elseif (!empty($age1) && empty($age2)) {
      // seul le déclarant 1 est renseigné
      $declarationRule = "Seul le déclarant 1 doit être mentionné dans le tableau ; ne remplir aucune variable pour le déclarant 2 les valeur doit rester vide.";
  } elseif (!empty($age1) && !empty($age2)) {
      // les deux sont renseignés
      $declarationRule = "Les deux déclarants doivent être mentionnés dans le tableau sauf et **Très important si tu ne vois pas mentionné de déclarant 2 dans l'extrait de l'avis d'imposition alors il faudra mettre la variable prenom_declarant2 à vide.**";
  } else {
      // aucun des deux n’est renseigné
      $declarationRule = "Aucun déclarant n'est renseigné ; impossible de remplir le tableau.";
  }
  if(!empty($age1) && !empty($versementMensuel1)){
    $declarant1 = "Le déclarant 1 à $age1 ans et propose d'épargner jusqu'à la retraite {$epargneCumule1}€ avec une mensualité de {$versementMensuel1}€ pour une plus value de {$plusValue1}€ soit un total pour sa sortie de retraite de {$capitalFinal1}€.
    il a un plafond non utilisé de {$plafond_non_utilise1}€ des 3 dernières années et un plafond pour cette année de {$plafond_revenus_declarant1}€.";
  }
  if(!empty($age2) && !empty($versementMensuel2)){
    $declarant2 = "Le déclarant 2 à $age2 ans et propose d'épargner jusqu'à la retraite {$epargneCumule2}€ avec une mensualité de {$versementMensuel2}€ pour une plus value de {$plusValue2}€ soit un total pour sa sortie de retraite de {$capitalFinal2}€
    il a un plafond non utilisé de {$plafond_non_utilise2}€ des 3 dernières années et un plafond pour cette année de {$plafond_revenus_declarant2}€.";
  }
  $prompt = "Tu es un expert en fiscalité française. Voici un texte brut extrait d’un avis d’imposition.
  Je souhaite que tu me retournes uniquement un objet JSON **brut**,
  sans aucune balise de code (pas de ```json ou ```), et **aucun texte** hors JSON.
  **Ne te sers que des variables fournies** ci-dessous et du texte brut qui suit.
  **Tu ne dois inventer aucune information**.
  **Règle de présence des déclarants** :
  {$declarationRule}
  {$declarant1} {$declarant2}
  Le format de réponse doit être un objet JSON avec les **clés suivantes**,
  Le format doit rester **strictement identique à chaque appel** pour permettre un traitement automatique.
  - is_avis_imposition: Est ce que le texte brut extrait du document provient d’un avis d’imposition officiel français, est ce que pour toi c'est un avis d'impot : tu réponds par true ou false.
  - nom_declarant1
  - prenom_declarant1
  - nom_declarant2
  - prenom_declarant2
  - tmi : taux marginal d’imposition (nombre décimal, exemple : 0.3 pour 30%)
  - plafond_par_cotisations_declarant1 : Le **plafond total disponible pour les cotisations versées l’année en cours** (par exemple “16 741 €”) — indique les montants séparément pour chaque déclarant s’il y a lieu
  - plafond_par_cotisations_declarant2 : idem
  - plafond_non_utilise_declarant1 : Le **plafond non utilisé pour les revenus de l’année précédente** (ex. “revenus de 2023” si l’avis est de 2024) — encore une fois, indique séparément par déclarant s’il y a lieu
  - plafond_non_utilise_declarant2 : idem
  - astuce_declarant1
  - astuce_declarant2
  - conseil_personnalise_declarant1
  - conseil_personnalise_declarant2
  Consignes pour chaque champ :
  **Très important l'astuce et le conseil personnalisé par déclarant doivent etre strictement différents.**
  Pour astuce_declarantX :
  Une phrase, 250 caractères max, personnalisée (utilise le prénom), qui propose une action concrète pour optimiser le PER : ajuster les versements (pour annuler l’impôt), prioriser les plafonds non utilisés, ajuster le rythme, etc.
  L’astuce doit être différente du conseil et différente entre les deux déclarants.
  Pour conseil_personnalise_declarantX :
  Une phrase, 250 caractères max, personnalisée, qui donne un conseil concret (priorisation, rythme, plafond, etc.), ton simple, confiant et rassurant.
  Différent de l’astuce et personnalisé pour chaque déclarant. Si TMI ≤ 0.11 et impôt quasi nul, propose une alternative (ex : assurance-vie), sinon focus PER.
  - exemple_astuce :  Mettez en place un versement automatique de XXX € par mois, vous lisserez le risque de marché tout en utilisant progressivement votre plafond courant.
                      Programmez vos virements juste après la paie : vous épargnez avant de dépenser et sécurisez votre rythme.
                      Augmentez automatiquement l'épargne mensuelle de 3 % chaque année pour suivre l’inflation sans y penser.
                      Scindez vos virements en deux dates (début et milieu de mois) pour lisser encore mieux l’effet de marché.
                      Versez votre prime annuelle en janvier : vous profitez toute l’année des intérêts composés.
                      Coupez le versement unique : 60 % maintenant, 40 % en fin d’année pour garder de la trésorerie et lisser le timing.
  - exemple_conseil : Utilisez XX € de prime unique cette année pour absorber d’un coup votre plafond non utilisé et annuler la quasi-totalité de votre impôt.
                      Priorisez d’abord les 17 000 € de plafond non utilisé : c’est la déduction la plus rentable.
                      Répartissez le plafond non utilisé entre les deux déclarants pour maximiser la déduction et garder une imposition équilibrée dans le foyer.
                      Calibrez vos versements pour annuler l’impôt ; au-delà, placez le surplus sur une assurance-vie plus souple.
                      Si votre TMI chute sous 11 % après usage du plafond, basculez le surplus d’épargne vers une assurance-vie pour conserver un cadre fiscal avantageux.
                      Impôt déjà à zéro ? Orientez les nouveaux versements vers un support libre pour rester liquide et diversifié.
      {
        \"nom_declarant1\": \"string\",
        \"prenom_declarant1\": \"string\",
        \"nom_declarant2\": \"string\",
        \"prenom_declarant2\": \"string\",
        \"tmi\": number,
        \"plafond_par_cotisations_declarant1\": \"string\",
        \"plafond_par_cotisations_declarant2\": \"string\",
        \"plafond_non_utilise_declarant1\": \"string\",
        \"plafond_non_utilise_declarant2\": \"string\",
        \"astuce_declarant1\": \"string\",
        \"astuce_declarant2\": \"string\",
        \"conseil_personnalise_declarant1\": \"string\",
        \"conseil_personnalise_declarant2\": \"string\",
        \"is_avis_imposition\": \"booleen\"
      }";

  $prompt .= "\n\nVoici le texte extrait de l’avis d’imposition :\n{$text}";
  return $prompt;
}
