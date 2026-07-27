<?php

require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../models/Usuario.php";

class UsuarioController
{

    public function listarUsuarios()
    {
        return $_SESSION["usuarios"];
    }

    public function registrar($datos)
    {

        $nuevo = new Usuario(

            count($_SESSION["usuarios"]) + 1,

            $datos["nombre"],

            $datos["apellido"],

            $datos["correo"],

            $datos["password"],

            $datos["rol"]

        );

        $_SESSION["usuarios"][] = $nuevo;

        return [

            "ok" => true,

            "mensaje" => "Usuario registrado correctamente"

        ];

    }

    public function login($datos)
    {

        foreach ($_SESSION["usuarios"] as $usuario) {

            if (
                $usuario["correo"] == $datos["correo"] &&
                $usuario["password"] == $datos["password"]
            ) {

                $_SESSION["login"] = true;
                $_SESSION["usuario"] = $usuario;

                return [

                    "ok" => true,

                    "mensaje" => "Login correcto",

                    "usuario" => $usuario

                ];

            }

        }

        return [

            "ok" => false,

            "mensaje" => "Correo o contraseña incorrectos"

        ];

    }

}
