<?php

// controllers/Reservation/CartController.php

declare(strict_types=1);

namespace Controllers\Reservation;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;

#[RequirePrivilege(Privilege::GUEST)]
class CartController extends Controller
{
    public function get(): void
    {
        $cart = $_SESSION['cart'] ?? [];
        $totalPrice = 0.0;
        foreach ($cart as $item) {
            $totalPrice += $item['prix_total'];
        }

        $this->data['cart'] = $cart;
        $this->data['total_price'] = $totalPrice;
        $this->data['connected'] = isset($_SESSION['userId']);

        $gradeDiscountPercent = 0;
        $gradeDiscountValue = 0.0;
        $finalPrice = $totalPrice;

        if ($this->data['connected']) {
            // Include UserModel logic here, but we need the namespace
            $userModel = new \Models\User\UserModel();
            $user = $userModel->getUserById((int)$_SESSION['userId']);
            $typReduc = (int)($user['typ_reduc'] ?? 100);
            
            // typ_reduc est la part à payer (ex: 95 = on paie 95% = 5% de réduction)
            if ($typReduc === 0) {
                $typReduc = 100; // Sécurité si NULL ou 0
            }

            if ($typReduc > 0 && $typReduc < 100) {
                $gradeDiscountPercent = 100 - $typReduc;
                $gradeDiscountValue = $totalPrice * ($gradeDiscountPercent / 100.0);
                $finalPrice -= $gradeDiscountValue;
            }
        }

        $this->data['grade_discount_percent'] = $gradeDiscountPercent;
        $this->data['grade_discount_value'] = $gradeDiscountValue;
        $this->data['final_price'] = $finalPrice;

        $this->render();
    }

    public function post(): void
    {
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'remove') {
            $index = (int)($_POST['index'] ?? -1);
            if (isset($_SESSION['cart'][$index])) {
                unset($_SESSION['cart'][$index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
                $_SESSION['flash_success'] = "Trajet retiré du panier.";
            }
            redirect('index.php?route=reservation/cart');
        } elseif ($action === 'clear') {
            $_SESSION['cart'] = [];
            $_SESSION['flash_success'] = "Panier vidé.";
            redirect('index.php?route=reservation/cart');
        } else {
            // Unexpected action
            redirect('index.php?route=reservation/cart');
        }
    }
}
