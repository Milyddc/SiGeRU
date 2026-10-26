CREATE DATABASE IF NOT EXISTS proyecto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proyecto;

CREATE TABLE Rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Centro_Acopio (
    id_centro_acopio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(150) NOT NULL,
    capacidad DECIMAL(10,2) NOT NULL,
    latitud DECIMAL(10,7) NULL,
    longitud DECIMAL(10,7) NULL
);

CREATE TABLE Tipo_Residuo (
    id_tipo_residuo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

CREATE TABLE Cuadrilla (
    id_cuadrilla INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    turno VARCHAR(50)
);

CREATE TABLE Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100) NOT NULL UNIQUE,
    estado ENUM('Pendiente','Activo','Inactivo') NOT NULL DEFAULT 'Pendiente',
    password VARCHAR(255) NOT NULL,              -- hash bcrypt/argon2, nunca texto plano
    id_rol INT NOT NULL,
    id_centro_acopio INT NULL,
    intentos_fallidos INT NOT NULL DEFAULT 0,    -- política: bloqueo por intentos fallidos
    bloqueado_hasta DATETIME NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol),
    FOREIGN KEY (id_centro_acopio) REFERENCES Centro_Acopio(id_centro_acopio)
);

CREATE TABLE Cuadrilla_Usuario (
    id_cuadrilla INT NOT NULL,
    id_usuario INT NOT NULL,
    PRIMARY KEY (id_cuadrilla, id_usuario),
    FOREIGN KEY (id_cuadrilla) REFERENCES Cuadrilla(id_cuadrilla),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Ruta (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    zona VARCHAR(100) NOT NULL,
    frecuencia VARCHAR(100),
    id_cuadrilla INT,
    FOREIGN KEY (id_cuadrilla) REFERENCES Cuadrilla(id_cuadrilla)
);

CREATE TABLE Contenedor (
    id_contenedor INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(100) NOT NULL UNIQUE,
    ubicacion VARCHAR(150) NOT NULL,
    latitud DECIMAL(10,7) NULL,                  -- para el mapa interactivo
    longitud DECIMAL(10,7) NULL,
    zona VARCHAR(100) NULL,                      -- para estadísticas por zona
    estado VARCHAR(50) NOT NULL,
    capacidad DECIMAL(10,2) NOT NULL,
    id_tipo_residuo INT NOT NULL,
    id_ruta INT NULL,
    id_centro_acopio INT NULL,

    FOREIGN KEY (id_tipo_residuo) REFERENCES Tipo_Residuo(id_tipo_residuo),
    FOREIGN KEY (id_ruta) REFERENCES Ruta(id_ruta),
    FOREIGN KEY (id_centro_acopio) REFERENCES Centro_Acopio(id_centro_acopio)
);

CREATE TABLE Camion (
    id_camion INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    modelo VARCHAR(100),
    capacidad DECIMAL(10,2) NOT NULL,
    estado VARCHAR(50) NOT NULL,
    id_cuadrilla INT NOT NULL,
    FOREIGN KEY (id_cuadrilla) REFERENCES Cuadrilla(id_cuadrilla)
);

CREATE TABLE Incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('Rotura','Desborde','Falta de recolección','Otro') NOT NULL DEFAULT 'Otro',
    descripcion TEXT NOT NULL,
    estado ENUM('Abierta','En curso','Resuelta') NOT NULL DEFAULT 'Abierta',
    prioridad ENUM('Baja','Media','Alta') NOT NULL DEFAULT 'Media',
    fecha_resolucion DATETIME NULL,              -- para calcular tiempos de resolución
    id_usuario INT NOT NULL,
    id_contenedor INT NOT NULL,
    id_cuadrilla INT NULL,

    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_contenedor) REFERENCES Contenedor(id_contenedor),
    FOREIGN KEY (id_cuadrilla) REFERENCES Cuadrilla(id_cuadrilla),
    INDEX idx_incidencia_estado (estado),
    INDEX idx_incidencia_fecha (fecha)
);

CREATE TABLE Historial_Incidencia (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_incidencia INT NOT NULL,
    id_usuario INT NOT NULL,
    estado_anterior VARCHAR(50) NULL,
    estado_nuevo VARCHAR(50) NOT NULL,
    comentario VARCHAR(255) NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_incidencia) REFERENCES Incidencia(id_incidencia) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Notificacion (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NULL,                         -- destinatario puntual
    id_rol INT NULL,                             -- o todos los de un rol
    id_cuadrilla INT NULL,                       -- o toda una cuadrilla
    mensaje VARCHAR(255) NOT NULL,
    tipo ENUM('info','alerta') NOT NULL DEFAULT 'info',
    leida TINYINT(1) NOT NULL DEFAULT 0,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol),
    FOREIGN KEY (id_cuadrilla) REFERENCES Cuadrilla(id_cuadrilla),
    INDEX idx_notif_leida (leida)
);


CREATE TABLE Mantenimiento_Camion (
    id_mantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_camion INT NOT NULL,
    tipo ENUM('Preventivo','Correctivo') NOT NULL DEFAULT 'Preventivo',
    descripcion VARCHAR(255) NOT NULL,
    fecha_programada DATE NOT NULL,
    fecha_realizada DATE NULL,
    estado ENUM('Programado','Realizado') NOT NULL DEFAULT 'Programado',
    FOREIGN KEY (id_camion) REFERENCES Camion(id_camion) ON DELETE CASCADE,
    INDEX idx_mant_estado (estado)
);

CREATE TABLE Recorrido (
    id_recorrido INT AUTO_INCREMENT PRIMARY KEY,
    id_ruta INT NOT NULL,
    id_camion INT NOT NULL,
    id_usuario INT NOT NULL,                     -- quien inició el recorrido
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME NULL,
    estado ENUM('En curso','Finalizado') NOT NULL DEFAULT 'En curso',
    volumen_kg DECIMAL(10,2) NULL,
    observaciones VARCHAR(255) NULL,
    FOREIGN KEY (id_ruta) REFERENCES Ruta(id_ruta),
    FOREIGN KEY (id_camion) REFERENCES Camion(id_camion),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    INDEX idx_recorrido_estado (estado)
);

CREATE TABLE Recorrido_Recurso (
    id_recurso INT AUTO_INCREMENT PRIMARY KEY,
    id_recorrido INT NOT NULL,
    recurso VARCHAR(100) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    unidad VARCHAR(20) NULL,
    FOREIGN KEY (id_recorrido) REFERENCES Recorrido(id_recorrido) ON DELETE CASCADE
);

-- =========================== DATOS DE PRUEBA ===========================

INSERT INTO Rol (nombre) VALUES ('Administrador'), ('Cuadrilla'), ('Operario'), ('Vecino');

INSERT INTO Centro_Acopio (nombre, direccion, capacidad, latitud, longitud) VALUES
('Centro de Acopio Municipal', 'Av. Italia 1234', 5000.00, -34.8870000, -56.1500000),
('Centro de Acopio Oeste', 'Av. Luis Batlle Berres 2500', 4000.00, -34.8600000, -56.2500000),
('Centro de Acopio Norte', 'Av. Don Pedro de Mendoza 1800', 4500.00, -34.8200000, -56.1800000);

INSERT INTO Tipo_Residuo (nombre) VALUES
('Orgánico'), ('Reciclable'), ('Vidrio'), ('Plástico'), ('Papel y Cartón'), ('No reciclable');

INSERT INTO Cuadrilla (nombre, turno) VALUES
('Cuadrilla 1', 'Matutino'), ('Cuadrilla 2', 'Vespertino'), ('Cuadrilla 3', 'Nocturno');

INSERT INTO Camion (matricula, modelo, capacidad, estado, id_cuadrilla) VALUES
('SAB 1234', 'Mercedes-Benz Atego', 8000.00, 'Disponible', 1),
('SAB 5678', 'Iveco Tector', 10000.00, 'Disponible', 2),
('SAB 9012', 'Volkswagen Constellation', 12000.00, 'En mantenimiento', 3);

INSERT INTO Ruta (nombre, zona, frecuencia, id_cuadrilla) VALUES
('Ruta Centro', 'Centro', 'Diaria', 1),
('Ruta Oeste', 'Oeste', 'Lunes, Miércoles y Viernes', 2),
('Ruta Norte', 'Norte', 'Martes, Jueves y Sábado', 3);

INSERT INTO Contenedor (codigo, ubicacion, latitud, longitud, zona, estado, capacidad, id_tipo_residuo, id_ruta, id_centro_acopio) VALUES
('CONT-001', 'Av. Italia 1200', -34.8860000, -56.1420000, 'Centro', 'Disponible', 1100.00, 1, 1, 1),
('CONT-002', 'Av. Rivera 2500', -34.9040000, -56.1370000, 'Centro', 'Disponible', 1100.00, 2, 1, 1),
('CONT-003', 'Bv. Artigas 1800', -34.8990000, -56.1650000, 'Centro', 'Lleno', 2400.00, 3, 1, 1),
('CONT-004', 'Av. 18 de Julio 1500', -34.9060000, -56.1900000, 'Centro', 'Disponible', 1100.00, 4, 1, 1),
('CONT-005', 'Av. Luis Batlle Berres 2200', -34.8650000, -56.2400000, 'Oeste', 'En mantenimiento', 2400.00, 5, 2, 2),
('CONT-006', 'Camino Maldonado 3200', -34.8400000, -56.1100000, 'Norte', 'Disponible', 1100.00, 6, 3, 3);

-- Contraseña de TODOS los usuarios de prueba: Sigeru2026!   (guardada como hash bcrypt)
INSERT INTO Usuario (nombre_completo, telefono, email, estado, password, id_rol, id_centro_acopio) VALUES
('Emily Ferreira', '099123456', 'emily@sigeru.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 1, 1),
('Santiago Valenzuela', '099234567', 'santiago@sigeru.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 3, 2),
('Lautaro Ibáñez', '099345678', 'lautaro@sigeru.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 2, 3),
('Usuario Operario', '099456789', 'operador@sigeru.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 3, NULL),
('Usuario Cuadrilla', '099567890', 'recolector@sigeru.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 2, NULL),
('Administrador Prueba', NULL, 'admin@gmail.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 1, NULL),
('Vecino Prueba', NULL, 'vecino@sigeru.com', 'Activo', '$2y$10$aTqkm8ZcTl.cVoVx6LLbOOBTgKK4wKoCywHpUPdXhk.f.Q7b/.81W', 4, NULL);

INSERT INTO Cuadrilla_Usuario (id_cuadrilla, id_usuario) VALUES (1, 1), (1, 3), (2, 2), (2, 4), (3, 5);

INSERT INTO Incidencia (fecha, tipo, descripcion, estado, prioridad, fecha_resolucion, id_usuario, id_contenedor, id_cuadrilla) VALUES
('2026-10-01 09:00:00', 'Desborde', 'Contenedor lleno y requiere recolección.', 'Abierta', 'Alta', NULL, 3, 1, 1),
('2026-10-02 10:30:00', 'Rotura', 'Contenedor presenta daños en la tapa.', 'En curso', 'Media', NULL, 2, 3, 2),
('2026-10-03 12:00:00', 'Desborde', 'Acumulación de residuos alrededor del contenedor.', 'Resuelta', 'Baja', '2026-10-03 16:30:00', 1, 5, 3),
('2026-10-05 08:15:00', 'Desborde', 'Desborde recurrente en la esquina.', 'Resuelta', 'Alta', '2026-10-05 14:00:00', 3, 1, 1),
('2026-10-08 11:00:00', 'Falta de recolección', 'No pasó el camión esta semana.', 'Abierta', 'Media', NULL, 7, 6, NULL);

INSERT INTO Historial_Incidencia (id_incidencia, id_usuario, estado_anterior, estado_nuevo, comentario) VALUES
(1, 3, NULL, 'Abierta', 'Incidencia creada'),
(2, 2, NULL, 'Abierta', 'Incidencia creada'),
(2, 6, 'Abierta', 'En curso', 'Asignada a Cuadrilla 2'),
(3, 1, NULL, 'Abierta', 'Incidencia creada'),
(3, 6, 'Abierta', 'Resuelta', 'Recolectado'),
(4, 3, NULL, 'Abierta', 'Incidencia creada'),
(4, 6, 'Abierta', 'Resuelta', 'Recolectado'),
(5, 7, NULL, 'Abierta', 'Incidencia creada');

INSERT INTO Mantenimiento_Camion (id_camion, tipo, descripcion, fecha_programada, estado) VALUES
(3, 'Correctivo', 'Reparación del sistema hidráulico de compactación', '2026-10-10', 'Programado'),
(1, 'Preventivo', 'Cambio de aceite y revisión de frenos', '2026-11-05', 'Programado');

INSERT INTO Recorrido (id_ruta, id_camion, id_usuario, fecha_inicio, fecha_fin, estado, volumen_kg, observaciones) VALUES
(1, 1, 3, '2026-10-12 06:00:00', '2026-10-12 11:30:00', 'Finalizado', 2350.00, 'Sin novedades');

INSERT INTO Recorrido_Recurso (id_recorrido, recurso, cantidad, unidad) VALUES
(1, 'Combustible', 38.50, 'L'),
(1, 'Bolsas de recambio', 60, 'u');
