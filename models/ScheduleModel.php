<?php

// models/ScheduleModel.php

declare(strict_types=1);

namespace Models;

use Core\Model;

class ScheduleModel extends Model
{
    /**
     * Retourne toutes les stations d'une ligne avec leurs horaires,
     * triées par heure de passage.
     */
    public function getSchedule(string $ligNum): array
    {
        $sql = "SELECT n.com_code_insee_arret,
                       c.com_nom            AS arret_nom,
                       n.noe_heure_passage,
                       n.noe_distance_prochain,
                       n.noe_duree_prochain,
                       n.com_code_insee_suivant
                FROM vik_noeud n
                JOIN vik_commune c ON n.com_code_insee_arret = c.com_code_insee
                WHERE n.lig_num = ?
                ORDER BY n.noe_heure_passage ASC";
        return $this->fetchAll($sql, [$ligNum]);
    }

    /**
     * Retourne les informations d'une ligne (communes départ/arrivée).
     */
    public function getLine(string $ligNum): mixed
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
     * Retourne toutes les stations d'une ligne avec leurs noms,
     * utilisable pour construire un sélecteur d'arrêts.
     */
    public function getStops(int $ligNum): array
    {
        $sql = "SELECT n.com_code_insee_arret AS code,
                       c.com_nom              AS nom,
                       n.noe_heure_passage
                FROM vik_noeud n
                JOIN vik_commune c ON n.com_code_insee_arret = c.com_code_insee
                WHERE n.lig_num = ?
                ORDER BY n.noe_heure_passage ASC";
        return $this->fetchAll($sql, [$ligNum]);
    }
}
