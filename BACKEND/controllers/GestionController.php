<?php

require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../models/Camion.php";
require_once __DIR__ . "/../models/Contenedor.php";

class GestionController
{

    public function listarCamiones()
    {
        return $_SESSION["camiones"];
    }

    public function crearCamion($datos)
    {

        $nuevo = new Camion(

            count($_SESSION["camiones"]) + 1,

            $datos["matricula"],

            $datos["modelo"],

            $datos["estado"]

        );

        $_SESSION["camiones"][] = $nuevo;

        return [

            "mensaje" => "Camión registrado correctamente"

        ];

    }

    public function listarContenedores()
    {
        return $_SESSION["contenedores"];
    }

    public function crearContenedor($datos)
    {

        $nuevo = new Contenedor(

            count($_SESSION["contenedores"]) + 1,

            $datos["ubicacion"],

            $datos["estado"],

            $datos["tipo"]

        );

        $_SESSION["contenedores"][] = $nuevo;

        return [

            "mensaje" => "Contenedor registrado correctamente"

        ];

    }

}