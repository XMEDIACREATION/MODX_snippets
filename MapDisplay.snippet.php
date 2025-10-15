<?php
/**
 * Snippet MODX - Carte Interactive avec Leaflet.js
 * 
 *
 * @author David MEYER
 * @copyright Copyright 2025, XMEDIACREATION
 *
 * USAGE CARTE SIMPLE (un point) :
 * [[!MapDisplay? 
 *   &coordsTV=`googlemap`
 *   &height=`350px`
 *   &width=`100%`
 *   &titleField=`pagetitle`
 *   &subtitleField=`introtext`
 * ]]
 * 
 * USAGE CARTE MULTIPLE (plusieurs points) :
 * [[!MapDisplay? 
 *   &coordsTV=`googlemap`
 *   &height=`500px`
 *   &width=`100%`
 *   &resources=`12,45,67,89`
 *   &titleField=`pagetitle`
 *   &subtitleField=`introtext`
 * ]]
 * 
 * OU avec parent :
 * [[!MapDisplay? 
 *   &coordsTV=`googlemap`
 *   &parent=`5`
 *   &titleField=`pagetitle`
 *   &subtitleField=`introtext`
 * ]]
 */

// Paramètres
$coordsTV = $modx->getOption('coordsTV', $scriptProperties, 'googlemap');
$height = $modx->getOption('height', $scriptProperties, '350px');
$width = $modx->getOption('width', $scriptProperties, '100%');
$titleField = $modx->getOption('titleField', $scriptProperties, 'pagetitle');
$subtitleField = $modx->getOption('subtitleField', $scriptProperties, 'introtext');
$resources = $modx->getOption('resources', $scriptProperties, '');
$parent = $modx->getOption('parent', $scriptProperties, '');
$zoom = $modx->getOption('zoom', $scriptProperties, 13);

// Fonction pour parser les coordonnées
function parseCoordinates($coordString) {
    if (empty($coordString)) return false;
    
    // Si c'est du JSON
    $decoded = json_decode($coordString, true);
    if ($decoded && isset($decoded['lat']) && isset($decoded['lng'])) {
        return array(
            'lat' => floatval($decoded['lat']),
            'lng' => floatval($decoded['lng'])
        );
    }
    
    // Si c'est au format "lat,lng"
    if (strpos($coordString, ',') !== false) {
        $coords = explode(',', $coordString);
        if (count($coords) == 2) {
            return array(
                'lat' => floatval(trim($coords[0])),
                'lng' => floatval(trim($coords[1]))
            );
        }
    }
    
    return false;
}

// Fonction pour obtenir la valeur d'un champ
function getFieldValue($modx, $resource, $fieldName) {
    // Si c'est un champ standard
    if (in_array($fieldName, array('pagetitle', 'longtitle', 'introtext', 'content', 'description', 'menutitle'))) {
        return $resource->get($fieldName);
    }
    
    // Sinon c'est une TV
    $tv = $modx->getObject('modTemplateVar', array('name' => $fieldName));
    if ($tv) {
        return $tv->renderOutput($resource->get('id'));
    }
    
    return '';
}

$markers = array();
$mapId = 'modx-map-' . uniqid();

// Mode : Carte multiple
if (!empty($resources) || !empty($parent)) {
    
    // Construire la liste des IDs
    $resourceIds = array();
    
    if (!empty($resources)) {
        // Liste d'IDs fournie
        $resourceIds = array_map('trim', explode(',', $resources));
    } elseif (!empty($parent)) {
        // Récupérer les enfants du parent
        $children = $modx->getChildIds($parent, 10, array('context' => $modx->context->key));
        $resourceIds = $children;
    }
    
    // Récupérer les ressources
    foreach ($resourceIds as $resId) {
        $resource = $modx->getObject('modResource', $resId);
        
        if (!$resource || !$resource->get('published')) continue;
        
        // Récupérer les coordonnées
        $coordsValue = $resource->getTVValue($coordsTV);
        $coords = parseCoordinates($coordsValue);
        
        if (!$coords) continue;
        
        // Récupérer le titre et sous-titre
        $title = getFieldValue($modx, $resource, $titleField);
        $subtitle = getFieldValue($modx, $resource, $subtitleField);
        
        // Limiter le sous-titre à 150 caractères
        if (strlen($subtitle) > 150) {
            $subtitle = substr(strip_tags($subtitle), 0, 150) . '...';
        }
        
        $markers[] = array(
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'subtitle' => htmlspecialchars(strip_tags($subtitle), ENT_QUOTES, 'UTF-8'),
            'link' => $modx->makeUrl($resource->get('id'), '', '', 'full')
        );
    }
    
    if (empty($markers)) {
        return '<p>Aucun point à afficher sur la carte.</p>';
    }
    
    // Charger Leaflet CSS & JS
    $modx->regClientCSS('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    $modx->regClientStartupScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
    
    // Générer le code HTML et JavaScript
    $output = '<div id="' . $mapId . '" style="height: ' . $height . '; width: ' . $width . '; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>';
    
    $markersJson = json_encode($markers);
    
    $output .= <<<HTML
<script>
(function() {
    function initMap() {
        var markers = {$markersJson};
        
        if (markers.length === 0) return;
        
        var map = L.map('{$mapId}').setView([markers[0].lat, markers[0].lng], {$zoom});
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        var bounds = [];
        
        markers.forEach(function(marker) {
            var m = L.marker([marker.lat, marker.lng]).addTo(map);
            
            var popupContent = '<div style="min-width: 200px;">';
            popupContent += '<strong><a href="' + marker.link + '" style="text-decoration: none; color: #0066cc;">' + marker.title + '</a></strong>';
            
            if (marker.subtitle) {
                popupContent += '<br><span style="color: #666; font-size: 13px;">' + marker.subtitle + '</span>';
            }
            
            popupContent += '</div>';
            
            m.bindPopup(popupContent);
            bounds.push([marker.lat, marker.lng]);
        });
        
        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }
    
    if (typeof L !== 'undefined') {
        initMap();
    } else {
        window.addEventListener('load', initMap);
    }
})();
</script>
HTML;
    
    return $output;
    
} else {
    // Mode : Carte simple (ressource actuelle)
    $resource = $modx->resource;
    
    if (!$resource) {
        return '<p>Aucune ressource trouvée.</p>';
    }
    
    // Récupérer les coordonnées
    $coordsValue = $resource->getTVValue($coordsTV);
    $coords = parseCoordinates($coordsValue);
    
    if (!$coords) {
        return ''; // Pas de coordonnées, on n'affiche rien
    }
    
    // Récupérer le titre et sous-titre
    $title = getFieldValue($modx, $resource, $titleField);
    $subtitle = getFieldValue($modx, $resource, $subtitleField);
    
    // Limiter le sous-titre
    if (strlen($subtitle) > 150) {
        $subtitle = substr(strip_tags($subtitle), 0, 150) . '...';
    }
    
    // Charger Leaflet CSS & JS
    $modx->regClientCSS('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    $modx->regClientStartupScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
    
    // Échapper les données pour JavaScript
    $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $subtitleEscaped = htmlspecialchars(strip_tags($subtitle), ENT_QUOTES, 'UTF-8');
    
    // Générer le code HTML et JavaScript
    $output = '<div id="' . $mapId . '" style="height: ' . $height . '; width: ' . $width . '; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>';
    
    $output .= <<<HTML
<script>
(function() {
    function initMap() {
        var map = L.map('{$mapId}').setView([{$coords['lat']}, {$coords['lng']}], {$zoom});
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        var popupContent = '<div style="min-width: 150px;">';
        popupContent += '<strong>{$titleEscaped}</strong>';
        
HTML;
    
    if (!empty($subtitle)) {
        $output .= <<<HTML
        popupContent += '<br><span style="color: #666; font-size: 13px;">{$subtitleEscaped}</span>';
HTML;
    }
    
    $output .= <<<HTML
        popupContent += '</div>';
        
        L.marker([{$coords['lat']}, {$coords['lng']}])
            .addTo(map)
            .bindPopup(popupContent)
            .openPopup();
    }
    
    if (typeof L !== 'undefined') {
        initMap();
    } else {
        window.addEventListener('load', initMap);
    }
})();
</script>
HTML;
    
    return $output;
}
