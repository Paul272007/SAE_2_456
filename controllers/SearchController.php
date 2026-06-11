<?php

// controllers/SearchController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\SearchModel;

#[RequirePrivilege(Privilege::USER)]
class SearchController extends Controller
{
    public function get(): void
    {
        $model = new SearchModel();
        
        $this->data['communes'] = $model->getCommunes();

        // If search parameters are present
        if (isset($_GET['depart'], $_GET['arrivee'])) {
            $depart = $_GET['depart'];
            $arrivee = $_GET['arrivee'];
            $criterion = $_GET['criterion'] ?? 'duration'; // duration or distance
            
            if ($depart !== $arrivee) {
                $path = $model->findPath($depart, $arrivee, $criterion);
                $this->data['path'] = $path;
                $this->data['search_depart'] = $depart;
                $this->data['search_arrivee'] = $arrivee;
                $this->data['search_criterion'] = $criterion;
                
                if ($path) {
                    // Set dates to today by default for display
                    $today = date('Y-m-d');
                    foreach ($this->data['path']['segments'] as &$segment) {
                        $segment['date'] = $today;
                    }
                }
            } else {
                $this->data['error'] = "Les lieux de départ et d'arrivée doivent être différents.";
            }
        }

        $this->render();
    }

    public function post(): void
    {
        
        // Ajout du trajet trouvé au panier
        if (isset($_POST['add_to_cart'])) {
            $segmentsJson = $_POST['segments'] ?? '[]';
            $segments = json_decode($segmentsJson, true);
            $date = $_POST['date'] ?? date('Y-m-d');
            
            if (is_array($segments) && !empty($segments)) {
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                foreach ($segments as $segment) {
                    $_SESSION['cart'][] = [
                        'lig_num'        => $segment['lig_num'],
                        'ligne_nom'      => 'Ligne ' . $segment['lig_num'], // Simplified
                        'code_depart'    => $segment['code_depart'],
                        'code_arrivee'   => $segment['code_arrivee'],
                        'nom_depart'     => $segment['nom_depart'],
                        'nom_arrivee'    => $segment['nom_arrivee'],
                        'date'           => $date,
                        'distance'       => $segment['distance'],
                        'tar_num_tranche'=> $segment['tar_num_tranche'],
                        'prix_total'     => $segment['prix'],
                        'nb_points'      => (int)ceil($segment['distance'] * 0.5), // Simple rules
                    ];
                }
                
                $_SESSION['flash_success'] = "Itinéraire ajouté au panier.";
                redirect('index.php?route=reservation/cart');
            }
        }
        
        redirect('index.php?route=search');
    }
}
