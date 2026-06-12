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
        $sql = "SELECT DISTINCT TRIM(c.com_code_insee) as com_code_insee, TRIM(c.com_nom) as com_nom
                FROM vik_commune c
                JOIN vik_noeud n ON TRIM(n.com_code_insee_arret) = TRIM(c.com_code_insee)
                ORDER BY com_nom ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Algorithme de Dijkstra pour trouver le plus court, le plus rapide ou le moins cher chemin.
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

        foreach ($startNodes as $startNode) {
            if (isset($distances[$startNode])) {
                $distances[$startNode] = 0;
                $queue[$startNode] = 0;
            }
        }

        // 3. Boucle principale
        while (!empty($queue)) {
            asort($queue);
            $u = array_key_first($queue);
            $distU = $queue[$u];
            unset($queue[$u]);

            if ($distU === INF) {
                break;
            }

            if (in_array($u, $endNodes)) {
                $path = [];
                $current = $u;
                while ($previous[$current] !== null) {
                    array_unshift($path, $current);
                    $current = $previous[$current];
                }
                array_unshift($path, $current);
                
                return $this->formatPath($path, $criterion);
            }

            if (isset($graph[$u])) {
                foreach ($graph[$u] as $v => $weight) {
                    if (isset($queue[$v]) || (isset($distances[$v]) && !isset($queue[$v]))) {
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

    private function buildGraph(string $criterion): array
    {
        $graph = [];

        // 1. Initialiser TOUS les nœuds possibles du réseau
        $sql = "SELECT DISTINCT TRIM(lig_num) as lig_num, TRIM(com_code_insee_arret) as com_code_insee_arret FROM vik_noeud";
        $allNodes = $this->fetchAll($sql);
        foreach ($allNodes as $node) {
            $nodeId = $node['lig_num'] . '_' . $node['com_code_insee_arret'];
            $graph[$nodeId] = [];
        }

        // 2. Ajouter les segments de ligne (Aller et Retour)
        $sql = "SELECT TRIM(lig_num) as lig_num, 
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
            
            $weight = ($criterion === 'duration') ? (float)$edge['noe_duree_prochain'] : (float)$edge['noe_distance_prochain'];
            
            if (isset($graph[$u])) {
                $graph[$u][$v] = $weight;
            }
            
            // Pour maximiser les chances de trouver un chemin sur un réseau parfois 
            // partiellement renseigné, on peut autoriser le retour sur le même segment 
            // (bidirectionnel) si la ligne le permet.
            if (isset($graph[$v])) {
                $graph[$v][$u] = $weight;
            }
        }

        // 3. Ajouter les correspondances (Transferts entre lignes dans la même ville)
        $communeToNodes = [];
        foreach ($allNodes as $node) {
            $communeToNodes[$node['com_code_insee_arret']][] = $node['lig_num'] . '_' . $node['com_code_insee_arret'];
        }

        foreach ($communeToNodes as $commune => $nodeIds) {
            $uniqueNodeIds = array_unique($nodeIds);
            if (count($uniqueNodeIds) > 1) {
                foreach ($uniqueNodeIds as $u) {
                    foreach ($uniqueNodeIds as $v) {
                        if ($u === $v) continue;
                        
                        // Pénalités de transfert
                        if ($criterion === 'duration') $transferWeight = 10.0;
                        elseif ($criterion === 'price') $transferWeight = 50.0;
                        else $transferWeight = 0.0;
                        
                        $graph[$u][$v] = $transferWeight;
                    }
                }
            }
        }

        return $graph;
    }

    private function getNodesForCommune(string $codeInsee): array
    {
        $sql = "SELECT DISTINCT TRIM(lig_num) as lig_num, TRIM(com_code_insee_arret) as com_code_insee_arret 
                FROM vik_noeud 
                WHERE TRIM(com_code_insee_arret) = ?";
        $nodes = $this->fetchAll($sql, [trim($codeInsee)]);
        $result = [];
        foreach ($nodes as $n) {
            $result[] = $n['lig_num'] . '_' . $n['com_code_insee_arret'];
        }
        return $result;
    }

    private function formatPath(array $pathNodeIds, string $criterion): array
    {
        $segments = [];
        $currentSegment = null;

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

        $detailedSegments = [];
        $totalDistance = 0;
        $totalDuration = 0;

        foreach ($segments as $segment) {
            $start = $segment['stops'][0];
            $end = end($segment['stops']);
            $ligNum = $segment['lig_num'];

            $sql = "SELECT c1.com_nom as start_name, c2.com_nom as end_name
                    FROM vik_commune c1, vik_commune c2
                    WHERE c1.com_code_insee = ? AND c2.com_code_insee = ?";
            $names = $this->fetch($sql, [$start, $end]);

            $sql = "SELECT TRIM(com_code_insee_arret) as com_code_insee_arret, 
                           noe_distance_prochain, 
                           noe_duree_prochain 
                    FROM vik_noeud 
                    WHERE TRIM(lig_num) = ? 
                    ORDER BY noe_heure_passage ASC";
            $nodes = $this->fetchAll($sql, [$ligNum]);
            
            $dist = 0;
            $dur = 0;
            $counting = false;
            foreach ($nodes as $n) {
                if ($n['com_code_insee_arret'] === $start) $counting = true;
                if ($counting) {
                    if ($n['com_code_insee_arret'] === $end) break;
                    $dist += (float)$n['noe_distance_prochain'];
                    $dur += (float)$n['noe_duree_prochain'];
                }
            }

            $totalDistance += $dist;
            $totalDuration += $dur;

            $sql = "SELECT tar_num_tranche, tar_prix FROM vik_tarif WHERE tar_min_dist <= ? AND tar_max_dist >= ?";
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
