<?php
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;


function load_config(string $path, ?string $section = null, bool $processSections = true): array{
    if (!file_exists($path)) {
        throw new Exception("Fichier de configuration introuvable : $path");
    }

    $config = parse_ini_file($path, $processSections);

    if ($config === false) {
        throw new Exception("Erreur lors du chargement de la configuration : $path");
    }

    if ($section !== null) {
        if (!isset($config[$section])) {
            throw new Exception("Section '$section' introuvable dans le fichier : $path");
        }
        return $config[$section];
    }

    return $config;
}

function extraireInfosFiscalesImages(string $text): array {
    $tmi = null;
    $plafond_non_utilise_declarant1 = 0;
    $plafond_non_utilise_declarant2 = 0;
    $plafond_calcule_declarant1 = '';
    $plafond_calcule_declarant2 = '';
    $plafonds_non_utilise_declarant1 = [];
    $plafonds_non_utilise_declarant2 = [];

    // Extraire le TMI
    if (preg_match('/Taux marginal.*?(\d{1,2}),\d{2}/', $text, $match)) {
        $tmi = (int) $match[1];
    }

    // Plafond non utilisé pour les revenus de ...
    preg_match_all('/Plafond non utilisé pour les revenus de \d{4}[^0-9]*(\d{4,5})(?:[^0-9]+(\d{4,5}))?/', $text, $matches, PREG_SET_ORDER);
    $N1 = 4;
    $N2 = 4;
    foreach ($matches as $match) {
        if (isset($match[1])) {
            $key = "N-$N1";
            $plafonds_non_utilise_declarant1[$key] = (int) $match[1];
            $plafond_non_utilise_declarant1 += (int) $match[1];
            $N1--;
        }
        if (isset($match[2])) {
            $key = "N-$N2";
            $plafond_non_utilise_declarant2 += (int) $match[2];
            $plafonds_non_utilise_declarant2[$key] = (int) $match[1];
            $N2--;
        }
    }

    // Plafond calculé sur les revenus de ...
    if (preg_match('/Plafond calculé sur les revenus de \d{4}[^0-9]*(\d{4,5})(?:[^0-9]+(\d{4,5}))?/', $text, $match)) {
        $plafond_calcule_declarant1 = $match[1];
        $plafond_calcule_declarant2 = $match[2] ?? '';
    }
    return [
        'tmi' => ($tmi/100) ?? 0,
        'plafonds_non_utilise_declarant1'     => $plafonds_non_utilise_declarant1,
        'plafonds_non_utilise_declarant2'     => $plafonds_non_utilise_declarant2,
        'plafond_non_utilise_declarant1'      => strval($plafond_non_utilise_declarant1),
        'plafond_non_utilise_declarant2'      => $plafond_non_utilise_declarant2 > 0 ? strval($plafond_non_utilise_declarant2) : '',
        'plafond_revenus_declarant1' => $plafond_calcule_declarant1,
        'plafond_revenus_declarant2' => $plafond_calcule_declarant2
    ];
}

function calculEconomiesImpotsPluriannuelles(
    $tmi,
    $montantAnnuelDeclarant1,
    $montantAnnuelDeclarant2,
    $plafond_non_utilise1,
    $plafond_non_utilise2,
    $plafond_revenus_declarant1,
    $plafond_revenus_declarant2,
    $age1,
    $age2,
    $age_retraite = 64
) {
    // Cast & validation
    $tmi = (float)$tmi;
    $montantAnnuelDeclarant1    = (float)$montantAnnuelDeclarant1;
    $montantAnnuelDeclarant2    = (float)$montantAnnuelDeclarant2;
    $plafond_non_utilise1       = (float)$plafond_non_utilise1;
    $plafond_non_utilise2       = (float)$plafond_non_utilise2;
    $plafond_revenus_declarant1 = (float)$plafond_revenus_declarant1;
    $plafond_revenus_declarant2 = (float)$plafond_revenus_declarant2;
    $age1 = (int)$age1; $age2 = (int)$age2; $age_retraite = (int)$age_retraite;

    if ($tmi <= 0) {
        return [
            'declarant1' => ['economie_totale'=>0,'nb_annees'=>0,'plafond_transfere'=>0,'plafond_restant'=>0],
            'declarant2' => ['economie_totale'=>0,'nb_annees'=>0,'plafond_transfere'=>0,'plafond_restant'=>0],
        ];
    }

    $nb_annees1 = max(0, $age_retraite - $age1);
    $nb_annees2 = max(0, $age_retraite - $age2);

    $economie_declarant1 = 0.0;
    $economie_declarant2 = 0.0;

    $plafond_transfere_vers_1 = 0.0;
    $plafond_transfere_vers_2 = 0.0;

    // Valeurs par défaut si pas d'années
    $reste_plafond_1 = 0.0;
    $reste_plafond_2 = 0.0;

    // ---- 1ère année : chacun utilise son plafond (revenus + non utilisé) ----
    if ($nb_annees1 > 0) {
        $plafond_total_1 = $plafond_revenus_declarant1 + $plafond_non_utilise1;
        $deductible1     = min($montantAnnuelDeclarant1, $plafond_total_1);
        $reste_plafond_1 = max(0.0, $plafond_total_1 - $deductible1);
        $economie_declarant1 += $deductible1 * $tmi;
    }
    $economie_declarant1_annee1 = $economie_declarant1;
    if ($nb_annees2 > 0) {
        $plafond_total_2 = $plafond_revenus_declarant2 + $plafond_non_utilise2;
        $deductible2     = min($montantAnnuelDeclarant2, $plafond_total_2);
        $reste_plafond_2 = max(0.0, $plafond_total_2 - $deductible2);
        $economie_declarant2 += $deductible2 * $tmi;
    }
    $economie_declarant2_annee1 = $economie_declarant2;
    // ---- Redistribution de plafond (1ère année uniquement) si 2 déclarants valides ----
    if ($nb_annees1 > 0 && $nb_annees2 > 0 && $montantAnnuelDeclarant1 > 0 && $montantAnnuelDeclarant2 > 0) {
        // D1 -> D2
        if ($reste_plafond_1 > 0 && $montantAnnuelDeclarant2 > ($plafond_revenus_declarant2 + $plafond_non_utilise2)) {
            $besoin2   = $montantAnnuelDeclarant2 - ($plafond_revenus_declarant2 + $plafond_non_utilise2);
            $utilise12 = min($reste_plafond_1, $besoin2);
            if ($utilise12 > 0) {
                $economie_declarant2   += $utilise12 * $tmi;
                $plafond_transfere_vers_2 = $utilise12;
                $reste_plafond_1       -= $utilise12;
            }
        }
        // D2 -> D1
        if ($reste_plafond_2 > 0 && $montantAnnuelDeclarant1 > ($plafond_revenus_declarant1 + $plafond_non_utilise1)) {
            $besoin1   = $montantAnnuelDeclarant1 - ($plafond_revenus_declarant1 + $plafond_non_utilise1);
            $utilise21 = min($reste_plafond_2, $besoin1);
            if ($utilise21 > 0) {
                $economie_declarant1   += $utilise21 * $tmi;
                $plafond_transfere_vers_1 = $utilise21;
                $reste_plafond_2       -= $utilise21;
            }
        }
    }

    // ---- Années suivantes : on ne simule que le plafond_non_utilise, sans transfert ----
    if ($nb_annees1 > 1) {
        for ($an = 2; $an <= $nb_annees1; $an++) {
            $economie_declarant1 += min($montantAnnuelDeclarant1, $plafond_non_utilise1) * $tmi;
        }
    }
    if ($nb_annees2 > 1) {
        for ($an = 2; $an <= $nb_annees2; $an++) {
            $economie_declarant2 += min($montantAnnuelDeclarant2, $plafond_non_utilise2) * $tmi;
        }
    }

    return [
        'declarant1' => [
            'economie_totale'   => round($economie_declarant1, 2),
            'nb_annees'         => $nb_annees1,
            'plafond_transfere' => round($plafond_transfere_vers_1, 2), // pris chez D2
            'plafond_restant'   => round(max(0.0, $reste_plafond_1), 2), // reste à D1 après transferts
            'economie_annee1' => round($economie_declarant1_annee1, 2),
        ],
        'declarant2' => [
            'economie_totale'   => round($economie_declarant2, 2),
            'nb_annees'         => $nb_annees2,
            'plafond_transfere' => round($plafond_transfere_vers_2, 2), // pris chez D1
            'plafond_restant'   => round(max(0.0, $reste_plafond_2), 2), // reste à D2 après transferts
            'economie_annee1' => round($economie_declarant2_annee1, 2),
        ],
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

function extraireInfosFiscalesPdf(string $texte): array
{
    $res = [
        'annee_N'                          => null,
        'plafond_non_utilise_declarant1'   => null,
        'plafond_non_utilise_declarant2'   => null,
        'plafond_revenus_declarant1'       => null,
        'plafond_revenus_declarant2'       => null,
        'plafonds_non_utilise_declarant1'  => null, // ['N-4'=>..., 'N-3'=>..., 'N-2'=>...]
        'plafonds_non_utilise_declarant2'  => null,
    ];

    // --- Helpers ---
    $toInt = static function(string $s): int {
        // Garde uniquement chiffres, espaces, points, virgules
        $s = preg_replace('/[^0-9\ \.,]/u', '', $s);
        // Supprime séparateurs
        $s = str_replace([' ', ',', '.'], '', $s);
        if ($s === '') return 0;
        // Garde-fou anti overflow si jamais la capture "avale" trop
        if (strlen($s) > 12) $s = substr($s, 0, 12);
        return (int)$s;
    };

    // Normalise pour forcer un saut de ligne avant "Déclar."
    $texte = str_replace('Déclar.', "\nDéclar.", $texte);
    $lines = preg_split('/\r\n|\r|\n/u', $texte);

    // --- Année N (cotisations versées en YYYY) ---
    foreach ($lines as $line) {
        if (preg_match('/cotisations\s+versées\s+en\s+(\d{4})/iu', $line, $m)) {
            $res['annee_N'] = (int)$m[1];
            break;
        }
    }

    // --- Découpage blocs Déclarant 1 / Déclarant 2 ---
    $d1bloc = [];
    $d2bloc = [];
    $current = null;

    foreach ($lines as $line) {
        if (preg_match('/Déclar\.\s*1\b/u', $line)) { $current = 1; continue; }
        if (preg_match('/Déclar\.\s*2\b/u', $line)) { $current = 2; continue; }

        if ($current === 1) {
            if (preg_match('/\bEnfant\b|Déclar\./u', $line)) { $current = null; }
            else { $d1bloc[] = $line; }
        } elseif ($current === 2) {
            if (preg_match('/\bEnfant\b|Déclar\./u', $line)) { $current = null; }
            else { $d2bloc[] = $line; }
        }
    }

    // --- Extraction par déclarant ---
    foreach ([1, 2] as $who) {
        /** @var string[] $bloc */
        $bloc = ${"d{$who}bloc"};
        $blocstr = implode(' ', array_map('trim', $bloc));

        // 1) Plafond NON UTILISÉ total (ligne contenant "=") — LIGNE PAR LIGNE
        $plafondTotal = null;
        foreach ($bloc as $l) {
            $l = trim($l);
            // Exemple: "... + 4 399 + 4 637 = 12 627"
            if (preg_match('/=\s*([0-9][0-9\ \.,]{0,20})$/u', $l, $m)) {
                $plafondTotal = $toInt($m[1]);
                break;
            }
        }
        if ($plafondTotal !== null) {
            $res["plafond_non_utilise_declarant{$who}"] = $plafondTotal;
        }

        // 2) Plafond ACTUEL (calculé sur les revenus de YYYY) — on cherche d'abord ligne par ligne
        $plafondActuel = null;
        foreach ($bloc as $l) {
            if (preg_match('/Plafond\s+calculé\s+sur\s+les\s+revenus\s+de\s+\d{4}\s+([0-9\ \.,]{1,20})/iu', $l, $m)) {
                $plafondActuel = $toInt($m[1]);
                break;
            }
        }
        // Fallback : dernier "+ xxx" du bloc
        if ($plafondActuel === null) {
            if (preg_match_all('/\+\s*([0-9\ \.,]{1,20})/u', $blocstr, $plus) && !empty($plus[1])) {
                $plafondActuel = $toInt(end($plus[1]));
            }
        }
        if ($plafondActuel !== null) {
            $res["plafond_revenus_declarant{$who}"] = $plafondActuel;
        }

        // 3) Détail des 3 années "non utilisé" (N-4, N-3, N-2)
        $nuBuckets = ['N-4'=>0, 'N-3'=>0, 'N-2'=>0];

        // Cas A : l’avis liste explicitement "2023 : 4 399"…
        $anneesMontants = [];
        if (preg_match_all('/(20\d{2})\s*[:\-]?\s*([0-9\ \.,]{3,})/u', $blocstr, $ym, PREG_SET_ORDER)) {
            foreach ($ym as $mm) {
                $y = (int)$mm[1];
                $v = $toInt($mm[2]);
                if ($v > 0) $anneesMontants[$y] = $v;
            }
        }
        if (count($anneesMontants) >= 3) {
            ksort($anneesMontants);                         // plus ancien → plus récent
            $last3 = array_slice($anneesMontants, -3, 3, true);
            $vals  = array_values($last3);
            $nuBuckets['N-4'] = (int)$vals[0];
            $nuBuckets['N-3'] = (int)$vals[1];
            $nuBuckets['N-2'] = (int)$vals[2];
        } else {
            // Cas B : format "+ xxxx + yyyy + zzzz = total"
            if (preg_match_all('/\+\s*([0-9\ \.,]{3,})/u', $blocstr, $mPlus)) {
                $vals = array_map($toInt, $mPlus[1]);
                if (count($vals) >= 3) {
                    // On prend les 3 derniers +... (supposés être les 3 années reportables)
                    $last3 = array_slice($vals, -3, 3);
                    $nuBuckets['N-4'] = (int)$last3[0];
                    $nuBuckets['N-3'] = (int)$last3[1];
                    $nuBuckets['N-2'] = (int)$last3[2];
                }
            }
        }

        $res["plafonds_non_utilise_declarant{$who}"] = $nuBuckets;

        // 4) Ajustement : si on a le total (=) ET le plafond actuel, certains avis affichent
        //    "N-4 + N-3 + N-2 + (plafond actuel) = total".
        //    Pour conserver la compatibilité avec ton ancien code, on soustrait le plafond actuel du total.
        if ($res["plafond_non_utilise_declarant{$who}"] !== null
            && $res["plafond_revenus_declarant{$who}"] !== null) {
            $res["plafond_non_utilise_declarant{$who}"] -= $res["plafond_revenus_declarant{$who}"];
            // Sécurité : pas de négatif
            if ($res["plafond_non_utilise_declarant{$who}"] < 0) {
                $res["plafond_non_utilise_declarant{$who}"] = 0;
            }
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
  2 phrases, 350 caractères max, personnalisée (utilise le prénom), qui propose une action concrète pour optimiser le PER : ajuster les versements (pour annuler l’impôt), prioriser les plafonds non utilisés, ajuster le rythme, etc.
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

function getPromptSansAvis($age1, $age2, $data, $versementMensuel1, $versementMensuel2){
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
  $tmi                                      = $data['tmi'];
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
      $declarationRule = "Les deux déclarants doivent être mentionnés dans le tableau";
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
  $prompt = "Tu es un expert en fiscalité française. Voici des informations essentielles pour me trouver une astucve et un conseil.
  Je souhaite que tu me retournes uniquement un objet JSON **brut**,
  sans aucune balise de code (pas de ```json ou ```), et **aucun texte** hors JSON.
  **Ne te sers que des variables fournies** ci-dessous et du texte brut qui suit.
  **Tu ne dois inventer aucune information**.
  **Règle de présence des déclarants** :
  {$declarationRule}
  {$declarant1} {$declarant2}
  Le format de réponse doit être un objet JSON avec les **clés suivantes**,
  Le format doit rester **strictement identique à chaque appel** pour permettre un traitement automatique.
  - astuce_declarant1
  - astuce_declarant2
  - conseil_personnalise_declarant1
  - conseil_personnalise_declarant2
  Consignes pour chaque champ :
  **Très important l'astuce et le conseil personnalisé par déclarant doivent etre strictement différents.**
  Pour info, le TMI du foyer fiscal est de {$tmi}%.
  Règles de génération :
  **Pas de : ou de ; dans les textes.**
  Pour astuce_declarantX :
  Une phrase (≤ 350 caractères).
  Action concrète pour optimiser un PER : ajuster les versements, prioriser les plafonds non utilisés, ajuster le rythme, etc.
  Doit être différente du conseil_personnalise_declarantX.
  Doit être différente entre déclarant 1 et déclarant 2.
  Pour conseil_personnalise_declarantX :
  Une phrase (≤ 350 caractères).
  Ton simple, confiant, rassurant.
  Si {$tmi} ≤ 11 (TMI ≤ 0,11) et impôt quasi nul, alors :Ne proposer pas de PER. proposer une alternative au PER (par exemple assurance-vie, produit plus souple) en expliquant brièvement pourquoi le PER n’est pas optimal.
  Sinon : se concentrer sur le PER (priorisation du plafond, ajustement du rythme, usage des plafonds non utilisés, optimisation fiscale).
  Doit être différente de l’astuce correspondante et personnalisée pour chaque déclarant.
  Exemples d’astuce :
  Mettez en place un versement automatique de XXX € par mois pour lisser le risque de marché tout en utilisant votre plafond courant.
  Programmez vos virements juste après la paie, vous épargnez avant de dépenser et sécurisez votre rythme.
  Augmentez automatiquement l'épargne mensuelle de 3 % chaque année pour suivre l’inflation sans y penser.
  Scindez vos virements en deux dates (début et milieu de mois) pour lisser l’effet de marché.
  Versez votre prime annuelle en janvier, vous profitez toute l’année des intérêts composés.
  Coupez le versement unique : 60 % maintenant, 40 % en fin d’année pour garder de la trésorerie.
  Exemples de conseil :
  Utilisez XX € de prime unique cette année pour absorber votre plafond non utilisé et annuler la quasi-totalité de votre impôt.
  Priorisez d’abord les 17 000 € de plafond non utilisé, c’est la déduction la plus rentable.
  Répartissez le plafond non utilisé entre les deux déclarants pour maximiser la déduction et équilibrer la fiscalité du foyer.
  Calibrez vos versements pour annuler l’impôt, au-delà, placez le surplus sur une assurance-vie.
  Si votre TMI chute sous 11 % après usage du plafond, basculez le surplus d’épargne vers une assurance-vie.
  Impôt déjà à zéro ? Orientez vos versements vers un support libre pour rester liquide et diversifié.
      {
        \"astuce_declarant1\": \"string\",
        \"astuce_declarant2\": \"string\",
        \"conseil_personnalise_declarant1\": \"string\",
        \"conseil_personnalise_declarant2\": \"string\",
      }";

  return $prompt;
}

function plafond_per_annee($anneeCotisation, $salaireNMoins1, $revActNMoins1, $PASS){
    // PASS de l’année de base (N-1), fallback = dernier PASS connu
    $lastYear  = array_key_last($PASS);
    $pass = $PASS[$anneeCotisation] ?? $PASS[$lastYear];
    // Salarié : 10% du salaire (limité à 8 PASS), plancher 10% PASS
    $plafond_salarie = max(0.10 * min($salaireNMoins1, 8 * $pass), 0.10 * $pass);

    // Indépendant : 10% [0..1 PASS] + 15% [1..8 PASS], plancher 10% PASS, plafond 1.85 PASS
    $plafond_indep = 0.0;
    if ($revActNMoins1 > 0) {
        $part_0a1 = max(min($revActNMoins1, $pass), 0);
        $part_1a8 = max(min($revActNMoins1, 8 * $pass) - $pass, 0);
        $plafond_indep = 0.10 * $part_0a1 + 0.15 * $part_1a8;
        $plafond_indep = max($plafond_indep, 0.10 * $pass);
        $plafond_indep = min($plafond_indep, 1.85 * $pass);
    }
    return round(max($plafond_salarie, $plafond_indep), 2); // pas de cumul
}

function calcul_plafonds_structures($anneeCotisation, $PASS, $salaireDefault, $revActDefault, $declarant = 'declarant1'){
    $plafonds = [];
    for ($an = $anneeCotisation - 3; $an <= $anneeCotisation; $an++) {
        $plafonds[$an] = plafond_per_annee($an, $salaireDefault, $revActDefault, $PASS);
    }

    ksort($plafonds);
    // Transformation en N-4, N-3, N-2
    $annees   = array_keys($plafonds);
    $valeurs  = array_values($plafonds);

    // Les 3 premières années correspondent au N-4, N-3, N-2
    $plafonds_non_utilise = [
        "N-4" => round($valeurs[0], 0),
        "N-3" => round($valeurs[1], 0),
        "N-2" => round($valeurs[2], 0),
    ];

    // Les 3 premières années = plafonds non utilisés → somme
    $plafond_non_utilise = array_sum(array_slice($plafonds, 0, 3, true));

    // Le dernier = plafond revenus (année N)
    $plafond_revenus = end($plafonds);

    return [
        "plafond_non_utilise_{$declarant}"        => $plafond_non_utilise,
        "plafond_revenus_{$declarant}"            => $plafond_revenus,
        "plafonds_non_utilise_{$declarant}"       => $plafonds_non_utilise
    ];
}

function calculer_tmi(array $contexte, array $tranches): float{
    // Calcul via quotient familial
    $nb_personne = isset($contexte['nb_personne']) ? (int)$contexte['nb_personne'] : 1;

    $salaires_1   = isset($contexte['salaires_1'])   ? (float)$contexte['salaires_1']   : 0.0;
    $revenu_act_1 = isset($contexte['revenu_act_1']) ? (float)$contexte['revenu_act_1'] : 0.0;
    $salaires_2   = ($nb_personne === 2) ? (float)($contexte['salaires_2'] ?? 0.0) : 0.0;
    $revenu_act_2 = ($nb_personne === 2) ? (float)($contexte['revenu_act_2'] ?? 0.0) : 0.0;

    $parts = (isset($contexte['parts']) && (int)$contexte['parts'] > 0) ? (int)$contexte['parts'] : 1;

    $revenu_imposable_total = max(0.0, $salaires_1 + $revenu_act_1 + $salaires_2 + $revenu_act_2);
    $quotient_familial = $revenu_imposable_total / max(1, $parts);

    // Application du barème
    $tmi = 0.0;
    foreach ($tranches as $tranche) {
        if ($quotient_familial <= $tranche['max']) {
            $tmi = $tranche['taux'];
            break;
        }
    }
    return $tmi;
}

function charger_tranches_tmi(array $section): array {
    $tranches = [];
    for ($i = 1; $i <= 10; $i++) { // marge si tu ajoutes un jour d'autres tranches
        $maxKey = "max{$i}";
        $tauxKey = "taux{$i}";
        if (!isset($section[$maxKey], $section[$tauxKey])) break;

        $max  = ($section[$maxKey] === 'INF') ? INF : (float)$section[$maxKey];
        $taux = (float)$section[$tauxKey];

        $tranches[] = ['max' => $max, 'taux' => $taux];
    }
    return $tranches;
}

function traiter_millesime_reliquat(string $k, float &$besoin1, float &$besoin2, array &$nu1, array &$nu2): array {
    $res = [
        'd1' => ['propre' => 0.0, 'transfert_recu' => 0.0, 'transfert_donne' => 0.0],
        'd2' => ['propre' => 0.0, 'transfert_recu' => 0.0, 'transfert_donne' => 0.0],
    ];

    // Conso propre
    $propre1 = min($besoin1, (float)($nu1[$k] ?? 0.0));
    $nu1[$k] -= $propre1;  $besoin1 -= $propre1;  $res['d1']['propre'] = $propre1;

    $propre2 = min($besoin2, (float)($nu2[$k] ?? 0.0));
    $nu2[$k] -= $propre2;  $besoin2 -= $propre2;  $res['d2']['propre'] = $propre2;

    // Transfert intra-millésime
    if ($besoin1 > 0 && ($nu2[$k] ?? 0) > 0) {
        $prendre = min($besoin1, $nu2[$k]);
        $nu2[$k] -= $prendre;  $besoin1 -= $prendre;
        $res['d1']['transfert_recu'] += $prendre;
        $res['d2']['transfert_donne'] += $prendre;
    }
    if ($besoin2 > 0 && ($nu1[$k] ?? 0) > 0) {
        $prendre = min($besoin2, $nu1[$k]);
        $nu1[$k] -= $prendre;  $besoin2 -= $prendre;
        $res['d2']['transfert_recu'] += $prendre;
        $res['d1']['transfert_donne'] += $prendre;
    }

    return $res;
}

function traiter_millesime_actuel(float &$besoin1, float &$besoin2, float &$pa1, float &$pa2): array {
    $res = [
        'd1' => ['propre' => 0.0, 'transfert_recu' => 0.0, 'transfert_donne' => 0.0],
        'd2' => ['propre' => 0.0, 'transfert_recu' => 0.0, 'transfert_donne' => 0.0],
    ];

    // Conso propre
    $propre1 = min($besoin1, $pa1);  $pa1 -= $propre1;  $besoin1 -= $propre1;  $res['d1']['propre'] = $propre1;
    $propre2 = min($besoin2, $pa2);  $pa2 -= $propre2;  $besoin2 -= $propre2;  $res['d2']['propre'] = $propre2;

    // Transferts
    if ($besoin1 > 0 && $pa2 > 0) {
        $prendre = min($besoin1, $pa2);
        $pa2 -= $prendre; $besoin1 -= $prendre;
        $res['d1']['transfert_recu'] += $prendre;
        $res['d2']['transfert_donne'] += $prendre;
    }
    if ($besoin2 > 0 && $pa1 > 0) {
        $prendre = min($besoin2, $pa1);
        $pa1 -= $prendre; $besoin2 -= $prendre;
        $res['d2']['transfert_recu'] += $prendre;
        $res['d1']['transfert_donne'] += $prendre;
    }

    return $res;
}

function calcul_exact_par_millesime($tmi,$versementAnnuel1,$versementAnnuel2,$plafondActuel1,$plafondActuel2,$nonUtilise1,$nonUtilise2,$anneeCotisation) {
    // --- INIT identique ---
    $nu1 = [
        'N-4' => (float)($nonUtilise1['N-4'] ?? 0.0),
        'N-3' => (float)($nonUtilise1['N-3'] ?? 0.0),
        'N-2' => (float)($nonUtilise1['N-2'] ?? 0.0),
    ];
    $nu2 = [
        'N-4' => (float)($nonUtilise2['N-4'] ?? 0.0),
        'N-3' => (float)($nonUtilise2['N-3'] ?? 0.0),
        'N-2' => (float)($nonUtilise2['N-2'] ?? 0.0),
    ];
    $pa1 = (float)$plafondActuel1;
    $pa2 = (float)$plafondActuel2;

    $besoin1 = max(0.0, (float)$versementAnnuel1);
    $besoin2 = max(0.0, (float)$versementAnnuel2);

    $eco1 = 0.0; $eco2 = 0.0;
    $details = ['N-4'=>[],'N-3'=>[],'N-2'=>[],'N-1'=>[]];

    // --- Détection de présence D1/D2 ---
    $sumNu = static function(array $nu): float {
        return (float)($nu['N-4'] ?? 0) + (float)($nu['N-3'] ?? 0) + (float)($nu['N-2'] ?? 0);
    };
    $isD1 = ($besoin1 > 0.0) || ($pa1 > 0.0) || ($sumNu($nu1) > 0.0);
    $isD2 = ($besoin2 > 0.0) || ($pa2 > 0.0) || ($sumNu($nu2) > 0.0);

    // Si un déclarant est absent, on neutralise complètement ses besoins et ses droits
    if (!$isD1) { $besoin1 = 0.0; $pa1 = 0.0; $nu1 = ['N-4'=>0.0,'N-3'=>0.0,'N-2'=>0.0]; }
    if (!$isD2) { $besoin2 = 0.0; $pa2 = 0.0; $nu2 = ['N-4'=>0.0,'N-3'=>0.0,'N-2'=>0.0]; }

    // --- N-4 à N-2 identique ---
    foreach (['N-4','N-3','N-2'] as $k) {
        $r = traiter_millesime_reliquat($k, $besoin1, $besoin2, $nu1, $nu2);
        $details[$k] = $r;
        $eco1 += ($r['d1']['propre'] + $r['d1']['transfert_recu']) * $tmi;
        $eco2 += ($r['d2']['propre'] + $r['d2']['transfert_recu']) * $tmi;
    }
    // --- N-1 identique ---
    $r = traiter_millesime_actuel($besoin1, $besoin2, $pa1, $pa2);
    $details['N-1'] = $r;
    $eco1 += ($r['d1']['propre'] + $r['d1']['transfert_recu']) * $tmi;
    $eco2 += ($r['d2']['propre'] + $r['d2']['transfert_recu']) * $tmi;

    // --- Rollover identique ---
    $prochaine_annee1 = [
        'N-4' => $nu1['N-3'],
        'N-3' => $nu1['N-2'],
        'N-2' => max(0.0, $pa1),
    ];

    $prochaine_annee2 = [
        'N-4' => $nu2['N-3'],
        'N-3' => $nu2['N-2'],
        'N-2' => max(0.0, $pa2),
    ];
    $mapAnnees = function(array $bucket) use ($anneeCotisation) {
        return [
            $anneeCotisation - 3 => round($bucket['N-4'] ?? 0.0, 2),
            $anneeCotisation - 2 => round($bucket['N-3'] ?? 0.0, 2),
            $anneeCotisation - 1 => round($bucket['N-2'] ?? 0.0, 2),
        ];
    };

    // ====== ENRICHISSEMENTS FRONT-FRIENDLY ======
    $sumUsed = function(array $det, string $who, string $k): float {
        return (float)($det[$k][$who]['propre'] ?? 0.0) + (float)($det[$k][$who]['transfert_recu'] ?? 0.0);
    };
    $used1 = [
        'N-4' => $sumUsed($details,'d1','N-4'),
        'N-3' => $sumUsed($details,'d1','N-3'),
        'N-2' => $sumUsed($details,'d1','N-2'),
        'N-1' => $sumUsed($details,'d1','N-1'),
    ];
    $used2 = [
        'N-4' => $sumUsed($details,'d2','N-4'),
        'N-3' => $sumUsed($details,'d2','N-3'),
        'N-2' => $sumUsed($details,'d2','N-2'),
        'N-1' => $sumUsed($details,'d2','N-1'),
    ];

    $stateOf = function(float $used, float $rest): string {
        if ($used > 0.0 && $rest <= 0.0) return 'full';
        if ($used > 0.0 && $rest  > 0.0) return 'part';
        return 'none';
    };

    // totaux transferts
    $sumKey = function(array $det, string $who, string $key): float {
        $s = 0.0;
        foreach (['N-4','N-3','N-2','N-1'] as $k) $s += (float)($det[$k][$who][$key] ?? 0.0);
        return round($s, 2);
    };
    $transf_recu_d1  = $sumKey($details,'d1','transfert_recu');
    $transf_donne_d1 = $sumKey($details,'d1','transfert_donne');
    $transf_recu_d2  = $sumKey($details,'d2','transfert_recu');
    $transf_donne_d2 = $sumKey($details,'d2','transfert_donne');

    // ====== PAYLOAD ======
    $out1 = [
        'is_present' => $isD1,

        'economie_totale' => round($eco1, 2),
        'details' => $details,
        'plafond_actuel_restant' => round(max(0.0, $pa1), 2),
        'plafond_non_utilise_restant' => [
            'N-4' => round($nu1['N-4'], 2),
            'N-3' => round($nu1['N-3'], 2),
            'N-2' => round($nu1['N-2'], 2),
        ],
        'plafond_non_utilise_restant_par_annee' => $mapAnnees($nu1),
        'transfert_total_recu' => $transf_recu_d1,
        'plafond_non_utilise_prochaine_annee' => [
            'details' => [
                'N-4' => round($prochaine_annee1['N-4'], 2),
                'N-3' => round($prochaine_annee1['N-3'], 2),
                'N-2' => round($prochaine_annee1['N-2'], 2),
            ],
            'par_annee' => $mapAnnees($prochaine_annee1),
        ],

        // Nouveaux champs
        'plafond_utilise_par_annee' => [
            'N-4' => round($used1['N-4'], 2),
            'N-3' => round($used1['N-3'], 2),
            'N-2' => round($used1['N-2'], 2),
            'N-1' => round($used1['N-1'], 2),
        ],
        'etat_par_annee' => [
            'N-4' => $stateOf($used1['N-4'], $nu1['N-4']),
            'N-3' => $stateOf($used1['N-3'], $nu1['N-3']),
            'N-2' => $stateOf($used1['N-2'], $nu1['N-2']),
            'N'   => $stateOf($used1['N-1'], $pa1),
        ],
        'totaux' => [
            'utilise' => round($used1['N-4'] + $used1['N-3'] + $used1['N-2'] + $used1['N-1'], 2),
            'restant' => round($nu1['N-4'] + $nu1['N-3'] + $nu1['N-2'] + $pa1, 2),
        ],
        'transferts' => [
            'recu'  => $transf_recu_d1,
            'donne' => $transf_donne_d1,
        ],
    ];

    $out2 = [
        'is_present' => $isD2,

        'economie_totale' => round($eco2, 2),
        'details' => $details,
        'plafond_actuel_restant' => round(max(0.0, $pa2), 2),
        'plafond_non_utilise_restant' => [
            'N-4' => round($nu2['N-4'], 2),
            'N-3' => round($nu2['N-3'], 2),
            'N-2' => round($nu2['N-2'], 2),
        ],
        'plafond_non_utilise_restant_par_annee' => $mapAnnees($nu2),
        'transfert_total_recu' => $transf_recu_d2,
        'plafond_non_utilise_prochaine_annee' => [
            'details' => [
                'N-4' => round($prochaine_annee2['N-4'], 2),
                'N-3' => round($prochaine_annee2['N-3'], 2),
                'N-2' => round($prochaine_annee2['N-2'], 2),
            ],
            'par_annee' => $mapAnnees($prochaine_annee2),
        ],
        'plafond_utilise_par_annee' => [
            'N-4' => round($used2['N-4'], 2),
            'N-3' => round($used2['N-3'], 2),
            'N-2' => round($used2['N-2'], 2),
            'N-1' => round($used2['N-1'], 2),
        ],
        'etat_par_annee' => [
            'N-4' => $stateOf($used2['N-4'], $nu2['N-4']),
            'N-3' => $stateOf($used2['N-3'], $nu2['N-3']),
            'N-2' => $stateOf($used2['N-2'], $nu2['N-2']),
            'N'   => $stateOf($used2['N-1'], $pa2),
        ],
        'totaux' => [
            'utilise' => round($used2['N-4'] + $used2['N-3'] + $used2['N-2'] + $used2['N-1'], 2),
            'restant' => round($nu2['N-4'] + $nu2['N-3'] + $nu2['N-2'] + $pa2, 2),
        ],
        'transferts' => [
            'recu'  => $transf_recu_d2,
            'donne' => $transf_donne_d2,
        ],
    ];

    return [
        'declarant1' => $out1,
        'declarant2' => $out2,
    ];
}
