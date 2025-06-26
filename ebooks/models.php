<?php
require_once __DIR__ . '/../connectione.php';

class EbookModel
{
    private $db;

    // Inyección de dependencias para mejor testabilidad
    public function __construct(Database3 $db)
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

    // ================= MÉTODOS EBOOKS =================
    public function getAllEbooks(): array
    {
        $ebooks = $this->db->getEbooks();
        return $this->addCoverUrls($ebooks);
    }

    public function getEbookById(int $id): ?array
    {
        $ebook = $this->db->getEbookById($id);
        if ($ebook) {
            return $this->addCoverUrl($ebook);
        }
        return null;
    }

    /**
     * Genera la URL de la portada basándose en el archivo_url
     */
    private function generateCoverUrl(string $archivo_url): string
    {
        // Eliminar la parte "/var/www/sources/libros/" del archivo_url
        $filename = str_replace('/var/www/sources/libros/', '', $archivo_url);
        
        // Cambiar la extensión .pdf por .jpg (o .png si prefieres)
        $coverFilename = preg_replace('/\.pdf$/i', '.jpg', $filename);
        
        // Retornar la URL completa hacia la carpeta covers
        return 'covers/' . $coverFilename;
    }

    /**
     * Añade la URL de portada a un ebook individual
     */
    private function addCoverUrl(array $ebook): array
    {
        $ebook['portada_url'] = $this->generateCoverUrl($ebook['archivo_url']);
        return $ebook;
    }

    /**
     * Añade la URL de portada a un array de ebooks
     */
    private function addCoverUrls(array $ebooks): array
    {
        foreach ($ebooks as &$ebook) {
            $ebook['portada_url'] = $this->generateCoverUrl($ebook['archivo_url']);
        }
        return $ebooks;
    }

    // ================= MÉTODOS PARA VERIFICAR EBOOKS ADQUIRIDOS =================
    public function isEbookOwnedByUser(int $user_id, int $ebook_id): bool
    {
        try {
            // Verificar en la tabla de ebooks adquiridos usando el mismo método que getUserAcceptedEbooks
            $ownedEbooks = $this->getUserAcceptedEbooks($user_id);
            
            foreach ($ownedEbooks as $ebook) {
                if ($ebook['ebook_id'] == $ebook_id) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error verificando ebook adquirido: " . $e->getMessage());
            return false;
        }
    }

    public function getUserOwnedEbookIds(int $user_id): array
    {
        try {
            // Obtener los IDs de los ebooks adquiridos usando el mismo método que getUserAcceptedEbooks
            $ownedEbooks = $this->getUserAcceptedEbooks($user_id);
            $ebookIds = [];
            
            foreach ($ownedEbooks as $ebook) {
                $ebookIds[] = $ebook['ebook_id'];
            }
            
            return $ebookIds;
        } catch (Exception $e) {
            error_log("Error obteniendo ebooks del usuario: " . $e->getMessage());
            return [];
        }
    }

    public function getAllEbooksWithOwnershipStatus(int $user_id): array
    {
        $ebooks = $this->getAllEbooks();
        $ownedEbookIds = $this->getUserOwnedEbookIds($user_id);
        
        // Marcar cada ebook con su estado de propiedad
        foreach ($ebooks as &$ebook) {
            $ebook['is_owned'] = in_array($ebook['ebook_id'], $ownedEbookIds);
        }
        
        return $ebooks;
    }

    // ================= MÉTODOS CARRITO =================
    public function getOrCreateUserCart(int $user_id): int
    {
        return $this->db->getOrCreateUserCart($user_id);
    }

    public function isEbookInCart(int $user_id, int $ebook_id): bool
    {
        try {
            $cart_id = $this->db->getOrCreateUserCart($user_id);
            $stmt = $this->db->executeQuery("
                SELECT 1 
                FROM carrito_items_ebooks 
                WHERE carrito_id = ? AND ebook_id = ?
            ", [$cart_id, $ebook_id]);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error verificando carrito: " . $e->getMessage());
            return false;
        }
    }

    public function addToCart(int $user_id, int $ebook_id, int $quantity = 1): void
    {
        // Verificar existencia del ebook primero
        if (!$this->getEbookById($ebook_id)) {
            throw new RuntimeException("El ebook no existe");
        }

        // Verificar si el usuario ya posee este ebook
        if ($this->isEbookOwnedByUser($user_id, $ebook_id)) {
            throw new RuntimeException("Ya posees este ebook");
        }

        // Verificar si el ebook ya está en el carrito
        if ($this->isEbookInCart($user_id, $ebook_id)) {
            // Si ya está en el carrito, no hacemos nada (o podríamos lanzar una excepción)
            return;
        }

        // Siempre agregamos con cantidad 1
        $this->db->addToCart($user_id, $ebook_id, 1);
    }

    public function updateCartItem(int $user_id, int $ebook_id, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeCartItem($user_id, $ebook_id);
            return;
        }

        // Siempre establecer la cantidad a 1
        $this->db->updateCartItem($user_id, $ebook_id, 1);
    }

    public function removeCartItem(int $user_id, int $ebook_id): void
    {
        $this->db->removeCartItem($user_id, $ebook_id);
    }

    public function getCartItems(int $user_id): array
    {
        $items = $this->db->getCartItems($user_id);
        
        // Añadir URLs de portada y asegurar que todas las cantidades sean 1
        foreach ($items as &$item) {
            $item['cantidad'] = 1;
            if (isset($item['archivo_url'])) {
                $item['portada_url'] = $this->generateCoverUrl($item['archivo_url']);
            }
        }
        
        return $items;
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
    public function registerOrder(int $user_id): int
    {
        $items = $this->getCartItems($user_id);
        if (empty($items)) {
            throw new RuntimeException("Carrito vacío");
        }

        // Verificar que ningún item del carrito ya esté en posesión del usuario
        foreach ($items as $item) {
            if ($this->isEbookOwnedByUser($user_id, $item['ebook_id'])) {
                throw new RuntimeException("Uno o más ebooks en tu carrito ya los posees");
            }
        }

        // Asegurar que el precio sea el precio unitario directamente ya que la cantidad es siempre 1
        $total = array_sum(array_map(
            fn($item) => $item['precio_unitario'],
            $items
        ));

        return $this->db->registerOrder($user_id, $items, $total);
    }

    public function getPedidosByUser(int $user_id): array
    {
        return $this->db->getPedidosByUser($user_id);
    }

    // ================= EBOOKS ADQUIRIDOS =================
    public function getUserAcceptedEbooks(int $user_id): array
    {
        $ebooks = $this->db->getUserAcceptedEbooks($user_id);
        return $this->addCoverUrls($ebooks);
    }

    public function getMisEbooks(int $user_id): array
    {
        return $this->getUserAcceptedEbooks($user_id);
    }
}