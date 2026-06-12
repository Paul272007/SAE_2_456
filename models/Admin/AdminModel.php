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
    public function getLevels(): array
    {
        $sql = "SELECT typ_num, typ_nom FROM vik_type_client ORDER BY typ_num ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Liste tous les utilisateurs avec filtres et tris.
     */
    public function getUsers(string $filterActivity = 'all', string $sort = 'cli_num', string $order = 'DESC', ?int $filterNiveau = null, ?int $filterStatut = null, string $search = ''): array
    {
        $sql = "SELECT c.cli_num, c.cli_nom, c.cli_prenom, c.cli_courriel, TO_CHAR(c.cli_date_connec, 'YYYY-MM-DD') as cli_date_connec, c.typ_num, c.is_admin, c.is_deleted, t.typ_nom
                FROM vik_client c
                LEFT JOIN vik_type_client t ON c.typ_num = t.typ_num 
                WHERE 1=1 ";
        $params = [];

        if ($filterActivity === 'inactive') {
            $sql .= " AND c.cli_date_connec < SYSDATE - 180 AND (c.is_deleted = 0 OR c.is_deleted IS NULL) ";
        } elseif ($filterActivity === 'active') {
            $sql .= " AND (c.cli_date_connec >= SYSDATE - 180 OR c.cli_date_connec IS NULL) AND (c.is_deleted = 0 OR c.is_deleted IS NULL) ";
        } elseif ($filterActivity === 'deleted') {
            $sql .= " AND c.is_deleted = 1 ";
        }
        
        if ($filterNiveau !== null) {
            $sql .= " AND c.typ_num = ? ";
            $params[] = $filterNiveau;
        }

        if ($filterStatut !== null) {
            $sql .= " AND c.is_admin = ? ";
            $params[] = $filterStatut;
        }

        if ($search !== '') {
            $sql .= " AND (LOWER(c.cli_nom) LIKE ? 
                        OR LOWER(c.cli_prenom) LIKE ? 
                        OR LOWER(c.cli_prenom || ' ' || c.cli_nom) LIKE ? 
                        OR LOWER(c.cli_nom || ' ' || c.cli_prenom) LIKE ? 
                        OR LOWER(c.cli_courriel) LIKE ? 
                        OR TRIM(TO_CHAR(c.cli_num)) = ?) ";
            $searchParam = '%' . strtolower(trim($search)) . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = trim($search); // Pour la recherche par ID exact
        }

        $validSorts = [
            'cli_num' => 'c.cli_num',
            'cli_nom' => 'c.cli_nom',
            'cli_prenom' => 'c.cli_prenom',
            'cli_courriel' => 'c.cli_courriel',
            'cli_date_connec' => 'c.cli_date_connec'
        ];
        
        $sortColumn = $validSorts[$sort] ?? 'c.cli_num';
        $orderDir = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY $sortColumn $orderDir";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Marque un compte utilisateur comme supprimé (Soft Delete).
     */
    public function deleteUser(int $cliNum): void
    {
        $sql = "UPDATE vik_client SET is_deleted = 1 WHERE cli_num = ?";
        $this->runQuery($sql, [$cliNum]);
    }

    /**
     * Promeut un utilisateur au rang d'administrateur.
     */
    public function makeAdmin(int $cliNum): void
    {
        $sql = "UPDATE vik_client SET is_admin = 1 WHERE cli_num = ?";
        $this->runQuery($sql, [$cliNum]);
    }

    /**
     * Marque comme supprimés les utilisateurs qui ne se sont pas connectés depuis 2 ans (730 jours).
     */
    public function deleteInactiveUsers(): int
    {
        $sql = "UPDATE vik_client SET is_deleted = 1 WHERE cli_date_connec < SYSDATE - 730 AND (is_deleted = 0 OR is_deleted IS NULL)";
        $stmt = $this->runQuery($sql);
        return $stmt->rowCount();
    }

    public function updateScheduleTime(string $ligNum, string $codeArret, string $oldHeure, string $newHeure): void
    {
        if (strlen($newHeure) === 5) {
            $newHeure .= ':00';
        }
        
        // oldHeure is in HH:MM format from our inputs
        $sql = "UPDATE vik_noeud 
                SET noe_heure_passage = TO_DATE(:new_heure, 'HH24:MI:SS') 
                WHERE TRIM(lig_num) = TRIM(:lig_num) 
                AND com_code_insee_arret = :arret 
                AND TO_CHAR(noe_heure_passage, 'HH24:MI') = :old_heure";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'new_heure' => $newHeure,
            'lig_num'   => $ligNum,
            'arret'     => $codeArret,
            'old_heure' => substr($oldHeure, 0, 5) // just to be safe
        ]);
    }
}
