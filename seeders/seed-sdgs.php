<?php
/**
 * Seeder: SDGs taxonomy terms
 *
 * Populates the `sdgs` taxonomy with the 17 Sustainable Development Goals,
 * matching the `sdgsOptions` array in the frontend (lp2m/src/data/content.json).
 *
 * USAGE (from project root):
 *   wp eval-file web/app/themes/itsi/seeders/seed-sdgs.php
 *
 * Safe to re-run: existing terms are skipped. To force re-seed, delete the
 * `itsi_sdgs_seeded` option first:
 *   wp option delete itsi_sdgs_seeded
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file seed-sdgs.php\n";
    return;
}

$force = in_array('--force', $argv ?? [], true);

if (!taxonomy_exists('sdgs')) {
    echo "✗ Taxonomy 'sdgs' is not registered. Aborting.\n";
    return;
}

if (!$force && get_option('itsi_sdgs_seeded')) {
    $ts = (int) get_option('itsi_sdgs_seeded');
    echo "ⓘ SDGs already seeded on " . date('Y-m-d H:i:s', $ts) . ".\n";
    echo "  To re-run, delete the option: wp option delete itsi_sdgs_seeded\n";
    echo "  To wipe + re-seed:            wp eval-file seed-sdgs.php -- --force\n";
    return;
}

if ($force) {
    echo "⚠ --force: deleting all existing sdgs terms…\n";
    $existing_terms = get_terms(['taxonomy' => 'sdgs', 'hide_empty' => false]);
    if (!is_wp_error($existing_terms)) {
        foreach ($existing_terms as $t) {
            wp_delete_term($t->term_id, 'sdgs');
        }
    }
    delete_option('itsi_sdgs_seeded');
}

echo "Seeding SDGs taxonomy terms...\n\n";

$sdgs = [
    '1'  => '1 No Poverty',
    '2'  => '2 Zero Hunger',
    '3'  => '3 Good Health and Well-being',
    '4'  => '4 Quality Education',
    '5'  => '5 Gender Equality',
    '6'  => '6 Clean Water and Sanitation',
    '7'  => '7 Affordable and Clean Energy',
    '8'  => '8 Decent Work and Economic Growth',
    '9'  => '9 Industry, Innovation and Infrastructure',
    '10' => '10 Reduced Inequalities',
    '11' => '11 Sustainable Cities and Communities',
    '12' => '12 Responsible Consumption and Production',
    '13' => '13 Climate Action',
    '14' => '14 Life Below Water',
    '15' => '15 Life on Land',
    '16' => '16 Peace, Justice and Strong Institutions',
    '17' => '17 Partnerships for the Goals',
];

$created = 0;
$skipped = 0;
foreach ($sdgs as $number => $name) {
    $slug = sanitize_title($name); // e.g. "1-no-poverty"
    $term = term_exists($slug, 'sdgs');
    if (!$term) {
        $result = wp_insert_term($name, 'sdgs', ['slug' => $slug]);
        if (is_wp_error($result)) {
            echo "  ✗ Error creating SDG $name: " . $result->get_error_message() . "\n";
            continue;
        }
        echo "  ✓ Created SDG: $name\n";
        $created++;
    } else {
        echo "  → SDG exists: $name\n";
        $skipped++;
    }
}

update_option('itsi_sdgs_seeded', time());

echo "\nDone: {$created} created, {$skipped} skipped.\n";
