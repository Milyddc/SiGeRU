<?php

class Camion
{
    public $id;
    public $matricula;
    public $modelo;
    public $estado;

    public function __construct($id, $matricula, $modelo, $estado)
    {
        $this->id = $id;
        $this->matricula = $matricula;
        $this->modelo = $modelo;
        $this->estado = $estado;
    }
}
