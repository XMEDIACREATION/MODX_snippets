<?php
/**
 * getValueByIndex - Filtre de sortie personnalisé MODX avancé
 * 
 * Extrait une valeur spécifique d'une chaîne contenant des valeurs séparées par un délimiteur
 * 
 * UTILISATION BASIQUE :
 * [[*maTv:getValueByIndex=`1`]]
 * 
 * UTILISATION AVANCÉE (avec délimiteur personnalisé) :
 * [[*maTv:getValueByIndex=`index=2&delimiter=|`]]
 * 
 * @param $input string - La valeur de la TV
 * @param $options string - L'index ou les options avancées
 * @return string - La valeur correspondant à l'index
 */

// Vérifier que l'input n'est pas vide
if (empty($input)) {
    return '';
}

// Valeurs par défaut
$index = 0;
$delimiter = ';';
$default = ''; // Valeur retournée si l'index n'existe pas

// Parser les options
if (!empty($options)) {
    // Vérifier si c'est juste un nombre (utilisation simple)
    if (is_numeric($options)) {
        $index = intval($options);
    } else {
        // Parser les options avancées
        $params = array();
        $optionsArray = explode('&', $options);
        
        foreach ($optionsArray as $option) {
            $temp = explode('=', $option);
            if (count($temp) == 2) {
                $params[trim($temp[0])] = trim($temp[1]);
            }
        }
        
        // Récupérer les paramètres
        if (isset($params['index'])) {
            $index = intval($params['index']);
        }
        if (isset($params['delimiter'])) {
            $delimiter = $params['delimiter'];
        }
        if (isset($params['default'])) {
            $default = $params['default'];
        }
    }
}

// Séparer les valeurs par le délimiteur
$values = explode($delimiter, $input);

// Nettoyer les espaces autour de chaque valeur
$values = array_map('trim', $values);

// Support des index négatifs (comme en Python)
// -1 = dernier élément, -2 = avant-dernier, etc.
if ($index < 0) {
    $count = count($values);
    $index = $count + $index;
}

// Vérifier si l'index existe dans le tableau
if (isset($values[$index])) {
    return $values[$index];
}

// Retourner la valeur par défaut si l'index n'existe pas
return $default;


/* =============================================
   EXEMPLES D'UTILISATION
   =============================================
   
   Si votre TV "maListe" contient : "pomme;orange;banane;fraise"
   
   1. Obtenir le premier élément (index 0) :
      [[*maListe:getValueByIndex=`0`]]
      Résultat : pomme
   
   2. Obtenir le deuxième élément (index 1) :
      [[*maListe:getValueByIndex=`1`]]
      Résultat : orange
   
   3. Obtenir le dernier élément :
      [[*maListe:getValueByIndex=`-1`]]
      Résultat : fraise
   
   4. Avec un délimiteur personnalisé (si la TV contient "pomme|orange|banane") :
      [[*maListe:getValueByIndex=`index=1&delimiter=|`]]
      Résultat : orange
   
   5. Avec une valeur par défaut si l'index n'existe pas :
      [[*maListe:getValueByIndex=`index=10&default=Non trouvé`]]
      Résultat : Non trouvé
   
   INSTALLATION :
   1. Créez un nouveau Snippet dans MODX
   2. Nommez-le "getValueByIndex"
   3. Collez ce code dans le snippet
   4. Sauvegardez
   
   Le filtre sera immédiatement disponible pour toutes vos TVs !
*/
