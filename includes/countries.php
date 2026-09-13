<?php
// ============================================================
//  includes/countries.php
//  African country list + phone number rules for registration.
//  To add a country later: just add one line to $AFRICAN_COUNTRIES.
// ============================================================

/**
 * name => [dial code, min local digits, max local digits]
 * "Local digits" = what the user types in the phone field,
 * INCLUDING the leading 0 where that country uses one
 * (e.g. Nigeria 08012345678 = 11 digits).
 * Min/max is a range (not exact) to stay lenient on formats
 * we're less sure about — this is light validation, not billing.
 */
$AFRICAN_COUNTRIES = [
    'Nigeria'        => ['+234', 11, 11],
    'Ghana'          => ['+233', 10, 10],
    'Kenya'          => ['+254', 9, 10],
    'South Africa'   => ['+27',  9, 10],
    'Egypt'          => ['+20',  10, 11],
    'Tanzania'       => ['+255', 9, 10],
    'Uganda'         => ['+256', 9, 10],
    'Rwanda'         => ['+250', 9, 10],
    'Ethiopia'       => ['+251', 9, 10],
    'Cameroon'       => ['+237', 8, 9],
    "Côte d'Ivoire"  => ['+225', 8, 10],
    'Senegal'        => ['+221', 8, 9],
    'Zambia'         => ['+260', 9, 10],
    'Zimbabwe'       => ['+263', 8, 10],
    'Morocco'        => ['+212', 9, 10],
    'Algeria'        => ['+213', 9, 10],
    'Tunisia'        => ['+216', 8, 8],
    'Mali'           => ['+223', 8, 8],
    'Benin'          => ['+229', 8, 10],
    'Togo'           => ['+228', 8, 8],
    'DR Congo'       => ['+243', 8, 9],
    'Sierra Leone'   => ['+232', 8, 9],
    'Liberia'        => ['+231', 7, 9],
    'Namibia'        => ['+264', 8, 9],
    'Botswana'       => ['+267', 7, 8],
    'Malawi'         => ['+265', 8, 9],
    'Mozambique'     => ['+258', 8, 9],
    'Gambia'         => ['+220', 7, 7],
];

/** Returns the full list for building a <select> dropdown. */
function get_country_list(): array {
    global $AFRICAN_COUNTRIES;
    return $AFRICAN_COUNTRIES;
}

/** Returns [dial_code, min, max] for a country name, or null if unknown. */
function get_country_info(string $country): ?array {
    global $AFRICAN_COUNTRIES;
    return $AFRICAN_COUNTRIES[$country] ?? null;
}

/** True if the digits-only phone matches the given country's expected length. */
function validate_phone_for_country(string $phone, string $country): bool {
    $info = get_country_info($country);
    if (!$info) return false;
    [, $min, $max] = $info;
    $digits = preg_replace('/\D/', '', $phone);
    $len = strlen($digits);
    return $len >= $min && $len <= $max;
}

/** True only for Nigeria — used to gate NCWallet (Nigerian bank transfer). */
function country_supports_ncwallet(string $country): bool {
    return $country === 'Nigeria';
}
