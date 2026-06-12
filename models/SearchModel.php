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
    public function findPaths(string $startCode, string $endCode, string $criterion = 'duration', string $time = '00:00'): array
    {
        $startNodes = $this->getNodesForCommune($startCode);
        $endNodes = $this->getNodesForCommune($endCode);

        if (empty($startNodes) || empty($endNodes)) {
            return []; 
        }

        // Try both criteria to find candidate paths
        $candidateSegments = [];
        foreach (['duration', 'distance'] as $crit) {
            $segs = $this->dijkstraPath($startNodes, $endNodes, $crit);
            if (!empty($segs)) {
                $key = $this->segmentsKey($segs);
                $candidateSegments[$key] = $segs;
            }
        }

        if (empty($candidateSegments)) return [];

        // Fetch line data for all segments
        $lineData = [];
        foreach ($candidateSegments as $segments) {
            foreach ($segments as $seg) {
                if (!isset($lineData[$seg['lig_num']])) {
                    $sql = "SELECT com_code_insee_arret, com_code_insee_suivant, TO_CHAR(noe_heure_passage, 'HH24:MI') as noe_heure_passage, noe_duree_prochain, noe_distance_prochain 
                            FROM vik_noeud WHERE TRIM(lig_num) = ?";
                    $lineData[$seg['lig_num']] = $this->fetchAll($sql, [$seg['lig_num']]);
                }
            }
        }

        // Build up to 3 schedule instances per candidate path
        $allResults = [];
        $seenKeys = [];
        foreach ($candidateSegments as $segments) {
            $searchTime = $time;
            for ($i = 0; $i < 3; $i++) {
                $instance = $this->buildPathInstance($segments, $lineData, $searchTime, $criterion);
                if (!$instance) break;
                
                $instanceKey = $instance['heure_depart'] . '_' . $instance['heure_arrivee'] . '_' . $instance['total_distance'];
                if (!isset($seenKeys[$instanceKey])) {
                    $seenKeys[$instanceKey] = true;
                    $allResults[] = $instance;
                }
                $searchTime = $this->addMinutes($instance['segments'][0]['heure_depart'], 1);
            }
        }

        // Sort by chosen criterion
        usort($allResults, function ($a, $b) use ($criterion) {
            if ($criterion === 'distance') {
                return $a['total_distance'] <=> $b['total_distance'] ?: $a['total_duration'] <=> $b['total_duration'];
            } elseif ($criterion === 'price') {
                return $a['total_price'] <=> $b['total_price'] ?: $a['total_duration'] <=> $b['total_duration'];
            }
            return $a['total_duration'] <=> $b['total_duration'] ?: $a['total_distance'] <=> $b['total_distance'];
        });

        return array_slice($allResults, 0, 5);
    }

    private function segmentsKey(array $segments): string
    {
        $parts = [];
        foreach ($segments as $seg) {
            $parts[] = $seg['lig_num'] . ':' . implode('-', $seg['stops']);
        }
        return implode('|', $parts);
    }

    private function dijkstraPath(array $startNodes, array $endNodes, string $criterion): array
    {
        $graph = $this->buildGraph($criterion);

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

        $pathNodeIds = [];
        while (!empty($queue)) {
            asort($queue);
            $u = array_key_first($queue);
            $distU = $queue[$u];
            unset($queue[$u]);

            if ($distU === INF) break;

            if (in_array($u, $endNodes)) {
                $current = $u;
                while ($previous[$current] !== null) {
                    array_unshift($pathNodeIds, $current);
                    $current = $previous[$current];
                }
                array_unshift($pathNodeIds, $current);
                break;
            }

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

        if (empty($pathNodeIds)) return [];

        // Group node IDs into segments by line
        $segments = [];
        $currentSegment = null;

        foreach ($pathNodeIds as $nodeId) {
            [$ligNum, $comCode] = explode('_', $nodeId);
            $ligNum = trim((string)$ligNum);

            if ($currentSegment === null) {
                $currentSegment = ['lig_num' => $ligNum, 'stops' => [$comCode]];
            } elseif ($currentSegment['lig_num'] === $ligNum) {
                $currentSegment['stops'][] = $comCode;
            } else {
                $segments[] = $currentSegment;
                $currentSegment = ['lig_num' => $ligNum, 'stops' => [$comCode]];
            }
        }
        if ($currentSegment !== null) {
            $segments[] = $currentSegment;
        }

        return $segments;
    }

    private function buildPathInstance(array $segments, array $lineData, string $startTime, string $criterion): ?array
    {
        $detailedSegments = [];
        $totalDistance = 0;
        $totalDuration = 0;
        $currentTime = $startTime;
        $totalWaitTime = 0;

        foreach ($segments as $index => $segment) {
            if (count($segment['stops']) < 2) continue;
            
            $ligNum = $segment['lig_num'];
            $startStop = $segment['stops'][0];
            $endStop = end($segment['stops']);

            $validDepartures = [];
            foreach ($lineData[$ligNum] as $e) {
                if ($e['com_code_insee_arret'] === $startStop && $e['com_code_insee_suivant'] === $segment['stops'][1]) {
                    if ($e['noe_heure_passage'] >= $currentTime) {
                        $validDepartures[] = $e['noe_heure_passage'];
                    }
                }
            }
            if (empty($validDepartures)) return null; 
            
            sort($validDepartures);
            $segmentDepartureTime = $validDepartures[0];
            
            if ($index > 0) {
                $waitMins = $this->diffMinutes($currentTime, $segmentDepartureTime);
                $totalWaitTime += $waitMins;
                $totalDuration += $waitMins; 
            }

            $currentStopTime = $segmentDepartureTime;
            $segDist = 0;
            $segDur = 0;

            for ($k = 0; $k < count($segment['stops']) - 1; $k++) {
                $curr = $segment['stops'][$k];
                $next = $segment['stops'][$k+1];
                
                $foundEdge = null;
                foreach ($lineData[$ligNum] as $e) {
                    if ($e['com_code_insee_arret'] === $curr && $e['com_code_insee_suivant'] === $next && $e['noe_heure_passage'] === $currentStopTime) {
                        $foundEdge = $e; break;
                    }
                }
                if (!$foundEdge) {
                    foreach ($lineData[$ligNum] as $e) {
                        if ($e['com_code_insee_arret'] === $curr && $e['com_code_insee_suivant'] === $next) {
                            $foundEdge = $e; break;
                        }
                    }
                }
                if (!$foundEdge) return null;

                $segDist += (float)$foundEdge['noe_distance_prochain'];
                $segDur += (float)$foundEdge['noe_duree_prochain'];
                $currentStopTime = $this->addMinutes($currentStopTime, (int)$foundEdge['noe_duree_prochain']);
            }

            $segmentArrivalTime = $currentStopTime;
            
            $sqlNames = "SELECT c1.com_nom as start_name, c2.com_nom as end_name FROM vik_commune c1, vik_commune c2 WHERE c1.com_code_insee = ? AND c2.com_code_insee = ?";
            $names = $this->fetch($sqlNames, [$startStop, $endStop]);

            $sqlTarif = "SELECT tar_num_tranche, tar_prix FROM vik_tarif WHERE tar_min_dist <= ? AND tar_max_dist >= ? FETCH FIRST 1 ROWS ONLY";
            $tarif = $this->fetch($sqlTarif, [$segDist, $segDist]);

            $detailedSegments[] = [
                'lig_num' => $ligNum,
                'code_depart' => $startStop,
                'code_arrivee' => $endStop,
                'nom_depart' => $names['start_name'] ?? $startStop,
                'nom_arrivee' => $names['end_name'] ?? $endStop,
                'heure_depart' => $segmentDepartureTime,
                'heure_arrivee' => $segmentArrivalTime,
                'distance' => $segDist,
                'duration' => $segDur,
                'prix' => $tarif['tar_prix'] ?? 0,
                'tar_num_tranche' => $tarif['tar_num_tranche'] ?? 1
            ];

            $totalDistance += $segDist;
            $totalDuration += $segDur;
            $currentTime = $segmentArrivalTime;
        }

        if (empty($detailedSegments)) return null;

        $numTransfers = max(0, count($detailedSegments) - 1);
        $realDeparture = $detailedSegments[0]['heure_depart'];
        $realArrival = end($detailedSegments)['heure_arrivee'];
        $wallClockDuration = $this->diffMinutes($realDeparture, $realArrival);
        
        return [
            'segments' => $detailedSegments,
            'total_distance' => $totalDistance,
            'total_duration' => $wallClockDuration, 
            'total_price' => array_sum(array_column($detailedSegments, 'prix')),
            'num_transfers' => $numTransfers,
            'criterion' => $criterion,
            'heure_depart' => $realDeparture,
            'heure_arrivee' => $realArrival
        ];
    }

    private function addMinutes(string $time, int $minutes): string
    {
        [$h, $m] = explode(':', $time);
        $totalMins = (int)$h * 60 + (int)$m + $minutes;
        $newH = floor($totalMins / 60) % 24;
        $newM = $totalMins % 60;
        return sprintf('%02d:%02d', $newH, $newM);
    }
    
    private function diffMinutes(string $time1, string $time2): int
    {
        [$h1, $m1] = explode(':', $time1);
        [$h2, $m2] = explode(':', $time2);
        $mins1 = (int)$h1 * 60 + (int)$m1;
        $mins2 = (int)$h2 * 60 + (int)$m2;
        $diff = $mins2 - $mins1;
        if ($diff < 0) $diff += 24 * 60; 
        return $diff;
    }

    private function buildGraph(string $criterion): array
    {
        $graph = [];

        $sql = "SELECT lig_num, com_code_insee_arret, com_code_insee_suivant, MAX(noe_distance_prochain) as noe_distance_prochain, MAX(noe_duree_prochain) as noe_duree_prochain
                FROM vik_noeud
                WHERE com_code_insee_suivant IS NOT NULL
                GROUP BY lig_num, com_code_insee_arret, com_code_insee_suivant";
        $edges = $this->fetchAll($sql);

        foreach ($edges as $edge) {
            $u = $edge['lig_num'] . '_' . $edge['com_code_insee_arret'];
            $v = $edge['lig_num'] . '_' . $edge['com_code_insee_suivant'];
            
            $weight = ($criterion === 'distance') ? (float)$edge['noe_distance_prochain'] : (float)$edge['noe_duree_prochain'];
            
            if (!isset($graph[$u])) $graph[$u] = [];
            if (!isset($graph[$v])) $graph[$v] = []; 
            
            $graph[$u][$v] = $weight;
        }

        $sql = "SELECT com_code_insee_arret, lig_num FROM vik_noeud";
        $nodes = $this->fetchAll($sql);
        
        $communeToNodes = [];
        foreach ($nodes as $node) {
            $communeToNodes[$node['com_code_insee_arret']][] = $node['lig_num'] . '_' . $node['com_code_insee_arret'];
        }

        foreach ($communeToNodes as $commune => $nodeIds) {
            if (count($nodeIds) > 1) {
                for ($i = 0; $i < count($nodeIds); $i++) {
                    for ($j = $i + 1; $j < count($nodeIds); $j++) {
                        $u = $nodeIds[$i];
                        $v = $nodeIds[$j];
                        
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
}
