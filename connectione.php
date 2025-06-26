<?php
require_once 'config.php';

class Database3
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
            error_log("Error de conexión (Ebooks): " . $e->getMessage());
            die("Error en el sistema. Por favor intente más tarde.");
        }
    }

    // ================= TRANSACCIONES =================
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    // En la clase Database3
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
                FROM carritos_ebooks 
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
                INSERT INTO carritos_ebooks 
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

    public function addToCart($user_id, $ebook_id, $quantity)
    {
        try {
            $cart_id = $this->getOrCreateUserCart($user_id);
            $precio = $this->getEbookPrice($ebook_id);

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO carrito_items_ebooks 
                (carrito_id, ebook_id, cantidad, precio_unitario)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    cantidad = cantidad + VALUES(cantidad),
                    precio_unitario = VALUES(precio_unitario)
            ");
            $stmt->execute([$cart_id, $ebook_id, $quantity, $precio]);

            $this->updateCartTotal($cart_id);
            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateCartItem($user_id, $ebook_id, $quantity)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            UPDATE carrito_items_ebooks
            SET cantidad = ?
            WHERE ebook_id = ?
            AND carrito_id = ?
        ");
        $stmt->execute([$quantity, $ebook_id, $cart_id]);
        $this->updateCartTotal($cart_id);
    }

    public function removeCartItem($user_id, $ebook_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
            DELETE FROM carrito_items_ebooks
            WHERE ebook_id = ?
            AND carrito_id = ?
        ");
        $stmt->execute([$ebook_id, $cart_id]);
        $this->updateCartTotal($cart_id);
    }

    public function getCartItems($user_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $stmt = $this->pdo->prepare("
        SELECT cie.*, e.titulo, e.autor, e.archivo_url AS url 
        FROM carrito_items_ebooks cie
        JOIN ebooks e ON cie.ebook_id = e.ebook_id
        WHERE cie.carrito_id = ?
    ");
        $stmt->execute([$cart_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function updateCartTotal($cart_id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE carritos_ebooks 
            SET total = (
                SELECT SUM(cantidad * precio_unitario) 
                FROM carrito_items_ebooks 
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
            FROM carrito_items_ebooks 
            WHERE carrito_id = ?
        ");
        $stmt->execute([$cart_id]);
        return (int) $stmt->fetchColumn();
    }

    public function clearCart($user_id)
    {
        $cart_id = $this->getOrCreateUserCart($user_id);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM carrito_items_ebooks 
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

    // ================= OPERACIONES EBOOKS =================
    public function getEbooks()
    {
        $stmt = $this->pdo->query("SELECT * FROM ebooks");
        return $stmt->fetchAll();
    }

    public function getEbookById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ebooks WHERE ebook_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function getEbookPrice($ebook_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT precio 
            FROM ebooks 
            WHERE ebook_id = ?
        ");
        $stmt->execute([$ebook_id]);
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
            SELECT pe.*, 
                   JSON_ARRAYAGG(
                       JSON_OBJECT(
                           'titulo', e.titulo,
                           'cantidad', pei.quantity,
                           'precio', pei.price
                       )
                   ) AS items
            FROM pedidos_ebooks pe
            JOIN pedidos_ebooks_items pei ON pe.id = pei.pedido_id
            JOIN ebooks e ON pei.ebook_id = e.ebook_id
            WHERE pe.user_id = ?
            GROUP BY pe.id
            ORDER BY pe.fecha DESC
        ");
        $stmt->execute([$user_id]);

        $pedidos = $stmt->fetchAll();
        foreach ($pedidos as &$pedido) {
            $pedido['items'] = json_decode($pedido['items'], true);
        }

        return $pedidos;
    }

    // ================= EBOOKS ADQUIRIDOS =================
    public function getUserAcceptedEbooks($user_id)
    {
        $stmt = $this->pdo->prepare("
        SELECT DISTINCT e.*
        FROM pedidos p,
             JSON_TABLE(p.items, '$[*]' COLUMNS (
                ebook_id INT PATH '$.ebook_id',
                tipo VARCHAR(20) PATH '$.tipo'
             )) AS items
        JOIN ebooks e ON e.ebook_id = items.ebook_id
        WHERE p.user_id = ? 
          AND p.status = 'aprobado'
          AND items.tipo = 'ebook'
    ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }


}