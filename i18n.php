<?php

function loadTranslations($lang = 'en') {
    $translations = [];
    if (($handle = fopen("translations.csv", "r")) !== false) {
        // Read the header row (key, english, spanish, french, german)
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            // data[0]=key, data[1]=english, data[2]=spanish, data[3]=french, data[4]=german
            @$translations[$data[0]] = [
                'en' => $data[1],
                'es' => $data[2],
                'fr' => $data[3],
                'de' => $data[4]
            ];
        }
        fclose($handle);
    }
    return $translations;
}

// Set default language to English if not set in session.
if (isset($_SESSION['lang'])) {
    $currentLang = $_SESSION['lang'];
} else {
    $currentLang = 'en';
}
$GLOBALS['currentLang'] = $currentLang;
$GLOBALS['__translations'] = loadTranslations($currentLang);

function __($key) {
    if (isset($GLOBALS['__translations'][$key][$GLOBALS['currentLang']])) {
        return $GLOBALS['__translations'][$key][$GLOBALS['currentLang']];
    }
    // Fallback to English if the key is not defined in the current language.
    return isset($GLOBALS['__translations'][$key]['en']) ? $GLOBALS['__translations'][$key]['en'] : $key;
}
?>

