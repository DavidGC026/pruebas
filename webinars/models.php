<?php
require_once __DIR__ . '/../connectionw.php';

class WebinarModel
{
    private $db;

    // Inyección de dependencias para mejor testabilidad
    public function __construct(DatabaseW $db)
    {
        $this->db = $db;
    }

    // ================= MÉTODOS DE TRANSACCIÓN =================
    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollBack()
    {
        $this->db->rollBack();
    }

    // ================= MÉTODOS WEBINARS =================
    public function getAllWebinars(): array
    {
        return $this->db->getWebinars();
    }

    public function getActiveWebinars(): array
    {
        return $this->db->getActiveWebinars();
    }

    public function getWebinarById(int $id): ?array
    {
        return $this->db->getWebinarById($id);
    }

    public function getWebinarsByCategory(string $category): array
    {
        return $this->db->getWebinarsByCategory($category);
    }

    public function getCategories(): array
    {
        return $this->db->getCategories();
    }

    // ================= MÉTODOS PARA VERIFICAR WEBINARS ADQUIRIDOS =================
    public function hasUserAccessToWebinar(int $user_id, int $webinar_id): bool
    {
        return $this->db->hasUserAccessToWebinar($user_id, $webinar_id);
    }

    public function getUserOwnedWebinarIds(int $user_id): array
    {
        try {
            $ownedWebinars = $this->getUserAcceptedWebinars($user_id);
            $webinarIds = [];

            foreach ($ownedWebinars as $webinar) {
                $webinarIds[] = $webinar['webinar_id'];
            }

            return $webinarIds;
        } catch (Exception $e) {
            error_log("Error obteniendo webinars del usuario: " . $e->getMessage());
            return [];
        }
    }

    public function getAllWebinarsWithOwnershipStatus(int $user_id): array
    {
        $webinars = $this->getAllWebinars();
        $ownedWebinarIds = $this->getUserOwnedWebinarIds($user_id);

        // Marcar cada webinar con su estado de propiedad
        foreach ($webinars as &$webinar) {
            $webinar['is_owned'] = in_array($webinar['webinar_id'], $ownedWebinarIds);
        }

        return $webinars;
    }

    // ================= MÉTODOS CARRITO =================
    public function getOrCreateUserCart(int $user_id): int
    {
        return $this->db->getOrCreateUserCart($user_id);
    }

    public function isWebinarInCart(int $user_id, int $webinar_id): bool
    {
        return $this->db->isWebinarInCart($user_id, $webinar_id);
    }

    public function addToCart(int $user_id, int $webinar_id, int $quantity = 1): void
    {
        // Verificar existencia del webinar primero
        if (!$this->getWebinarById($webinar_id)) {
            throw new RuntimeException("El webinar no existe");
        }

        // Verificar si el usuario ya tiene acceso a este webinar
        if ($this->hasUserAccessToWebinar($user_id, $webinar_id)) {
            throw new RuntimeException("Ya tienes acceso a este webinar");
        }

        // Verificar si el webinar ya está en el carrito
        if ($this->isWebinarInCart($user_id, $webinar_id)) {
            throw new RuntimeException("Este webinar ya está en tu carrito");
        }

        $this->db->addToCart($user_id, $webinar_id, $quantity);
    }

    public function updateCartItem(int $user_id, int $webinar_id, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeCartItem($user_id, $webinar_id);
            return;
        }

        $this->db->updateCartItem($user_id, $webinar_id, $quantity);
    }

    public function removeCartItem(int $user_id, int $webinar_id): void
    {
        $this->db->removeCartItem($user_id, $webinar_id);
    }

    public function getCartItems(int $user_id): array
    {
        return $this->db->getCartItems($user_id);
    }

    public function getCartItemCount(int $user_id): int
    {
        return $this->db->getCartItemCount($user_id);
    }

    public function clearCart(int $user_id): void
    {
        $this->db->clearCart($user_id);
    }

    // ================= MÉTODOS PEDIDOS =================
    public function registerOrder(int $user_id, string $order_id): bool
    {
        $items = $this->getCartItems($user_id);
        if (empty($items)) {
            throw new RuntimeException("Carrito vacío");
        }

        // Verificar que ningún item del carrito ya esté en posesión del usuario
        foreach ($items as $item) {
            if ($this->hasUserAccessToWebinar($user_id, $item['webinar_id'])) {
                throw new RuntimeException("Uno o más webinars en tu carrito ya los tienes");
            }
        }

        // Calcular total
        $total = array_sum(array_map(
            fn($item) => $item['cantidad'] * $item['precio_unitario'],
            $items
        ));

        // Preparar items para el pedido
        $orderItems = [];
        foreach ($items as $item) {
            $orderItems[] = [
                'webinar_id' => $item['webinar_id'],
                'titulo' => $item['titulo'],
                'cantidad' => $item['cantidad'],
                'precio' => $item['precio_unitario'],
                'tipo' => 'webinar'
            ];
        }

        // Convertir items a formato JSON
        $items_json = json_encode($orderItems);

        return $this->db->registerOrder($user_id, $items_json, $total, $order_id);
    }

    public function updateOrderStatus(string $order_id, string $status): bool
    {
        return $this->db->updateOrderStatus($order_id, $status);
    }

    public function getPedidosByUser(int $user_id): array
    {
        return $this->db->getPedidosByUser($user_id);
    }

    // ================= WEBINARS ADQUIRIDOS =================
    public function getUserAcceptedWebinars(int $user_id): array
    {
        return $this->db->getUserAcceptedWebinars($user_id);
    }

    public function getMisWebinars(int $user_id): array
    {
        return $this->getUserAcceptedWebinars($user_id);
    }
}