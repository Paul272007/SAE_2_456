<?php

// models/LinesModel.php

declare(strict_types=1);

namespace Models;

use Core\Model;

class LinesModel extends Model
{
    /**
     * Retourne toutes les lignes avec leur commune de départ et d'arrivée.
     */
    public function getLines(): array
    {
        $sql = "SELECT l.lig_num,
                       c1.com_nom AS commune_depart,
                       c2.com_nom AS commune_arrivee,
                       l.com_code_insee_debu,
                       l.com_code_insee_term
                FROM vik_ligne l
                JOIN vik_commune c1 ON l.com_code_insee_debu = c1.com_code_insee
                JOIN vik_commune c2 ON l.com_code_insee_term = c2.com_code_insee
                ORDER BY l.lig_num";
        return $this->fetchAll($sql);
    }

    /**
     * Retourne une ligne par son numéro.
     */
    public function getLineById(int $ligNum): mixed
    {
        $sql = "SELECT l.lig_num,
                       c1.com_nom AS commune_depart,
                       c2.com_nom AS commune_arrivee,
                       l.com_code_insee_debu,
                       l.com_code_insee_term
                FROM vik_ligne l
                JOIN vik_commune c1 ON l.com_code_insee_debu = c1.com_code_insee
                JOIN vik_commune c2 ON l.com_code_insee_term = c2.com_code_insee
                WHERE l.lig_num = ?";
        return $this->fetch($sql, [$ligNum]);
    }

    /**
     * Retourne des détails complémentaires pour chaque ligne
     * (nombre d'arrêts, distance totale, durée totale).
     */
    public function getLinesWithDetails(): array
    {
        $sql = "SELECT l.lig_num,
                       c1.com_nom AS commune_depart,
                       c2.com_nom AS commune_arrivee,
                       (SELECT COUNT(DISTINCT n.com_code_insee_arret) FROM vik_noeud n WHERE n.lig_num = l.lig_num) AS nb_arrets,
                       (SELECT NVL(SUM(dist), 0) FROM (SELECT DISTINCT lig_num, com_code_insee_arret, com_code_insee_suivant, noe_distance_prochain AS dist FROM vik_noeud) nd WHERE nd.lig_num = l.lig_num) AS distance_totale,
                       (SELECT NVL(SUM(dur), 0) FROM (SELECT DISTINCT lig_num, com_code_insee_arret, com_code_insee_suivant, noe_duree_prochain AS dur FROM vik_noeud) nd WHERE nd.lig_num = l.lig_num) AS duree_totale
                FROM vik_ligne l
                JOIN vik_commune c1 ON l.com_code_insee_debu = c1.com_code_insee
                JOIN vik_commune c2 ON l.com_code_insee_term = c2.com_code_insee
                ORDER BY l.lig_num";
        return $this->fetchAll($sql);
    }
}