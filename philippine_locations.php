<?php
$philippineLocations = [
    'Zambales' => [
        'Botolan' => [
            'Acoje', 'Ambala', 'Apo', 'Apo-apo', 'Bacabac', 'Bancal', 'Bangan', 'Batonlapoc', 'Belbel', 'Beneg', 'Binuclutan', 'Burgos', 'Cabatuan', 'Capayawan', 'Carael', 'Danacbunga', 'Maguisguis', 'Malomboy', 'Mambog', 'Moraza', 'Nacolcol', 'Owaog-Nibloc', 'Paan', 'Panan', 'Poblacion', 'Porac', 'San Isidro', 'San Juan', 'San Miguel', 'Santiago', 'Tampo', 'Taugtog', 'Villan'
        ],
        'Cabangan' => [
            'Anonang', 'Apo-apo', 'Arew', 'Banuambayo', 'Cadmang-Reserva', 'Camiling', 'Casabaan', 'Dolores', 'Felmida-Diaz', 'Laoag', 'Lomboy', 'Panganiban', 'San Antonio', 'San Isidro', 'San Juan', 'San Rafael', 'Santa Rita', 'Santo Niño'
        ],
        'Candelaria' => [
            'Babancal', 'Binabalian', 'Catol', 'Dampay', 'Lauis', 'Libertador', 'Malabon', 'Malimanga', 'Pamibian', 'Panayonan', 'Pinagrealan', 'Poblacion', 'Sinabacan', 'Taposo'
        ],
        'Castillejos' => [
            'Balaybay', 'Buenavista', 'Del Pilar', 'Looc', 'Magsaysay', 'Nagbayan', 'Nagbunga', 'San Agustin', 'San Jose', 'San Juan', 'San Nicolas', 'San Pablo', 'San Roque', 'Santa Maria'
        ],
        'Iba' => [
            'Amungan', 'Bangantalinga', 'Dirita-Baloguen', 'Lipay-Dingin-Panibuatan', 'Palanginan', 'San Agustin', 'Santa Barbara', 'Santo Rosario', 'Zone 1', 'Zone 2', 'Zone 3', 'Zone 4', 'Zone 5', 'Zone 6'
        ],
        'Masinloc' => [
            'Baloganon', 'Bamban', 'Bani', 'Collat', 'Inhobol', 'North Poblacion', 'San Lorenzo', 'San Salvador', 'Santa Rita', 'South Poblacion', 'Taltal'
        ],
        'Olongapo' => [
            'Asinan', 'Banicain', 'Barretto', 'East Bajac-Bajac', 'East Tapinac', 'Gordon Heights', 'Kalaklan', 'Mabayuan', 'New Cabalan', 'New Ilalim', 'New Kababae', 'Old Cabalan', 'Pag-asa', 'Santa Rita', 'West Bajac-Bajac', 'West Tapinac'
        ],
        'Palauig' => [
            'Alwa', 'Bato', 'Bulawen', 'Cauyan', 'East Poblacion', 'Garreta', 'Libaba', 'Liozon', 'Lipay', 'Locloc', 'Macarang', 'Magalawa', 'Pangolingan', 'Salaza', 'San Juan', 'Santo Niño', 'Tition', 'West Poblacion'
        ],
        'San Antonio' => [
            'Angeles', 'Antipolo', 'Burgos', 'East Dirita', 'Luna', 'Pundaquit', 'Rizal', 'San Esteban', 'San Gregorio', 'San Juan', 'San Miguel', 'San Nicolas', 'Santiago', 'West Dirita'
        ],
        'San Felipe' => [
            'Amagna', 'Apostol', 'Balincaguing', 'Farañal', 'Feria', 'Maloma', 'Manglicmot', 'Rosete', 'San Rafael', 'Santo Niño', 'Sindol'
        ],
        'San Marcelino' => [
            'Aglao', 'Buhawen', 'Burgos', 'Central', 'Consuelo Norte', 'Consuelo Sur', 'La Paz', 'Laoag', 'Linasin', 'Linusungan', 'Lucero', 'Nagbunga', 'Rabanes', 'Rizal', 'San Guillermo', 'San Isidro', 'San Rafael', 'Santa Fe'
        ],
        'San Narciso' => [
            'Alusiis', 'Beddeng', 'Candelaria', 'Dallipawen', 'Grullo', 'La Paz', 'Libertad', 'Namatacan', 'Natividad', 'Omaya', 'Paite', 'Patrocinio', 'San Jose', 'San Juan', 'San Pascual', 'San Rafael', 'Siminublan'
        ],
        'Santa Cruz' => [
            'Babancal', 'Baliwet', 'Bayto', 'Biay', 'Bolitoc', 'Bulawon', 'Canaynayan', 'Gama', 'Guisguis', 'Guisguis-San Isidro', 'Lipay', 'Lomboy', 'Lucapon North', 'Lucapon South', 'Malabago', 'Naulo', 'Pagatpat', 'Pamonoran', 'Poblacion North', 'Poblacion South', 'Sabang', 'San Fernando', 'Tabluan', 'Tubotubo North', 'Tubotubo South'
        ],
        'Subic' => [
            'Aningway-Sacatihan', 'Asinan', 'Baraca-Camachile', 'Batiawan', 'Calapacuan', 'Cawag', 'Ilwas', 'Mangan-Vaca', 'Matain', 'Naugsol', 'Pamatawan', 'San Isidro', 'San Jose', 'San Martin', 'Santa Rita', 'Wawandue'
        ]
    ]
];


function getAllMunicipalities() {
    global $philippineLocations;
    return array_keys($philippineLocations['Zambales']);
}


function getBarangays($municipality) {
    global $philippineLocations;
    return isset($philippineLocations['Zambales'][$municipality]) ? $philippineLocations['Zambales'][$municipality] : [];
}


function validateLocation($municipality, $barangay) {
    global $philippineLocations;
    
    
    $municipality = ucwords(strtolower($municipality));
    $barangay = ucwords(strtolower($barangay));
    
    
    if (!isset($philippineLocations['Zambales'][$municipality])) {
        return false;
    }
    
    
    if (!in_array($barangay, $philippineLocations['Zambales'][$municipality])) {
        return false;
    }
    
    return true;
}
?> 