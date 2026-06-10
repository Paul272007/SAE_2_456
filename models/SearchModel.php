<?php

// models/SearchModel.php

declare(strict_types=1);

namespace Models;

use Core\Model;

class SearchModel extends Model
{
    /**
     * Récupère la liste de toutes les communes (pour les listes déroulantes).
     */
    public function getCommunes(): array
    {
        $sql = "SELECT DISTINCT c.com_code_insee, c.com_nom
                FROM vik_commune c
                JOIN vik_noeud n ON n.com_code_insee_arret = c.com_code_insee
                ORDER BY c.com_nom ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Algorithme de Dijkstra pour trouver le plus court ou le plus rapide chemin.
     * @param string $startCode Code INSEE de départ
     * @param string $endCode Code INSEE d'arrivée
     * @param string $criterion 'distance' ou 'duration'
     */
    public function findPath(string $startCode, string $endCode, string $criterion = 'duration'): ?array
    {
        // 1. Construire le graphe
        $graph = $this->buildGraph($criterion);

        // Nœuds de départ et d'arrivée (une commune peut avoir plusieurs nœuds si elle est sur plusieurs lignes)
        $startNodes = $this->getNodesForCommune($startCode);
        $endNodes = $this->getNodesForCommune($endCode);

        if (empty($startNodes) || empty($endNodes)) {
            return null; // Commune introuvable
        }

        // 2. Initialisation Dijkstra
        $distances = [];
        $previous = [];
        $queue = [];

        foreach ($graph as $nodeId => $edges) {
            $distances[$nodeId] = INF;
            $previous[$nodeId] = null;
            $queue[$nodeId] = INF;
        }

        // On initialise tous les nœuds de départ à 0
        foreach ($startNodes as $startNode) {
            if (isset($distances[$startNode])) {
                $distances[$startNode] = 0;
                $queue[$startNode] = 0;
            }
        }

        // 3. Boucle principale
        while (!empty($queue)) {
            // Trouver le nœud avec la distance minimum
            asort($queue);
            $u = array_key_first($queue);
            $distU = $queue[$u];
            unset($queue[$u]);

            if ($distU === INF) {
                break; // Plus aucun nœud accessible
            }

            // Si on a atteint un nœud d'arrivée
            if (in_array($u, $endNodes)) {
                // Reconstruire le chemin
                $path = [];
                $current = $u;
                while ($previous[$current] !== null) {
                    array_unshift($path, $current);
                    $current = $previous[$current];
                }
                array_unshift($path, $current);
                
                return $this->formatPath($path, $criterion);
            }

            // Mettre à jour les voisins
            if (isset($graph[$u])) {
                foreach ($graph[$u] as $v => $weight) {
                    if (isset($queue[$v])) {
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

        return null; // Aucun chemin trouvé
    }

    private function buildGraph(string $criterion): array
    {
        $graph = [];

        // Récupérer toutes les arêtes de ligne
        $sql = "SELECT lig_num, com_code_insee_arret, com_code_insee_suivant, noe_distance_prochain, noe_duree_prochain
                FROM vik_noeud
                WHERE com_code_insee_suivant IS NOT NULL";
        $edges = $this->fetchAll($sql);

        foreach ($edges as $edge) {
            $u = $edge['lig_num'] . '_' . $edge['com_code_insee_arret'];
            $v = $edge['lig_num'] . '_' . $edge['com_code_insee_suivant'];
            
            $weight = ($criterion === 'distance') ? (float)$edge['noe_distance_prochain'] : (float)$edge['noe_duree_prochain'];
            
            if (!isset($graph[$u])) $graph[$u] = [];
            if (!isset($graph[$v])) $graph[$v] = []; // Ensure destination exists
            
            $graph[$u][$v] = $weight;
        }

        // Ajouter les arêtes de correspondance (transfert entre lignes dans la même commune)
        $sql = "SELECT com_code_insee_arret, lig_num FROM vik_noeud";
        $nodes = $this->fetchAll($sql);
        
        $communeToNodes = [];
        foreach ($nodes as $node) {
            $communeToNodes[$node['com_code_insee_arret']][] = $node['lig_num'] . '_' . $node['com_code_insee_arret'];
        }

        foreach ($communeToNodes as $commune => $nodeIds) {
            if (count($nodeIds) > 1) {
                // Créer des liens entre tous les nœuds de cette commune
                for ($i = 0; $i < count($nodeIds); $i++) {
                    for ($j = $i + 1; $j < count($nodeIds); $j++) {
                        $u = $nodeIds[$i];
                        $v = $nodeIds[$j];
                        
                        // Pénalité de correspondance: 10 minutes ou 0 km
                        $transferWeight = ($criterion === 'duration') ? 10.0 : 0.0;
                        
                        $graph[$u][$v] = $transferWeight;
                        $graph[$v][$u] = $transferWeight;
                    }
                }
            }
        }

        return $graph;
    }

    private function getNodesForCommune(string $codeInsee): array
    {
        $sql = "SELECT lig_num, com_code_insee_arret FROM vik_noeud WHERE com_code_insee_arret = ?";
        $nodes = $this->fetchAll($sql, [$codeInsee]);
        $result = [];
        foreach ($nodes as $n) {
            $result[] = $n['lig_num'] . '_' . $n['com_code_insee_arret'];
        }
        return $result;
    }

    private function formatPath(array $pathNodeIds, string $criterion): array
    {
        // $pathNodeIds = ['1_49000', '1_49100', '2_49100', '2_49200']
        // We will group them by line to create a list of segments
        $segments = [];
        $currentSegment = null;

        foreach ($pathNodeIds as $nodeId) {
            [$ligNum, $comCode] = explode('_', $nodeId);
            $ligNum = (int)$ligNum;

            if ($currentSegment === null) {
                $currentSegment = ['lig_num' => $ligNum, 'stops' => [$comCode]];
            } elseif ($currentSegment['lig_num'] === $ligNum) {
                $currentSegment['stops'][] = $comCode;
            } else {
                // Line changed
                $segments[] = $currentSegment;
                $currentSegment = ['lig_num' => $ligNum, 'stops' => [$comCode]];
            }
        }
        if ($currentSegment !== null) {
            $segments[] = $currentSegment;
        }

        // Now fetch details for each segment
        $detailedSegments = [];
        $totalDistance = 0;
        $totalDuration = 0;

        foreach ($segments as $segment) {
            if (count($segment['stops']) < 2) continue; // Transfer only, skip
            
            $start = $segment['stops'][0];
            $end = end($segment['stops']);
            $ligNum = $segment['lig_num'];

            // Get names and metrics
            $sql = "SELECT c1.com_nom as start_name, c2.com_nom as end_name
                    FROM vik_commune c1, vik_commune c2
                    WHERE c1.com_code_insee = ? AND c2.com_code_insee = ?";
            $names = $this->fetch($sql, [$start, $end]);

            // Calculate distance and duration for this segment
            $sql = "SELECT com_code_insee_arret, noe_distance_prochain, noe_duree_prochain 
                    FROM vik_noeud 
                    WHERE lig_num = ? 
                    ORDER BY noe_heure_passage ASC";
            $nodes = $this->fetchAll($sql, [$ligNum]);
            
            $dist = 0;
            $dur = 0;
            $counting = false;
            foreach ($nodes as $n) {
                if ($n['com_code_insee_arret'] === $start) $counting = true;
                if ($counting) {
                    $dist += (float)$n['noe_distance_prochain'];
                    $dur += (float)$n['noe_duree_prochain'];
                }
                if ($n['com_code_insee_arret'] === $end) break;
            }

            $totalDistance += $dist;
            $totalDuration += $dur;

            // Fetch tarif info
            $sql = "SELECT tar_num_tranche, tar_prix FROM vik_tarif WHERE tar_min_dist <= ? AND tar_max_dist >= ? FETCH FIRST 1 ROWS ONLY";
            $tarif = $this->fetch($sql, [$dist, $dist]);

            $detailedSegments[] = [
                'lig_num' => $ligNum,
                'code_depart' => $start,
                'code_arrivee' => $end,
                'nom_depart' => $names['start_name'] ?? $start,
                'nom_arrivee' => $names['end_name'] ?? $end,
                'distance' => $dist,
                'duration' => $dur,
                'prix' => $tarif['tar_prix'] ?? 0,
                'tar_num_tranche' => $tarif['tar_num_tranche'] ?? 1
            ];
        }

        // Add 10min penalty per transfer for total duration
        $numTransfers = max(0, count($detailedSegments) - 1);
        $totalDuration += $numTransfers * 10;

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
