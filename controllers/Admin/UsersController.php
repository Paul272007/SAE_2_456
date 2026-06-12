<?php

// controllers/Admin/UsersController.php

declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\Admin\AdminModel;

#[RequirePrivilege(Privilege::ADMIN)]
class UsersController extends Controller
{
    public function get(): void
    {
        $model = new AdminModel();
        
        $filterActivity = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'cli_num';
        $order = $_GET['order'] ?? 'DESC';
        $filterNiveau = isset($_GET['niveau']) && $_GET['niveau'] !== '' ? (int)$_GET['niveau'] : null;
        $filterStatut = isset($_GET['statut']) && $_GET['statut'] !== '' ? (int)$_GET['statut'] : null;
        
        $this->data['users'] = $model->getUsers($filterActivity, $sort, $order, $filterNiveau, $filterStatut);
        $this->data['levels'] = $model->getLevels();
        
        $this->data['filter'] = $filterActivity;
        $this->data['sort'] = $sort;
        $this->data['order'] = $order;
        $this->data['filterNiveau'] = $filterNiveau;
        $this->data['filterStatut'] = $filterStatut;
        
        $this->render();
    }

    public function post(): void
    {
        $action = $_POST['action'] ?? '';

        if ($action === 'clean_inactive') {
            $model = new AdminModel();
            try {
                $deletedCount = $model->deleteInactiveUsers();
                $_SESSION['flash_success'] = "$deletedCount utilisateur(s) inactif(s) depuis plus de 2 ans ont été supprimés avec succès.";
            } catch (\Exception $e) {
                $_SESSION['flash_error'] = "Impossible de supprimer certains utilisateurs. Ils ont peut-être des réservations en cours.";
            }
        }

        redirect('index.php?route=admin/users');
    }
}
