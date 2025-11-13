<?php
require_once 'Conexion.php';

class Asistencia {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->getConexion();
    }

    // Registrar asistencia (verifica si ya existe en el día)
    public function registrarAsistencia($legajo, $cargo) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM asistencia_c 
                WHERE Id_asiste = ? AND Cargo = ? AND fecha = CURDATE()
            ");
            $stmt->execute([$legajo]);

            if ($stmt->rowCount() > 0) {
                return 'Ya registró su asistencia hoy.';
            }

            $horaActual = date('H:i:s');
            $stmt = $this->pdo->prepare("
                INSERT INTO asistencia_c (Id_asiste, fecha, Entrada, Salida, Cargo) 
                VALUES (?, CURDATE(), ?, ?, ?)
            ");
            $stmt->execute([$legajo, $horaActual, $horaActual, $cargo]);

            return 'Asistencia registrada correctamente.';
        } catch (PDOException $e) {
            return 'Error al registrar asistencia: ' . $e->getMessage();
        }
    }

    // Obtener ausentes del día
    public function obtenerAusentes() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.legajo, u.nombre, u.apellido, c.Denominacion AS cargo
                FROM usuario u
                LEFT JOIN cargo c ON u.id_cargo = c.id_cargo
                WHERE u.legajo NOT IN (
                    SELECT DISTINCT a.Id_asiste 
                    FROM asistencia_c a 
                    WHERE DATE(a.fecha) = CURDATE()
                )
                ORDER BY u.apellido, u.nombre
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>