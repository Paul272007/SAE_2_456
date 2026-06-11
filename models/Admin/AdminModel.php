<?php

// models/Admin/AdminModel.php

declare(strict_types=1);

namespace Models\Admin;

use Core\Model;

class AdminModel extends Model
{
    /**
     * Récupère les statistiques globales (F16).
     */
    public function getStats(): array
    {
        $stats = [];
        
        $sql = "SELECT COUNT(*) as nb FROM vik_client";
        $stats['total_clients'] = $this->fetch($sql)['nb'] ?? 0;

        $sql = "SELECT COUNT(*) as nb FROM vik_reservation";
        $stats['total_reservations'] = $this->fetch($sql)['nb'] ?? 0;

        $sql = "SELECT SUM(res_prix_tot) as ca FROM vik_reservation";
        $stats['chiffre_affaires'] = $this->fetch($sql)['ca'] ?? 0;

        $sql = "SELECT l.lig_num, c1.com_nom as depart, c2.com_nom as arrivee, COUNT(e.lig_num) as nb_trajets
                FROM vik_etape e
                JOIN vik_ligne l ON l.lig_num = e.lig_num
                JOIN vik_commune c1 ON c1.com_code_insee = l.com_code_insee_debu
                JOIN vik_commune c2 ON c2.com_code_insee = l.com_code_insee_term
                GROUP BY l.lig_num, c1.com_nom, c2.com_nom
                ORDER BY nb_trajets DESC
                FETCH FIRST 5 ROWS ONLY";
        $stats['top_lines'] = $this->fetchAll($sql);

        $sql = "SELECT c.cli_num, c.cli_nom, c.cli_prenom, c.cli_courriel, COUNT(r.res_num) as nb_reservations, SUM(r.res_prix_tot) as total_depense
                FROM vik_client c
                JOIN vik_reservation r ON c.cli_num = r.cli_num
                GROUP BY c.cli_num, c.cli_nom, c.cli_prenom, c.cli_courriel
                ORDER BY total_depense DESC, nb_reservations DESC
                FETCH FIRST 5 ROWS ONLY";
        $stats['top_clients'] = $this->fetchAll($sql);

        return $stats;
    }

    /**
     * Liste tous les utilisateurs (F13). Peut filtrer les inactifs (F15).
     */
    public function getUsers(bool $onlyInactive = false): array
    {
        $sql = "SELECT c.cli_num, c.cli_nom, c.cli_prenom, c.cli_courriel, TO_CHAR(c.cli_date_connec, 'YYYY-MM-DD') as cli_date_connec, c.typ_num, c.is_admin, t.typ_nom
                FROM vik_client c
                LEFT JOIN vik_type_client t ON c.typ_num = t.typ_num ";
                
        // Inactif : pas connecté depuis plus de 6 mois (180 jours)
        if ($onlyInactive) {
            $sql .= " WHERE c.cli_date_connec < SYSDATE - 180 ";
        }
        
        $sql .= " ORDER BY c.cli_num DESC";

        return $this->fetchAll($sql);
    }

    /**
     * Supprime un compte utilisateur (F14).
     */
    public function deleteUser(int $cliNum): void
    {
        $sql = "DELETE FROM vik_client WHERE cli_num = ?";
        $this->runQuery($sql, [$cliNum]);
    }

    /**
     * Supprime les utilisateurs qui ne se sont pas connectés depuis 2 ans (730 jours).
     */
    public function deleteInactiveUsers(): int
    {
        $sql = "DELETE FROM vik_client WHERE cli_date_connec < SYSDATE - 730";
        $stmt = $this->runQuery($sql);
        return $stmt->rowCount();
    }

    public function updateScheduleTime(string $ligNum, string $codeArret, string $oldHeure, string $newHeure): void
    {
        $sql = "UPDATE vik_noeud 
                SET noe_heure_passage = TO_DATE(:new_heure, 'HH24:MI:SS') 
                WHERE lig_num = :lig_num 
                AND com_code_insee_arret = :arret 
                AND TO_CHAR(noe_heure_passage, 'HH24:MI:SS') = :old_heure";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'new_heure' => $newHeure,
            'lig_num'   => $ligNum,
            'arret'     => $codeArret,
            'old_heure' => $oldHeure
        ]);
    }
}
