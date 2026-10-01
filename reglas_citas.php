<?php

const ETIQUETAS_ESTADO_CITA = [
    "pendiente" => "Pendiente",
    "confirmada" => "Confirmada",
    "atendida" => "Atendida",
    "cancelada" => "Cancelada"
];

const TRANSICIONES_ESTADO_CITA = [
    "pendiente" => ["confirmada", "cancelada"],
    "confirmada" => ["atendida", "cancelada"],
    "atendida" => [],
    "cancelada" => []
];