<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Resultado global de una consulta de registros de facturación
 */
enum QueryResult: string {
    /** La consulta devuelve registros */
    case WithData = 'ConDatos';

    /** La consulta no encuentra registros para los filtros indicados */
    case WithoutData = 'SinDatos';
}
