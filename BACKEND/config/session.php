<?php

session_start();

/* ====== USUARIOS ====== */

if (!isset($_SESSION["usuarios"])) {

    $_SESSION["usuarios"] = [

        [
            "id" => 1,
            "nombre" => "Juan",
            "apellido" => "Pérez",
            "correo" => "juan@email.com",
            "password" => "1234",
            "rol" => "Administrador"
        ],

        [
            "id" => 2,
            "nombre" => "Ana",
            "apellido" => "Rodríguez",
            "correo" => "ana@email.com",
            "password" => "1234",
            "rol" => "Operario"
        ]

    ];
}

/* ====== CONTENEDORES ====== */

if (!isset($_SESSION["contenedores"])) {

    $_SESSION["contenedores"] = [

        [
            "id" => 1,
            "ubicacion" => "Av. Italia",
            "estado" => "Disponible",
            "tipo" => "Reciclables"
        ],

        [
            "id" => 2,
            "ubicacion" => "18 de Julio",
            "estado" => "Lleno",
            "tipo" => "Orgánicos"
        ]

    ];
}

/* ====== CAMIONES ====== */

if (!isset($_SESSION["camiones"])) {

    $_SESSION["camiones"] = [

        [
            "id" => 1,
            "matricula" => "SAB1234",
            "modelo" => "Mercedes Benz",
            "estado" => "Disponible"
        ],

        [
            "id" => 2,
            "matricula" => "SBB5678",
            "modelo" => "Volvo FMX",
            "estado" => "Mantenimiento"
        ]

    ];
}
