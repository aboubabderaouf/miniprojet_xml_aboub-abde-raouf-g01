<?php
// ═══════════════════════════════════════════════════════
//  Club Info_Tech — Gestion des Concours
//  Fichier : web/index.php
//  XML     : ../club.xml  (un niveau au-dessus du dossier web/)
// ═══════════════════════════════════════════════════════

$xml_path = '../club.xml';
$message  = '';
$msgType  = '';

// Fonction de comparaison pour trier par score décroissant
function compare_scores_desc($a, $b) {
    if ($a['score'] === $b['score']) return 0;
    return ($a['score'] < $b['score']) ? 1 : -1;
}

// ── Traitement du formulaire d'inscription (POST) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inscrire') {
    $concoursId = trim($_POST['concours']  ?? '');
    $membreId   = trim($_POST['membre']    ?? '');
    $complexite = intval($_POST['complexite'] ?? 0);
    $temps      = intval($_POST['temps']      ?? 0);

    if (!$concoursId || !$membreId) {
        $message = "Veuillez sélectionner un concours et un membre.";
        $msgType = 'error';
    } elseif ($complexite < 0 || $complexite > 100) {
        $message = "La complexité doit être comprise entre 0 et 100.";
        $msgType = 'error';
    } elseif ($temps <= 0) {
        $message = "Le temps d'exécution doit être supérieur à 0.";
        $msgType = 'error';
    } elseif (!file_exists($xml_path)) {
        $message = "Fichier club.xml introuvable.";
        $msgType = 'error';
    } else {
        $xml = simplexml_load_file($xml_path);

        // Chercher le concours cible
        $targetConcours = null;
        foreach ($xml->concours->concours as $c) {
            if ((string)$c['id'] === $concoursId) {
                $targetConcours = $c;
                break;
            }
        }

        // Vérifier que le membre n'est pas déjà inscrit
        $dejaInscrit = false;
        if ($targetConcours) {
            foreach ($targetConcours->participants->participant as $p) {
                if ((string)$p['membreRef'] === $membreId) {
                    $dejaInscrit = true;
                    break;
                }
            }
        }

        if (!$targetConcours) {
            $message = "Concours introuvable.";
            $msgType = 'error';
        } elseif ($dejaInscrit) {
            $message = "Ce membre est déjà inscrit à ce concours.";
            $msgType = 'error';
        } else {
            // Ajouter le participant
            $participant = $targetConcours->participants->addChild('participant');
            $participant->addAttribute('membreRef', $membreId);
            $participant->addChild('complexite',     (string)$complexite);
            $participant->addChild('tempsExecution', (string)$temps);

            // Sauvegarder avec formatage
            $dom = dom_import_simplexml($xml)->ownerDocument;
            $dom->formatOutput = true;
            $dom->save($xml_path);

            $message = "Inscription réussie et sauvegardée !";
            $msgType = 'success';

            // Rediriger vers les résultats du concours inscrit
            header("Location: index.php?view_concours={$concoursId}&inscrit=1#results-anchor");
            exit;
        }
    }
}

// ── Chargement du XML ──────────────────────────────────
$club = simplexml_load_file($xml_path);

// Récupérer le concours sélectionné (GET)
$selectedConcoursId = $_GET['view_concours'] ?? '';
if (isset($_GET['inscrit']) && $_GET['inscrit'] === '1' && !$message) {
    $message = "Inscription réussie et sauvegardée !";
    $msgType = 'success';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Info_Tech - Gestion des Concours</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <h1>🏆 Club Info_Tech - Gestion des Concours</h1>
    </header>

    <main>

        <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════
             SECTION 1 — Liste des concours disponibles
        ═════════════════════════════════════════════ -->
        <section class="card">
            <h2>📅 Liste des Concours Disponibles</h2>
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Catégorie</th>
                        <th>Coefficient</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($club->concours->concours as $c):
                        $catId  = (string)$c['categorieRef'];
                        $cat    = $club->xpath("//categorie[@id='$catId']")[0];
                        $catLib = (string)$cat['libelle'];

                        // Choisir la couleur du badge selon la catégorie
                        $badgeClass = 'badge-blue';
                        if (strpos($catLib, 'Sécurité') !== false) $badgeClass = 'badge-red';
                        elseif (strpos($catLib, 'Web') !== false)  $badgeClass = 'badge-green';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$c->titre) ?></td>
                        <td><?= htmlspecialchars((string)$c['date']) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($catLib) ?></span></td>
                        <td><?= htmlspecialchars((string)$c['coefficient']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- ════════════════════════════════════════════
             SECTION 2 — Résultats des concours
        ═════════════════════════════════════════════ -->
        <section class="card" id="results-anchor">
            <h2>🥇 Résultats des Concours</h2>

            <!-- Formulaire de sélection du concours (GET) -->
            <div class="select-row">
                <form method="GET" action="index.php">
                    <select name="view_concours" id="selectConcours">
                        <option value="">Sélectionnez...</option>
                        <?php foreach ($club->concours->concours as $c): ?>
                        <option value="<?= htmlspecialchars((string)$c['id']) ?>"
                            <?= ($selectedConcoursId === (string)$c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$c->titre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary">Afficher résultats</button>
                </form>
            </div>

            <?php if ($selectedConcoursId):
                // Trouver le concours sélectionné
                $selectedConcours = null;
                foreach ($club->concours->concours as $c) {
                    if ((string)$c['id'] === $selectedConcoursId) {
                        $selectedConcours = $c;
                        break;
                    }
                }

                if ($selectedConcours):
                    $coeff = (float)$selectedConcours['coefficient'];
                    $participants = [];

                    foreach ($selectedConcours->participants->participant as $p) {
                        $mRef    = (string)$p['membreRef'];
                        $membre  = $club->xpath("//membre[@id='$mRef']")[0];
                        $comp    = (int)$p->complexite;
                        $temps   = (int)$p->tempsExecution;
                        $score   = round(($comp + $temps) * $coeff, 2);

                        $participants[] = [
                            'nom'   => (string)$membre->prenom . ' ' . (string)$membre->nom,
                            'comp'  => $comp,
                            'temps' => $temps,
                            'score' => $score,
                        ];
                    }

                    // Trier par score décroissant
                    usort($participants, 'compare_scores_desc');
                    $maxScore = !empty($participants) ? $participants[0]['score'] : 0;
            ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Participant</th>
                        <th>Complexité</th>
                        <th>Temps (ms)</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $i => $data):
                        $rang     = $i + 1;
                        $isWinner = ($data['score'] == $maxScore && $maxScore > 0);
                    ?>
                    <tr class="<?= $isWinner ? 'winner' : '' ?>">
                        <td><?= $rang ?> <?= $isWinner ? '🥇' : '' ?></td>
                        <td><?= htmlspecialchars($data['nom']) ?></td>
                        <td><?= $data['comp'] ?></td>
                        <td><?= $data['temps'] ?></td>
                        <td><strong><?= number_format($data['score'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ════════════════════════════════════════════
             SECTION 3 — Nouvelle Inscription
        ═════════════════════════════════════════════ -->
        <section class="card">
            <h2>✏️ Nouvelle Inscription</h2>

            <form method="POST" action="index.php" class="inscription-form">
                <input type="hidden" name="action" value="inscrire">

                <div class="form-group">
                    <label for="concours">Concours :</label>
                    <select name="concours" id="concours" required>
                        <option value="">Sélectionnez...</option>
                        <?php foreach ($club->concours->concours as $c): ?>
                        <option value="<?= htmlspecialchars((string)$c['id']) ?>">
                            <?= htmlspecialchars((string)$c->titre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="membre">Membre :</label>
                    <select name="membre" id="membre" required>
                        <option value="">Sélectionnez...</option>
                        <?php foreach ($club->membres->membre as $m): ?>
                        <option value="<?= htmlspecialchars((string)$m['id']) ?>">
                            <?= htmlspecialchars((string)$m->prenom . ' ' . (string)$m->nom) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="complexite">Complexité de l'algo (0-100) :</label>
                        <input type="number" name="complexite" id="complexite"
                               min="0" max="100" placeholder="ex: 75" required>
                    </div>
                    <div class="form-group">
                        <label for="temps">Temps exécution (ms) :</label>
                        <input type="number" name="temps" id="temps"
                               min="1" placeholder="ex: 120" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">S'inscrire</button>
            </form>
        </section>

    </main>

    <footer>
        <p>Club Info_Tech &mdash; Mini Projet XML/XSD/XQuery/PHP &mdash; 2025</p>
    </footer>

</body>
</html>