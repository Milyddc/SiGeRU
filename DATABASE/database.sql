CREATE DATABASE IF NOT EXISTS proyecto;

USE proyecto;

CREATE TABLE Rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

CREATE TABLE Centro_Acopio (
    id_centro_acopio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(150) NOT NULL,
    capacidad DECIMAL(10,2) NOT NULL
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
    estado VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    id_centro_acopio INT,

    FOREIGN KEY (id_rol)
        REFERENCES Rol(id_rol),

    FOREIGN KEY (id_centro_acopio)
        REFERENCES Centro_Acopio(id_centro_acopio)
);

CREATE TABLE Cuadrilla_Usuario (
    id_cuadrilla INT NOT NULL,
    id_usuario INT NOT NULL,

    PRIMARY KEY(id_cuadrilla, id_usuario),

    FOREIGN KEY(id_cuadrilla)
        REFERENCES Cuadrilla(id_cuadrilla),

    FOREIGN KEY(id_usuario)
        REFERENCES Usuario(id_usuario)
);

CREATE TABLE Ruta (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    zona VARCHAR(100) NOT NULL,
    frecuencia VARCHAR(100),
    id_cuadrilla INT,

    FOREIGN KEY(id_cuadrilla)
        REFERENCES Cuadrilla(id_cuadrilla)
);

CREATE TABLE Contenedor (
    id_contenedor INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(100) NOT NULL UNIQUE,
    ubicacion VARCHAR(150) NOT NULL,
    estado VARCHAR(50) NOT NULL,
    capacidad DECIMAL(10,2) NOT NULL,
    id_tipo_residuo INT NOT NULL,
    id_ruta INT,

    FOREIGN KEY(id_tipo_residuo)
        REFERENCES Tipo_Residuo(id_tipo_residuo),

    FOREIGN KEY(id_ruta)
        REFERENCES Ruta(id_ruta)
);

CREATE TABLE Camion (
    id_camion INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    modelo VARCHAR(100),
    capacidad DECIMAL(10,2) NOT NULL,
    estado VARCHAR(50) NOT NULL,
    id_cuadrilla INT NOT NULL,

    FOREIGN KEY(id_cuadrilla)
        REFERENCES Cuadrilla(id_cuadrilla)
);

CREATE TABLE Incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL,
    descripcion TEXT NOT NULL,
    estado VARCHAR(50) NOT NULL,
    prioridad VARCHAR(30) NOT NULL,
    id_usuario INT NOT NULL,
    id_contenedor INT NOT NULL,
    id_cuadrilla INT,

    FOREIGN KEY(id_usuario)
        REFERENCES Usuario(id_usuario),

    FOREIGN KEY(id_contenedor)
        REFERENCES Contenedor(id_contenedor),

    FOREIGN KEY(id_cuadrilla)
        REFERENCES Cuadrilla(id_cuadrilla)
);

INSERT INTO Rol (nombre) VALUES
('Administrador'),
('Operador'),
('Recolector');

INSERT INTO Centro_Acopio (nombre, direccion, capacidad) VALUES
('Centro de Acopio Municipal', 'Av. Italia 1234', 5000.00),
('Centro de Acopio Oeste', 'Av. Luis Batlle Berres 2500', 4000.00),
('Centro de Acopio Norte', 'Av. Don Pedro de Mendoza 1800', 4500.00);

INSERT INTO Tipo_Residuo (nombre) VALUES
('Orgánico'),
('Reciclable'),
('Vidrio'),
('Plástico'),
('Papel y Cartón'),
('No reciclable');

INSERT INTO Cuadrilla (nombre, turno) VALUES
('Cuadrilla 1', 'Matutino'),
('Cuadrilla 2', 'Vespertino'),
('Cuadrilla 3', 'Nocturno');

INSERT INTO Camion (matricula, modelo, capacidad, estado, id_cuadrilla) VALUES
('SAB 1234', 'Mercedes-Benz Atego', 8000.00, 'Disponible', 1),
('SAB 5678', 'Iveco Tector', 10000.00, 'Disponible', 2),
('SAB 9012', 'Volkswagen Constellation', 12000.00, 'En mantenimiento', 3);

INSERT INTO Ruta (nombre, zona, frecuencia, id_cuadrilla) VALUES
('Ruta Centro', 'Centro de Montevideo', 'Diaria', 1),
('Ruta Oeste', 'Oeste de Montevideo', 'Lunes, Miércoles y Viernes', 2),
('Ruta Norte', 'Norte de Montevideo', 'Martes, Jueves y Sábado', 3);

INSERT INTO Contenedor (codigo, ubicacion, estado, capacidad, id_tipo_residuo, id_ruta) VALUES
('CONT-001', 'Av. Italia 1200', 'Disponible', 1100.00, 1, NULL),
('CONT-002', 'Av. Rivera 2500', 'Disponible', 1100.00, 2, NULL),
('CONT-003', 'Bv. Artigas 1800', 'Lleno', 2400.00, 3, NULL),
('CONT-004', 'Av. 18 de Julio 1500', 'Disponible', 1100.00, 4, NULL),
('CONT-005', 'Av. Luis Batlle Berres 2200', 'En mantenimiento', 2400.00, 5, NULL),
('CONT-006', 'Camino Maldonado 3200', 'Disponible', 1100.00, 6, NULL);

INSERT INTO Usuario
(nombre_completo, telefono, email, estado, password, id_rol, id_centro_acopio)
VALUES
('Emily Ferreira', '099123456', 'emily@sigeru.com', 'Activo', '123456', 1, 1),
('Santiago Valenzuela', '099234567', 'santiago@sigeru.com', 'Activo', '123456', 2, 2),
('Lautaro Ibáñez', '099345678', 'lautaro@sigeru.com', 'Activo', '123456', 3, 3),
('Usuario Operador', '099456789', 'operador@sigeru.com', 'Activo', '123456', 2, NULL),
('Usuario Recolector', '099567890', 'recolector@sigeru.com', 'Activo', '123456', 3, NULL),
('Administrador Prueba', NULL, 'admin@gmail.com', 'Activo', '123456', 1, NULL);

INSERT INTO Cuadrilla_Usuario (id_cuadrilla, id_usuario) VALUES
(1, 1),
(1, 3),
(2, 2),
(2, 4),
(3, 5);

INSERT INTO Incidencia
(fecha, descripcion, estado, prioridad, id_usuario, id_contenedor, id_cuadrilla)
VALUES
('2026-09-06 09:00:00', 'Contenedor lleno y requiere recolección.', 'Pendiente', 'Alta', 3, 1, 1),
('2026-09-06 10:30:00', 'Contenedor presenta daños en la tapa.', 'En revisión', 'Media', 2, 3, 2),
('2026-09-06 12:00:00', 'Se detectó acumulación de residuos alrededor del contenedor.', 'Resuelta', 'Baja', 1, 5, 3);

