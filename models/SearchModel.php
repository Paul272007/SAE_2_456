<?php

// models/SearchModel.php

declare(strict_types=1);

namespace Models;

use Core\Model;

class SearchModel extends Model
{
    /**
     * Récupère la liste de toutes les communes qui ont au moins un arrêt.
     * Optimisé avec UNION pour éviter le OR dans la jointure qui est lent sous Oracle.
     */
    public function getCommunes(): array
    {
        $sql = "SELECT com_code_insee, com_nom FROM (
                    SELECT DISTINCT TRIM(c.com_code_insee) as com_code_insee, TRIM(c.com_nom) as com_nom
                    FROM vik_commune c
                    JOIN vik_noeud n ON TRIM(n.com_code_insee_arret) = TRIM(c.com_code_insee)
                    UNION
                    SELECT DISTINCT TRIM(c.com_code_insee) as com_code_insee, TRIM(c.com_nom) as com_nom
                    FROM vik_commune c
                    JOIN vik_noeud n ON TRIM(n.com_code_insee_suivant) = TRIM(c.com_code_insee)
                ) ORDER BY com_nom ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Algorithme de Dijkstra pour trouver le meilleur itinéraire.
     * @param string $startCode Code INSEE de départ
     * @param string $endCode Code INSEE d'arrivée
     * @param string $criterion 'distance', 'duration' ou 'price'
     */
    public function findPath(string $startCode, string $endCode, string $criterion = 'duration'): ?array
    {
        // 1. Construire le graphe
        $graph = $this->buildGraph($criterion);

        // Nœuds de départ et d'arrivée
        $startNodes = $this->getNodesForCommune($startCode);
        $endNodes = $this->getNodesForCommune($endCode);

        if (empty($startNodes) || empty($endNodes)) {
            return null;
        }

        // 2. Initialisation Dijkstra
        $distances = [];
        $previous = [];
        $queue = [];

        foreach ($graph as $nodeId => $neighbors) {
            $distances[$nodeId] = INF;
            $previous[$nodeId] = null;
            $queue[$nodeId] = INF;
        }

        foreach ($startNodes as $startNode) {
            if (isset($distances[$startNode])) {
                $distances[$startNode] = 0;
                $queue[$startNode] = 0;
            }
        }

        // 3. Boucle principale
        while (!empty($queue)) {
            asort($queue);
            $u = (string)array_key_first($queue);
            $distU = $queue[$u];
            unset($queue[$u]);

            if ($distU === INF) {
                break;
            }

            // Si on a atteint une des destinations possibles
            if (in_array($u, $endNodes)) {
                $path = [];
                $current = $u;
                while ($current !== null) {
                    array_unshift($path, $current);
                    $current = $previous[$current] ?? null;
                }
                
                return $this->formatPath($path, $criterion);
            }

            if (isset($graph[$u])) {
                foreach ($graph[$u] as $v => $weight) {
                    if (isset($distances[$v])) {
                        $alt = $distances[$u] + $weight;
                        if ($alt < $distances[$v]) {
                            $distances[$v] = $alt;
                            $previous[$v] = $u;
                            $queue[$v] = $alt;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Construit le graphe pondéré à partir des segments de ligne et des correspondances.
     */
    private function buildGraph(string $criterion): array
    {
        $graph = [];

        // 1. Initialiser les nœuds uniques par ligne/commune
        $sql = "SELECT DISTINCT TRIM(lig_num) as lig_num, TRIM(com_code_insee_arret) as com_code_insee FROM vik_noeud
                UNION
                SELECT DISTINCT TRIM(lig_num) as lig_num, TRIM(com_code_insee_suivant) as com_code_insee FROM vik_noeud WHERE com_code_insee_suivant IS NOT NULL";
        $allNodes = $this->fetchAll($sql);
        foreach ($allNodes as $node) {
            $nodeId = $node['lig_num'] . '_' . $node['com_code_insee'];
            $graph[$nodeId] = [];
        }

        // 2. Segments de ligne (pondération selon le critère)
        $sql = "SELECT DISTINCT TRIM(lig_num) as lig_num, 
                       TRIM(com_code_insee_arret) as com_code_insee_arret, 
                       TRIM(com_code_insee_suivant) as com_code_insee_suivant, 
                       noe_distance_prochain, 
                       noe_duree_prochain
                FROM vik_noeud
                WHERE com_code_insee_suivant IS NOT NULL";
        $edges = $this->fetchAll($sql);

        foreach ($edges as $edge) {
            $u = $edge['lig_num'] . '_' . $edge['com_code_insee_arret'];
            $v = $edge['lig_num'] . '_' . $edge['com_code_insee_suivant'];
            
            $dist = (float)str_replace(',', '.', (string)($edge['noe_distance_prochain'] ?? 0));
            $dur = (float)str_replace(',', '.', (string)($edge['noe_duree_prochain'] ?? 0));

            // Si le critère est le prix, on utilise la distance comme proxy car le prix est proportionnel
            $weight = ($criterion === 'duration') ? $dur : $dist;
            
            if (isset($graph[$u])) {
                // En cas de doublons (mêmes arrêts mais temps différents), on garde le plus avantageux
                if (!isset($graph[$u][$v]) || $weight < $graph[$u][$v]) {
                    $graph[$u][$v] = $weight;
                }
            }
        }

        // 3. Correspondances (Transferts entre lignes dans la même commune)
        $communeToNodes = [];
        foreach ($allNodes as $node) {
            $communeToNodes[$node['com_code_insee']][] = $node['lig_num'] . '_' . $node['com_code_insee'];
        }

        foreach ($communeToNodes as $commune => $nodeIds) {
            $uniqueNodeIds = array_unique($nodeIds);
            if (count($uniqueNodeIds) > 1) {
                foreach ($uniqueNodeIds as $u) {
                    foreach ($uniqueNodeIds as $v) {
                        if ($u === $v) continue;
                        
                        // Coût fixe d'un transfert (pénalité pour éviter les changements inutiles)
                        if ($criterion === 'duration') $transferWeight = 15.0; // 15 minutes
                        elseif ($criterion === 'price') $transferWeight = 0.1; // Pénalité minime pour le prix
                        else $transferWeight = 0.5; // 500 mètres de pénalité pour la distance
                        
                        $graph[$u][$v] = $transferWeight;
                    }
                }
            }
        }

        return $graph;
    }

    /**
     * Trouve tous les nœuds (ligne_commune) associés à un code INSEE.
     */
    private function getNodesForCommune(string $codeInsee): array
    {
        $codeInsee = trim($codeInsee);
        $sql = "SELECT DISTINCT TRIM(lig_num) as lig_num, TRIM(com_code_insee_arret) as com_code_insee FROM vik_noeud WHERE TRIM(com_code_insee_arret) = ?
                UNION
                SELECT DISTINCT TRIM(lig_num) as lig_num, TRIM(com_code_insee_suivant) as com_code_insee FROM vik_noeud WHERE TRIM(com_code_insee_suivant) = ?";
        $nodes = $this->fetchAll($sql, [$codeInsee, $codeInsee]);
        
        $result = [];
        foreach ($nodes as $n) {
            $result[] = $n['lig_num'] . '_' . $n['com_code_insee'];
        }
        return $result;
    }

    /**
     * Formate le chemin trouvé pour l'affichage et calcule les totaux.
     */
    private function formatPath(array $pathNodeIds, string $criterion): array
    {
        $segments = [];
        $currentSegment = null;

        // Regrouper les nœuds consécutifs de la même ligne en segments
        foreach ($pathNodeIds as $nodeId) {
            [$ligNum, $comCode] = explode('_', $nodeId);

            if ($currentSegment === null) {
                $currentSegment = ['lig_num' => $ligNum, 'stops' => [$comCode]];
            } elseif ($currentSegment['lig_num'] === $ligNum) {
                if (end($currentSegment['stops']) !== $comCode) {
                    $currentSegment['stops'][] = $comCode;
                }
            } else {
                if (count($currentSegment['stops']) >= 2) {
                    $segments[] = $currentSegment;
                }
                $currentSegment = ['lig_num' => $ligNum, 'stops' => [$comCode]];
            }
        }
        if ($currentSegment !== null && count($currentSegment['stops']) >= 2) {
            $segments[] = $currentSegment;
        }

        // Calculer les détails pour chaque segment
        $detailedSegments = [];
        $totalDistance = 0.0;
        $totalDuration = 0.0;

        foreach ($segments as $segment) {
            $start = $segment['stops'][0];
            $end = end($segment['stops']);
            $ligNum = $segment['lig_num'];

            // Récupérer les noms des communes
            $sql = "SELECT TRIM(c1.com_nom) as start_name, TRIM(c2.com_nom) as end_name
                    FROM vik_commune c1, vik_commune c2
                    WHERE TRIM(c1.com_code_insee) = ? AND TRIM(c2.com_code_insee) = ?";
            $names = $this->fetch($sql, [$start, $end]);

            // Calculer distance et durée sur ce segment
            $sql = "SELECT TRIM(com_code_insee_arret) as com_code_insee_arret, 
                           MAX(noe_distance_prochain) as noe_distance_prochain, 
                           MAX(noe_duree_prochain) as noe_duree_prochain 
                    FROM vik_noeud 
                    WHERE TRIM(lig_num) = ? 
                    GROUP BY com_code_insee_arret
                    ORDER BY MIN(noe_heure_passage) ASC";
            $nodes = $this->fetchAll($sql, [$ligNum]);

            $dist = 0.0;
            $dur = 0.0;
            $counting = false;
            foreach ($nodes as $n) {
                $nodeCode = $n['com_code_insee_arret'];
                if ($nodeCode === $start) $counting = true;
                if ($counting) {
                    if ($nodeCode === $end) break;
                    $dist += (float)str_replace(',', '.', (string)($n['noe_distance_prochain'] ?? 0));
                    $dur += (float)str_replace(',', '.', (string)($n['noe_duree_prochain'] ?? 0));
                }
            }

            // Calculer le prix pour ce segment
            $sql = "SELECT tar_num_tranche, tar_prix FROM vik_tarif WHERE tar_min_dist <= ? AND tar_max_dist >= ?";
            $tarif = $this->fetch($sql, [$dist, $dist]);
            
            if (!$tarif) {
                // Si distance hors tranches, prendre la tranche maximale
                $sqlMax = "SELECT tar_num_tranche, tar_prix FROM vik_tarif ORDER BY tar_max_dist DESC FETCH FIRST 1 ROWS ONLY";
                $tarif = $this->fetch($sqlMax);
            }

            $detailedSegments[] = [
                'lig_num' => $ligNum,
                'code_depart' => $start,
                'code_arrivee' => $end,
                'nom_depart' => $names['start_name'] ?? $start,
                'nom_arrivee' => $names['end_name'] ?? $end,
                'distance' => $dist,
                'duration' => $dur,
                'tar_num_tranche' => $tarif['tar_num_tranche'] ?? 1,
                'prix' => $tarif['tar_prix'] ?? 0
            ];

            $totalDistance += $dist;
            $totalDuration += $dur;
        }

        // Ajouter le temps des correspondances
        $numTransfers = max(0, count($detailedSegments) - 1);
        $totalDuration += $numTransfers * 15;

        return [
            'segments' => $detailedSegments,
            'total_distance' => $totalDistance,
            'total_duration' => $totalDuration,
            'total_price' => array_sum(array_column($detailedSegments, 'prix')),
            'num_transfers' => $numTransfers,
            'criterion' => $criterion
        ];
    }
}
