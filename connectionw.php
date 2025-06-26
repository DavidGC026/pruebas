<?php
require_once 'config.php';

class DatabaseW
{
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                DB_DSN,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Error de conexión (Webinars): " . $e->getMessage());
            die("Error en el sistema. Por favor intente más tarde.");
        }
    }

    // ================= TRANSACCIONES =================
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    public function commit()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // ================= OPERACIONES CARRITO =================

    public function executeQuery(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function getOrCreateUserCart($user_id)
    {
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            // Buscar carrito activo existente
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM carritos_webinars
                WHERE user_id = ?
                AND estado = 'activo'
            ");
            $stmt->execute([$user_id]);
            $cart = $stmt->fetch();

            if ($cart) {
                $this->pdo->commit();
                return $cart['id'];
            }

            // Crear nuevo carrito
            $stmt = $this->pdo->prepare("
                INSERT INTO carritos_webinars
                (user_id, estado)
                VALUES (?, 'activo')
            ");
            $stmt->execute([$user_id]);
            $cart_id = $this->pdo->lastInsertId();

            $this->pdo->commit();
            return $cart_id;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Error al obtener carrito: " . $e->getMessage());
        }
    }

    public function addToCart($user_id, $webinar_id, $quantity = 1)
    {
        try {
            $cart_id = $this->getOrCreateUserCart($user_id);
            $precio = $this->getWebinarPrice($webinar_id);

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO carrito_items_webinars
                (carrito_id, webinar_id, cantidad, precio_unitario)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    cantidad = cantidad + VALUES(cantidad),
                    precio_unitario = VALUES(precio_unitario)
            ");
            $stmt->execute([$cart_id, $webinar_id, $quantity, $precio]);

            $this->updateCartTotal($cart_id);
            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateCartItem($user_id, $webinar_id, $quantity)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            UPDATE carrito_items_webinars
            SET cantidad = ?
            WHERE webinar_id = ?
            AND carrito_id = ?
        ");
        $stmt->execute([$quantity, $webinar_id, $cart_id]);
        $this->updateCartTotal($cart_id);
    }

    public function removeCartItem($user_id, $webinar_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            DELETE FROM carrito_items_webinars
            WHERE webinar_id = ?
            AND carrito_id = ?
        ");
        $stmt->execute([$webinar_id, $cart_id]);
        $this->updateCartTotal($cart_id);
    }

    public function getCartItems($user_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            SELECT ciw.*, w.titulo, w.descripcion, w.fecha, w.duracion, w.imagen
            FROM carrito_items_webinars ciw
            JOIN webinars w ON ciw.webinar_id = w.webinar_id
            WHERE ciw.carrito_id = ?
            AND w.activo = 1
        ");
        $stmt->execute([$cart_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCartTotal($cart_id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE carritos_webinars
            SET total = (
                SELECT SUM(cantidad * precio_unitario)
                FROM carrito_items_webinars
                WHERE carrito_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$cart_id, $cart_id]);
    }

    public function getCartItemCount($user_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            SELECT SUM(cantidad)
            FROM carrito_items_webinars
            WHERE carrito_id = ?
        ");
        $stmt->execute([$cart_id]);
        return (int) $stmt->fetchColumn();
    }

    public function getCartTotal($cart_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT total
            FROM carritos_webinars
            WHERE id = ?
        ");
        $stmt->execute([$cart_id]);
        return (float) $stmt->fetchColumn();
    }

    public function clearCart($user_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM carrito_items_webinars
                WHERE carrito_id = ?
            ");
            $stmt->execute([$cart_id]);

            $this->updateCartTotal($cart_id);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================= OPERACIONES WEBINARS =================
    public function getWebinars()
    {
        $stmt = $this->pdo->query("
            SELECT * FROM webinars
            WHERE activo = 1
            ORDER BY fecha ASC
        ");
        return $stmt->fetchAll();
    }

    public function getActiveWebinars()
    {
        $stmt = $this->pdo->query("
            SELECT * FROM webinars
            WHERE activo = 1
            ORDER BY fecha ASC
        ");
        return $stmt->fetchAll();
    }

    public function getWebinarById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM webinars
            WHERE webinar_id = ?
            AND activo = 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getWebinarsByCategory($category)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM webinars
            WHERE categoria = ?
            AND activo = 1
            ORDER BY fecha ASC
        ");
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }

    private function getWebinarPrice($webinar_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT precio
            FROM webinars
            WHERE webinar_id = ?
        ");
        $stmt->execute([$webinar_id]);
        return $stmt->fetchColumn();
    }

    // ================= OPERACIONES PEDIDOS =================

    public function registerOrder($user_id, $items_json, $total, $order_id)
    {
        $sql = "INSERT INTO pedidos (user_id, items, total, fecha, status, order_id)
                VALUES (:user_id, :items, :total, NOW(), 'pendiente', :order_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':items', $items_json);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':order_id', $order_id);
        return $stmt->execute();
    }

    public function updateOrderStatus($order_id, $status)
    {
        $sql = "UPDATE pedidos SET status = :status WHERE order_id = :order_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_id', $order_id);
        return $stmt->execute();
    }

    public function getPedidosByUser($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM pedidos
            WHERE user_id = ?
            AND JSON_EXTRACT(items, '$[0].tipo') = 'webinar'
            ORDER BY fecha DESC
        ");
        $stmt->execute([$user_id]);

        $pedidos = $stmt->fetchAll();
        foreach ($pedidos as &$pedido) {
            $pedido['items'] = json_decode($pedido['items'], true);
        }

        return $pedidos;
    }

    // ================= WEBINARS ADQUIRIDOS =================
    public function getUserAcceptedWebinars($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT w.*
            FROM pedidos p,
                 JSON_TABLE(p.items, '$[*]' COLUMNS (
                    webinar_id INT PATH '$.webinar_id',
                    tipo VARCHAR(20) PATH '$.tipo'
                 )) AS items
            JOIN webinars w ON w.webinar_id = items.webinar_id
            WHERE p.user_id = ?
              AND p.status = 'aprobado'
              AND items.tipo = 'webinar'
              AND w.activo = 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    // ================= CATEGORÍAS =================
    public function getCategories()
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT categoria
            FROM webinars
            WHERE activo = 1
            ORDER BY categoria ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ================= VERIFICACIONES =================
    public function hasUserAccessToWebinar($user_id, $webinar_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) > 0 as has_access
            FROM pedidos p,
                 JSON_TABLE(p.items, '$[*]' COLUMNS (
                    webinar_id INT PATH '$.webinar_id',
                    tipo VARCHAR(20) PATH '$.tipo'
                 )) AS items
            WHERE p.user_id = ?
              AND p.status = 'aprobado'
              AND items.webinar_id = ?
              AND items.tipo = 'webinar'
        ");
        $stmt->execute([$user_id, $webinar_id]);
        return $stmt->fetchColumn();
    }

    public function isWebinarInCart($user_id, $webinar_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) > 0 as in_cart
            FROM carrito_items_webinars
            WHERE carrito_id = ?
            AND webinar_id = ?
        ");
        $stmt->execute([$cart_id, $webinar_id]);
        return $stmt->fetchColumn();
    }
}