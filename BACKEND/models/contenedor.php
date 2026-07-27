<?php

class Contenedor
{
    public $id;
    public $ubicacion;
    public $estado;
    public $tipo;

    public function __construct($id, $ubicacion, $estado, $tipo)
    {
        $this->id = $id;
        $this->ubicacion = $ubicacion;
        $this->estado = $estado;
        $this->tipo = $tipo;
    }
}
