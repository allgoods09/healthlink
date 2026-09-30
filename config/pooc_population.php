<?php

return [
    'version' => 1,
    'seed' => 27092026,
    'as_of' => '2026-09-27',
    'target' => 3000,
    'barangay' => 'Pooc Oriental',
    // Existing HealthLink configuration, NOT verified geographic purok names.
    'configured_puroks' => [1 => 'Purok Centro', 2 => 'Purok Baybay', 3 => 'Purok Ilaya',
        4 => 'Purok Luyo', 5 => 'Purok Riverside', 6 => 'Purok Crossing', 7 => 'Purok Proper'],
    'household_sizes' => [1 => 60, 2 => 120, 3 => 160, 4 => 230, 5 => 200, 6 => 120, 7 => 60, 8 => 30, 9 => 15, 10 => 5],
    'family_types' => ['nuclear' => 62, 'single_parent' => 16, 'grandparents' => 8, 'extended' => 9, 'blended' => 5],
    'ages' => [
        'independent' => [[18, 29, 20], [30, 59, 40], [60, 79, 35], [80, 94, 5]],
        'parent' => [[24, 34, 30], [35, 49, 45], [50, 65, 25]],
        'child' => [[0, 4, 18], [5, 11, 30], [12, 17, 26], [18, 29, 26]],
        'grandparent' => [[63, 74, 80], [75, 84, 20]],
    ],
    'female_head_percent' => 35,
    'unmarried_couple_percent' => 12,
    'missing_caregiver_percent' => 8,
    'occupations' => ['Farmer', 'Market Vendor', 'Tricycle Driver', 'Construction Laborer', 'Homemaker',
        'Storekeeper', 'Fisher', 'Office Clerk', 'Teacher', 'Carpenter', 'Mechanic', 'Service Worker', 'Self-employed'],
];
