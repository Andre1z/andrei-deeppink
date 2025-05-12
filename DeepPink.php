<?php
/**
 * DeepPink.php
 *
 * Esta clase se encarga de analizar el contenido HTML de una URL proporcionada,
 * extrayendo elementos relevantes para el SEO y la auditoría web (título, descripción,
 * encabezados, palabras frecuentes, nube de palabras, y comprobaciones de archivos clave).
 *
 * NOTA: Este código está reestructurado completamente para mantener la misma funcionalidad
 * que la versión original, pero usando un estilo de comentarios y docstrings diferente.
 */

require_once 'i18n.php';

class DeepPink {
    protected $htmlContent; // Contenido HTML obtenido de la URL.
    protected $doc;         // Objeto DOMDocument para procesar el HTML.
    protected $domain;      // Dominio base extraído de la URL.

    /**
     * Constructor de la clase DeepPink.
     *
     * @param string $url URL del sitio web que se desea analizar.
     */
    public function __construct($url) {
        // Se intenta obtener el contenido de la URL.
        $this->htmlContent = @file_get_contents($url);
        if ($this->htmlContent === false) {
            die("Error crítico: No se pudo cargar el contenido de la URL.");
        }
        
        // Se crea el objeto DOMDocument y se carga el HTML, suprimiendo errores de formato.
        libxml_use_internal_errors(true);
        $this->doc = new DOMDocument();
        $this->doc->loadHTML($this->htmlContent);
        libxml_clear_errors();
        
        // Se parsea la URL para establecer el dominio base, necesario para comprobaciones posteriores.
        $parts = parse_url($url);
        if (isset($parts['scheme']) && isset($parts['host'])) {
            $this->domain = $parts['scheme'] . '://' . $parts['host'];
        } else {
            die("Error crítico: La estructura de la URL no es válida.");
        }
    }

    /**
     * Muestra el título de la página en una fila de tabla.
     *
     * El método utiliza la etiqueta <title> del HTML y dibuja un indicador de estado.
     */
    public function displayPageTitle() {
        $titles = $this->doc->getElementsByTagName('title');
        echo "<tr>";
            echo "<td>";
                echo ($titles->length > 0) ? "<div class='ok'></div>" : "<div class='ko'></div>";
            echo "</td>";
            echo "<td><h4>" . __('site_title') . "</h4></td>";
            echo "<td>";
                if ($titles->length > 0) {
                    echo $titles->item(0)->nodeValue;
                }
            echo "</td>";
        echo "</tr>";
    }

    /**
     * Muestra la descripción meta (meta description) de la página.
     *
     * Recorre las etiquetas meta del documento para encontrar aquella cuyo atributo name sea "description".
     */
    public function displayMetaDescription() {
        $metas = $this->doc->getElementsByTagName('meta');
        $found = false;
        $desc = "";
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $found = true;
                $desc = $meta->getAttribute('content');
                break;
            }
        }
        echo "<tr>";
            echo "<td>";
                echo ($found) ? "<div class='ok'></div>" : "<div class='ko'></div>";
            echo "</td>";
            echo "<td><h4>" . __('site_description') . "</h4></td>";
            echo "<td>";
                echo ($found) ? $desc : "<div class='ko'></div>";
            echo "</td>";
        echo "</tr>";
    }

    /**
     * Muestra los encabezados de nivel específico (de h1 a h6) en el documento.
     *
     * @param int $level Nivel del encabezado (por ejemplo, 1 para h1, 2 para h2, etc.).
     */
    public function displayHeadingTags($level) {
        $headings = $this->doc->getElementsByTagName("h{$level}");
        echo "<tr>";
            echo "<td>";
                echo ($headings->length > 0) ? "<div class='ok'></div>" : "<div class='ko'></div>";
            echo "</td>";
            echo "<td><h4>" . sprintf(__('heading_tag'), $level) . "</h4></td>";
            echo "<td>";
                foreach ($headings as $header) {
                    echo $header->nodeValue . "<br>";
                }
            echo "</td>";
        echo "</tr>";
    }

    /**
     * Obtiene el texto limpio del elemento <body>, eliminando etiquetas no deseadas.
     *
     * @return string Texto en minúsculas limpio, listo para el análisis de palabras.
     */
    private function getCleanBodyText() {
        $body = $this->doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            die("Error fatal: No se encontró la etiqueta <body> en el HTML.");
        }
        // Se eliminan etiquetas <script> y <style> para evitar contenido irrelevante.
        $this->purgeElements($body, 'script');
        $this->purgeElements($body, 'style');
        $rawText = $body->textContent;
        // Se filtra el texto para dejar solo letras y espacios.
        $clean = preg_replace('/[^a-zA-Z\s]/', '', $rawText);
        return strtolower($clean);
    }

    /**
     * Elimina del nodo dado todos los elementos que corresponden a la etiqueta especificada.
     *
     * @param DOMNode $node Nodo del que se eliminarán los elementos.
     * @param string $tagName Nombre de la etiqueta que se purgará (por ejemplo, 'script').
     */
    private function purgeElements($node, $tagName) {
        $elements = $node->getElementsByTagName($tagName);
        for ($i = $elements->length - 1; $i >= 0; $i--) {
            $elements->item($i)->parentNode->removeChild($elements->item($i));
        }
    }

    /**
     * Calcula la frecuencia de aparición de cada palabra en el contenido limpio del body.
     *
     * Se descartan palabras muy comunes (stopwords) para obtener datos significativos.
     *
     * @return array Array asociativo donde las claves son palabras y los valores son sus frecuencias.
     */
    private function calculateWordFrequency() {
        $ignoredWords = array(
            "a", "acá", "ahí", "al", "algo", "algunas", "algunos", "allá", "allí", "ambos",
            "ante", "antes", "aquel", "aquella", "aquellas", "aquellos", "aquí", "arriba",
            "así", "atrás", "bajo", "bien", "cabe", "cada", "casi", "como", "con", "conmigo",
            "conseguir", "consigo", "consigue", "consiguen", "contra", "cual", "cuales",
            "cualquier", "cualquiera", "cuándo", "cuanta", "cuanto", "cuatro", "cuya", "cuyas",
            "cuyo", "cuyos", "de", "del", "desde", "donde", "dos", "el", "él", "ella",
            "ellas", "ellos", "en", "encima", "entre", "era", "eras", "éramos", "eran",
            "eres", "es", "esa", "esas", "ese", "eso", "esos", "esta", "estaba", "estabais",
            "estábamos", "estaban", "está", "estamos", "están", "este", "esto", "estos",
            "estoy", "etc", "fue", "fuera", "fuimos", "ha", "había", "habéis", "habíamos",
            "habían", "hace", "hacen", "hacer", "hacia", "hago", "incluso", "la", "las",
            "lo", "los", "más", "me", "mi", "mis", "mía", "mías", "mío", "míos", "muy",
            "nada", "ni", "no", "nos", "nosotras", "nosotros", "nuestra", "nuestras",
            "nuestro", "nuestros", "o", "os", "otra", "otras", "otro", "otros", "para",
            "pero", "poco", "por", "porque", "que", "quien", "quienes", "qué", "se",
            "si", "sí", "siendo", "sin", "sobre", "sois", "solo", "somos", "soy", "su",
            "sus", "también", "tan", "tanto", "te", "tendrá", "tendremos", "tienen",
            "tener", "tengo", "ti", "tiene", "todo", "todos", "tu", "tus", "un", "una",
            "unas", "uno", "unos", "vosotras", "vosotros", "vuestra", "vuestras", "ya", "yo", "y"
        );
        $text = $this->getCleanBodyText();
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $frequency = array_count_values($words);
        
        // Se eliminan las palabras que no aportan valor al análisis.
        foreach ($ignoredWords as $ignore) {
            if (isset($frequency[$ignore])) {
                unset($frequency[$ignore]);
            }
        }
        arsort($frequency);
        return $frequency;
    }

    /**
     * Muestra las palabras más frecuentes (mínimo 3 repeticiones) en formato de fila de tabla.
     */
    public function displayFrequentWords() {
        $freqData = $this->calculateWordFrequency();
        echo "<tr>";
            echo "<td>" . (!empty($freqData) ? "<div class='ok'></div>" : "<div class='ko'></div>") . "</td>";
            echo "<td><h4>" . __('frequent_words') . "</h4></td>";
            echo "<td>";
                foreach ($freqData as $word => $count) {
                    if ($count > 2) {
                        echo "{$word}: {$count}<br>";
                    }
                }
            echo "</td>";
        echo "</tr>";
    }

    /**
     * Presenta una nube de palabras donde el tamaño de letra de cada palabra es proporcional a su frecuencia.
     */
    public function displayWordCloud() {
        $freqData = $this->calculateWordFrequency();
        echo "<tr>";
            echo "<td>" . (!empty($freqData) ? "<div class='ok'></div>" : "<div class='ko'></div>") . "</td>";
            echo "<td><h4>" . __('word_cloud') . "</h4></td>";
            echo "<td>";
                foreach ($freqData as $word => $count) {
                    if ($count > 2) {
                        $fontSize = $count * 4;
                        echo "<span style='font-size:{$fontSize}px;'>{$word}</span> ";
                    }
                }
            echo "</td>";
        echo "</tr>";
    }

    /**
     * Verifica la presencia del archivo robots.txt en el dominio.
     */
    public function checkRobotsFile() {
        $robotsPath = rtrim($this->domain, '/') . '/robots.txt';
        $exists = (@file_get_contents($robotsPath) !== false);
        echo "<tr>";
            echo "<td>" . ($exists ? "<div class='ok'></div>" : "<div class='ko'></div>") . "</td>";
            echo "<td><h4>" . __('robots_txt') . "</h4></td>";
            echo "<td>" . ($exists ? "Found" : "Not Found") . "</td>";
        echo "</tr>";
    }

    /**
     * Verifica la existencia del sitemap.xml en el dominio.
     */
    public function checkSitemapFile() {
        $sitemapPath = rtrim($this->domain, '/') . '/sitemap.xml';
        $exists = (@file_get_contents($sitemapPath) !== false);
        echo "<tr>";
            echo "<td>" . ($exists ? "<div class='ok'></div>" : "<div class='ko'></div>") . "</td>";
            echo "<td><h4>" . __('sitemap_xml') . "</h4></td>";
            echo "<td>" . ($exists ? "Found" : "Not Found") . "</td>";
        echo "</tr>";
    }

    /**
     * Comprueba que todas las imágenes del documento tengan el atributo alt definido.
     * Si se encuentra alguna imagen sin alt, se reporta.
     */
    public function checkImagesAltText() {
        $images = $this->doc->getElementsByTagName('img');
        $total = $images->length;
        $missing = 0;
        $details = "";

        if ($total == 0) {
            echo "<tr>";
                echo "<td><div class='ok'></div></td>";
                echo "<td><h4>" . __('images_alt') . "</h4></td>";
                echo "<td>No images available.</td>";
            echo "</tr>";
            return;
        }

        foreach ($images as $index => $img) {
            $alt = trim($img->getAttribute('alt'));
            if ($alt === "") {
                $missing++;
                $details .= "Image " . ($index + 1) . " missing alt.<br>";
            }
        }

        echo "<tr>";
            echo "<td>" . ($missing == 0 ? "<div class='ok'></div>" : "<div class='ko'></div>") . "</td>";
            echo "<td><h4>" . __('images_alt') . "</h4></td>";
            echo "<td>";
                if ($missing == 0) {
                    echo "All {$total} images have alt attributes.";
                } else {
                    echo "{$missing} out of {$total} images lack alt attributes.<br>" . $details;
                }
            echo "</td>";
        echo "</tr>";
    }

    /**
     * Comprueba la existencia de un favicon.
     * Primero revisa si hay un <link> con rel que contenga "icon"; de lo contrario,
     * intenta cargar el favicon por defecto ubicado en '/favicon.ico'.
     */
    public function checkFaviconPresence() {
        $links = $this->doc->getElementsByTagName('link');
        $found = false;
        foreach ($links as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            if (strpos($rel, 'icon') !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $faviconUrl = rtrim($this->domain, '/') . '/favicon.ico';
            $found = (@file_get_contents($faviconUrl) !== false);
        }
        
        echo "<tr>";
            echo "<td>" . ($found ? "<div class='ok'></div>" : "<div class='ko'></div>") . "</td>";
            echo "<td><h4>" . __('favicon') . "</h4></td>";
            echo "<td>" . ($found ? "Found" : "Not Found") . "</td>";
        echo "</tr>";
    }
}
?>