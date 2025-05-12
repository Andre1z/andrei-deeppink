<?php
/**
 * i18n.php
 *
 * Archivo encargado de gestionar la internacionalización (i18n) de la aplicación.
 * Lee el archivo "translations.csv" para obtener los textos traducidos a varios idiomas.
 * Se establece un idioma activo (por defecto, inglés) que puede cambiarse mediante la sesión.
 */

/**
 * Obtiene el conjunto de traducciones desde el archivo CSV.
 *
 * @param string $idioma El código del idioma deseado (por defecto 'en').
 * @return array Arreglo asociativo con las claves de traducción y sus textos para cada idioma.
 */
function obtenerTraducciones($idioma = 'en') {
    $traducciones = array();
    // Se intenta abrir el archivo CSV con las traducciones.
    if (($archivo = fopen("translations.csv", "r")) !== false) {
        // Se lee la primera línea para descartar los encabezados
        $cabecera = fgetcsv($archivo, 1000, ",");
        // Se procesan cada línea del archivo
        while (($linea = fgetcsv($archivo, 1000, ",")) !== false) {
            // los índices de $linea:
            // [0] => clave, [1] => inglés, [2] => español, [3] => francés, [4] => alemán
            $clave = $linea[0];
            $traducciones[$clave] = array(
                'en' => $linea[1],
                'es' => $linea[2],
                'fr' => $linea[3],
                'de' => $linea[4]
            );
        }
        fclose($archivo);
    }
    return $traducciones;
}

// Determina el idioma activo a partir de la sesión; si no está definido, se usa inglés ('en').
if (isset($_SESSION['language'])) {
    $idiomaActivo = $_SESSION['language'];
} else {
    $idiomaActivo = 'en';
}
$GLOBALS['idiomaActivo'] = $idiomaActivo;
$GLOBALS['conjuntoTraducciones'] = obtenerTraducciones($idiomaActivo);

/**
 * Traduce una clave determinada al texto correspondiente en el idioma activo.
 *
 * @param string $clave La clave que identifica el mensaje a traducir.
 * @return string Devuelve el mensaje traducido según el idioma activo o
 *                el equivalente en inglés como alternativa; en caso de no existir,
 *                retorna la propia clave.
 */
function __($clave) {
    if (isset($GLOBALS['conjuntoTraducciones'][$clave][$GLOBALS['idiomaActivo']])) {
        return $GLOBALS['conjuntoTraducciones'][$clave][$GLOBALS['idiomaActivo']];
    }
    return isset($GLOBALS['conjuntoTraducciones'][$clave]['en']) ? $GLOBALS['conjuntoTraducciones'][$clave]['en'] : $clave;
}
?>