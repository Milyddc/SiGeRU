<?php

class Usuario
{
    public $id;
    public $nombre;
    public $apellido;
    public $correo;
    public $password;
    public $rol;

    public function __construct($id, $nombre, $apellido, $correo, $password, $rol)
    {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->apellido = $apellido;
        $this->correo = $correo;
        $this->password = $password;
        $this->rol = $rol;
    }
}
